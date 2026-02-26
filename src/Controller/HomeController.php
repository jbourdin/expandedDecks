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

use App\Entity\User;
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(DeckRepository $deckRepository, EventRepository $eventRepository): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('home/index.html.twig', [
            'decks' => $deckRepository->findAvailableDecks(),
            'events' => $eventRepository->findUpcoming(),
        ]);
    }

    /**
     * @see docs/features.md F1.2 — Log in / Log out
     */
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function dashboard(
        DeckRepository $deckRepository,
        EventRepository $eventRepository,
        BorrowRepository $borrowRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('home/dashboard.html.twig', [
            'user' => $user,
            'ownedDecks' => $user->getOwnedDecks(),
            'events' => $eventRepository->findUpcoming(),
            'recentBorrows' => $borrowRepository->findRecentByBorrower($user),
        ]);
    }
}
