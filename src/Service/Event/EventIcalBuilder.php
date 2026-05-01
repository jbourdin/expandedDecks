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

namespace App\Service\Event;

use App\Entity\Event as AppEvent;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event as IcalEvent;
use Eluceo\iCal\Domain\Enum\EventStatus;
use Eluceo\iCal\Domain\ValueObject\DateTime as IcalDateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Timestamp;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Render a list of Expanded Decks events as an iCalendar (RFC 5545) feed.
 *
 * @see docs/features.md F3.16 — Public iCal feed
 */
final class EventIcalBuilder
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param iterable<AppEvent> $events
     */
    public function build(iterable $events, ?string $feedTitle = null): string
    {
        $icalEvents = [];

        foreach ($events as $appEvent) {
            $icalEvents[] = $this->buildEvent($appEvent);
        }

        $calendar = new Calendar($icalEvents);
        $calendar->setProductIdentifier('-//Expanded Decks//Event Calendar//EN');

        $output = (string) (new CalendarFactory())->createCalendar($calendar);

        // eluceo/ical 2.x has no first-class API for the human-readable feed name
        // (X-WR-CALNAME / NAME), so we inject it after rendering when provided.
        if (null !== $feedTitle && '' !== $feedTitle) {
            $output = $this->injectCalendarName($output, $feedTitle);
        }

        return $output;
    }

    private function injectCalendarName(string $calendar, string $name): string
    {
        $escaped = $this->escapeText($name);
        $headers = "X-WR-CALNAME:{$escaped}\r\nNAME:{$escaped}\r\n";

        return preg_replace(
            '/(PRODID:[^\r\n]+\r\n)/',
            '$1'.$headers,
            $calendar,
            1,
        ) ?? $calendar;
    }

    private function escapeText(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            "\r\n" => '\\n',
            "\n" => '\\n',
            ',' => '\\,',
            ';' => '\\;',
        ]);
    }

    private function buildEvent(AppEvent $appEvent): IcalEvent
    {
        $startUtc = $this->toUtc($appEvent->getDate());
        $endUtc = $this->toUtc($appEvent->getEndDate() ?? $appEvent->getDate()->modify('+3 hours'));

        $url = $this->urlGenerator->generate(
            'app_event_show',
            ['id' => $appEvent->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $icalEvent = (new IcalEvent(new UniqueIdentifier(\sprintf('event-%d@expandeddecks.app', $appEvent->getId() ?? 0))))
            ->setSummary($appEvent->getName())
            ->setDescription($this->buildDescription($appEvent, $url))
            ->setUrl(new Uri($url))
            ->setOccurrence(new TimeSpan(
                new IcalDateTime($startUtc, true),
                new IcalDateTime($endUtc, true),
            ));

        $location = $appEvent->getLocation();

        if (null !== $location && '' !== $location) {
            $icalEvent->setLocation(new Location($location));
        }

        if (null !== $appEvent->getCancelledAt()) {
            $icalEvent->setStatus(EventStatus::CANCELLED());
        } elseif (null !== $appEvent->getFinishedAt()) {
            $icalEvent->setStatus(EventStatus::CONFIRMED());
        }

        $icalEvent->touch(new Timestamp($appEvent->getCreatedAt()));

        return $icalEvent;
    }

    private function toUtc(\DateTimeImmutable $dateTime): \DateTimeImmutable
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'));
    }

    private function buildDescription(AppEvent $appEvent, string $url): string
    {
        $lines = [];

        $organizer = $appEvent->getOrganizer()->getScreenName();
        $lines[] = $this->translator->trans('app.event.ical.organized_by', ['%name%' => $organizer]);

        if (null !== $appEvent->getTournamentStructure()) {
            $structureLabel = ucwords(str_replace('_', ' ', $appEvent->getTournamentStructure()->value));
            $lines[] = $this->translator->trans('app.event.ical.structure', ['%structure%' => $structureLabel]);
        }

        $description = $appEvent->getDescription();

        if (null !== $description && '' !== trim($description)) {
            $lines[] = '';
            $lines[] = trim($description);
        }

        $lines[] = '';
        $lines[] = $url;

        return implode("\n", $lines);
    }
}
