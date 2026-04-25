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
        $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Regidrago');
    }

    public function testDisplaysSpritesInHeader(): void
    {
        $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1 .archetype-sprites');
        self::assertSelectorExists('h1 img.archetype-sprite[title="Regidrago"]');
    }

    public function testRendersMarkdownDescription(): void
    {
        $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.cms-content');
        self::assertSelectorExists('.cms-content strong');
    }

    public function testExpandsArchetypeTags(): void
    {
        $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        // Kyurem archetype tag should be rendered as a link
        self::assertSelectorExists('.cms-content a[href="/en/archetypes/kyurem"]');
    }

    public function testExpandsCardTags(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        $content = $crawler->filter('.cms-content')->html();
        // Some card tags resolve via TCGdex API, others may remain as raw text
        // if the set code is unknown. At minimum, the resolved ones should render.
        self::assertStringContainsString('card-hover', $content);
    }

    public function testDisplaysLatestDecks(): void
    {
        $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.list-group-item');
        self::assertSelectorExists('.badge-short-id');
    }

    public function testUnpublishedArchetypeReturns404ForAnonymous(): void
    {
        $this->client->request('GET', '/en/archetypes/lugia-archeops');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnpublishedArchetypeReturns404ForRegularUser(): void
    {
        $this->loginAs('borrower@example.com');
        $this->client->request('GET', '/en/archetypes/lugia-archeops');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnpublishedArchetypeAccessibleByAdminWithPreview(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/en/archetypes/lugia-archeops?preview=true');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-warning');
    }

    public function testUnpublishedArchetypeReturns404ForAdminWithoutPreview(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/en/archetypes/lugia-archeops');

        self::assertResponseStatusCodeSame(404);
    }

    public function testNonExistentSlugReturns404(): void
    {
        $this->client->request('GET', '/en/archetypes/nonexistent-archetype');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeletedArchetypeReturns404(): void
    {
        $entityManager = $this->getEntityManager();
        $archetype = $entityManager->getRepository(Archetype::class)->findOneBy(['slug' => 'regidrago']);
        self::assertNotNull($archetype);

        $archetype->setDeletedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeletedArchetypeReturns404ForAdmin(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        $archetype = $entityManager->getRepository(Archetype::class)->findOneBy(['slug' => 'regidrago']);
        self::assertNotNull($archetype);

        $archetype->setDeletedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseStatusCodeSame(404);
    }

    public function testMetaDescriptionRendered(): void
    {
        // Set a meta description on the EN translation for testing
        $entityManager = $this->getEntityManager();
        $archetype = $entityManager->getRepository(Archetype::class)->findOneBy(['slug' => 'regidrago']);
        self::assertNotNull($archetype);
        $translation = $archetype->getTranslation('en');
        self::assertNotNull($translation);
        $translation->setMetaDescription('The best deck in Expanded format.');
        $entityManager->flush();

        $crawler = $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        $metaTag = $crawler->filter('meta[name="description"]');
        self::assertSame('The best deck in Expanded format.', $metaTag->attr('content'));
    }

    public function testMultipleSpriteArchetype(): void
    {
        $this->client->request('GET', '/en/archetypes/ancient-box');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('img.archetype-sprite[title="Roaring Moon"]');
        self::assertSelectorExists('img.archetype-sprite[title="Flutter Mane"]');
    }

    // ---------------------------------------------------------------
    // Archetype variant selector (F18.16)
    // ---------------------------------------------------------------

    /**
     * @see docs/features.md F18.16 — Archetype detail: variant selector
     */
    public function testArchetypeWithVariantsShowsVariantSelector(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#archetype-variant-selector-root');

        $selectorRoot = $crawler->filter('#archetype-variant-selector-root');
        $variantsJson = $selectorRoot->attr('data-variants');
        self::assertNotNull($variantsJson);

        /** @var list<array{id: int, name: string, canonical: bool}> $variants */
        $variants = json_decode($variantsJson, true);
        self::assertIsArray($variants);
        self::assertGreaterThanOrEqual(2, \count($variants));

        // Canonical variant should be first
        self::assertTrue($variants[0]['canonical']);
    }

    /**
     * @see docs/features.md F18.16 — Archetype detail: variant selector
     */
    public function testVariantDataContainsExpectedFields(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseIsSuccessful();

        $selectorRoot = $crawler->filter('#archetype-variant-selector-root');

        /** @var list<array{id: int, name: string, canonical: bool, sprites: list<string>, groupedCards: array<string, mixed>}> $variants */
        $variants = json_decode((string) $selectorRoot->attr('data-variants'), true);

        $canonical = $variants[0];
        self::assertArrayHasKey('id', $canonical);
        self::assertArrayHasKey('name', $canonical);
        self::assertArrayHasKey('canonical', $canonical);
        self::assertArrayHasKey('sprites', $canonical);
        self::assertArrayHasKey('groupedCards', $canonical);
        self::assertArrayHasKey('mosaicUrl', $canonical);
        self::assertArrayHasKey('description', $canonical);
    }

    /**
     * @see docs/features.md F18.16 — Archetype detail: variant selector
     */
    public function testVariantDataContainsDescription(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes/regidrago');

        self::assertResponseIsSuccessful();

        $selectorRoot = $crawler->filter('#archetype-variant-selector-root');

        /** @var list<array{description: string|null}> $variants */
        $variants = json_decode((string) $selectorRoot->attr('data-variants'), true);

        // Canonical variant has a markdown description rendered to HTML
        self::assertNotNull($variants[0]['description']);
        self::assertStringContainsString('Strategy', $variants[0]['description']);
    }

    /**
     * @see docs/features.md F18.16 — Archetype detail: variant selector
     */
    public function testArchetypeWithoutVariantsHasNoSelector(): void
    {
        $this->client->request('GET', '/en/archetypes/iron-thorns-ex');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#archetype-variant-selector-root');
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }
}
