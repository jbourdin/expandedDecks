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
use App\Repository\EventRepository;
use App\Service\Event\EventIcalBuilder;
use App\Service\Event\PersonalCalendarTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Per-user agenda view + private iCal feed.
 *
 * @see docs/features.md F3.14 — iCal agenda feed
 */
class EventAgendaController extends AbstractController
{
    private const int FEED_LIMIT = 200;
    private const int CACHE_MAX_AGE = 1800;

    /**
     * Logged-in agenda: every upcoming event the current user is engaged with
     * (interested, invited, registered playing, registered spectating).
     */
    #[Route('/event/agenda', name: 'app_event_agenda', methods: ['GET'], priority: 20)]
    #[IsGranted('ROLE_USER')]
    public function show(
        EventRepository $eventRepository,
        PersonalCalendarTokenService $tokenService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $token = $tokenService->ensureToken($user);

        $events = $eventRepository->findUpcomingForUserAgenda($user);

        return $this->render('event/agenda.html.twig', [
            'events' => $events,
            'icalUrl' => $this->generateUrl(
                'app_event_agenda_ical',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_PATH,
            ),
            'icalAbsoluteUrl' => $this->generateUrl(
                'app_event_agenda_ical',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ]);
    }

    /**
     * Issue a fresh token, invalidating the previous URL.
     */
    #[Route('/event/agenda/regenerate-token', name: 'app_event_agenda_regenerate', methods: ['POST'], priority: 20)]
    #[IsGranted('ROLE_USER')]
    public function regenerate(Request $request, PersonalCalendarTokenService $tokenService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('event-agenda-regenerate', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.flash.invalid_token');

            return $this->redirectToRoute('app_event_agenda');
        }

        /** @var User $user */
        $user = $this->getUser();

        $tokenService->regenerateToken($user);
        $this->addFlash('success', 'app.event.agenda.token_regenerated');

        return $this->redirectToRoute('app_event_agenda');
    }

    /**
     * Anonymous-friendly per-user iCal feed. The token in the URL is the
     * sole authentication factor, so we never reveal whether a token exists
     * (404 covers both unknown tokens and unknown users).
     */
    #[Route('/calendar/event/{token}.ics', name: 'app_event_agenda_ical', methods: ['GET'], requirements: ['token' => '[A-Za-z0-9_-]{8,64}'], priority: 30)]
    public function feed(
        string $token,
        EventRepository $eventRepository,
        PersonalCalendarTokenService $tokenService,
        EventIcalBuilder $builder,
        TranslatorInterface $translator,
    ): Response {
        $user = $tokenService->findUserByToken($token);

        if (null === $user) {
            throw $this->createNotFoundException();
        }

        $events = $eventRepository->findUpcomingForUserAgenda($user);

        $calendar = $builder->build(
            \array_slice($events, 0, self::FEED_LIMIT),
            $translator->trans('app.event.ical.agenda_feed_title', ['%name%' => $user->getScreenName()]),
        );

        $response = new Response($calendar);

        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', \sprintf('inline; filename="agenda-%s.ics"', $user->getScreenName()));
        $response->setPrivate();
        $response->setMaxAge(self::CACHE_MAX_AGE);
        $response->headers->set('Cache-Control', 'private, max-age='.self::CACHE_MAX_AGE);

        return $response;
    }
}
