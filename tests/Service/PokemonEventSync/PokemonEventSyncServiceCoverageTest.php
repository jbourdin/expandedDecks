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
 * Additional coverage for PokemonEventSyncService error paths and edge cases.
 *
 * @see docs/features.md F3.18 — Sync from Pokemon event page
 */
class PokemonEventSyncServiceCoverageTest extends TestCase
{
    public function testFetchHtmlThrowsOnHttpClientException(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $service = new PokemonEventSyncService($httpClient, new ArrayAdapter());

        $this->expectException(PokemonEventSyncException::class);
        $this->expectExceptionMessage('Failed to fetch');

        $service->fetchEventData('network-error');
    }

    public function testFetchHtmlThrowsOnGetContentException(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willThrowException(new \RuntimeException('Timeout'));

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $service = new PokemonEventSyncService($httpClient, new ArrayAdapter());

        $this->expectException(PokemonEventSyncException::class);
        $this->expectExceptionMessage('Failed to fetch');

        $service->fetchEventData('timeout-event');
    }

    public function testThrowsOnInvalidJsonLd(): void
    {
        $html = '<html><head><script type="application/ld+json">{invalid json!!!</script></head><body></body></html>';
        $service = $this->createServiceWithHtml($html);

        $this->expectException(PokemonEventSyncException::class);
        $this->expectExceptionMessage('No JSON-LD');

        $service->fetchEventData('bad-json');
    }

    public function testDefaultsToSwissWhenNoKeywordMatches(): void
    {
        $jsonLd = '{"@type": "Event", "name": "Regional Championship"}';
        $html = $this->buildHtml($jsonLd);
        $service = $this->createServiceWithHtml($html);

        $data = $service->fetchEventData('regional-event');

        self::assertSame('swiss', $data->tournamentStructure);
    }

    public function testOrganizerReturnsNullWhenNoOrganizerElement(): void
    {
        $jsonLd = '{"@type": "Event", "name": "No Org Event", "startDate": "2026-05-01"}';
        $html = '<html><head><script type="application/ld+json">'.$jsonLd.'</script></head><body></body></html>';
        $service = $this->createServiceWithHtml($html);

        $data = $service->fetchEventData('no-organizer');

        self::assertNull($data->organizer);
    }

    public function testOrganizerReturnsNullWhenOrganizerElementIsEmpty(): void
    {
        $jsonLd = '{"@type": "Event", "name": "Empty Org Event", "startDate": "2026-05-01"}';
        $html = '<html><head><script type="application/ld+json">'.$jsonLd.'</script></head><body><div class="organizer">   </div></body></html>';
        $service = $this->createServiceWithHtml($html);

        $data = $service->fetchEventData('empty-organizer');

        self::assertNull($data->organizer);
    }

    public function testExtractStartDateReturnsRawValueWhenNotIsoFormat(): void
    {
        $jsonLd = '{"@type": "Event", "name": "Odd Date Event", "startDate": "April 15, 2026"}';
        $html = $this->buildHtml($jsonLd);
        $service = $this->createServiceWithHtml($html);

        $data = $service->fetchEventData('odd-date');

        self::assertSame('April 15, 2026', $data->startDate);
    }

    public function testExtractStartDateStripsTimeComponent(): void
    {
        $jsonLd = '{"@type": "Event", "name": "Full Datetime Event", "startDate": "2026-04-15T10:00:00-05:00"}';
        $html = $this->buildHtml($jsonLd);
        $service = $this->createServiceWithHtml($html);

        $data = $service->fetchEventData('datetime-event');

        self::assertSame('2026-04-15', $data->startDate);
    }

    public function testExtractEntryFeeReturnsNullCurrencyWhenMissing(): void
    {
        $jsonLd = '{"@type": "Event", "name": "No Currency Event", "offers": {"price": "10.00"}}';
        $html = $this->buildHtml($jsonLd);
        $service = $this->createServiceWithHtml($html);

        $data = $service->fetchEventData('no-currency');

        self::assertSame(1000, $data->entryFeeAmount);
        self::assertNull($data->entryFeeCurrency);
    }

    public function testExtractEntryFeeReturnsNullAmountWithCurrencyWhenPriceNotNumeric(): void
    {
        $jsonLd = '{"@type": "Event", "name": "Bad Price Event", "offers": {"price": "free", "priceCurrency": "EUR"}}';
        $html = $this->buildHtml($jsonLd);
        $service = $this->createServiceWithHtml($html);

        $data = $service->fetchEventData('free-price');

        self::assertNull($data->entryFeeAmount);
        self::assertSame('EUR', $data->entryFeeCurrency);
    }

    public function testLocationReturnsNullWhenLocationIsNotArray(): void
    {
        $jsonLd = '{"@type": "Event", "name": "String Location Event", "location": "Online"}';
        $html = $this->buildHtml($jsonLd);
        $service = $this->createServiceWithHtml($html);

        $data = $service->fetchEventData('string-location');

        self::assertNull($data->location);
    }

    public function testLocationReturnsNullWhenNameAndAddressAreEmpty(): void
    {
        $jsonLd = '{"@type": "Event", "name": "Empty Location Event", "location": {"@type": "Place"}}';
        $html = $this->buildHtml($jsonLd);
        $service = $this->createServiceWithHtml($html);

        $data = $service->fetchEventData('empty-location');

        self::assertNull($data->location);
    }

    public function testLocationWithOnlyAddressNoName(): void
    {
        $jsonLd = <<<'JSON'
            {
                "@type": "Event",
                "name": "Address Only Event",
                "location": {
                    "@type": "Place",
                    "address": {
                        "@type": "PostalAddress",
                        "addressLocality": "Paris",
                        "addressCountry": "FR"
                    }
                }
            }
            JSON;
        $html = $this->buildHtml($jsonLd);
        $service = $this->createServiceWithHtml($html);

        $data = $service->fetchEventData('address-only');

        self::assertSame('Paris, FR', $data->location);
    }

    private function buildHtml(string $jsonLd): string
    {
        return '<html><head><script type="application/ld+json">'.$jsonLd.'</script></head><body></body></html>';
    }

    private function createServiceWithHtml(string $html, int $statusCode = 200): PokemonEventSyncService
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getContent')->willReturn($html);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        return new PokemonEventSyncService($httpClient, new ArrayAdapter());
    }
}
