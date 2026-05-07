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

use App\Constants\RuleboxType;
use App\Constants\StapleCardBucket;
use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Entity\StapleCard;
use App\Entity\StapleCardPrinting;
use App\Repository\CardPrintingRepository;
use App\Repository\StapleCardPrintingRepository;
use App\Repository\StapleCardRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\Tcgdex\TcgdexApiClient;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves CardPrinting + CardIdentity for {@see StapleCardPrinting} children, drives
 * the editor "create from code" flow, and recomputes per-row {@see StapleCard::$bucket}
 * values from the canonical (ruleboxType, category, trainerType) on the linked identity.
 *
 * Mirrors {@see BannedCardEnricher} for the printing-resolution + reparent-by-identity
 * machinery, with two staple-specific additions:
 *   1. `createFromCode()` — entry point for the admin form (one editor-supplied code
 *      yields a fully-populated StapleCard with sibling printings expanded).
 *   2. `recomputeBucket()` / bucket priority — Ace Spec wins over the type-based
 *      buckets so editors curating Ace Specs see them gathered.
 *
 * @see docs/features.md F6.15 — Staple cards
 */
final readonly class StapleCardEnricher
{
    public function __construct(
        private TcgdexApiClient $apiClient,
        private CardIdentityResolver $identityResolver,
        private CardPrintingRepository $cardPrintingRepository,
        private StapleCardPrintingRepository $stapleCardPrintingRepository,
        private StapleCardRepository $stapleCardRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Editor entry point: take a (set code, card number) submitted from the admin form
     * and create or update a StapleCard for the resolved CardIdentity, with sibling
     * printings expanded via TCGdex.
     *
     * Idempotent: if a StapleCard already exists for the identity (active or soft-deleted),
     * it is updated and restored; the row's existing position is preserved.
     *
     * Returns null when the (setCode, cardNumber) cannot be resolved on TCGdex.
     */
    public function createFromCode(string $setCode, string $cardNumber, int $hotness, ?string $note): ?StapleCard
    {
        $tcgdexCard = $this->apiClient->findCard($setCode, $cardNumber);
        if (null === $tcgdexCard) {
            return null;
        }

        $cardPrinting = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
        $identity = $cardPrinting->getCardIdentity();
        $this->identityResolver->expandPrintings($identity);

        $bucket = $this->computeBucketFor($identity);
        $staple = $this->stapleCardRepository->findOneByCardIdentity($identity);

        if (null !== $staple) {
            // Restore + update existing row, preserve its position.
            $staple->setDeletedAt(null);
            $staple->setHotness($hotness);
            $staple->setNote($note);
            $staple->setCardName($identity->getName());
            $previousBucket = $staple->getBucket();
            $staple->setBucket($bucket);

            // If the bucket changed (e.g. Ace Spec detection caught up), reposition at the new bucket's tail.
            if ($previousBucket !== $bucket) {
                $staple->setPosition($this->stapleCardRepository->findMaxPositionInBucket($bucket) + 1);
            }
        } else {
            $staple = new StapleCard();
            $staple->setCardIdentity($identity);
            $staple->setCardName($identity->getName());
            $staple->setBucket($bucket);
            $staple->setHotness($hotness);
            $staple->setNote($note);
            $staple->setPosition($this->stapleCardRepository->findMaxPositionInBucket($bucket) + 1);
            $this->entityManager->persist($staple);
        }

        $this->syncChildPrintings($staple, $identity);

        $this->entityManager->flush();

        return $staple;
    }

    /**
     * Re-enriches every staple printing then reparents each child under the canonical
     * StapleCard for its CardIdentity, recomputing each canonical row's bucket from
     * the now-known identity. Returns [linked, unresolved-list].
     *
     * @return array{0: int, 1: list<string>}
     */
    public function enrichAllActive(bool $force = false): array
    {
        $printings = $this->stapleCardPrintingRepository->findAllOrderedBySetAndNumber();

        /** @var array<int, StapleCard> $parentsByIdentityId */
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
                    $printing->getStapleCard()->getCardName(),
                    $printing->getSetCode(),
                    $printing->getCardNumber(),
                );
            }
        }

        // Recompute bucket for every active StapleCard whose identity is known.
        foreach ($this->stapleCardRepository->findAllActive() as $staple) {
            $identity = $staple->getCardIdentity();
            if (null === $identity) {
                continue;
            }
            $newBucket = $this->computeBucketFor($identity);
            if ($newBucket !== $staple->getBucket()) {
                $staple->setBucket($newBucket);
                $staple->setPosition($this->stapleCardRepository->findMaxPositionInBucket($newBucket) + 1);
            }
        }

        $this->entityManager->flush();

        return [$linked, $unresolved];
    }

    /**
     * Resolves and links a CardPrinting on a staple printing. No-op when already linked.
     */
    public function enrichPrinting(StapleCardPrinting $printing): bool
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
                $printing->getStapleCard()->getCardName(),
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
     * Compute the bucket for a card identity per the priority rule documented in
     * {@see StapleCardBucket}. Defaults to POKEMON when the identity is null
     * (placeholder rows that haven't been enriched yet — should be transient).
     */
    public function computeBucketFor(?CardIdentity $identity): string
    {
        if (null === $identity) {
            return StapleCardBucket::POKEMON;
        }

        if (RuleboxType::ACE_SPEC === $identity->getRuleboxType()) {
            return StapleCardBucket::ACE_SPEC;
        }

        if ('pokemon' === $identity->getCategory()) {
            return StapleCardBucket::POKEMON;
        }

        if ('energy' === $identity->getCategory()) {
            return StapleCardBucket::ENERGY;
        }

        return match ($identity->getTrainerType()) {
            'Supporter' => StapleCardBucket::SUPPORTER,
            'Tool' => StapleCardBucket::TOOL,
            'Stadium' => StapleCardBucket::STADIUM,
            // Defaults non-recognized trainer subtypes to ITEM — most Trainer cards are Items, and
            // an unrecognized subtype is rare enough that an Item-bucket sighting flags it for editor review.
            default => StapleCardBucket::ITEM,
        };
    }

    /**
     * Once a printing knows its CardIdentity, move it under the canonical StapleCard
     * parent for that identity. Drops empty placeholder parents.
     *
     * @param array<int, StapleCard> $parentsByIdentityId
     */
    private function reparentByIdentity(StapleCardPrinting $printing, array &$parentsByIdentityId): void
    {
        $cardPrinting = $printing->getCardPrinting();
        if (null === $cardPrinting) {
            return;
        }

        $identity = $cardPrinting->getCardIdentity();
        $identityId = $identity->getId();
        $currentParent = $printing->getStapleCard();

        $canonical = (null !== $identityId ? ($parentsByIdentityId[$identityId] ?? null) : null)
            ?? $this->stapleCardRepository->findOneByCardIdentity($identity);

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

        $currentParent->removePrinting($printing);
        $canonical->addPrinting($printing);

        if ($currentParent->getPrintings()->isEmpty() && null === $currentParent->getCardIdentity()) {
            $this->entityManager->remove($currentParent);
        }

        if (null !== $identityId) {
            $parentsByIdentityId[$identityId] = $canonical;
        }
    }

    /**
     * Make sure the StapleCard has one StapleCardPrinting child per local CardPrinting on its identity.
     * Adds missing ones; never removes (editors may have added printings for a sibling card with a
     * different name that we don't want to silently drop).
     */
    private function syncChildPrintings(StapleCard $staple, CardIdentity $identity): void
    {
        $existingByCardPrintingId = [];
        foreach ($staple->getPrintings() as $child) {
            $cp = $child->getCardPrinting();
            if (null !== $cp && null !== $cp->getId()) {
                $existingByCardPrintingId[$cp->getId()] = true;
            }
        }

        foreach ($identity->getPrintings() as $cardPrinting) {
            $cardPrintingId = $cardPrinting->getId();
            if (null !== $cardPrintingId && isset($existingByCardPrintingId[$cardPrintingId])) {
                continue;
            }

            // Skip if a global-unique (setCode, cardNumber) row already exists under another StapleCard;
            // the editor would need to delete that one first.
            $existing = $this->stapleCardPrintingRepository->findOneBySetCodeAndCardNumber(
                $cardPrinting->getSetCode(),
                $cardPrinting->getCardNumber(),
            );
            if (null !== $existing && $existing->getStapleCard() !== $staple) {
                continue;
            }

            if (null !== $existing) {
                continue;
            }

            $child = new StapleCardPrinting();
            $child->setSetCode($cardPrinting->getSetCode());
            $child->setCardNumber($cardPrinting->getCardNumber());
            $child->setCardPrinting($cardPrinting);
            $staple->addPrinting($child);
            $this->entityManager->persist($child);
        }
    }
}
