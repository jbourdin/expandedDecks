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
use App\Service\Tcgdex\TcgdexApiThrottle;
use App\Service\Tcgdex\TcgdexCardHydrator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches a single card from the TCGdex API, one locale at a time, and persists it.
 *
 * The base locale (first configured, English) carries the locale-independent fields;
 * each additional locale is merged into the JSON columns. In Sync mode a card that
 * already has every configured locale is skipped with no HTTP call, and only the
 * missing locales are fetched. In ForceUpdate mode every locale is re-fetched.
 *
 * 404 handling is locale-aware:
 *  - base locale 404 → the card genuinely doesn't exist; warn and stop.
 *  - translation locale 404 → that translation isn't published yet; skip it quietly.
 * On other HTTP errors of the base locale: redispatch with a delay for retry.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
 */
#[AsMessageHandler]
class SyncTcgdexCardHandler
{
    /**
     * @param list<string> $tcgdexLocales
     */
    public function __construct(
        private readonly HttpClientInterface $tcgdexClient,
        private readonly TcgdexApiThrottle $throttle,
        private readonly TcgdexCardHydrator $hydrator,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly string $tcgdexHost,
        private readonly array $tcgdexLocales,
    ) {
    }

    public function __invoke(SyncTcgdexCardMessage $message): void
    {
        $cardId = $message->cardId;
        $setId = $message->setId;
        $mode = $message->mode;

        $existing = $this->entityManager->find(TcgdexCard::class, $cardId);

        // Gap-fill: a card with every configured locale already populated needs no HTTP call.
        if (SyncMode::Sync === $mode && $existing instanceof TcgdexCard && $existing->hasAllLocales($this->tcgdexLocales)) {
            return;
        }

        $baseLocale = $this->tcgdexLocales[0] ?? 'en';
        $localesToFetch = $this->resolveLocalesToFetch($mode, $existing, $baseLocale);

        $card = $existing;
        $dirty = false;

        foreach ($localesToFetch as $locale) {
            $isBaseLocale = $locale === $baseLocale;

            $data = $this->fetchCard($cardId, $locale, $isBaseLocale, $message);

            if (null === $data) {
                // Base locale failure/404 aborts the whole card; a translation gap is skipped.
                if ($isBaseLocale) {
                    return;
                }

                continue;
            }

            if ($isBaseLocale && null === $card) {
                $set = $this->entityManager->find(TcgdexSet::class, $setId);

                if (null === $set) {
                    $this->logger->warning('TCGdex sync: set {setId} not found for card {cardId}, skipping.', [
                        'setId' => $setId,
                        'cardId' => $cardId,
                    ]);

                    return;
                }

                $card = $this->hydrator->hydrateFromApiResponse($data, $set, $locale);
                $this->entityManager->persist($card);
                $dirty = true;

                continue;
            }

            if (null === $card) {
                // Defensive: a translation arrived before the base locale established the card.
                continue;
            }

            if ($isBaseLocale) {
                $this->hydrator->updateFromApiResponse($card, $data, $locale);
            } else {
                $this->hydrator->mergeLocaleFields($card, $locale, $data);
            }

            $dirty = true;
        }

        if ($dirty) {
            $this->entityManager->flush();
        }
    }

    /**
     * Decide which locales to fetch: every configured locale in ForceUpdate mode, or
     * the base locale (as a probe) plus the locales the card still lacks in Sync mode.
     *
     * @return list<string>
     */
    private function resolveLocalesToFetch(SyncMode $mode, ?TcgdexCard $existing, string $baseLocale): array
    {
        if (SyncMode::ForceUpdate === $mode) {
            return $this->tcgdexLocales;
        }

        $missing = $existing instanceof TcgdexCard
            ? $existing->getMissingLocales($this->tcgdexLocales)
            : $this->tcgdexLocales;

        return array_values(array_unique([$baseLocale, ...$missing]));
    }

    /**
     * Fetch one locale of a card. Returns the decoded payload, or null when the card
     * should be skipped (404) — redispatching the message on a transient base-locale error.
     *
     * @return array<string, mixed>|null
     */
    private function fetchCard(string $cardId, string $locale, bool $isBaseLocale, SyncTcgdexCardMessage $message): ?array
    {
        $this->throttle->waitIfNeeded();

        try {
            $response = $this->tcgdexClient->request('GET', $this->tcgdexHost.'/'.$locale.'/cards/'.$cardId);
            $statusCode = $response->getStatusCode();
        } catch (\Throwable $exception) {
            $this->throttle->reportFailure();
            $this->logger->error('TCGdex sync: failed to fetch card {cardId} ({locale}): {error}', [
                'cardId' => $cardId,
                'locale' => $locale,
                'error' => $exception->getMessage(),
            ]);

            // Only the base locale is worth retrying; a flaky translation is filled on the next sync.
            if ($isBaseLocale) {
                $this->messageBus->dispatch($message, [new DelayStamp(60000)]);
            }

            return null;
        }

        if (404 === $statusCode) {
            $this->throttle->reportSuccess();

            if ($isBaseLocale) {
                $this->logger->warning('TCGdex sync: card {cardId} returned 404, skipping.', ['cardId' => $cardId]);
            } else {
                $this->logger->info('TCGdex sync: card {cardId} has no {locale} translation yet, skipping locale.', [
                    'cardId' => $cardId,
                    'locale' => $locale,
                ]);
            }

            return null;
        }

        $this->throttle->reportSuccess();

        try {
            /** @var array<string, mixed> $data */
            $data = $response->toArray();
        } catch (\Throwable $exception) {
            $this->logger->error('TCGdex sync: failed to decode card {cardId} ({locale}): {error}', [
                'cardId' => $cardId,
                'locale' => $locale,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        return $data;
    }
}
