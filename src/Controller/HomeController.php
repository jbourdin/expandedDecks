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

use App\Entity\Channel;
use App\Entity\User;
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Repository\HomepageLayoutRepository;
use App\Repository\MenuCategoryRepository;
use App\Repository\PageRepository;
use App\Service\HomepageRenderer;
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
     * @see docs/features.md F10.4 — Homepage rendering service and Twig block partials
     */
    #[Route('/', name: 'app_home')]
    public function index(
        Request $request,
        DeckRepository $deckRepository,
        EventRepository $eventRepository,
        PageRepository $pageRepository,
        MenuCategoryRepository $menuCategoryRepository,
        MarkdownRenderer $markdownRenderer,
        HomepageLayoutRepository $homepageLayoutRepository,
        HomepageRenderer $homepageRenderer,
    ): Response {
        $locale = $request->getLocale();

        // Use published layout for the current channel if available
        $channel = $request->attributes->get('_channel');
        $layout = $homepageLayoutRepository->findPublished($channel instanceof Channel ? $channel : null);
        if (null !== $layout) {
            $blocks = $homepageRenderer->resolve($layout, $locale);

            return $this->render('home/blocks.html.twig', [
                'blocks' => $blocks,
            ]);
        }

        // Fallback to hardcoded homepage when no layout exists
        // Only redirect to dashboard on channels with decks enabled
        if ($this->getUser() && $channel instanceof Channel && $channel->getEnableDecks()) {
            return $this->redirectToRoute('app_dashboard');
        }
        $welcomePage = $pageRepository->findBySlug('welcome');
        $welcomeHtml = null;
        if (null !== $welcomePage && $welcomePage->isPublished()) {
            $translation = $welcomePage->getDisplayTranslation($locale);
            if (null !== $translation) {
                $welcomeHtml = $markdownRenderer->render($translation->getContent());
            }
        }

        $newsPages = [];
        $newsCategory = null;
        $newsTotalCount = 0;
        $newsCategories = $menuCategoryRepository->findBy(['id' => array_map(
            static fn ($category) => $category->getId(),
            array_filter(
                $menuCategoryRepository->findAllOrdered(),
                static fn ($category) => 'news' === strtolower($category->getName('en')),
            ),
        )]);

        if (\count($newsCategories) > 0) {
            $newsCategory = $newsCategories[0];
            $newsPages = $pageRepository->findPublishedByCategory($newsCategory, 5);
            $newsTotalCount = $pageRepository->countPublishedByCategory($newsCategory);
        }

        return $this->render('home/index.html.twig', [
            'publicDeckCount' => $deckRepository->countPublicDecks(),
            'upcomingEventCount' => $eventRepository->countUpcoming(),
            'welcomeHtml' => $welcomeHtml,
            'newsPages' => $newsPages,
            'newsCategory' => $newsCategory,
            'newsTotalCount' => $newsTotalCount,
            'locale' => $locale,
        ]);
    }

    /**
     * @see docs/features.md F7.1 — Dashboard
     * @see docs/features.md F7.4 — Dashboard action reminders
     */
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function dashboard(
        DeckRepository $deckRepository,
        EventRepository $eventRepository,
        BorrowRepository $borrowRepository,
        PageRepository $pageRepository,
        MenuCategoryRepository $menuCategoryRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Latest news
        $newsPages = [];
        $newsCategory = null;
        $newsTotalCount = 0;
        $newsCategories = array_filter(
            $menuCategoryRepository->findAllOrdered(),
            static fn ($category) => 'news' === strtolower($category->getName('en')),
        );

        if (\count($newsCategories) > 0) {
            $newsCategory = reset($newsCategories);
            $newsPages = $pageRepository->findPublishedByCategory($newsCategory, 5);
            $newsTotalCount = $pageRepository->countPublishedByCategory($newsCategory);
        }

        // Action reminders (F7.4)
        $borrowsToReturn = $borrowRepository->findBorrowsToReturn($user);
        $pendingRequests = $borrowRepository->findPendingRequestsForOwner($user);
        $eventsNeedingDeck = $eventRepository->findUpcomingNeedingDeckSelection($user);

        $params = [
            'newsPages' => $newsPages,
            'newsCategory' => $newsCategory,
            'newsTotalCount' => $newsTotalCount,
            'user' => $user,
            'ownedDecks' => $user->getOwnedDecks(),
            'myEvents' => $eventRepository->findUpcomingByEngagement($user),
            'staffingEvents' => $eventRepository->findRecentByOrganizerOrStaff($user),
            'recentBorrows' => $borrowRepository->findRecentByBorrower($user),
            'recentLends' => $borrowRepository->findRecentByDeckOwner($user),
            'recentManagedBorrows' => $borrowRepository->findRecentByEventOrganizerOrStaff($user),
            'borrowsToReturn' => $borrowsToReturn,
            'pendingRequests' => $pendingRequests,
            'eventsNeedingDeck' => $eventsNeedingDeck,
        ];

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
