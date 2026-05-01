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

use App\Repository\EventRepository;
use App\Repository\EventTagRepository;
use App\Service\Event\EventIcalBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Public iCal calendar feeds for upcoming events.
 *
 * @see docs/features.md F3.16 — Public iCal feed
 */
class EventCalendarController extends AbstractController
{
    private const int CACHE_MAX_AGE = 3600;
    private const int FEED_LIMIT = 200;

    /**
     * Calendar feed of all upcoming public events.
     */
    #[Route('/event.ics', name: 'app_event_list_ical', methods: ['GET'], priority: 20)]
    public function list(
        EventRepository $eventRepository,
        EventIcalBuilder $builder,
        TranslatorInterface $translator,
    ): Response {
        $events = $eventRepository->findPublicUpcoming(self::FEED_LIMIT);

        $calendar = $builder->build(
            $events,
            $translator->trans('app.event.ical.feed_title'),
        );

        return $this->buildIcalResponse($calendar, 'expanded-decks-events.ics');
    }

    /**
     * Calendar feed of upcoming public events tagged with the given slug.
     */
    #[Route('/event/tag/{slug}.ics', name: 'app_event_tag_ical', methods: ['GET'], requirements: ['slug' => '[a-z0-9][a-z0-9-]*'], priority: 20)]
    public function tag(
        string $slug,
        EventRepository $eventRepository,
        EventTagRepository $tagRepository,
        EventIcalBuilder $builder,
        TranslatorInterface $translator,
    ): Response {
        $tag = $tagRepository->findOneBySlug($slug);

        if (null === $tag) {
            throw $this->createNotFoundException();
        }

        $events = $eventRepository->findPublicUpcomingByTag($tag, self::FEED_LIMIT);

        $calendar = $builder->build(
            $events,
            $translator->trans('app.event.ical.feed_title_tag', ['%name%' => $tag->getName()]),
        );

        return $this->buildIcalResponse($calendar, \sprintf('expanded-decks-events-%s.ics', $tag->getSlug()));
    }

    private function buildIcalResponse(string $calendar, string $filename): Response
    {
        $response = new Response($calendar, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => \sprintf('inline; filename="%s"', $filename),
            'Cache-Control' => \sprintf('public, max-age=%d', self::CACHE_MAX_AGE),
        ]);

        $response->setPublic();
        $response->setMaxAge(self::CACHE_MAX_AGE);

        return $response;
    }
}
