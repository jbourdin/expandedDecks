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

namespace App\Service\Mosaic;

use App\Message\GenerateDeckMosaicMessage;
use App\Repository\DeckVersionRepository;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Finds fully enriched DeckVersions missing a mosaic image and dispatches
 * generation messages for each.
 *
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
class MosaicRedispatchService
{
    public function __construct(
        private readonly DeckVersionRepository $deckVersionRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Dispatch mosaic generation for all enriched versions without a mosaic.
     *
     * @return int the number of messages dispatched
     */
    public function redispatch(): int
    {
        $versions = $this->deckVersionRepository->findEnrichedWithoutMosaic();

        foreach ($versions as $version) {
            /** @var int $id */
            $id = $version->getId();
            $this->messageBus->dispatch(new GenerateDeckMosaicMessage($id));
        }

        return \count($versions);
    }
}
