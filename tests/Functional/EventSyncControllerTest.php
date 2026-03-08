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

namespace App\Tests\Functional;

use App\Service\PokemonEventSync\PokemonEventData;
use App\Service\PokemonEventSync\PokemonEventSyncException;
use App\Service\PokemonEventSync\PokemonEventSyncService;

/**
 * @see docs/features.md F3.18 — Sync from Pokemon event page
 */
class EventSyncControllerTest extends AbstractFunctionalTest
{
    public function testSyncRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/event/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['tournamentId' => 'test-123']));

        self::assertResponseRedirects('/login');
    }

    public function testSyncRequiresOrganizerRole(): void
    {
        // Borrower has ROLE_USER only
        $this->loginAs('borrower@example.com');

        $this->client->request('POST', '/api/event/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['tournamentId' => 'test-123']));

        self::assertResponseStatusCodeSame(403);
    }

    public function testSyncReturnsDataOnSuccess(): void
    {
        $this->loginAs('organizer@example.com');
        $this->client->disableReboot();

        $mockService = $this->createStub(PokemonEventSyncService::class);
        $mockService->method('fetchEventData')->willReturn(new PokemonEventData(
            name: 'Weekly Pokemon League',
            startDate: '2026-04-15',
            location: 'Cool Game Store — 123 Main St, Springfield, IL',
            entryFeeAmount: 500,
            entryFeeCurrency: 'USD',
            tournamentStructure: 'swiss',
            organizer: 'John Doe',
        ));

        static::getContainer()->set(PokemonEventSyncService::class, $mockService);

        $this->client->request('POST', '/api/event/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['tournamentId' => 'test-123']));

        self::assertResponseIsSuccessful();

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertTrue($data['success']);
        self::assertSame('Weekly Pokemon League', $data['data']['name']);
        self::assertSame('2026-04-15', $data['data']['startDate']);
        self::assertSame(500, $data['data']['entryFeeAmount']);
        self::assertSame('swiss', $data['data']['tournamentStructure']);
    }

    public function testSyncReturnsMissingIdError(): void
    {
        $this->loginAs('organizer@example.com');
        $this->client->disableReboot();

        $mockService = $this->createStub(PokemonEventSyncService::class);
        $mockService->method('fetchEventData')
            ->willThrowException(PokemonEventSyncException::emptyId());

        static::getContainer()->set(PokemonEventSyncService::class, $mockService);

        $this->client->request('POST', '/api/event/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['tournamentId' => '']));

        self::assertResponseStatusCodeSame(400);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertSame('missing_id', $data['code']);
    }

    public function testSyncReturnsNotFoundError(): void
    {
        $this->loginAs('organizer@example.com');
        $this->client->disableReboot();

        $mockService = $this->createStub(PokemonEventSyncService::class);
        $mockService->method('fetchEventData')
            ->willThrowException(PokemonEventSyncException::notFound('nonexistent'));

        static::getContainer()->set(PokemonEventSyncService::class, $mockService);

        $this->client->request('POST', '/api/event/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['tournamentId' => 'nonexistent']));

        self::assertResponseStatusCodeSame(404);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertSame('not_found', $data['code']);
    }

    public function testSyncReturnsFetchFailedError(): void
    {
        $this->loginAs('organizer@example.com');
        $this->client->disableReboot();

        $mockService = $this->createStub(PokemonEventSyncService::class);
        $mockService->method('fetchEventData')
            ->willThrowException(PokemonEventSyncException::fetchFailed('broken'));

        static::getContainer()->set(PokemonEventSyncService::class, $mockService);

        $this->client->request('POST', '/api/event/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['tournamentId' => 'broken']));

        self::assertResponseStatusCodeSame(502);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertSame('fetch_failed', $data['code']);
    }

    public function testSyncHandlesMissingTournamentIdInPayload(): void
    {
        $this->loginAs('organizer@example.com');
        $this->client->disableReboot();

        $mockService = $this->createStub(PokemonEventSyncService::class);
        $mockService->method('fetchEventData')
            ->willThrowException(PokemonEventSyncException::emptyId());

        static::getContainer()->set(PokemonEventSyncService::class, $mockService);

        $this->client->request('POST', '/api/event/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        self::assertResponseStatusCodeSame(400);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertSame('missing_id', $data['code']);
    }
}
