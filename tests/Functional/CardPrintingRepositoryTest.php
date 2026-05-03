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

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Repository\CardPrintingRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Covers findFirstBySetCodeAndCardNumber, used by BannedCardEnricher's
 * local-first lookup chain.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
class CardPrintingRepositoryTest extends AbstractFunctionalTest
{
    public function testFindFirstBySetCodeAndCardNumberReturnsLowestRarityExpandedLegal(): void
    {
        $identity = $this->persistIdentity('Pikachu');

        // Three printings sharing (LOT, 90):
        //   - high-rarity Expanded-legal
        //   - low-rarity Expanded-legal (this is what we expect)
        //   - low-rarity NOT Expanded-legal (excluded by ORDER BY isExpandedLegal DESC)
        $this->persistPrinting($identity, 'LOT', '90', 'lot-90-rare', 5, isExpandedLegal: true);
        $expected = $this->persistPrinting($identity, 'LOT', '90', 'lot-90-common', 1, isExpandedLegal: true);
        $this->persistPrinting($identity, 'LOT', '90', 'lot-90-illegal', 1, isExpandedLegal: false);

        $found = $this->getRepository()->findFirstBySetCodeAndCardNumber('LOT', '90');

        self::assertInstanceOf(CardPrinting::class, $found);
        self::assertSame($expected->getId(), $found->getId());
    }

    public function testFindFirstBySetCodeAndCardNumberReturnsNullWhenMissing(): void
    {
        self::assertNull($this->getRepository()->findFirstBySetCodeAndCardNumber('NOPE', '0'));
    }

    public function testFindFirstBySetCodeAndCardNumberPrefersExpandedLegalOverLowerTier(): void
    {
        // Expanded-legal flag wins over rarity tier in the ORDER BY chain.
        $identity = $this->persistIdentity('Charizard');
        $expandedLegalRare = $this->persistPrinting($identity, 'LOT', '5', 'lot-5-a', 6, isExpandedLegal: true);
        $this->persistPrinting($identity, 'LOT', '5', 'lot-5-b', 1, isExpandedLegal: false);

        $found = $this->getRepository()->findFirstBySetCodeAndCardNumber('LOT', '5');

        self::assertInstanceOf(CardPrinting::class, $found);
        self::assertSame($expandedLegalRare->getId(), $found->getId());
    }

    private function getRepository(): CardPrintingRepository
    {
        /** @var CardPrintingRepository $repository */
        $repository = static::getContainer()->get(CardPrintingRepository::class);

        return $repository;
    }

    private function persistIdentity(string $name): CardIdentity
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $identity = new CardIdentity();
        $identity->setName($name);
        $identity->setCategory('pokemon');
        $em->persist($identity);
        $em->flush();

        return $identity;
    }

    private function persistPrinting(
        CardIdentity $identity,
        string $setCode,
        string $cardNumber,
        string $tcgdexId,
        int $rarityTier,
        bool $isExpandedLegal,
    ): CardPrinting {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $printing = new CardPrinting();
        $printing->setCardIdentity($identity);
        $printing->setTcgdexId($tcgdexId);
        $printing->setSetCode($setCode);
        $printing->setCardNumber($cardNumber);
        $printing->setRarityTier($rarityTier);
        $printing->setIsExpandedLegal($isExpandedLegal);
        $em->persist($printing);
        $em->flush();

        return $printing;
    }
}
