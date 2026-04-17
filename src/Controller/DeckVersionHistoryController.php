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
use App\Message\EnrichDeckVersionMessage;
use App\Repository\DeckVersionRepository;
use App\Service\DeckVersionDiffer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F2.9 — Deck version history
 * @see docs/features.md F2.9 — Restore previous deck version (#412)
 */
class DeckVersionHistoryController extends AbstractAppController
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
            'isOwner' => $this->canMutateDeck($deck),
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

    /**
     * Export the raw deck list for a specific version as a text file download.
     */
    #[Route('/deck/{short_tag}/versions/{versionNumber}/export', name: 'app_deck_version_export', methods: ['GET'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}', 'versionNumber' => '\d+'])]
    public function exportList(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        int $versionNumber,
        DeckVersionRepository $versionRepository,
    ): Response {
        $this->denyAccessUnlessOwner($deck);

        $version = $versionRepository->findOneByDeckAndVersion($deck, $versionNumber);

        if (null === $version || null === $version->getRawList()) {
            throw $this->createNotFoundException();
        }

        return new Response($version->getRawList(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => \sprintf('attachment; filename="deck-%s-v%d.txt"', $deck->getShortTag(), $versionNumber),
        ]);
    }

    /**
     * Soft-delete a previous deck version (not the current version).
     */
    #[Route('/deck/{short_tag}/versions/{versionNumber}/delete', name: 'app_deck_version_delete', methods: ['POST'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}', 'versionNumber' => '\d+'])]
    public function deleteVersion(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        int $versionNumber,
        Request $request,
        DeckVersionRepository $versionRepository,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $this->denyAccessUnlessOwner($deck);

        if (!$this->isCsrfTokenValid('deck-version-delete-'.$deck->getId().'-'.$versionNumber, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $version = $versionRepository->findOneByDeckAndVersion($deck, $versionNumber);

        if (null === $version) {
            throw $this->createNotFoundException();
        }

        // Cannot delete the current version
        if ($deck->getCurrentVersion()?->getId() === $version->getId()) {
            $this->addFlash('warning', 'app.deck.version.cannot_delete_current');

            return $this->redirectToRoute('app_deck_versions', ['short_tag' => $deck->getShortTag()]);
        }

        $version->setDeletedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'app.deck.version.deleted');

        return $this->redirectToRoute('app_deck_versions', ['short_tag' => $deck->getShortTag()]);
    }

    /**
     * Restore a previous version as the active deck version (pointer update, no new version created).
     *
     * @see docs/features.md F2.9 — Deck version history
     */
    #[Route('/deck/{short_tag}/versions/{versionNumber}/restore', name: 'app_deck_version_restore', methods: ['POST'], requirements: ['short_tag' => '[A-HJ-NP-Z0-9]{6}', 'versionNumber' => '\d+'])]
    public function restoreVersion(
        #[MapEntity(mapping: ['short_tag' => 'shortTag'])] Deck $deck,
        int $versionNumber,
        Request $request,
        DeckVersionRepository $versionRepository,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
    ): RedirectResponse {
        $this->denyAccessUnlessOwner($deck);

        if (!$this->isCsrfTokenValid('deck-version-restore-'.$deck->getId().'-'.$versionNumber, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $version = $versionRepository->findOneByDeckAndVersion($deck, $versionNumber);

        if (null === $version || null !== $version->getDeletedAt()) {
            throw $this->createNotFoundException();
        }

        if ($deck->getCurrentVersion()?->getId() === $version->getId()) {
            $this->addFlash('warning', 'app.deck.version.already_current');

            return $this->redirectToRoute('app_deck_versions', ['short_tag' => $deck->getShortTag()]);
        }

        $deck->setCurrentVersion($version);
        $entityManager->flush();

        if ('done' !== $version->getEnrichmentStatus()) {
            /** @var int $versionId */
            $versionId = $version->getId();
            $messageBus->dispatch(new EnrichDeckVersionMessage($versionId));
        }

        $this->addFlash('success', 'app.deck.version.restored');

        return $this->redirectToRoute('app_deck_versions', ['short_tag' => $deck->getShortTag()]);
    }

    /**
     * Check whether the current user can mutate the deck (owner for user decks, editor/admin for variants).
     */
    private function canMutateDeck(Deck $deck): bool
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (null === $user) {
            return false;
        }

        if ($deck->isArchetypeVariant()) {
            return $this->isGranted('ROLE_ARCHETYPE_EDITOR') || $this->isGranted('ROLE_ADMIN');
        }

        $owner = $deck->getOwner();

        return null !== $owner && $owner->getId() === $user->getId();
    }

    private function denyAccessUnlessOwner(Deck $deck): void
    {
        if (!$this->canMutateDeck($deck)) {
            throw $this->createAccessDeniedException();
        }
    }

    private function denyAccessUnlessCanViewDeck(Deck $deck): void
    {
        // Variant decks are editorial content, accessible to editors and admins only.
        if ($deck->isArchetypeVariant()) {
            if ($this->isGranted('ROLE_ARCHETYPE_EDITOR') || $this->isGranted('ROLE_ADMIN')) {
                return;
            }

            throw $this->createAccessDeniedException();
        }

        if ($deck->isPublic()) {
            return;
        }

        /** @var User|null $user */
        $user = $this->getUser();

        if (null === $user) {
            throw $this->createAccessDeniedException();
        }

        $owner = $deck->getOwner();

        if ((null !== $owner && $owner->getId() === $user->getId()) || $this->isGranted('ROLE_ADMIN')) {
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
