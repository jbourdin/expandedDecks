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
use App\Repository\MenuCategoryRepository;
use App\Repository\PageRepository;
use App\Service\MarkdownRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    /**
     * @see docs/features.md F10.2 — Anonymous homepage
     */
    #[Route('/', name: 'app_home')]
    public function index(
        Request $request,
        DeckRepository $deckRepository,
        EventRepository $eventRepository,
        PageRepository $pageRepository,
        MenuCategoryRepository $menuCategoryRepository,
        MarkdownRenderer $markdownRenderer,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $locale = $request->getLocale();

        // Welcome block: look for a published page with slug "welcome"
        $welcomePage = $pageRepository->findBySlug('welcome');
        $welcomeHtml = null;
        if (null !== $welcomePage && $welcomePage->isPublished()) {
            $translation = $welcomePage->getTranslation($locale);
            if (null !== $translation) {
                $welcomeHtml = $markdownRenderer->render($translation->getContent());
            }
        }

        // Latest news: look for pages in "news" category
        $newsPages = [];
        $newsCategories = $menuCategoryRepository->findBy(['id' => array_map(
            static fn ($category) => $category->getId(),
            array_filter(
                $menuCategoryRepository->findAllOrdered(),
                static fn ($category) => 'news' === strtolower($category->getName('en')),
            ),
        )]);

        if (\count($newsCategories) > 0) {
            $newsPages = $pageRepository->findPublishedByCategory($newsCategories[0], 3);
        }

        return $this->render('home/index.html.twig', [
            'publicDeckCount' => $deckRepository->countPublicDecks(),
            'upcomingEventCount' => $eventRepository->countUpcoming(),
            'welcomeHtml' => $welcomeHtml,
            'newsPages' => $newsPages,
            'locale' => $locale,
        ]);
    }

    /**
     * @see docs/features.md F7.1 — Dashboard
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

        $params = [
            'user' => $user,
            'ownedDecks' => $user->getOwnedDecks(),
            'myEvents' => $eventRepository->findUpcomingByEngagement($user),
            'staffingEvents' => $eventRepository->findRecentByOrganizerOrStaff($user),
            'recentBorrows' => $borrowRepository->findRecentByBorrower($user),
            'recentLends' => $borrowRepository->findRecentByDeckOwner($user),
            'recentManagedBorrows' => $borrowRepository->findRecentByEventOrganizerOrStaff($user),
        ];

        if ($this->isGranted('ROLE_ORGANIZER')) {
            $params['stats'] = [
                'totalDecks' => $deckRepository->countAll(),
                'activeBorrows' => $borrowRepository->countActive(),
                'upcomingEvents' => $eventRepository->countUpcoming(),
                'overdueReturns' => $borrowRepository->countOverdue(),
            ];
        }

        $myUpcomingEvents = $eventRepository->countUpcomingByOrganizerOrStaff($user);
        if ($myUpcomingEvents > 0 || $this->isGranted('ROLE_ORGANIZER')) {
            $params['myStats'] = [
                'registeredDecks' => $deckRepository->countRegisteredByOrganizerOrStaff($user),
                'activeBorrows' => $borrowRepository->countActiveByOrganizerOrStaff($user),
                'upcomingEvents' => $myUpcomingEvents,
                'overdueReturns' => $borrowRepository->countOverdueByOrganizerOrStaff($user),
            ];
        }

        return $this->render('home/dashboard.html.twig', $params);
    }
}
