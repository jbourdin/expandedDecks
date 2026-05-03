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
use App\Repository\BannedCardPrintingRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardPrintingRepositoryTest extends AbstractFunctionalTest
{
    public function testFindOneBySetCodeAndCardNumberReturnsMatchingRow(): void
    {
        $this->persistBannedPrinting('LOT', '90');
        $this->persistBannedPrinting('LOT', '91');

        $found = $this->getRepository()->findOneBySetCodeAndCardNumber('LOT', '90');

        self::assertInstanceOf(BannedCardPrinting::class, $found);
        self::assertSame('90', $found->getCardNumber());
    }

    public function testFindOneBySetCodeAndCardNumberReturnsNullWhenMissing(): void
    {
        $this->persistBannedPrinting('LOT', '90');

        self::assertNull($this->getRepository()->findOneBySetCodeAndCardNumber('UNKNOWN', '0'));
    }

    public function testFindAllOrderedBySetAndNumberSortsLexicographically(): void
    {
        $this->persistBannedPrinting('LOT', '91');
        $this->persistBannedPrinting('AOR', '74');
        $this->persistBannedPrinting('LOT', '90');

        $all = $this->getRepository()->findAllOrderedBySetAndNumber();

        self::assertCount(3, $all);
        self::assertSame('AOR', $all[0]->getSetCode());
        self::assertSame('LOT', $all[1]->getSetCode());
        self::assertSame('90', $all[1]->getCardNumber());
        self::assertSame('LOT', $all[2]->getSetCode());
        self::assertSame('91', $all[2]->getCardNumber());
    }

    public function testFindAllOrderedBySetAndNumberReturnsEmptyWhenNoRows(): void
    {
        self::assertSame([], $this->getRepository()->findAllOrderedBySetAndNumber());
    }

    private function getRepository(): BannedCardPrintingRepository
    {
        /** @var BannedCardPrintingRepository $repository */
        $repository = static::getContainer()->get(BannedCardPrintingRepository::class);

        return $repository;
    }

    private function persistBannedPrinting(string $setCode, string $cardNumber): BannedCardPrinting
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $parent = new BannedCard();
        $parent->setCardName(\sprintf('%s %s', $setCode, $cardNumber));

        $printing = new BannedCardPrinting();
        $printing->setSetCode($setCode);
        $printing->setCardNumber($cardNumber);
        $parent->addPrinting($printing);

        $em->persist($parent);
        $em->persist($printing);
        $em->flush();

        return $printing;
    }
}
