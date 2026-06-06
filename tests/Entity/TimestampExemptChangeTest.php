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

namespace App\Tests\Entity;

use App\Entity\Archetype;
use App\Entity\Deck;
use App\Entity\Page;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;

/**
 * A position-only change (drag-and-drop reorder) or a social-preview-only
 * change (`ogImage`/`ogDescription`) is not a content update, so it must not
 * bump any freshness or publication timestamp.
 *
 * @see docs/features.md F18.11 — Archetype relevance ordering
 * @see docs/features.md F18.19 — Archetype variant ordering
 * @see docs/features.md F18.32 — Card-fan OG image builder
 */
final class TimestampExemptChangeTest extends TestCase
{
    /**
     * @param array<string, array{mixed, mixed}> $changeSet
     */
    private function changeSetArgs(array $changeSet): PreUpdateEventArgs
    {
        $args = $this->createStub(PreUpdateEventArgs::class);
        $args->method('getEntityChangeSet')->willReturn($changeSet);
        $args->method('hasChangedField')->willReturn(false);

        return $args;
    }

    private function setTimestamp(object $entity, string $property, ?\DateTimeImmutable $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setValue($entity, $value);
    }

    public function testArchetypePositionOnlyChangeKeepsTimestamps(): void
    {
        $original = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $archetype = (new Archetype())->setName('Iron Thorns');
        $archetype->setIsPublished(true);
        $this->setTimestamp($archetype, 'updatedAt', $original);
        $this->setTimestamp($archetype, 'lastPublishedAt', $original);

        $archetype->onPreUpdate($this->changeSetArgs(['position' => [0, 5]]));

        self::assertSame($original, $archetype->getUpdatedAt());
        self::assertSame($original, $archetype->getLastPublishedAt());
    }

    public function testArchetypeContentChangeBumpsTimestamps(): void
    {
        $original = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $archetype = (new Archetype())->setName('Iron Thorns');
        $archetype->setIsPublished(true);
        $this->setTimestamp($archetype, 'updatedAt', $original);
        $this->setTimestamp($archetype, 'lastPublishedAt', $original);

        $archetype->onPreUpdate($this->changeSetArgs(['name' => ['Iron Thorns', 'Iron Thorns ex']]));

        self::assertGreaterThan($original, $archetype->getUpdatedAt());
        self::assertGreaterThan($original, $archetype->getLastPublishedAt());
    }

    public function testArchetypePositionAlongsideContentBumpsTimestamps(): void
    {
        $original = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $archetype = (new Archetype())->setName('Iron Thorns');
        $archetype->setIsPublished(true);
        $this->setTimestamp($archetype, 'updatedAt', $original);
        $this->setTimestamp($archetype, 'lastPublishedAt', $original);

        $archetype->onPreUpdate($this->changeSetArgs([
            'position' => [0, 5],
            'name' => ['Iron Thorns', 'Iron Thorns ex'],
        ]));

        self::assertGreaterThan($original, $archetype->getUpdatedAt());
        self::assertGreaterThan($original, $archetype->getLastPublishedAt());
    }

    public function testDeckPositionOnlyChangeKeepsUpdatedAt(): void
    {
        $original = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $deck = (new Deck())->setName('Variant A');
        $this->setTimestamp($deck, 'updatedAt', $original);

        $deck->onPreUpdate($this->changeSetArgs(['position' => [0, 1]]));

        self::assertSame($original, $deck->getUpdatedAt());
    }

    public function testDeckOgFieldsOnlyChangeKeepsUpdatedAt(): void
    {
        $original = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $deck = (new Deck())->setName('Variant A');
        $this->setTimestamp($deck, 'updatedAt', $original);

        $deck->onPreUpdate($this->changeSetArgs([
            'ogImage' => [null, '/api/editor/image/fan.png'],
            'ogDescription' => [null, 'A fan of key cards.'],
        ]));

        self::assertSame($original, $deck->getUpdatedAt());
    }

    public function testDeckContentChangeBumpsUpdatedAt(): void
    {
        $original = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $deck = (new Deck())->setName('Variant A');
        $this->setTimestamp($deck, 'updatedAt', $original);

        $deck->onPreUpdate($this->changeSetArgs(['name' => ['Variant A', 'Variant B']]));

        self::assertGreaterThan($original, $deck->getUpdatedAt());
    }

    public function testDeckOgFieldAlongsideContentBumpsUpdatedAt(): void
    {
        $original = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $deck = (new Deck())->setName('Variant A');
        $this->setTimestamp($deck, 'updatedAt', $original);

        $deck->onPreUpdate($this->changeSetArgs([
            'ogImage' => [null, '/api/editor/image/fan.png'],
            'name' => ['Variant A', 'Variant B'],
        ]));

        self::assertGreaterThan($original, $deck->getUpdatedAt());
    }

    public function testPageOgImageOnlyChangeKeepsTimestamps(): void
    {
        $original = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $page = new Page();
        $page->setIsPublished(true);
        $this->setTimestamp($page, 'updatedAt', $original);
        $this->setTimestamp($page, 'lastPublishedAt', $original);

        $page->setUpdatedAtValue($this->changeSetArgs(['ogImage' => [null, '/api/editor/image/fan.png']]));

        self::assertSame($original, $page->getUpdatedAt());
        self::assertSame($original, $page->getLastPublishedAt());
    }

    public function testPageContentChangeBumpsTimestamps(): void
    {
        $original = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $page = new Page();
        $page->setIsPublished(true);
        $this->setTimestamp($page, 'updatedAt', $original);
        $this->setTimestamp($page, 'lastPublishedAt', $original);

        $page->setUpdatedAtValue($this->changeSetArgs(['slug' => ['old-slug', 'new-slug']]));

        self::assertGreaterThan($original, $page->getUpdatedAt());
        self::assertGreaterThan($original, $page->getLastPublishedAt());
    }
}
