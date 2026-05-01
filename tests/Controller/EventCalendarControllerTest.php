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

namespace App\Tests\Controller;

use App\Controller\EventCalendarController;
use App\Entity\EventTag;
use App\Repository\EventRepository;
use App\Repository\EventTagRepository;
use App\Service\Event\EventIcalBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Direct (non-kernel) tests for the public iCal feeds — Symfony's
 * SessionListener rewrites cache headers in WebTestCase, so we exercise
 * the controller without the kernel to verify the headers we set.
 *
 * @see docs/features.md F3.16 — Public iCal feed
 */
final class EventCalendarControllerTest extends TestCase
{
    public function testListSetsPublicCacheHeadersAndContentType(): void
    {
        $controller = new EventCalendarController();

        $eventRepo = $this->createStub(EventRepository::class);
        $eventRepo->method('findPublicUpcoming')->willReturn([]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $builder = new EventIcalBuilder(
            $this->createStub(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class),
            $translator,
        );

        $response = $controller->list($eventRepo, $builder, $translator);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('inline; filename="expanded-decks-events.ics"', $response->headers->get('Content-Disposition'));

        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('max-age=3600', $cacheControl);

        self::assertStringStartsWith('BEGIN:VCALENDAR', (string) $response->getContent());
    }

    public function testTagSetsFilenameFromSlug(): void
    {
        $controller = new EventCalendarController();

        $tag = (new EventTag())->setName('League');

        $tagRepo = $this->createStub(EventTagRepository::class);
        $tagRepo->method('findOneBySlug')->willReturn($tag);

        $eventRepo = $this->createStub(EventRepository::class);
        $eventRepo->method('findPublicUpcomingByTag')->willReturn([]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $builder = new EventIcalBuilder(
            $this->createStub(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class),
            $translator,
        );

        $response = $controller->tag('league', $eventRepo, $tagRepo, $builder, $translator);

        self::assertSame('inline; filename="expanded-decks-events-league.ics"', $response->headers->get('Content-Disposition'));
    }

    public function testTagThrowsNotFoundForUnknownSlug(): void
    {
        $controller = new EventCalendarController();

        $tagRepo = $this->createStub(EventTagRepository::class);
        $tagRepo->method('findOneBySlug')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $translator = $this->createStub(TranslatorInterface::class);
        $builder = new EventIcalBuilder(
            $this->createStub(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class),
            $translator,
        );

        $controller->tag(
            'no-such-tag',
            $this->createStub(EventRepository::class),
            $tagRepo,
            $builder,
            $translator,
        );
    }
}
