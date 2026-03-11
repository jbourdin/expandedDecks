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

namespace App\Repository;

use App\Entity\DeckCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeckCard>
 */
class DeckCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeckCard::class);
    }

    /**
     * Find an enriched card by set code and card number (for custom tag rendering).
     *
     * Returns the first matching card that has an image URL, or any match otherwise.
     *
     * @see docs/features.md F2.10 — Archetype detail page
     */
    public function findOneBySetCodeAndCardNumber(string $setCode, string $cardNumber): ?DeckCard
    {
        /** @var DeckCard|null $card */
        $card = $this->createQueryBuilder('c')
            ->where('c.setCode = :setCode')
            ->andWhere('c.cardNumber = :cardNumber')
            ->andWhere('c.imageUrl IS NOT NULL')
            ->setParameter('setCode', $setCode)
            ->setParameter('cardNumber', $cardNumber)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $card) {
            return $card;
        }

        /** @var DeckCard|null $fallback */
        $fallback = $this->createQueryBuilder('c')
            ->where('c.setCode = :setCode')
            ->andWhere('c.cardNumber = :cardNumber')
            ->setParameter('setCode', $setCode)
            ->setParameter('cardNumber', $cardNumber)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $fallback;
    }
}
