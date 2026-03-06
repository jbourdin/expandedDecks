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

namespace App\Tests\Service\PokemonEventSync;

use App\Service\PokemonEventSync\PokemonEventSyncException;
use App\Service\PokemonEventSync\PokemonEventSyncService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @see docs/features.md F3.18 — Sync from Pokemon event page
 */
class PokemonEventSyncServiceTest extends TestCase
{
    private const string SAMPLE_JSON_LD = <<<'JSON'
        {
            "@context": "https://schema.org",
            "@type": "Event",
            "name": "Weekly Pokemon League",
            "startDate": "2026-04-15",
            "location": {
                "@type": "Place",
                "name": "Cool Game Store",
                "address": {
                    "@type": "PostalAddress",
                    "streetAddress": "123 Main St",
                    "addressLocality": "Springfield",
                    "addressRegion": "IL",
                    "postalCode": "62704",
                    "addressCountry": "US"
                }
            },
            "offers": {
                "@type": "Offer",
                "price": "5.00",
                "priceCurrency": "USD"
            }
        }
        JSON;

    public function testParsesJsonLdFields(): void
    {
        $service = $this->createService($this->buildHtml(self::SAMPLE_JSON_LD, 'TCG: Expanded'));
        $data = $service->fetchEventData('test-123');

        self::assertSame('Weekly Pokemon League', $data->name);
        self::assertSame('2026-04-15', $data->startDate);
        self::assertSame('Cool Game Store — 123 Main St, Springfield, IL, 62704, US', $data->location);
    }

    public function testParsesEntryFeeInCents(): void
    {
        $service = $this->createService($this->buildHtml(self::SAMPLE_JSON_LD));
        $data = $service->fetchEventData('test-123');

        self::assertSame(500, $data->entryFeeAmount);
        self::assertSame('USD', $data->entryFeeCurrency);
    }

    public function testMapsLeagueCupToSwissTopCut(): void
    {
        $jsonLd = str_replace('Weekly Pokemon League', 'League Cup — Spring', self::SAMPLE_JSON_LD);
        $service = $this->createService($this->buildHtml($jsonLd));
        $data = $service->fetchEventData('test-123');

        self::assertSame('swiss_top_cut', $data->tournamentStructure);
    }

    public function testMapsLeagueChallengeToSwissTopCut(): void
    {
        $jsonLd = str_replace('Weekly Pokemon League', 'League Challenge — Winter', self::SAMPLE_JSON_LD);
        $service = $this->createService($this->buildHtml($jsonLd));
        $data = $service->fetchEventData('test-123');

        self::assertSame('swiss_top_cut', $data->tournamentStructure);
    }

    public function testMapsLeagueToSwiss(): void
    {
        $service = $this->createService($this->buildHtml(self::SAMPLE_JSON_LD));
        $data = $service->fetchEventData('test-123');

        // "Weekly Pokemon League" contains "League" keyword
        self::assertSame('swiss', $data->tournamentStructure);
    }

    public function testMapsPrereleaseToSwiss(): void
    {
        $jsonLd = str_replace('Weekly Pokemon League', 'Prerelease Event', self::SAMPLE_JSON_LD);
        $service = $this->createService($this->buildHtml($jsonLd));
        $data = $service->fetchEventData('test-123');

        self::assertSame('swiss', $data->tournamentStructure);
    }

    public function testDecodesUnicodeEscapesInName(): void
    {
        $jsonLd = str_replace('Weekly Pokemon League', 'T2J \\u002D Coupe de Ligue Q1 2026', self::SAMPLE_JSON_LD);
        $service = $this->createService($this->buildHtml($jsonLd));
        $data = $service->fetchEventData('test-123');

        self::assertSame('T2J - Coupe de Ligue Q1 2026', $data->name);
    }

    public function testDecodesUnicodeEscapesInLocation(): void
    {
        $jsonLd = str_replace('Cool Game Store', 'TROLL2JEUX', self::SAMPLE_JSON_LD);
        $jsonLd = str_replace('123 Main St', '15 PL. D\\u0027ALIGRE', $jsonLd);
        $service = $this->createService($this->buildHtml($jsonLd));
        $data = $service->fetchEventData('test-123');

        self::assertStringContainsString("D'ALIGRE", (string) $data->location);
    }

    public function testParsesOrganizer(): void
    {
        $html = $this->buildHtml(self::SAMPLE_JSON_LD, 'TCG: Expanded', 'John Doe');
        $service = $this->createService($html);
        $data = $service->fetchEventData('test-123');

        self::assertSame('John Doe', $data->organizer);
    }

    public function testThrowsOnEmptyId(): void
    {
        $service = $this->createService('');

        $this->expectException(PokemonEventSyncException::class);
        $this->expectExceptionMessage('Tournament ID is required.');

        $service->fetchEventData('');
    }

