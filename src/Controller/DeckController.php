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

use App\Entity\Archetype;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Entity\User;
use App\Form\DeckFormType;
use App\Form\DeckImportFormType;
use App\Message\EnrichDeckVersionMessage;
use App\Repository\DeckVersionRepository;
use App\Repository\EventDeckRegistrationRepository;
use App\Service\DeckListParser;
use App\Service\DeckListValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F2.1 — Register a new deck (owner)
 * @see docs/features.md F2.2 — Import deck list (PTCG text format)
 * @see docs/features.md F2.8 — Update list
 */
#[Route('/deck')]
#[IsGranted('ROLE_USER')]
class DeckController extends AbstractController
{
    /**
     * @see docs/features.md F2.1 — Register a new deck (owner)
     */
    #[Route('/new', name: 'app_deck_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $deck = new Deck();
        $form = $this->createForm(DeckFormType::class, $deck);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $deck->setOwner($user);
            $this->handleArchetypeAndLanguages($form, $deck, $em);
            $em->persist($deck);
            $em->flush();

            $this->addFlash('success', \sprintf('Deck "%s" created.', $deck->getName()));

            return $this->redirectToRoute('app_deck_show', ['short_tag' => $deck->getShortTag()]);
        }

        return $this->render('deck/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * @see docs/features.md F2.1 — Register a new deck (owner)
     * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
     */
    #[Route('/{id}/edit', name: 'app_deck_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Deck $deck, Request $request, EntityManagerInterface $em, EventDeckRegistrationRepository $registrationRepository): Response
    {
        $this->denyAccessUnlessOwner($deck);

        $hasActiveRegistrations = $registrationRepository->hasActiveRegistrations($deck);
        $wasPublic = $deck->isPublic();

        $form = $this->createForm(DeckFormType::class, $deck, [
            'public_disabled' => $hasActiveRegistrations && $wasPublic,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($wasPublic && !$deck->isPublic() && $hasActiveRegistrations) {
                $deck->setPublic(true);
                $this->addFlash('warning', 'Cannot unpublish this deck — it has active event registrations.');

                return $this->redirectToRoute('app_deck_edit', ['id' => $deck->getId()]);
            }

            $this->handleArchetypeAndLanguages($form, $deck, $em);
            $em->flush();

            $this->addFlash('success', \sprintf('Deck "%s" updated.', $deck->getName()));

            return $this->redirectToRoute('app_deck_show', ['short_tag' => $deck->getShortTag()]);
        }

        return $this->render('deck/edit.html.twig', [
            'deck' => $deck,
            'form' => $form,
            'has_active_registrations' => $hasActiveRegistrations,
        ]);
    }

    /**
     * @see docs/features.md F2.2 — Import deck list (PTCG text format)
     * @see docs/features.md F2.8 — Update list
     */
    #[Route('/{id}/import', name: 'app_deck_import', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function import(
        Deck $deck,
        Request $request,
        EntityManagerInterface $em,
        DeckListParser $parser,
        DeckListValidator $validator,
        DeckVersionRepository $versionRepo,
        MessageBusInterface $messageBus,
    ): Response {
        $this->denyAccessUnlessOwner($deck);

        $nextVersion = $versionRepo->findMaxVersionNumber($deck) + 1;

        $form = $this->createForm(DeckImportFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $rawList */
            $rawList = $form->get('rawList')->getData();
            $result = $parser->parse($rawList);

            if (!$result->isValid()) {
                foreach ($result->errors as $error) {
                    $this->addFlash('warning', $error);
                }

                return $this->render('deck/import.html.twig', [
                    'deck' => $deck,
                    'form' => $form,
                    'nextVersion' => $nextVersion,
                ]);
            }

            // Validate deck rules (60 cards, max 4 copies)
            $validation = $validator->validate($result);

            if (!$validation->isValid()) {
                foreach ($validation->errors as $error) {
                    $this->addFlash('danger', $error);
                }

                return $this->render('deck/import.html.twig', [
                    'deck' => $deck,
                    'form' => $form,
                    'nextVersion' => $nextVersion,
                ]);
            }

            foreach ($validation->warnings as $warning) {
                $this->addFlash('warning', $warning);
            }

            $version = new DeckVersion();
            $version->setDeck($deck);
            $version->setVersionNumber($nextVersion);
            $version->setRawList($rawList);

            foreach ($result->cards as $parsedCard) {
                $card = new DeckCard();
                $card->setCardName($parsedCard->cardName);
                $card->setSetCode($parsedCard->setCode);
                $card->setCardNumber($parsedCard->cardNumber);
                $card->setQuantity($parsedCard->quantity);
                $card->setCardType($parsedCard->cardType);
                $version->addCard($card);
            }

            $em->persist($version);
            $deck->setCurrentVersion($version);
            $em->flush();

            /** @var int $versionId */
            $versionId = $version->getId();
            $messageBus->dispatch(new EnrichDeckVersionMessage($versionId));

            $this->addFlash('success', \sprintf(
                'Deck list imported (version %d, %d cards). Card enrichment is processing in the background.',
                $nextVersion,
                $result->totalCards(),
            ));

            return $this->redirectToRoute('app_deck_show', ['short_tag' => $deck->getShortTag()]);
        }

        return $this->render('deck/import.html.twig', [
            'deck' => $deck,
            'form' => $form,
            'nextVersion' => $nextVersion,
        ]);
    }

    /**
     * @param \Symfony\Component\Form\FormInterface<Deck> $form
     */
    private function handleArchetypeAndLanguages(\Symfony\Component\Form\FormInterface $form, Deck $deck, EntityManagerInterface $em): void
    {
        /** @var string|null $archetypeId */
        $archetypeId = $form->get('archetype')->getData();

        if (null !== $archetypeId && '' !== $archetypeId) {
            $archetype = $em->getRepository(Archetype::class)->find((int) $archetypeId);
            $deck->setArchetype($archetype);
        } else {
            $deck->setArchetype(null);
        }

        /** @var string|null $languagesJson */
        $languagesJson = $form->get('languages')->getData();

        if (null !== $languagesJson && '' !== $languagesJson) {
            /** @var list<string> $languages */
            $languages = json_decode($languagesJson, true);
            $deck->setLanguages($languages);
        } else {
            $deck->setLanguages([]);
        }
    }

    private function denyAccessUnlessOwner(Deck $deck): void
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($deck->getOwner()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You are not the owner of this deck.');
        }
    }
}
