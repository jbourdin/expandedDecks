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
use App\Entity\BannedCardPrinting;
use App\Repository\BannedCardPrintingRepository;
use App\Repository\BannedCardRepository;
use App\Repository\CardPrintingRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\Tcgdex\TcgdexApiClient;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves CardPrinting + CardIdentity for each {@see BannedCardPrinting} so
 * the parent {@see BannedCard} entries can collapse rows that share the same
 * functional card.
 *
 * Mirrors the deck-list enrichment chain:
 *   1. Local CardPrinting by (setCode, cardNumber).
 *   2. TCGdex API by (setCode, cardNumber) — local mirror first, HTTP fallback.
 *   3. TCGdex API by name within an aliased set (Asian / promo edge cases).
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
        private BannedCardPrintingRepository $bannedCardPrintingRepository,
        private BannedCardRepository $bannedCardRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Re-enriches every banned printing then reparents each child under the
     * canonical {@see BannedCard} for its CardIdentity. Returns
     * [linked, unresolved-list].
     *
     * @return array{0: int, 1: list<string>}
     */
    public function enrichAllActive(bool $force = false): array
    {
        $printings = $this->bannedCardPrintingRepository->findAllOrderedBySetAndNumber();

        /** @var array<int, BannedCard> $parentsByIdentityId */
        $parentsByIdentityId = [];

        $linked = 0;
        $unresolved = [];
        foreach ($printings as $printing) {
            if ($force) {
                $printing->setCardPrinting(null);
            }

            if ($this->enrichPrinting($printing)) {
                ++$linked;
                $this->reparentByIdentity($printing, $parentsByIdentityId);
            } else {
                $unresolved[] = \sprintf(
                    '%s (%s %s)',
                    $printing->getBannedCard()->getCardName(),
                    $printing->getSetCode(),
                    $printing->getCardNumber(),
                );
            }
        }

        $this->entityManager->flush();

        return [$linked, $unresolved];
    }

    /**
     * Resolves and links a CardPrinting on a banned printing. No-op when
     * already linked. Returns true if a printing is now linked.
     */
    public function enrichPrinting(BannedCardPrinting $printing): bool
    {
        if (null !== $printing->getCardPrinting()) {
            return true;
        }

        $local = $this->cardPrintingRepository->findFirstBySetCodeAndCardNumber(
            $printing->getSetCode(),
            $printing->getCardNumber(),
        );

        if (null !== $local) {
            $printing->setCardPrinting($local);

            return true;
        }

        $tcgdexCard = $this->apiClient->findCard($printing->getSetCode(), $printing->getCardNumber());

        if (null === $tcgdexCard) {
            $tcgdexCard = $this->apiClient->findCardByNameInAliasedSet(
                $printing->getSetCode(),
                $printing->getBannedCard()->getCardName(),
            );
        }

        if (null === $tcgdexCard) {
            return false;
        }

        $cardPrinting = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);

        if (null === $cardPrinting->getImageUrl() && null !== $tcgdexCard->imageUrl) {
            $cardPrinting->setImageUrl($tcgdexCard->imageUrl);
        }

        $printing->setCardPrinting($cardPrinting);

        return true;
    }

    /**
     * Once a printing knows its CardIdentity, move it under the canonical
     * {@see BannedCard} parent for that identity. Drops empty placeholder
     * parents. The in-memory $parentsByIdentityId cache covers parents
     * created in the same loop that haven't been flushed yet.
     *
     * @param array<int, BannedCard> $parentsByIdentityId
     */
    private function reparentByIdentity(BannedCardPrinting $printing, array &$parentsByIdentityId): void
    {
        $cardPrinting = $printing->getCardPrinting();
        if (null === $cardPrinting) {
            return;
        }

        $identity = $cardPrinting->getCardIdentity();
        $identityId = $identity->getId();
        $currentParent = $printing->getBannedCard();

        $canonical = (null !== $identityId ? ($parentsByIdentityId[$identityId] ?? null) : null)
            ?? $this->bannedCardRepository->findOneByCardIdentity($identity);

        if (null === $canonical) {
            $currentParent->setCardIdentity($identity);
            $currentParent->setCardName($identity->getName());
            if (null !== $identityId) {
                $parentsByIdentityId[$identityId] = $currentParent;
            }

            return;
        }

        if ($canonical === $currentParent) {
            if ('' === $canonical->getCardName()) {
                $canonical->setCardName($identity->getName());
            }
            if (null !== $identityId) {
                $parentsByIdentityId[$identityId] = $canonical;
            }

            return;
        }

        // Move under canonical, drop empty placeholder.
        $currentParent->removePrinting($printing);
        $canonical->addPrinting($printing);

        if ($currentParent->getPrintings()->isEmpty() && null === $currentParent->getCardIdentity()) {
            $this->entityManager->remove($currentParent);
        }

        if (null !== $identityId) {
            $parentsByIdentityId[$identityId] = $canonical;
        }
    }
}
