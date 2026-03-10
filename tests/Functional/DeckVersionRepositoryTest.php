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

use App\Entity\Deck;
use App\Entity\DeckVersion;
use App\Repository\DeckVersionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.2 — Import deck list (PTCG text format)
 */
class DeckVersionRepositoryTest extends AbstractFunctionalTest
{
    private function getRepository(): DeckVersionRepository
    {
        /** @var DeckVersionRepository $repository */
        $repository = static::getContainer()->get(DeckVersionRepository::class);

        return $repository;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    // ---------------------------------------------------------------
    // findNotEnriched()
    // ---------------------------------------------------------------

    public function testFindNotEnrichedReturnsPendingVersions(): void
    {
        $repository = $this->getRepository();

        $results = $repository->findNotEnriched();

        // All fixture deck versions default to 'pending' enrichment status
        self::assertNotEmpty($results);
        foreach ($results as $version) {
            self::assertContains(
                $version->getEnrichmentStatus(),
                ['pending', 'failed'],
                'findNotEnriched should only return pending or failed versions.',
            );
        }
    }

    public function testFindNotEnrichedIncludesFailedVersions(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        // Find a version and set its status to 'failed'
        $allVersions = $repository->findAll();
        self::assertNotEmpty($allVersions);

        $firstVersion = $allVersions[0];
        $firstVersion->setEnrichmentStatus('failed');
        $entityManager->flush();

        $results = $repository->findNotEnriched();

        $failedVersionIds = array_map(
            static fn (DeckVersion $version): ?int => $version->getId(),
            array_filter($results, static fn (DeckVersion $version): bool => 'failed' === $version->getEnrichmentStatus()),
        );

        self::assertContains($firstVersion->getId(), $failedVersionIds, 'Failed version should be included in findNotEnriched results.');
    }

    public function testFindNotEnrichedExcludesDoneVersions(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        // Set all versions to 'done'
        $allVersions = $repository->findAll();
        foreach ($allVersions as $version) {
            $version->setEnrichmentStatus('done');
        }
        $entityManager->flush();

        $results = $repository->findNotEnriched();

        self::assertEmpty($results, 'findNotEnriched should return empty when all versions are done.');
    }

    public function testFindNotEnrichedExcludesEnrichingVersions(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        // Set all versions to 'enriching'
        $allVersions = $repository->findAll();
        foreach ($allVersions as $version) {
            $version->setEnrichmentStatus('enriching');
        }
        $entityManager->flush();

        $results = $repository->findNotEnriched();

        self::assertEmpty($results, 'findNotEnriched should not return enriching versions.');
    }

    public function testFindNotEnrichedReturnsMixedStatuses(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        $allVersions = $repository->findAll();
        self::assertGreaterThanOrEqual(3, \count($allVersions), 'Need at least 3 versions for this test.');

        // Set distinct statuses: pending, failed, done, enriching
        $allVersions[0]->setEnrichmentStatus('pending');
        $allVersions[1]->setEnrichmentStatus('failed');
        $allVersions[2]->setEnrichmentStatus('done');
        if (\count($allVersions) > 3) {
            $allVersions[3]->setEnrichmentStatus('enriching');
        }
        $entityManager->flush();

        $results = $repository->findNotEnriched();

        // Should include the pending and failed ones, not the done and enriching ones
        $resultIds = array_map(static fn (DeckVersion $version): ?int => $version->getId(), $results);
        self::assertContains($allVersions[0]->getId(), $resultIds, 'Pending version should be in results.');
        self::assertContains($allVersions[1]->getId(), $resultIds, 'Failed version should be in results.');
        self::assertNotContains($allVersions[2]->getId(), $resultIds, 'Done version should not be in results.');
    }

    // ---------------------------------------------------------------
    // findMaxVersionNumber()
    // ---------------------------------------------------------------

    public function testFindMaxVersionNumberReturnsCorrectMax(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        // Get a deck with at least one version (Iron Thorns has versions 1 and 2)
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($deck);

        $maxVersion = $repository->findMaxVersionNumber($deck);

        self::assertSame(2, $maxVersion, 'Iron Thorns should have max version number 2.');
    }

    public function testFindMaxVersionNumberReturnsZeroForDeckWithNoVersions(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        // Create a deck with no versions
        /** @var \App\Entity\User $admin */
        $admin = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@example.com']);

        $emptyDeck = new Deck();
        $emptyDeck->setName('Empty Deck For Test');
        $emptyDeck->setOwner($admin);
        $emptyDeck->setFormat('Expanded');
        $entityManager->persist($emptyDeck);
        $entityManager->flush();

        $maxVersion = $repository->findMaxVersionNumber($emptyDeck);

        self::assertSame(0, $maxVersion, 'Deck with no versions should return 0.');
    }

    public function testFindMaxVersionNumberWithMultipleVersions(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        /** @var \App\Entity\User $admin */
        $admin = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@example.com']);

        $deck = new Deck();
        $deck->setName('Multi-Version Deck For Test');
        $deck->setOwner($admin);
        $deck->setFormat('Expanded');
        $entityManager->persist($deck);

        $version1 = new DeckVersion();
        $version1->setDeck($deck);
        $version1->setVersionNumber(1);
        $version1->setRawList('Version 1 list');
        $entityManager->persist($version1);

        $version2 = new DeckVersion();
        $version2->setDeck($deck);
        $version2->setVersionNumber(2);
        $version2->setRawList('Version 2 list');
        $entityManager->persist($version2);

        $version3 = new DeckVersion();
        $version3->setDeck($deck);
        $version3->setVersionNumber(3);
        $version3->setRawList('Version 3 list');
        $entityManager->persist($version3);

        $entityManager->flush();

        $maxVersion = $repository->findMaxVersionNumber($deck);

        self::assertSame(3, $maxVersion, 'Max version number should be 3.');
    }
}
