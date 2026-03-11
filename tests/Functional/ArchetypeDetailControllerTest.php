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

use App\Entity\Archetype;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.10 — Archetype detail page
 */
class ArchetypeDetailControllerTest extends AbstractFunctionalTest
{
    public function testPublishedArchetypeReturns200(): void
    {
        $this->client->request('GET', '/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Regidrago');
    }

    public function testDisplaysSpritesInHeader(): void
    {
        $this->client->request('GET', '/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1 .archetype-sprites');
        self::assertSelectorExists('h1 img.archetype-sprite[title="Regidrago"]');
    }

    public function testRendersMarkdownDescription(): void
    {
        $this->client->request('GET', '/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.cms-content');
        self::assertSelectorExists('.cms-content strong');
    }

    public function testExpandsArchetypeTags(): void
    {
        $this->client->request('GET', '/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        // Kyurem archetype tag should be rendered as a link
        self::assertSelectorExists('.cms-content a[href="/archetypes/kyurem"]');
    }

    public function testExpandsCardTags(): void
    {
        $crawler = $this->client->request('GET', '/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        $content = $crawler->filter('.cms-content')->html();
        // Some card tags resolve via TCGdex API, others may remain as raw text
        // if the set code is unknown. At minimum, the resolved ones should render.
        self::assertStringContainsString('card-hover', $content);
    }

    public function testDisplaysBrowseDecksCta(): void
    {
        $this->client->request('GET', '/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.btn-gold[href*="archetype=regidrago"]');
    }

    public function testUnpublishedArchetypeReturns404ForAnonymous(): void
    {
        $this->client->request('GET', '/archetypes/lugia-archeops');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnpublishedArchetypeReturns404ForRegularUser(): void
    {
        $this->loginAs('borrower@example.com');
        $this->client->request('GET', '/archetypes/lugia-archeops');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnpublishedArchetypeAccessibleByAdmin(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/archetypes/lugia-archeops');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-warning');
    }

    public function testNonExistentSlugReturns404(): void
    {
        $this->client->request('GET', '/archetypes/nonexistent-archetype');

        self::assertResponseStatusCodeSame(404);
    }

    public function testMetaDescriptionRendered(): void
    {
        // Set a meta description for testing
        $entityManager = $this->getEntityManager();
        $archetype = $entityManager->getRepository(Archetype::class)->findOneBy(['slug' => 'regidrago']);
        self::assertNotNull($archetype);
        $archetype->setMetaDescription('The best deck in Expanded format.');
        $entityManager->flush();

        $crawler = $this->client->request('GET', '/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        $metaTag = $crawler->filter('meta[name="description"]');
        self::assertSame('The best deck in Expanded format.', $metaTag->attr('content'));
    }

    public function testMultipleSpriteArchetype(): void
    {
        $this->client->request('GET', '/archetypes/ancient-box');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('img.archetype-sprite[title="Roaring Moon"]');
        self::assertSelectorExists('img.archetype-sprite[title="Flutter Mane"]');
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }
}