    public function testThrowsOnInvalidId(): void
    {
        $service = $this->createService('');

        $this->expectException(PokemonEventSyncException::class);
        $this->expectExceptionMessage('Invalid tournament ID');

        $service->fetchEventData('invalid id with spaces!');
    }

    public function testThrowsOn404(): void
    {
        $service = $this->createService('', 404);

        $this->expectException(PokemonEventSyncException::class);
        $this->expectExceptionMessage('not found');

        $service->fetchEventData('nonexistent-event');
    }

    public function testThrowsOn500(): void
    {
        $service = $this->createService('', 500);

        $this->expectException(PokemonEventSyncException::class);
        $this->expectExceptionMessage('Failed to fetch');

        $service->fetchEventData('broken-event');
    }

    public function testThrowsWhenNoJsonLd(): void
    {
        $html = '<html><body><p>No structured data here</p></body></html>';
        $service = $this->createService($html);

        $this->expectException(PokemonEventSyncException::class);
        $this->expectExceptionMessage('No JSON-LD');

        $service->fetchEventData('no-jsonld-event');
    }

    public function testCachesResult(): void
    {
        $callCount = 0;
        $html = $this->buildHtml(self::SAMPLE_JSON_LD);
        $service = $this->createService($html, 200, $callCount);

        $service->fetchEventData('cached-event');
        $firstCallCount = $callCount;

        $service->fetchEventData('cached-event');

        self::assertSame($firstCallCount, $callCount, 'Second call should use cache');
    }

    public function testHandlesMissingOffers(): void
    {
        $jsonLd = <<<'JSON'
            {
                "@type": "Event",
                "name": "Free Event",
                "startDate": "2026-05-01"
            }
            JSON;

        $service = $this->createService($this->buildHtml($jsonLd));
        $data = $service->fetchEventData('free-event');

        self::assertNull($data->entryFeeAmount);
        self::assertNull($data->entryFeeCurrency);
    }

    public function testHandlesEmptyFields(): void
    {
        $jsonLd = '{"@type": "Event"}';
        $service = $this->createService($this->buildHtml($jsonLd));
        $data = $service->fetchEventData('empty-event');

        self::assertNull($data->name);
        self::assertNull($data->startDate);
        self::assertNull($data->location);
        self::assertNull($data->tournamentStructure);
    }

    public function testHandlesZeroPriceFee(): void
    {
        $jsonLd = <<<'JSON'
            {
                "@type": "Event",
                "name": "Free Entry Event",
                "offers": {
                    "price": "0.00",
                    "priceCurrency": "USD"
                }
            }
            JSON;

        $service = $this->createService($this->buildHtml($jsonLd));
        $data = $service->fetchEventData('zero-fee');

        self::assertNull($data->entryFeeAmount);
        self::assertSame('USD', $data->entryFeeCurrency);
    }

    public function testSetsRegistrationLinkToEventUrl(): void
    {
        $service = $this->createService($this->buildHtml(self::SAMPLE_JSON_LD));
        $data = $service->fetchEventData('test-123');

        self::assertSame(
            'https://www.pokemon.com/us/play-pokemon/pokemon-events/test-123/',
            $data->registrationLink,
        );
    }

    public function testToArrayReturnsAllFields(): void
    {
        $service = $this->createService($this->buildHtml(self::SAMPLE_JSON_LD, 'TCG: Expanded', 'Jane'));
        $data = $service->fetchEventData('test-123');

        $array = $data->toArray();

        self::assertArrayHasKey('name', $array);
        self::assertArrayHasKey('startDate', $array);
        self::assertArrayHasKey('location', $array);
        self::assertArrayHasKey('entryFeeAmount', $array);
        self::assertArrayHasKey('entryFeeCurrency', $array);
        self::assertArrayHasKey('tournamentStructure', $array);
        self::assertArrayHasKey('organizer', $array);
        self::assertArrayHasKey('registrationLink', $array);
    }

    private function buildHtml(string $jsonLd, string $formatLabel = '', string $organizer = ''): string
    {
        $formatHtml = '' !== $formatLabel ? '<span class="format">'.$formatLabel.'</span>' : '';
        $organizerHtml = '' !== $organizer ? '<div class="organizer">'.$organizer.'</div>' : '';

        return <<<HTML
            <html>
            <head>
                <script type="application/ld+json">{$jsonLd}</script>
            </head>
            <body>
                {$formatHtml}
                {$organizerHtml}
            </body>
            </html>
            HTML;
    }

    private function createService(string $htmlContent, int $statusCode = 200, ?int &$callCount = null): PokemonEventSyncService
    {
        $callCount = 0;
        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function () use ($htmlContent, $statusCode, &$callCount): ResponseInterface {
                ++$callCount;
                $response = $this->createStub(ResponseInterface::class);
                $response->method('getStatusCode')->willReturn($statusCode);
                $response->method('getContent')->willReturn($htmlContent);

                return $response;
            });

        return new PokemonEventSyncService($httpClient, new ArrayAdapter());
    }
}
