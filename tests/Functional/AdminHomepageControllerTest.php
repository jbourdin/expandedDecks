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

    public function testSavePersistsPerLocaleMeta(): void
    {
        $this->loginAs('admin@example.com');

        $payload = json_encode([
            'blocks' => [],
            'translations' => ['en' => [], 'fr' => []],
            'channelCode' => 'app',
            'meta' => [
                'en' => ['title' => 'Home — EN', 'ogDescription' => 'English social description.'],
                'fr' => ['title' => 'Accueil — FR', 'ogDescription' => 'Description sociale FR.'],
            ],
        ]);

        $this->client->request('POST', '/admin/homepage/save', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        self::assertResponseIsSuccessful();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($entityManager instanceof \Doctrine\ORM\EntityManagerInterface);
        $layoutRepository = $entityManager->getRepository(\App\Entity\HomepageLayout::class);
        $layouts = $layoutRepository->findAll();
        self::assertNotEmpty($layouts, 'Save should have created at least one HomepageLayout.');
        $layout = $layouts[\count($layouts) - 1];

        $englishTranslation = $layout->getTranslation('en');
        self::assertNotNull($englishTranslation);
        self::assertSame('Home — EN', $englishTranslation->getTitle());
        self::assertSame('English social description.', $englishTranslation->getOgDescription());

        $frenchTranslation = $layout->getTranslation('fr');
        self::assertNotNull($frenchTranslation);
        self::assertSame('Accueil — FR', $frenchTranslation->getTitle());
        self::assertSame('Description sociale FR.', $frenchTranslation->getOgDescription());
    }

    public function testSaveIgnoresLocalesNotEnabledOnChannel(): void
    {
        $this->loginAs('admin@example.com');

        // The 'content' channel only ships English in DevFixtures, so any FR
        // payload should be silently dropped rather than creating a phantom
        // french translation row.
        $payload = json_encode([
            'blocks' => [],
            'translations' => ['en' => [], 'fr' => []],
            'channelCode' => 'content',
            'meta' => [
                'en' => ['title' => 'Content home', 'ogDescription' => 'EN description'],
                'fr' => ['title' => 'NE DOIT PAS ÊTRE SAUVEGARDÉ', 'ogDescription' => 'Should be ignored'],
            ],
        ]);

        $this->client->request('POST', '/admin/homepage/save', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        self::assertResponseIsSuccessful();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($entityManager instanceof \Doctrine\ORM\EntityManagerInterface);
        $layoutRepository = $entityManager->getRepository(\App\Entity\HomepageLayout::class);
        $layouts = $layoutRepository->findAll();
        $layout = $layouts[\count($layouts) - 1];

        $englishTranslation = $layout->getTranslation('en');
        self::assertNotNull($englishTranslation);
        self::assertSame('Content home', $englishTranslation->getTitle());
        self::assertSame('EN description', $englishTranslation->getOgDescription());

        // No translation row should have been created for FR.
        $frenchExists = false;
        foreach ($layout->getTranslations() as $translation) {
            if ('fr' === $translation->getLocale()) {
                $frenchExists = true;
                break;
            }
        }
        self::assertFalse($frenchExists, 'Save must not persist a FR translation on a channel without French.');
    }

    public function testSaveTreatsBlankMetaFieldsAsNull(): void
    {
        $this->loginAs('admin@example.com');

        $payload = json_encode([
            'blocks' => [],
            'translations' => ['en' => [], 'fr' => []],
            'channelCode' => 'app',
            'meta' => [
                'en' => ['title' => '   ', 'ogDescription' => ''],
                'fr' => ['title' => '', 'ogDescription' => '   '],
            ],
        ]);

        $this->client->request('POST', '/admin/homepage/save', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        self::assertResponseIsSuccessful();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($entityManager instanceof \Doctrine\ORM\EntityManagerInterface);
        $layoutRepository = $entityManager->getRepository(\App\Entity\HomepageLayout::class);
        $layouts = $layoutRepository->findAll();
        $layout = $layouts[\count($layouts) - 1];

        self::assertNull($layout->getTranslation('en')?->getTitle());
        self::assertNull($layout->getTranslation('en')?->getOgDescription());
        self::assertNull($layout->getTranslation('fr')?->getTitle());
        self::assertNull($layout->getTranslation('fr')?->getOgDescription());
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
