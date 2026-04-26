<?php

declare(strict_types=1);

/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\User;
use App\Enum\DeckEventStatus;
use App\Enum\DeckStatus;
use App\Message\EnrichDeckVersionMessage;
use App\Repository\BorrowRepository;
use App\Repository\EventDeckEntryRepository;
use App\Repository\EventDeckRegistrationRepository;
use App\Repository\EventRepository;
use App\Service\DeckList\CardmarketWishlistFormatter;
use App\Service\DeckList\MinifiedCardView;
use App\Service\DeckList\MinifiedCardViewBuilder;
use App\Service\DeckList\OriginalListFormatter;
use App\Service\DeckListParser;
use App\Service\Label\PdfDecklistGenerator;
use App\Service\Label\PdfLabelGenerator;
use App\Service\Tcgdex\TcgdexApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F2.3 — Detail view
 * @see docs/features.md F2.14 — Deck event status overview
 * @see docs/features.md F4.5 — Borrow history
 * @see docs/features.md F5.7 — PDF label card (home printing)
 * @see docs/features.md F5.12 — Deck show activity pagination
 */
class DeckShowController extends AbstractAppController
{
    private const int ACTIVITY_PREVIEW_LIMIT = 5;

    #[Route('/deck/{short_tag}', name: 'app_deck_show', methods: ['GET'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}'])]
    public function show(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        BorrowRepository $borrowRepository,
        EventRepository $eventRepository,
        EventDeckEntryRepository $eventDeckEntryRepository,
        EventDeckRegistrationRepository $eventDeckRegistrationRepository,
        MinifiedCardViewBuilder $minifiedCardViewBuilder,
        OriginalListFormatter $originalListFormatter,
        CardmarketWishlistFormatter $cardmarketWishlistFormatter,
        TcgdexApiClient $tcgdexApiClient,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        // Access control: public decks are visible to everyone
        if (!$deck->isPublic()) {
            $isOwnerOrAdmin = null !== $user
                && ($deck->getOwner()?->getId() === $user->getId()
                    || $this->isGranted('ROLE_ADMIN'));

            $hasStaffAccess = false;
            if (null !== $user && !$isOwnerOrAdmin) {
                foreach ($deck->getEventRegistrations() as $registration) {
                    if ($registration->getEvent()->isOrganizerOrStaff($user)) {
                        $hasStaffAccess = true;
                        break;
                    }
                }
            }

            if (!$isOwnerOrAdmin && !$hasStaffAccess) {
                // Limited view: show deck header and owner info but hide card list
                return $this->render('deck/show_limited.html.twig', [
                    'deck' => $deck,
                    'isOwner' => false,
                ]);
            }
        }

        // If set mappings are not built yet, show an awaiting page.
        // Mappings are populated by fixtures or via the admin rebuild button — never auto-dispatched.
        if (!$tcgdexApiClient->hasMappings()) {
            return $this->render('deck/awaiting_mappings.html.twig', [
                'deck' => $deck,
            ]);
        }

        $groupedCards = [];
        $currentVersion = $deck->getCurrentVersion();

        if (null !== $currentVersion) {
            foreach ($currentVersion->getCards() as $card) {
                $groupedCards[$card->getCardType()][] = $card;
            }

            // Sort within each group: trainer subtype, then quantity desc, then name asc
            foreach ($groupedCards as $type => &$cards) {
                usort($cards, static function (DeckCard $cardA, DeckCard $cardB) use ($type): int {
                    if ('trainer' === $type) {
                        $subtypeOrder = ['supporter' => 0, 'item' => 1, 'tool' => 2, 'stadium' => 3];
                        $subtypeA = $subtypeOrder[strtolower((string) $cardA->getTrainerSubtype())] ?? 4;
                        $subtypeB = $subtypeOrder[strtolower((string) $cardB->getTrainerSubtype())] ?? 4;

                        if ($subtypeA !== $subtypeB) {
                            return $subtypeA <=> $subtypeB;
                        }
                    }

                    if ($cardA->getQuantity() !== $cardB->getQuantity()) {
                        return $cardB->getQuantity() <=> $cardA->getQuantity();
                    }

                    return strcmp($cardA->getCardName(), $cardB->getCardName());
                });
            }
            unset($cards);
        }

        // Ensure consistent section order
        $orderedGroups = [];
        foreach (['pokemon', 'trainer', 'energy'] as $section) {
            if (isset($groupedCards[$section])) {
                $orderedGroups[$section] = $groupedCards[$section];
            }
        }

        $isOwner = null !== $user && $deck->getOwner()?->getId() === $user->getId();

        // Anonymous users get empty borrow data
        $deckBorrows = [];
        $totalBorrowCount = 0;
        $eligibleEvents = [];
        $eventStatusOverview = [];

        if (null !== $user) {
            $deckBorrows = $borrowRepository->findByDeckForUser($deck, $user, self::ACTIVITY_PREVIEW_LIMIT);
            $totalBorrowCount = (int) $borrowRepository->createDeckForUserQueryBuilder($deck, $user)
                ->select('COUNT(b.id)')
                ->getQuery()
                ->getSingleScalarResult();

            // Only show eligible events if deck is not retired, lendable, user is not owner, and deck has a version
            if (!$isOwner && DeckStatus::Retired !== $deck->getStatus() && $deck->isLendable() && null !== $currentVersion) {
                $candidates = $eventRepository->findEligibleForBorrow($user, $deck);

                // Filter out events with same-day conflicts
                foreach ($candidates as $candidate) {
                    if (null === $borrowRepository->findBlockingBorrowForDeckAtEvent($deck, $candidate)
                        && [] === $borrowRepository->findBlockingBorrowsOnSameDay($deck, $candidate)) {
                        $eligibleEvents[] = $candidate;
                    }
                }
            }

            if ($isOwner) {
                $upcomingEvents = $eventRepository->findUpcomingByEngagement($user);
                foreach ($upcomingEvents as $event) {
                    if (null !== $eventDeckEntryRepository->findOneByEventAndDeck($event, $deck)) {
                        $status = DeckEventStatus::Played;
                    } elseif (null !== $borrowRepository->findActiveBorrowForDeckAtEvent($deck, $event)) {
                        $status = DeckEventStatus::ActivelyBorrowed;
                    } elseif (null !== ($registration = $eventDeckRegistrationRepository->findOneByEventAndDeck($event, $deck))) {
                        $status = $registration->isDelegateToStaff()
                            ? DeckEventStatus::DelegatedToStaff
                            : DeckEventStatus::Registered;
                    } else {
                        $status = DeckEventStatus::NotRegistered;
                    }

                    $eventStatusOverview[] = [
                        'event' => $event,
                        'status' => $status,
                    ];
                }
            }
        }

        $activeBorrowCount = $isOwner ? $borrowRepository->countActiveBorrowsForDeck($deck) : 0;

        $minifiedGroupedCards = [];

        if (null !== $currentVersion && null !== $currentVersion->getMinifiedCardViews()) {
            $minifiedGroupedCards = MinifiedCardView::deserializeGrouped($currentVersion->getMinifiedCardViews());
        } elseif (null !== $currentVersion && null !== $currentVersion->getMinifiedList()) {
            // Fallback for deck versions not yet re-enriched with the new column
            $minifiedGroupedCards = $minifiedCardViewBuilder->buildGrouped($currentVersion);
        }

        $formattedOriginalList = null !== $currentVersion
            ? $originalListFormatter->format($currentVersion)
            : '';

        $showCardmarketExport = $user instanceof User && $user->isShowCardmarketExport();
        $cardmarketWishlist = $showCardmarketExport && null !== $currentVersion
            && (null !== $currentVersion->getMinifiedCardViews() || null !== $currentVersion->getMinifiedList())
            ? $cardmarketWishlistFormatter->format($currentVersion)
            : null;

        return $this->render('deck/show.html.twig', [
            'deck' => $deck,
            'groupedCards' => $orderedGroups,
            'minifiedGroupedCards' => $minifiedGroupedCards,
            'formattedOriginalList' => $formattedOriginalList,
            'cardmarketWishlist' => $cardmarketWishlist,
            'isOwner' => $isOwner,
            'deckBorrows' => $deckBorrows,
            'totalBorrowCount' => $totalBorrowCount,
            'activeBorrowCount' => $activeBorrowCount,
            'eligibleEvents' => $eligibleEvents,
            'eventStatusOverview' => $eventStatusOverview,
            'versionCount' => $deck->getVersions()->count(),
        ]);
    }

