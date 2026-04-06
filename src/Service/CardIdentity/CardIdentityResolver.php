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

namespace App\Service\CardIdentity;

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Repository\CardIdentityRepository;
use App\Repository\CardPrintingRepository;
use App\Service\Tcgdex\TcgdexApiClient;
use App\Service\Tcgdex\TcgdexCard;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves and populates card identities and their printings.
 *
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class CardIdentityResolver
{
    public function __construct(
        private readonly CardIdentityRepository $identityRepository,
        private readonly CardPrintingRepository $printingRepository,
        private readonly TcgdexApiClient $apiClient,
        private readonly RarityTierMapper $rarityTierMapper,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Resolve the CardPrinting for a TCGdex card, creating CardIdentity and CardPrinting if needed.
     */
    public function resolveFromTcgdexCard(TcgdexCard $tcgdexCard): CardPrinting
    {
        $existing = $this->printingRepository->findByTcgdexId($tcgdexCard->id);

        if (null !== $existing) {
            return $existing;
        }

        $identity = $this->findOrCreateIdentity($tcgdexCard);
        $printing = $this->createPrinting($identity, $tcgdexCard);

        $this->entityManager->persist($printing);
        $this->entityManager->flush();

        return $printing;
    }

    /**
     * Fetch all printings from TCGdex for a card identity and store them.
     *
     * For Pokemon, filters by matching HP + attacks to ensure same functional card.
     * For Trainers/Energy, all printings with the same name are considered equivalent.
     */
    public function expandPrintings(CardIdentity $identity): void
    {
        $allPrintings = $this->apiClient->findAllPrintingsByName($identity->getName());

        $newPrintings = [];

        foreach ($allPrintings as $tcgdexCard) {
            if ('' === $tcgdexCard->id) {
                continue;
            }

            // TCGdex name search is a "contains" match — filter to exact name only
            if ($tcgdexCard->name !== $identity->getName()) {
                continue;
            }

            // Skip if already stored
            if (null !== $this->printingRepository->findByTcgdexId($tcgdexCard->id)) {
                continue;
            }

            // For Pokemon, verify same functional card (HP + abilities + attacks)
            if ('Pokemon' === $identity->getCategory() || 'pokemon' === $identity->getCategory()) {
                $candidateAbilitySignature = self::computeAbilitySignature($tcgdexCard);
                $candidateAttackSignature = self::computeAttackSignature($tcgdexCard);
                $candidateHp = $tcgdexCard->hp ?? 0;

                if ($candidateHp !== $identity->getHp()
                    || $candidateAbilitySignature !== $identity->getAbilitySignature()
                    || $candidateAttackSignature !== $identity->getAttackSignature()) {
                    continue;
                }
            }

            $printing = $this->createPrinting($identity, $tcgdexCard);
            $this->entityManager->persist($printing);
            $newPrintings[] = $printing;
        }

        if ([] === $newPrintings) {
            return;
        }

        try {
            $this->entityManager->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            // Race condition: another worker already inserted some of these printings.
            // Detach the failed entities and continue — the data is already in the DB.
            foreach ($newPrintings as $printing) {
                $this->entityManager->detach($printing);
            }
        }
    }

    private function findOrCreateIdentity(TcgdexCard $tcgdexCard): CardIdentity
    {
        $category = strtolower($tcgdexCard->category);
        $hp = 'pokemon' === $category ? ($tcgdexCard->hp ?? 0) : 0;
        $abilitySignature = 'pokemon' === $category ? self::computeAbilitySignature($tcgdexCard) : '';
        $attackSignature = 'pokemon' === $category ? self::computeAttackSignature($tcgdexCard) : '';

        $existing = $this->identityRepository->findBySignature(
            $tcgdexCard->name,
            $category,
            $hp,
            $abilitySignature,
            $attackSignature,
        );

        if (null !== $existing) {
            // Backfill trainerType if missing on existing identity
            if (null === $existing->getTrainerType() && null !== $tcgdexCard->trainerType) {
                $existing->setTrainerType($tcgdexCard->trainerType);
            }

            return $existing;
        }

        $identity = new CardIdentity();
        $identity->setName($tcgdexCard->name);
        $identity->setCategory($category);
        $identity->setHp($hp);
        $identity->setAbilitySignature($abilitySignature);
        $identity->setAbilityNames(implode(',', $tcgdexCard->abilities));
        $identity->setAttackSignature($attackSignature);
        $identity->setAttackNames(implode(',', $tcgdexCard->attacks));
        $identity->setTrainerType($tcgdexCard->trainerType);

        $this->entityManager->persist($identity);

        return $identity;
    }

    private function createPrinting(CardIdentity $identity, TcgdexCard $tcgdexCard): CardPrinting
    {
        $printing = new CardPrinting();
        $printing->setCardIdentity($identity);
        $printing->setTcgdexId($tcgdexCard->id);
        $printing->setSetCode($tcgdexCard->setCode ?? '');
        $printing->setCardNumber($tcgdexCard->cardNumber ?? '');
        $printing->setRarity($tcgdexCard->rarity);
        // Extract set ID from tcgdexId (e.g. "sma-SV84" → "sma")
        $setId = str_contains($tcgdexCard->id, '-') ? substr($tcgdexCard->id, 0, (int) strpos($tcgdexCard->id, '-')) : null;
        $printing->setRarityTier($this->rarityTierMapper->map(
            $tcgdexCard->rarity,
            $setId,
            $tcgdexCard->cardNumber,
            $tcgdexCard->setOfficialCardCount,
        ));
        $printing->setImageUrl($tcgdexCard->imageUrl);
        $printing->setPriceInCents($tcgdexCard->priceInCents);
        $printing->setIsExpandedLegal($tcgdexCard->isExpandedLegal);
        $printing->setCardmarketProductId($tcgdexCard->cardmarketProductId);
        $printing->setTcgplayerProductId($tcgdexCard->tcgplayerProductId);

        if (null !== $tcgdexCard->setReleaseDate) {
            try {
                $printing->setSetReleaseDate(new \DateTimeImmutable($tcgdexCard->setReleaseDate));
            } catch (\Exception) {
                // Invalid date format — leave null
            }
        }

        $identity->addPrinting($printing);

        return $printing;
    }

    public static function computeAbilitySignature(TcgdexCard $tcgdexCard): string
    {
        $abilities = $tcgdexCard->abilities;
        sort($abilities);

        return implode(',', $abilities);
    }

    public static function computeAttackSignature(TcgdexCard $tcgdexCard): string
    {
        $attacks = $tcgdexCard->attacks;
        sort($attacks);

        return implode(',', $attacks);
    }
}
