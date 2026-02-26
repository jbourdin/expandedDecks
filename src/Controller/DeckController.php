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
use App\Entity\DeckVersion;
use App\Entity\User;
use App\Form\DeckFormType;
use App\Form\DeckImportFormType;
use App\Repository\DeckVersionRepository;
use App\Service\DeckListParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F2.1 — Register a new deck (owner)
 * @see docs/features.md F2.2 — Import deck list (PTCG text format)
 * @see docs/features.md F2.3 — Detail view
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
            $em->persist($deck);
            $em->flush();

            $this->addFlash('success', \sprintf('Deck "%s" created.', $deck->getName()));

            return $this->redirectToRoute('app_deck_show', ['id' => $deck->getId()]);
        }

        return $this->render('deck/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * @see docs/features.md F2.3 — Detail view
     */
    #[Route('/{id}', name: 'app_deck_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Deck $deck): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $groupedCards = [];
        $currentVersion = $deck->getCurrentVersion();

        if (null !== $currentVersion) {
            foreach ($currentVersion->getCards() as $card) {
                $groupedCards[$card->getCardType()][] = $card;
            }

            // Sort within each group: quantity desc, name asc
            foreach ($groupedCards as &$cards) {
                usort($cards, static function (DeckCard $a, DeckCard $b): int {
                    if ($a->getQuantity() !== $b->getQuantity()) {
                        return $b->getQuantity() - $a->getQuantity();
                    }

                    return strcmp($a->getCardName(), $b->getCardName());
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

        return $this->render('deck/show.html.twig', [
            'deck' => $deck,
            'groupedCards' => $orderedGroups,
            'isOwner' => $deck->getOwner()->getId() === $user->getId(),
        ]);
    }

    /**
     * @see docs/features.md F2.1 — Register a new deck (owner)
     */
    #[Route('/{id}/edit', name: 'app_deck_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Deck $deck, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessOwner($deck);

        $form = $this->createForm(DeckFormType::class, $deck);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', \sprintf('Deck "%s" updated.', $deck->getName()));

            return $this->redirectToRoute('app_deck_show', ['id' => $deck->getId()]);
        }

        return $this->render('deck/edit.html.twig', [
            'deck' => $deck,
            'form' => $form,
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
        DeckVersionRepository $versionRepo,
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

            $version = new DeckVersion();
            $version->setDeck($deck);
            $version->setVersionNumber($nextVersion);
            $version->setRawList($rawList);

            /** @var string|null $archetype */
            $archetype = $form->get('archetype')->getData();

            /** @var string|null $archetypeName */
            $archetypeName = $form->get('archetypeName')->getData();

            if (null !== $archetype && '' !== $archetype) {
                $version->setArchetype($archetype);
            }
            if (null !== $archetypeName && '' !== $archetypeName) {
                $version->setArchetypeName($archetypeName);
            }

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

            $this->addFlash('success', \sprintf(
                'Deck list imported (version %d, %d cards).',
                $nextVersion,
                $result->totalCards(),
            ));

            return $this->redirectToRoute('app_deck_show', ['id' => $deck->getId()]);
        }

        return $this->render('deck/import.html.twig', [
            'deck' => $deck,
            'form' => $form,
            'nextVersion' => $nextVersion,
        ]);
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
