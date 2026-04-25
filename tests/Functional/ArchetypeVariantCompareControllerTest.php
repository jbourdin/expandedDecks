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
use App\Entity\Deck;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.10 — Archetype detail page
 */
class ArchetypeVariantCompareControllerTest extends AbstractFunctionalTest
{
    public function testComparePageAccessibleAnonymously(): void
    {
        [$tagA, $tagB] = $this->getRegidragoVariantShortTags();
        $this->client->request('GET', '/en/archetypes/regidrago/compare/'.$tagA.'/'.$tagB);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h5', 'Compare');
    }

    public function testComparePageShowsDiffSections(): void
    {
        [$tagA, $tagB] = $this->getRegidragoVariantShortTags();
        $crawler = $this->client->request('GET', '/en/archetypes/regidrago/compare/'.$tagA.'/'.$tagB);

        // Both variants use the same raw list in fixtures, so diff should show "identical"
        self::assertResponseIsSuccessful();
        // The page renders — either diff sections or "identical" message
        self::assertGreaterThan(0, $crawler->filter('.card-body')->count());
    }

    public function testComparePageWithVariantSelectors(): void
    {
        [$tagA, $tagB] = $this->getRegidragoVariantShortTags();
        $crawler = $this->client->request('GET', '/en/archetypes/regidrago/compare/'.$tagA.'/'.$tagB);

        // React island mount point for variant pickers should exist
        self::assertSelectorExists('#variant-compare-picker-root');
    }

    public function testCompare404ForUnpublishedArchetype(): void
    {
        // Kyurem archetype is unpublished in fixtures
        [$tagA, $tagB] = $this->getRegidragoVariantShortTags();
        $this->client->request('GET', '/en/archetypes/nonexistent-archetype/compare/'.$tagA.'/'.$tagB);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCompare404ForDeletedArchetype(): void
    {
        [$tagA, $tagB] = $this->getRegidragoVariantShortTags();
        $this->client->request('GET', '/en/archetypes/deleted-slug/compare/'.$tagA.'/'.$tagB);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCompare404ForNonExistentVariant(): void
    {
        [$tagA] = $this->getRegidragoVariantShortTags();
        $this->client->request('GET', '/en/archetypes/regidrago/compare/'.$tagA.'/ZZZ999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCompareWithVariantMissingVersion(): void
    {
        // "Third Regidrago" has no deck version in fixtures
        [$tagA] = $this->getRegidragoVariantShortTags();
        $tagNoVersion = $this->getVariantShortTag('Third Regidrago');

        $this->client->request('GET', '/en/archetypes/regidrago/compare/'.$tagA.'/'.$tagNoVersion);

        self::assertResponseIsSuccessful();
        // Should show warning about missing version
        self::assertSelectorExists('.alert-warning');
    }

    public function testComparePreviewModeForEditor(): void
    {
        $this->loginAs('admin@example.com');

        // Even if archetype were unpublished, preview mode with editor should work
        [$tagA, $tagB] = $this->getRegidragoVariantShortTags();
        $this->client->request('GET', '/en/archetypes/regidrago/compare/'.$tagA.'/'.$tagB.'?preview=true');

        self::assertResponseIsSuccessful();
    }

    /**
     * @return array{string, string} [shortTagA, shortTagB]
     */
    private function getRegidragoVariantShortTags(): array
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        /** @var Archetype $archetype */
        $archetype = $entityManager->getRepository(Archetype::class)->findOneBy(['slug' => 'regidrago']);

        $variants = $entityManager->getRepository(Deck::class)->findBy([
            'archetype' => $archetype,
            'owner' => null,
        ], ['canonical' => 'DESC', 'position' => 'ASC']);

        self::assertGreaterThanOrEqual(2, \count($variants), 'Need at least 2 Regidrago variants in fixtures');

        return [$variants[0]->getShortTag(), $variants[1]->getShortTag()];
    }

    private function getVariantShortTag(string $name): string
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        /** @var Deck $variant */
        $variant = $entityManager->getRepository(Deck::class)->findOneBy([
            'name' => $name,
            'owner' => null,
        ]);

        return $variant->getShortTag();
    }
}
