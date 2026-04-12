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

use App\Entity\DeckVersion;
use App\Message\EnrichDeckVersionMessage;
use App\Repository\DeckCardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Re-enriches a single card identified by set code + card number.
 *
 * Detaches the existing CardPrinting from all DeckCards with the given set/number,
 * resets the affected DeckVersions to pending enrichment, and dispatches
 * enrichment + mosaic/minified generation messages for each.
 */
class CardReenrichService
{
    public function __construct(
        private readonly DeckCardRepository $deckCardRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return int the number of DeckVersions dispatched for re-enrichment
     */
    public function reenrich(string $setCode, string $cardNumber): int
    {
        $deckCards = $this->deckCardRepository->findBySetCodeAndCardNumber($setCode, $cardNumber);

        if ([] === $deckCards) {
            return 0;
        }

        // Collect unique affected DeckVersions and detach CardPrinting from each DeckCard
        /** @var array<int, DeckVersion> $affectedVersions */
        $affectedVersions = [];

        foreach ($deckCards as $deckCard) {
            $deckCard->setCardPrinting(null);

            $version = $deckCard->getDeckVersion();
            $versionId = $version->getId();

            if (null !== $versionId && !isset($affectedVersions[$versionId])) {
                $affectedVersions[$versionId] = $version;
            }
        }

        // Reset each affected version to pending state
        foreach ($affectedVersions as $version) {
            $version->setEnrichmentStatus('pending');
            $version->setMosaicImageUrl(null);
            $version->setMinifiedList(null);
            $version->setMinifiedCardViews(null);
            $version->setMinifiedMosaicImageUrl(null);
        }

        $this->entityManager->flush();

        // Dispatch re-enrichment (handler also triggers mosaic + minified generation)
        foreach ($affectedVersions as $versionId => $version) {
            $this->messageBus->dispatch(new EnrichDeckVersionMessage($versionId));
        }

        return \count($affectedVersions);
    }
}
