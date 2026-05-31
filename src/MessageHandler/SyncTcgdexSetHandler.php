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

namespace App\MessageHandler;

use App\Entity\TcgdexCard;
use App\Entity\TcgdexSet;
use App\Enum\SyncMode;
use App\Message\SyncTcgdexCardMessage;
use App\Message\SyncTcgdexSetMessage;
use App\Service\Tcgdex\TcgdexApiThrottle;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Syncs a single set: updates metadata and dispatches SyncTcgdexCardMessage for any
 * card that is new, missing a configured locale (gap-fill), or — in ForceUpdate mode —
 * every card in the set.
 *
 * Set discovery uses the base (English) locale; the per-card handler fans out the
 * remaining locales. Set responses carry no per-card timestamps, so locale freshness
 * is decided from the locally stored card, not the API stub.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
 */
#[AsMessageHandler]
class SyncTcgdexSetHandler
{
    /**
     * @param list<string> $tcgdexLocales
     */
    public function __construct(
        private readonly HttpClientInterface $tcgdexClient,
        private readonly TcgdexApiThrottle $throttle,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly string $tcgdexHost,
        private readonly array $tcgdexLocales,
    ) {
    }

    public function __invoke(SyncTcgdexSetMessage $message): void
    {
        $setId = $message->setId;
        $mode = $message->mode;

        $this->throttle->waitIfNeeded();

        try {
            $response = $this->tcgdexClient->request('GET', $this->tcgdexHost.'/en/sets/'.$setId);
            /** @var array<string, mixed> $setData */
            $setData = $response->toArray();
        } catch (\Throwable $exception) {
            $this->throttle->reportFailure();
            $this->logger->error('TCGdex sync: failed to fetch set {setId}: {error}', [
                'setId' => $setId,
                'error' => $exception->getMessage(),
            ]);
            $this->messageBus->dispatch($message, [new DelayStamp(60000)]);

            return;
        }

        $this->throttle->reportSuccess();

        $set = $this->entityManager->find(TcgdexSet::class, $setId);

        if (null === $set) {
            $this->logger->warning('TCGdex sync: set {setId} not found in database, skipping.', [
                'setId' => $setId,
            ]);

            return;
        }

        // Update set metadata
        $this->updateSetMetadata($set, $setData);
        $this->entityManager->flush();

        // Process cards from the set response
        /** @var list<array<string, mixed>> $cardsData */
        $cardsData = isset($setData['cards']) && \is_array($setData['cards']) ? $setData['cards'] : [];

        $dispatched = 0;
        $updatedImages = 0;

        foreach ($cardsData as $cardData) {
            $cardId = $this->extractString($cardData, 'id');

            if (null === $cardId) {
                continue;
            }

            $existing = $this->entityManager->find(TcgdexCard::class, $cardId);

            if (null === $existing) {
                // New card — dispatch hydration in the requested mode.
                $this->messageBus->dispatch(new SyncTcgdexCardMessage($cardId, $setId, $mode));
                ++$dispatched;
            } elseif (SyncMode::ForceUpdate === $mode) {
                // Force update — re-fetch every locale of the card via a per-card API call.
                $this->messageBus->dispatch(new SyncTcgdexCardMessage($cardId, $setId, $mode));
                ++$dispatched;
            } else {
                // Sync — refresh the locale-independent image URL from the set stub for free,
                // then dispatch a per-card fetch only when the card still lacks a locale.
                $imageBaseUrl = $this->extractString($cardData, 'image');

                if (null !== $imageBaseUrl && $imageBaseUrl !== $existing->getImageBaseUrl()) {
                    $existing->setImageBaseUrl($imageBaseUrl);
                    ++$updatedImages;
                }

                if (!$existing->hasAllLocales($this->tcgdexLocales)) {
                    $this->messageBus->dispatch(new SyncTcgdexCardMessage($cardId, $setId, $mode));
                    ++$dispatched;
                }
            }
        }

        if ($updatedImages > 0) {
            $this->entityManager->flush();
            $this->logger->info('TCGdex sync: set {setId} — updated {count} card image URLs from set response.', [
                'setId' => $setId,
                'count' => $updatedImages,
            ]);
        }

        if ($dispatched > 0) {
            $this->logger->info('TCGdex sync: set {setId} — dispatched {count} card sync messages.', [
                'setId' => $setId,
                'count' => $dispatched,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $setData
     */
    private function updateSetMetadata(TcgdexSet $set, array $setData): void
    {
        $releaseDate = $this->extractString($setData, 'releaseDate');

        if (null !== $releaseDate) {
            try {
                $set->setReleaseDate(new \DateTimeImmutable($releaseDate));
            } catch (\Exception) {
                // Invalid date — leave unchanged
            }
        }

        // Extract PTCG code from tcgOnline or abbreviation.official
        $tcgOnline = $this->extractString($setData, 'tcgOnline');

        if (null !== $tcgOnline) {
            $set->setPtcgCode(strtoupper($tcgOnline));
        } else {
            /** @var array<string, mixed> $abbreviation */
            $abbreviation = isset($setData['abbreviation']) && \is_array($setData['abbreviation']) ? $setData['abbreviation'] : [];
            $official = $this->extractString($abbreviation, 'official');

            if (null !== $official && '' !== $official) {
                $set->setPtcgCode(strtoupper($official));
            }
        }

        // Card count from nested cardCount object
        if (isset($setData['cardCount']) && \is_array($setData['cardCount'])) {
            $official = $setData['cardCount']['official'] ?? null;

            if (\is_int($official)) {
                $set->setOfficialCardCount($official);
            }
        }

        $set->setLogoUrl($this->extractString($setData, 'logo'));
        $set->setSymbolUrl($this->extractString($setData, 'symbol'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractString(array $data, string $key): ?string
    {
        return isset($data[$key]) && \is_string($data[$key]) ? $data[$key] : null;
    }
}
