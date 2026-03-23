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

use App\Entity\TcgdexSetMapping;
use App\Message\BuildSetMappingsMessage;
use App\Repository\TcgdexSetMappingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Fetches all set metadata from TCGdex and persists the mappings to the database.
 */
#[AsMessageHandler]
class BuildSetMappingsHandler
{
    private const BASE_URL = 'https://api.tcgdex.net/v2/en';

    /**
     * PTCG codes that don't match TCGdex's abbreviation.official or tcgOnline.
     */
    private const STATIC_OVERRIDES = [
        'PR-SV' => 'svp',
        'PR-SW' => 'swshp',
        'PR-SM' => 'smp',
        'PR-XY' => 'xyp',
        'PR-BW' => 'bwp',
        'SVI' => 'sv01',
    ];

    public function __construct(
        private readonly HttpClientInterface $tcgdexClient,
        private readonly TcgdexSetMappingRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(BuildSetMappingsMessage $message): void
    {
        $this->logger->info('Building TCGdex set mappings…');

        $listResponse = $this->tcgdexClient->request('GET', self::BASE_URL.'/sets');
        /** @var list<array{id: string}> $sets */
        $sets = $listResponse->toArray();

        /** @var array<string, ResponseInterface> $responses */
        $responses = [];

        foreach ($sets as $set) {
            $responses[$set['id']] = $this->tcgdexClient->request('GET', self::BASE_URL.'/sets/'.$set['id']);
        }

        // Build reverse mapping: TCGdex set ID → PTCG code
        // tcgOnline always wins over abbreviation.official
        $reverse = array_flip(self::STATIC_OVERRIDES);

        foreach ($responses as $setId => $response) {
            /** @var array<string, mixed> $detail */
            $detail = $response->toArray();

            /** @var array<string, mixed> $abbreviation */
            $abbreviation = isset($detail['abbreviation']) && \is_array($detail['abbreviation']) ? $detail['abbreviation'] : [];

            if (isset($abbreviation['official']) && \is_string($abbreviation['official']) && '' !== $abbreviation['official']) {
                if (!isset($reverse[$setId])) {
                    $reverse[$setId] = strtoupper($abbreviation['official']);
                }
            }

            if (isset($detail['tcgOnline']) && \is_string($detail['tcgOnline']) && '' !== $detail['tcgOnline']) {
                $reverse[$setId] = strtoupper($detail['tcgOnline']);
            }
        }

        // Replace all rows in a single transaction
        $this->repository->truncate();

        foreach ($reverse as $tcgdexSetId => $ptcgCode) {
            $this->entityManager->persist(new TcgdexSetMapping((string) $tcgdexSetId, $ptcgCode));
        }

        $this->entityManager->flush();

        $this->logger->info('TCGdex set mappings rebuilt: {count} entries.', [
            'count' => \count($reverse),
        ]);
    }
}
