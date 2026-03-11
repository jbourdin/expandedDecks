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
use App\Entity\User;
use App\Repository\DeckVersionRepository;
use App\Service\DeckVersionDiffer;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F2.9 — Deck version history
 */
class DeckVersionHistoryController extends AbstractController
{
    #[Route('/deck/{short_tag}/versions', name: 'app_deck_versions', methods: ['GET'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}'])]
    public function versions(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        DeckVersionRepository $versionRepository,
    ): Response {
        $this->denyAccessUnlessCanViewDeck($deck);

        $versions = $versionRepository->findByDeckOrderedByVersion($deck);

        return $this->render('deck/versions.html.twig', [
            'deck' => $deck,
            'versions' => $versions,
        ]);
    }

    #[Route('/api/deck/{short_tag}/versions/compare', name: 'app_deck_version_compare', methods: ['GET'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}'])]
    public function compare(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        Request $request,
        DeckVersionRepository $versionRepository,
        DeckVersionDiffer $differ,
    ): JsonResponse {
        $this->denyAccessUnlessCanViewDeck($deck);

        $fromNumber = $request->query->getInt('from');
        $toNumber = $request->query->getInt('to');

        if ($fromNumber < 1 || $toNumber < 1) {
            throw new NotFoundHttpException('Invalid version numbers.');
        }

        $versions = $versionRepository->findByDeckOrderedByVersion($deck);
        $fromVersion = null;
        $toVersion = null;

        foreach ($versions as $version) {
            if ($version->getVersionNumber() === $fromNumber) {
                $fromVersion = $version;
            }
            if ($version->getVersionNumber() === $toNumber) {
                $toVersion = $version;
            }
        }

        if (null === $fromVersion || null === $toVersion) {
            throw new NotFoundHttpException('Version not found.');
        }

        return $this->json($differ->diff($fromVersion, $toVersion));
    }

    private function denyAccessUnlessCanViewDeck(Deck $deck): void
    {
        if ($deck->isPublic()) {
            return;
        }

        /** @var User|null $user */
        $user = $this->getUser();

        if (null === $user) {
            throw $this->createAccessDeniedException();
        }

        if ($deck->getOwner()->getId() === $user->getId() || $this->isGranted('ROLE_ADMIN')) {
            return;
        }

        foreach ($deck->getEventRegistrations() as $registration) {
            if ($registration->getEvent()->isOrganizerOrStaff($user)) {
                return;
            }
        }

        throw $this->createAccessDeniedException();
    }
}
