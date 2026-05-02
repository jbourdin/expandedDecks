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

namespace App\Service;

use App\Entity\BannedCard;
use App\Repository\CardPrintingRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\Tcgdex\TcgdexApiClient;

/**
 * Resolves CardPrinting + CardIdentity for a BannedCard so the public list
 * can group rows that share the same functional card.
 *
 * Mirrors the deck-list enrichment chain:
 *   1. Local CardPrinting by (setCode, cardNumber).
 *   2. TCGdex API by (setCode, cardNumber) — local mirror first, HTTP fallback.
 *   3. TCGdex API by name within an aliased set (Asian / promo edge cases).
 *
 * Once a TCGdex card is found, CardIdentityResolver creates the CardPrinting
 * and CardIdentity rows if missing and copies over the imageUrl.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
final readonly class BannedCardEnricher
{
    public function __construct(
        private TcgdexApiClient $apiClient,
        private CardIdentityResolver $identityResolver,
        private CardPrintingRepository $cardPrintingRepository,
    ) {
    }

    /**
     * Resolves and links a CardPrinting on the BannedCard. No-op when already linked.
     * Returns true if a printing is now linked, false if no resolution succeeded.
     */
    public function enrich(BannedCard $card): bool
    {
        if (null !== $card->getCardPrinting()) {
            return true;
        }

        $local = $this->cardPrintingRepository->findFirstBySetCodeAndCardNumber(
            $card->getSetCode(),
            $card->getCardNumber(),
        );

        if (null !== $local) {
            $card->setCardPrinting($local);

            return true;
        }

        $tcgdexCard = $this->apiClient->findCard($card->getSetCode(), $card->getCardNumber());

        if (null === $tcgdexCard) {
            $tcgdexCard = $this->apiClient->findCardByNameInAliasedSet(
                $card->getSetCode(),
                $card->getCardName(),
            );
        }

        if (null === $tcgdexCard) {
            return false;
        }

        $printing = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);

        if (null === $printing->getImageUrl() && null !== $tcgdexCard->imageUrl) {
            $printing->setImageUrl($tcgdexCard->imageUrl);
        }

        $card->setCardPrinting($printing);

        return true;
    }
}
