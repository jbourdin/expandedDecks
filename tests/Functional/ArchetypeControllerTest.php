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

/**
 * @see docs/features.md F2.6 — Archetype management (create, browse, detail)
 */
class ArchetypeControllerTest extends AbstractFunctionalTest
{
    public function testSearchIsPubliclyAccessible(): void
    {
        $this->client->request('GET', '/api/archetype/search?q=iron');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertNotEmpty($data);
        self::assertSame('Iron Thorns ex', $data[0]['name']);
    }

    public function testSearchReturnsEmptyForShortQuery(): void
    {
        $this->client->request('GET', '/api/archetype/search?q=i');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame([], $data);
    }

    public function testSearchReturnsEmptyForNoMatch(): void
    {
        $this->client->request('GET', '/api/archetype/search?q=nonexistent');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame([], $data);
    }

    public function testCreateRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/archetype', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'New Archetype']));

        self::assertResponseRedirects('/login');
    }

    public function testCreateNewArchetype(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('POST', '/api/archetype', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Gardevoir ex']));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Gardevoir ex', $data['name']);
        self::assertSame('gardevoir-ex', $data['slug']);
        self::assertArrayHasKey('id', $data);
    }

    public function testCreateExistingArchetypeReturnsExisting(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('POST', '/api/archetype', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Iron Thorns ex']));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Iron Thorns ex', $data['name']);
    }

    public function testCreateWithEmptyNameReturnsBadRequest(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('POST', '/api/archetype', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => '']));

        self::assertResponseStatusCodeSame(400);
    }
}