    /**
     * @see docs/features.md F5.7 — PDF label card (home printing)
     * @see docs/technicalities/pdf_label.md
     */
    #[Route('/deck/{short_tag}/label.pdf', name: 'app_deck_label_pdf', methods: ['GET'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}'])]
    public function labelPdf(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        PdfLabelGenerator $labelGenerator,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($deck->getOwner()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $pdf = $labelGenerator->generate($deck);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => \sprintf('inline; filename="deck-%s-label.pdf"', $deck->getShortTag()),
        ]);
    }

    /**
     * @see docs/features.md F5.7 — PDF label card (home printing)
     * @see docs/technicalities/pdf_label.md
     */
    #[Route('/deck/{short_tag}/label-foldable.pdf', name: 'app_deck_label_foldable_pdf', methods: ['GET'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}'])]
    public function labelFoldablePdf(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        PdfLabelGenerator $labelGenerator,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($deck->getOwner()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $pdf = $labelGenerator->generateFoldable($deck);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => \sprintf('inline; filename="deck-%s-label-foldable.pdf"', $deck->getShortTag()),
        ]);
    }

    /**
     * @see docs/features.md F5.13 — Printable A4 decklist PDF
     */
    #[Route('/deck/{short_tag}/decklist.pdf', name: 'app_deck_decklist_pdf', methods: ['GET'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}'])]
    public function decklistPdf(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        PdfDecklistGenerator $decklistGenerator,
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($deck->getOwner()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (null === $deck->getCurrentVersion()) {
            throw $this->createNotFoundException('This deck has no version.');
        }

        $anonymous = $request->query->getBoolean('anonymous', false);

        $pdf = $anonymous
            ? $decklistGenerator->generateAnonymous($deck)
            : $decklistGenerator->generatePersonal($deck, $user);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => \sprintf('inline; filename="deck-%s-decklist.pdf"', $deck->getShortTag()),
        ]);
    }

    /**
     * Re-parse and re-enrich the current deck version from its raw list.
     *
     * Deletes all existing cards, re-parses the original deck list text,
     * creates fresh DeckCards, and dispatches enrichment.
     */
    #[Route('/deck/{short_tag}/re-enrich', name: 'app_deck_reenrich', methods: ['POST'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}'])]
    #[IsGranted('ROLE_TECHNICAL_ADMIN')]
    public function reEnrich(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        Request $request,
        EntityManagerInterface $entityManager,
        DeckListParser $parser,
        MessageBusInterface $messageBus,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('deck-reenrich-'.$deck->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $version = $deck->getCurrentVersion();

        if (null === $version) {
            $this->addFlash('warning', 'app.deck.reenrich.no_version');

            return $this->redirectToRoute('app_deck_show', ['short_tag' => $deck->getShortTag()]);
        }

        $rawList = $version->getRawList();

        if (null === $rawList || '' === trim($rawList)) {
            $this->addFlash('warning', 'app.deck.reenrich.no_raw_list');

            return $this->redirectToRoute('app_deck_show', ['short_tag' => $deck->getShortTag()]);
        }

        // Remove all existing cards and flush before re-creating
        // (unique constraint on deck_version_id + set_code + card_number)
        foreach ($version->getCards() as $card) {
            $version->removeCard($card);
            $entityManager->remove($card);
        }

        $entityManager->flush();

        // Re-parse from raw list
        $result = $parser->parse($rawList);

        foreach ($result->cards as $parsedCard) {
            $card = new DeckCard();
            $card->setCardName($parsedCard->cardName);
            $card->setSetCode($parsedCard->setCode);
            $card->setCardNumber($parsedCard->cardNumber);
            $card->setQuantity($parsedCard->quantity);
            $card->setCardType($parsedCard->cardType);
            $version->addCard($card);
        }

        // Reset version enrichment state
        $version->setEnrichmentStatus('pending');
        $version->setMosaicImageUrl(null);
        $version->setMinifiedList(null);
        $version->setMinifiedCardViews(null);
        $version->setMinifiedMosaicImageUrl(null);

        $entityManager->flush();

        // Dispatch enrichment
        /** @var int $versionId */
        $versionId = $version->getId();
        $messageBus->dispatch(new EnrichDeckVersionMessage($versionId));

        $this->addFlash('success', 'app.deck.reenrich.dispatched');

        return $this->redirectToRoute('app_deck_show', ['short_tag' => $deck->getShortTag()]);
    }
}
