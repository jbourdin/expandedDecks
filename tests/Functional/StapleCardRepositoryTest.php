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

use App\Constants\StapleCardBucket;
use App\Entity\CardIdentity;
use App\Entity\StapleCard;
use App\Repository\StapleCardRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F6.15 — Staple cards
 */
class StapleCardRepositoryTest extends AbstractFunctionalTest
{
    private function getRepository(): StapleCardRepository
    {
        /** @var StapleCardRepository $repository */
        $repository = static::getContainer()->get(StapleCardRepository::class);

        return $repository;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function persistStaple(
        string $cardName,
        string $bucket,
        int $position = 0,
        int $hotness = 5,
        ?\DateTimeImmutable $deletedAt = null,
        ?CardIdentity $identity = null,
    ): StapleCard {
        $staple = new StapleCard();
        $staple->setCardName($cardName);
        $staple->setBucket($bucket);
        $staple->setPosition($position);
        $staple->setHotness($hotness);
        $staple->setDeletedAt($deletedAt);
        $staple->setCardIdentity($identity);

        $em = $this->getEntityManager();
        $em->persist($staple);
        $em->flush();

        return $staple;
    }

    private function persistIdentity(string $name, string $category = 'pokemon'): CardIdentity
    {
        $identity = new CardIdentity();
        $identity->setName($name);
        $identity->setCategory($category);

        $em = $this->getEntityManager();
        $em->persist($identity);
        $em->flush();

        return $identity;
    }

    public function testFindActiveByBucketReturnsOnlyMatchingActiveRows(): void
    {
        $this->persistStaple('Iono', StapleCardBucket::SUPPORTER, position: 1);
        $this->persistStaple('Boss', StapleCardBucket::SUPPORTER, position: 0);
        $this->persistStaple('Nest Ball', StapleCardBucket::ITEM, position: 0);
        $this->persistStaple('Deleted Supporter', StapleCardBucket::SUPPORTER, deletedAt: new \DateTimeImmutable());

        $rows = $this->getRepository()->findActiveByBucket(StapleCardBucket::SUPPORTER);

        self::assertCount(2, $rows);
        // Ordered by position ASC.
        self::assertSame('Boss', $rows[0]->getCardName());
        self::assertSame('Iono', $rows[1]->getCardName());
    }

    public function testFindActiveByBucketAppliesMinHotnessFilter(): void
    {
        $this->persistStaple('Hot', StapleCardBucket::ITEM, position: 0, hotness: 9);
        $this->persistStaple('Lukewarm', StapleCardBucket::ITEM, position: 1, hotness: 4);
        $this->persistStaple('Cold', StapleCardBucket::ITEM, position: 2, hotness: 1);

        $rows = $this->getRepository()->findActiveByBucket(StapleCardBucket::ITEM, minHotness: 5);

        self::assertCount(1, $rows);
        self::assertSame('Hot', $rows[0]->getCardName());
    }

    public function testFindActiveGroupedByBucketReturnsEmptyWhenNoBuckets(): void
    {
        $this->persistStaple('Iono', StapleCardBucket::SUPPORTER);

        self::assertSame([], $this->getRepository()->findActiveGroupedByBucket([]));
    }

    public function testFindActiveGroupedByBucketGroupsRowsExcludingSoftDeleted(): void
    {
        $this->persistStaple('Iono', StapleCardBucket::SUPPORTER, position: 0);
        $this->persistStaple('Boss', StapleCardBucket::SUPPORTER, position: 1);
        $this->persistStaple('Nest Ball', StapleCardBucket::ITEM, position: 0);
        $this->persistStaple('Old', StapleCardBucket::ITEM, deletedAt: new \DateTimeImmutable());

        $grouped = $this->getRepository()->findActiveGroupedByBucket([
            StapleCardBucket::SUPPORTER,
            StapleCardBucket::ITEM,
        ]);

        self::assertSame([StapleCardBucket::SUPPORTER, StapleCardBucket::ITEM], array_keys($grouped));
        self::assertCount(2, $grouped[StapleCardBucket::SUPPORTER]);
        self::assertSame('Iono', $grouped[StapleCardBucket::SUPPORTER][0]->getCardName());
        self::assertCount(1, $grouped[StapleCardBucket::ITEM]);
        self::assertSame('Nest Ball', $grouped[StapleCardBucket::ITEM][0]->getCardName());
    }

    public function testFindActiveGroupedByBucketAppliesMinHotnessFilter(): void
    {
        $this->persistStaple('Hot', StapleCardBucket::ITEM, position: 0, hotness: 9);
        $this->persistStaple('Cold', StapleCardBucket::ITEM, position: 1, hotness: 1);

        $grouped = $this->getRepository()->findActiveGroupedByBucket([StapleCardBucket::ITEM], minHotness: 5);

        self::assertCount(1, $grouped[StapleCardBucket::ITEM]);
        self::assertSame('Hot', $grouped[StapleCardBucket::ITEM][0]->getCardName());
    }

    public function testFindDeletedOrderedByDeletionDate(): void
    {
        $this->persistStaple('Active', StapleCardBucket::ITEM);
        $this->persistStaple('Old Delete', StapleCardBucket::ITEM, deletedAt: new \DateTimeImmutable('2024-01-01'));
        $this->persistStaple('New Delete', StapleCardBucket::ITEM, deletedAt: new \DateTimeImmutable('2025-06-01'));

        $rows = $this->getRepository()->findDeletedOrderedByDeletionDate();

        self::assertCount(2, $rows);
        self::assertSame('New Delete', $rows[0]->getCardName());
        self::assertSame('Old Delete', $rows[1]->getCardName());
    }

    public function testFindOneByCardIdentityReturnsLinkedStaple(): void
    {
        $identity = $this->persistIdentity('Iono');
        $staple = $this->persistStaple('Iono', StapleCardBucket::SUPPORTER, identity: $identity);

        $found = $this->getRepository()->findOneByCardIdentity($identity);

        self::assertNotNull($found);
        self::assertSame($staple->getId(), $found->getId());
    }

    public function testFindOneByCardIdentityReturnsNullWhenNoMatch(): void
    {
        $linked = $this->persistIdentity('Iono');
        $orphan = $this->persistIdentity('Boss');
        $this->persistStaple('Iono', StapleCardBucket::SUPPORTER, identity: $linked);

        self::assertNull($this->getRepository()->findOneByCardIdentity($orphan));
    }

    public function testFindMaxPositionInBucketReturnsMinusOneWhenEmpty(): void
    {
        self::assertSame(-1, $this->getRepository()->findMaxPositionInBucket(StapleCardBucket::POKEMON));
    }

    public function testFindMaxPositionInBucketReturnsHighestActivePosition(): void
    {
        $this->persistStaple('A', StapleCardBucket::ITEM, position: 3);
        $this->persistStaple('B', StapleCardBucket::ITEM, position: 7);
        // Soft-deleted rows are ignored — the editor's "append" must not collide with a stale slot.
        $this->persistStaple('C', StapleCardBucket::ITEM, position: 99, deletedAt: new \DateTimeImmutable());

        self::assertSame(7, $this->getRepository()->findMaxPositionInBucket(StapleCardBucket::ITEM));
    }

    public function testFindAllActiveExcludesSoftDeleted(): void
    {
        $this->persistStaple('Iono', StapleCardBucket::SUPPORTER);
        $this->persistStaple('Nest Ball', StapleCardBucket::ITEM);
        $this->persistStaple('Old', StapleCardBucket::ITEM, deletedAt: new \DateTimeImmutable());

        $rows = $this->getRepository()->findAllActive();

        self::assertCount(2, $rows);
        $names = array_map(static fn (StapleCard $row): string => $row->getCardName(), $rows);
        self::assertContains('Iono', $names);
        self::assertContains('Nest Ball', $names);
        self::assertNotContains('Old', $names);
    }
}
