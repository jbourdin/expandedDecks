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
 * @see docs/features.md F10.5 — Homepage block editor (admin UI)
 */
class AdminHomepageControllerTest extends AbstractFunctionalTest
{
    public function testEditorRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin/homepage');

        self::assertResponseRedirects();
    }

    public function testEditorAccessibleByCmsEditor(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/homepage');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#homepage-editor-root');
    }

    public function testSaveRequiresAuthentication(): void
    {
        $this->client->request('POST', '/admin/homepage/save', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['blocks' => [], 'translations' => ['en' => [], 'fr' => []]]));

        self::assertResponseRedirects();
    }

    public function testSavePersistsLayout(): void
    {
        $this->loginAs('admin@example.com');

        $payload = json_encode([
            'blocks' => [
                ['type' => 'hero', 'columnWidth' => null, 'cssClasses' => null, 'startAt' => null, 'endAt' => null],
            ],
            'translations' => [
                'en' => [
                    '0' => ['title' => 'Test Hero', 'subtitle' => 'Test subtitle'],
                ],
                'fr' => [
                    '0' => ['title' => 'Héros test', 'subtitle' => 'Sous-titre test'],
                ],
            ],
            'channelCode' => 'app',
        ]);

        $this->client->request('POST', '/admin/homepage/save', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        self::assertResponseIsSuccessful();

        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertTrue($response['ok']);
    }

    public function testPreviewRequiresAuthentication(): void
    {
        $this->client->request('POST', '/admin/homepage/preview', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['blocks' => [], 'translations' => ['en' => [], 'fr' => []]]));

        self::assertResponseRedirects();
    }

    public function testPreviewRendersBlocks(): void
    {
        $this->loginAs('admin@example.com');

        $payload = json_encode([
            'blocks' => [
                ['type' => 'hero', 'columnWidth' => null, 'cssClasses' => null, 'startAt' => null, 'endAt' => null],
            ],
            'translations' => [
                'en' => [
                    '0' => ['title' => 'Preview Hero'],
                ],
                'fr' => [],
            ],
        ]);

        $this->client->request('POST', '/admin/homepage/preview', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hero-pokemon', 'Preview Hero');
    }
}
