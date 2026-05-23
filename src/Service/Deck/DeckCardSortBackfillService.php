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

namespace App\Service\Deck;

use App\Entity\DeckVersion;
use App\Message\BackfillDeckCardSortOrderMessage;
use App\Repository\DeckVersionRepository;
use App\Service\DeckListParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Finds DeckVersions with a stored rawList but at least one DeckCard
 * whose sortOrder is null, and either dispatches a backfill message
 * per version (`redispatch`) or runs the backfill synchronously for a
 * single version (`backfillVersion`, called from the enrichment handler
 * so freshly-enriched decks always end up with sortOrder populated).
 *
 * @see docs/features.md F2.28 — Preserve imported list order
 */
class DeckCardSortBackfillService
{
    public function __construct(
        private readonly DeckVersionRepository $versionRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly DeckListParser $parser,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Count versions still needing backfill (for the admin dashboard tile).
     */
    public function countPending(): int
    {
        return $this->versionRepository->countNeedingSortOrderBackfill();
    }

    /**
     * Dispatch backfill messages for all pending versions.
     *
     * @return int the number of messages dispatched
     */
    public function redispatch(): int
    {
        $ids = $this->versionRepository->findIdsNeedingSortOrderBackfill();

        foreach ($ids as $id) {
            $this->messageBus->dispatch(new BackfillDeckCardSortOrderMessage($id));
        }

        return \count($ids);
    }

    /**
     * Synchronously populate `DeckCard.sortOrder` for one version by re-parsing
     * its rawList and matching cards by `(setCode, cardNumber)`. Used by the
     * async handler AND by the enrichment pipeline so freshly-enriched decks
     * always have sortOrder set.
     *
     * Returns an array of metrics so callers can log them.
     *
     * cardName is NOT in the match key because `CardEnricher::enrich()`
     * overwrites cardName with TCGdex's canonical form — set+number are
     * stable, name is not.
     *
     * @return array{changed: int, missing: int, skipped: bool}
     */
    public function backfillVersion(DeckVersion $version): array
    {
        $rawList = $version->getRawList();
        if (null === $rawList || '' === trim($rawList)) {
            return ['changed' => 0, 'missing' => 0, 'skipped' => true];
        }

        // Cheap early-exit: if every card already has a sortOrder, there's nothing to do.
        $hasNull = false;
        foreach ($version->getCards() as $card) {
            if (null === $card->getSortOrder()) {
                $hasNull = true;
                break;
            }
        }
        if (!$hasNull) {
            return ['changed' => 0, 'missing' => 0, 'skipped' => true];
        }

        $result = $this->parser->parse($rawList);

        $parsedBySignature = [];
        foreach ($result->cards as $parsedCard) {
            $parsedBySignature[$parsedCard->setCode.'|'.$parsedCard->cardNumber] = $parsedCard->sortOrder;
        }

        $changed = 0;
        $missing = 0;
        foreach ($version->getCards() as $deckCard) {
            $signature = $deckCard->getSetCode().'|'.$deckCard->getCardNumber();
            $newSortOrder = $parsedBySignature[$signature] ?? null;

            if (null === $newSortOrder) {
                ++$missing;
                continue;
            }

            if ($deckCard->getSortOrder() !== $newSortOrder) {
                $deckCard->setSortOrder($newSortOrder);
                ++$changed;
            }
        }

        if ($changed > 0) {
            $this->entityManager->flush();
        }

        return ['changed' => $changed, 'missing' => $missing, 'skipped' => false];
    }
}
