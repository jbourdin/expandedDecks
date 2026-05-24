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
use App\Entity\Page;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F11.4 — CMS publication dates
 * @see docs/features.md F2.27 — Archetype publication dates
 */
final class PublishableTimestampsTest extends TestCase
{
    public function testDraftPagePersistDoesNotStampTimestamps(): void
    {
        $page = new Page();
        $page->setIsPublished(false);

        $page->setCreatedAtValue();

        self::assertNull($page->getFirstPublishedAt());
        self::assertNull($page->getLastPublishedAt());
    }

    public function testPublishedAtCreateStampsBothTimestamps(): void
    {
        $page = new Page();
        $page->setIsPublished(true);

        $page->setCreatedAtValue();

        self::assertNotNull($page->getFirstPublishedAt());
        self::assertNotNull($page->getLastPublishedAt());
        self::assertSame($page->getFirstPublishedAt(), $page->getLastPublishedAt());
    }

    public function testDraftToPublishedTransitionStampsFirstPublishedAt(): void
    {
        $page = new Page();
        $page->setIsPublished(true);

        $args = $this->createStub(PreUpdateEventArgs::class);
        $args->method('hasChangedField')->willReturn(true);
        $args->method('getNewValue')->willReturn(true);

        $page->setUpdatedAtValue($args);

        self::assertNotNull($page->getFirstPublishedAt());
        self::assertNotNull($page->getLastPublishedAt());
    }

    public function testRepublishedPageRefreshesLastButKeepsFirst(): void
    {
        $page = new Page();
        $reflection = new \ReflectionClass($page);
        $firstField = $reflection->getProperty('firstPublishedAt');
        $lastField = $reflection->getProperty('lastPublishedAt');
        $originalFirst = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $firstField->setValue($page, $originalFirst);
        $lastField->setValue($page, $originalFirst);
        $page->setIsPublished(true);

        $args = $this->createStub(PreUpdateEventArgs::class);
        $args->method('hasChangedField')->willReturn(false);

        $page->setUpdatedAtValue($args);

        self::assertSame($originalFirst, $page->getFirstPublishedAt());
        self::assertGreaterThan($originalFirst, $page->getLastPublishedAt());
    }

    public function testDraftSaveOnPublishedPageDoesNothing(): void
    {
        // Edge case: page transitions FROM published TO draft (unpublish).
        // No timestamp should change — the trait only acts when isPublished is true.
        $page = new Page();
        $reflection = new \ReflectionClass($page);
        $firstField = $reflection->getProperty('firstPublishedAt');
        $lastField = $reflection->getProperty('lastPublishedAt');
        $original = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $firstField->setValue($page, $original);
        $lastField->setValue($page, $original);
        $page->setIsPublished(false);

        $args = $this->createStub(PreUpdateEventArgs::class);
        $args->method('hasChangedField')->willReturn(true);
        $args->method('getNewValue')->willReturn(false);

        $page->setUpdatedAtValue($args);

        self::assertSame($original, $page->getFirstPublishedAt());
        self::assertSame($original, $page->getLastPublishedAt());
    }

    public function testArchetypeSharesTheSameTraitBehavior(): void
    {
        $archetype = (new Archetype())->setName('Iron Thorns');
        $archetype->setIsPublished(true);

        $archetype->onPrePersist();

        self::assertNotNull($archetype->getFirstPublishedAt());
        self::assertNotNull($archetype->getLastPublishedAt());
        self::assertSame('iron-thorns', $archetype->getSlug());
    }
}
