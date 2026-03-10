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

use App\Entity\BannedCard;
use App\Repository\BannedCardRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F6.5 — Banned card list management
 */
class BannedCardRepositoryTest extends AbstractFunctionalTest
{
    private function getRepository(): BannedCardRepository
    {
        /** @var BannedCardRepository $repository */
        $repository = static::getContainer()->get(BannedCardRepository::class);

        return $repository;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function createBannedCard(string $cardName, string $setCode, string $cardNumber): BannedCard
    {
        $entityManager = $this->getEntityManager();

        $bannedCard = new BannedCard();
        $bannedCard->setCardName($cardName);
        $bannedCard->setSetCode($setCode);
        $bannedCard->setCardNumber($cardNumber);
        $bannedCard->setEffectiveDate(new \DateTimeImmutable());
        $entityManager->persist($bannedCard);
        $entityManager->flush();

        return $bannedCard;
    }

    // ---------------------------------------------------------------
    // findBannedCardKeys
    // ---------------------------------------------------------------

    public function testFindBannedCardKeysReturnsEmptyWhenNoBannedCards(): void
    {
        $repository = $this->getRepository();

        $keys = $repository->findBannedCardKeys();

        // No banned cards in fixtures by default
        self::assertSame([], $keys);
    }

    public function testFindBannedCardKeysReturnsCorrectFormat(): void
    {
        $repository = $this->getRepository();

        $this->createBannedCard('Lysandre\'s Trump Card', 'PHF', '99');
        $this->createBannedCard('Forest of Giant Plants', 'AOR', '74');

        $keys = $repository->findBannedCardKeys();

        self::assertArrayHasKey('PHF|99', $keys);
        self::assertArrayHasKey('AOR|74', $keys);
        self::assertTrue($keys['PHF|99']);
        self::assertTrue($keys['AOR|74']);
    }

    public function testFindBannedCardKeysContainsAllBannedCards(): void
    {
        $repository = $this->getRepository();

        $this->createBannedCard('Delinquent', 'BKP', '98');
        $this->createBannedCard('Lt. Surge\'s Strategy', 'UNB', '178');
        $this->createBannedCard('Mismagius', 'UNB', '78');

        $keys = $repository->findBannedCardKeys();

        self::assertCount(3, $keys);
    }

    // ---------------------------------------------------------------
    // findOneBySetCodeAndNumber
    // ---------------------------------------------------------------

    public function testFindOneBySetCodeAndNumberReturnsMatchingCard(): void
    {
        $repository = $this->getRepository();

        $created = $this->createBannedCard('Chip-Chip Ice Axe', 'UNB', '165');

        $found = $repository->findOneBySetCodeAndNumber('UNB', '165');

        self::assertNotNull($found);
        self::assertSame($created->getId(), $found->getId());
        self::assertSame('Chip-Chip Ice Axe', $found->getCardName());
        self::assertSame('UNB', $found->getSetCode());
        self::assertSame('165', $found->getCardNumber());
    }

    public function testFindOneBySetCodeAndNumberReturnsNullWhenNotFound(): void
    {
        $repository = $this->getRepository();

        $found = $repository->findOneBySetCodeAndNumber('XXX', '999');

        self::assertNull($found);
    }

    public function testFindOneBySetCodeAndNumberMatchesExactly(): void
    {
        $repository = $this->getRepository();

        $this->createBannedCard('Island Challenge Amulet', 'CEC', '194');

        // Same set, different number
        self::assertNull($repository->findOneBySetCodeAndNumber('CEC', '195'));

        // Same number, different set
        self::assertNull($repository->findOneBySetCodeAndNumber('PHF', '194'));

        // Exact match
        self::assertNotNull($repository->findOneBySetCodeAndNumber('CEC', '194'));
    }
}
