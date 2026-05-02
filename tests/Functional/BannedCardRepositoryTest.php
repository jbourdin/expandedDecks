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
use App\Entity\BannedCardPrinting;
use App\Entity\CardIdentity;
use App\Repository\BannedCardRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F6.5 — Banned card list management
 * @see docs/features.md F6.14 — Banned cards public page
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

    private function persistBannedCard(string $name, ?CardIdentity $identity = null, ?\DateTimeImmutable $effectiveDate = null): BannedCard
    {
        $em = $this->getEntityManager();
        $card = new BannedCard();
        $card->setCardName($name);
        $card->setCardIdentity($identity);
        if (null !== $effectiveDate) {
            $card->setEffectiveDate($effectiveDate);
        }
        $em->persist($card);
        $em->flush();

        return $card;
    }

    private function persistPrinting(BannedCard $parent, string $setCode, string $cardNumber): BannedCardPrinting
    {
        $em = $this->getEntityManager();
        $printing = new BannedCardPrinting();
        $printing->setSetCode($setCode);
        $printing->setCardNumber($cardNumber);
        $parent->addPrinting($printing);
        $em->persist($printing);
        $em->flush();

        return $printing;
    }

    public function testFindBannedPrintingKeysReturnsActiveOnly(): void
    {
        $active = $this->persistBannedCard('Forest of Giant Plants');
        $this->persistPrinting($active, 'AOR', '74');

        $deleted = $this->persistBannedCard('Lysandre\'s Trump Card');
        $deleted->setDeletedAt(new \DateTimeImmutable());
        $this->persistPrinting($deleted, 'PHF', '99');
        $this->getEntityManager()->flush();

        $keys = $this->getRepository()->findBannedPrintingKeys();

        self::assertArrayHasKey('AOR|74', $keys);
        self::assertArrayNotHasKey('PHF|99', $keys);
    }

    public function testFindActiveOrderedByEffectiveDate(): void
    {
        $old = $this->persistBannedCard('Old Ban', null, new \DateTimeImmutable('2020-01-01'));
        $this->persistPrinting($old, 'PHF', '99');

        $new = $this->persistBannedCard('New Ban', null, new \DateTimeImmutable('2024-01-01'));
        $this->persistPrinting($new, 'AOR', '74');

        $deleted = $this->persistBannedCard('Deleted Ban');
        $deleted->setDeletedAt(new \DateTimeImmutable());
        $this->persistPrinting($deleted, 'BKP', '98');
        $this->getEntityManager()->flush();

        $rows = $this->getRepository()->findActiveOrderedByEffectiveDate();

        self::assertCount(2, $rows);
        self::assertSame('New Ban', $rows[0]->getCardName());
        self::assertSame('Old Ban', $rows[1]->getCardName());
    }

    public function testFindDeletedOrderedByDeletionDate(): void
    {
        $active = $this->persistBannedCard('Active');
        $this->persistPrinting($active, 'AOR', '74');

        $deleted = $this->persistBannedCard('Deleted');
        $deleted->setDeletedAt(new \DateTimeImmutable());
        $this->persistPrinting($deleted, 'PHF', '99');
        $this->getEntityManager()->flush();

        $rows = $this->getRepository()->findDeletedOrderedByDeletionDate();

        self::assertCount(1, $rows);
        self::assertSame('Deleted', $rows[0]->getCardName());
    }
}
