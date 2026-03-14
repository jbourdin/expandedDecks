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

use App\Message\EnrichDeckVersionMessage;
use App\Repository\BannedCardRepository;
use App\Repository\DeckVersionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/technical')]
#[IsGranted('ROLE_TECHNICAL_ADMIN')]
class AdminTechnicalController extends AbstractAppController
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly DeckVersionRepository $deckVersionRepository,
        private readonly BannedCardRepository $bannedCardRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_technical_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $pendingEnrichments = $this->deckVersionRepository->findNotEnriched();
        $bannedCardCount = $this->bannedCardRepository->count();

        return $this->render('admin/technical/dashboard.html.twig', [
            'pendingEnrichments' => $pendingEnrichments,
            'bannedCardCount' => $bannedCardCount,
        ]);
    }

    #[Route('/enrich-retry', name: 'app_admin_technical_enrich_retry', methods: ['POST'])]
    public function enrichRetry(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-enrich-retry', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $versions = $this->deckVersionRepository->findNotEnriched();

        if ([] === $versions) {
            $this->addFlash('info', 'app.admin.technical.enrich.none_pending');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        foreach ($versions as $version) {
            /** @var int $id */
            $id = $version->getId();
            $this->messageBus->dispatch(new EnrichDeckVersionMessage($id));
        }

        $this->addFlash('success', 'app.admin.technical.enrich.dispatched', ['%count%' => \count($versions)]);

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }

    #[Route('/banned-cards-sync', name: 'app_admin_technical_banned_cards_sync', methods: ['POST'])]
    public function bannedCardsSync(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('technical-banned-cards-sync', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_technical_dashboard');
        }

        $process = new Process(['symfony', 'console', 'app:banned-cards:sync'], $this->getParameter('kernel.project_dir'));
        $process->setTimeout(60);
        $process->run();

        if ($process->isSuccessful()) {
            $this->addFlash('success', 'app.admin.technical.banned_cards.synced');
        } else {
            $this->addFlash('danger', 'app.admin.technical.banned_cards.failed');
        }

        return $this->redirectToRoute('app_admin_technical_dashboard');
    }
}
