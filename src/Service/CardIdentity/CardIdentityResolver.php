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

        foreach ($allPrintings as $tcgdexCard) {
            if ('' === $tcgdexCard->id) {
                continue;
            }

            // Skip if already stored
            if (null !== $this->printingRepository->findByTcgdexId($tcgdexCard->id)) {
                continue;
            }

            // For Pokemon, verify same functional card (HP + attacks)
            if ('Pokemon' === $identity->getCategory() || 'pokemon' === $identity->getCategory()) {
                $candidateSignature = self::computeAttackSignature($tcgdexCard);
                $candidateHp = $tcgdexCard->hp ?? 0;

                if ($candidateHp !== $identity->getHp() || $candidateSignature !== $identity->getAttackSignature()) {
                    continue;
                }
            }

            $printing = $this->createPrinting($identity, $tcgdexCard);
            $this->entityManager->persist($printing);
        }

        $this->entityManager->flush();
    }

    private function findOrCreateIdentity(TcgdexCard $tcgdexCard): CardIdentity
    {
        $category = strtolower($tcgdexCard->category);
        $hp = 'pokemon' === $category ? ($tcgdexCard->hp ?? 0) : 0;
        $attackSignature = 'pokemon' === $category ? self::computeAttackSignature($tcgdexCard) : '';

        $existing = $this->identityRepository->findBySignature(
            $tcgdexCard->name,
            $category,
            $hp,
            $attackSignature,
        );

        if (null !== $existing) {
            return $existing;
        }

        $identity = new CardIdentity();
        $identity->setName($tcgdexCard->name);
        $identity->setCategory($category);
        $identity->setHp($hp);
        $identity->setAttackSignature($attackSignature);

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
        $printing->setRarityTier($this->rarityTierMapper->map($tcgdexCard->rarity, $setId));
        $printing->setImageUrl($tcgdexCard->imageUrl);
        $printing->setIsExpandedLegal($tcgdexCard->isExpandedLegal);

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

    public static function computeAttackSignature(TcgdexCard $tcgdexCard): string
    {
        $attacks = $tcgdexCard->attacks;
        sort($attacks);

        return implode(',', $attacks);
    }
}
