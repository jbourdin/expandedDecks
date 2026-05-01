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

use App\Entity\EventTag;
use App\Repository\EventTagRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F3.12 — Event tags
 */
class EventTagRepositoryTest extends AbstractFunctionalTest
{
    public function testResolveByNamesCreatesNewTagsForUnknownNames(): void
    {
        $repository = $this->repository();

        $tags = $repository->resolveByNames(['Regional', 'League Cup']);

        self::assertCount(2, $tags);
        self::assertSame('Regional', $tags[0]->getName());
        self::assertSame('regional', $tags[0]->getSlug());
        self::assertSame('League Cup', $tags[1]->getName());
        self::assertSame('league-cup', $tags[1]->getSlug());
        self::assertNull($tags[0]->getId(), 'New tags are returned un-flushed.');
    }

    public function testResolveByNamesReusesExistingTagsBySlug(): void
    {
        $em = $this->em();

        $existing = new EventTag();
        $existing->setName('Weekend');
        $em->persist($existing);
        $em->flush();

        $tags = $this->repository()->resolveByNames(['weekend', 'WEEKEND', 'New One']);

        self::assertCount(2, $tags, 'Duplicates collapsing to the same slug must be deduped.');
        self::assertSame($existing->getId(), $tags[0]->getId());
        self::assertSame('new-one', $tags[1]->getSlug());
    }

    public function testResolveByNamesIgnoresEmptyAndPunctuationOnlyEntries(): void
    {
        $tags = $this->repository()->resolveByNames(['', '   ', '???', 'Real']);

        self::assertCount(1, $tags);
        self::assertSame('Real', $tags[0]->getName());
    }

    public function testFindAllOrderedByNameReturnsSortedList(): void
    {
        $em = $this->em();

        foreach (['Zeta', 'Alpha', 'Mu'] as $name) {
            $tag = new EventTag();
            $tag->setName($name);
            $em->persist($tag);
        }
        $em->flush();

        $names = array_map(static fn (EventTag $tag): string => $tag->getName(), $this->repository()->findAllOrderedByName());

        self::assertSame(['Alpha', 'Mu', 'Zeta'], $names);
    }

    private function repository(): EventTagRepository
    {
        /** @var EventTagRepository $repository */
        $repository = static::getContainer()->get(EventTagRepository::class);

        return $repository;
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }
}
