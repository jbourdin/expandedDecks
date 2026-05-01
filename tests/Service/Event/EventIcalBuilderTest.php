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

namespace App\Tests\Service\Event;

use App\Entity\Event;
use App\Entity\User;
use App\Service\Event\EventIcalBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F3.16 — Public iCal feed
 */
final class EventIcalBuilderTest extends TestCase
{
    public function testBuildsValidVCalendarWithVEventForEachInputEvent(): void
    {
        $builder = $this->makeBuilder();

        $output = $builder->build([$this->makeEvent(1, 'League Cup'), $this->makeEvent(2, 'Regional')]);

        self::assertStringStartsWith('BEGIN:VCALENDAR', $output);
        self::assertStringContainsString('PRODID:-//Expanded Decks//Event Calendar//EN', $output);
        self::assertStringContainsString('SUMMARY:League Cup', $output);
        self::assertStringContainsString('SUMMARY:Regional', $output);
        self::assertStringContainsString('UID:event-1@expandeddecks.app', $output);
        self::assertStringContainsString('UID:event-2@expandeddecks.app', $output);
        self::assertStringContainsString('URL:https://example.test/event/1', $output);
        self::assertStringContainsString("END:VCALENDAR\r\n", $output);
    }

    public function testIncludesFeedTitleAsCalendarName(): void
    {
        $builder = $this->makeBuilder();

        $output = $builder->build([$this->makeEvent(1, 'Cup')], 'My Feed');

        self::assertStringContainsString('NAME:My Feed', $output);
    }

    public function testEmptyEventListProducesEmptyCalendar(): void
    {
        $builder = $this->makeBuilder();

        $output = $builder->build([]);

        self::assertStringContainsString('BEGIN:VCALENDAR', $output);
        self::assertStringNotContainsString('BEGIN:VEVENT', $output);
    }

    public function testCancelledEventGetsCancelledStatus(): void
    {
        $builder = $this->makeBuilder();
        $event = $this->makeEvent(1, 'Cancelled Cup');
        $event->setCancelledAt(new \DateTimeImmutable('2026-04-01'));

        $output = $builder->build([$event]);

        self::assertStringContainsString('STATUS:CANCELLED', $output);
    }

    private function makeBuilder(): EventIcalBuilder
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->willReturnCallback(static fn (string $name, array $params): string => \sprintf('https://example.test/event/%d', $params['id'] ?? 0));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new EventIcalBuilder($urlGenerator, $translator);
    }

    private function makeEvent(int $id, string $name): Event
    {
        $organizer = $this->createStub(User::class);
        $organizer->method('getScreenName')->willReturn('Alice');

        $event = new Event();

        // Force the auto-incremented id via reflection so that the iCal UID is deterministic.
        $reflection = new \ReflectionProperty(Event::class, 'id');
        $reflection->setValue($event, $id);

        $event->setName($name);
        $event->setOrganizer($organizer);
        $event->setDate(new \DateTimeImmutable('2026-06-01 14:00:00', new \DateTimeZone('Europe/Paris')));

        return $event;
    }
}
