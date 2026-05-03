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

namespace App\Service\Sprite;

use App\Entity\PokemonSpriteMapping;
use App\Repository\PokemonSpriteMappingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Synchronizes the PokemonSpriteMapping table from PokeAPI's pokemon.csv.
 *
 * Fetches the CSV from GitHub, parses identifier→id pairs, and upserts rows.
 * Also adds alias entries for known slug naming mismatches between PokeAPI
 * and PokéSprite conventions.
 *
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
class SpriteMappingSyncService
{
    private const string POKEAPI_CSV_URL = 'https://raw.githubusercontent.com/PokeAPI/pokeapi/master/data/v2/csv/pokemon.csv';

    /**
     * Known slug aliases: PokéSprite slug → PokeAPI dex ID.
     *
     * PokeAPI uses game-internal identifiers (e.g. "calyrex-shadow") while
     * PokéSprite and this project use fan-friendly names (e.g. "calyrex-shadow-rider").
     */
    private const array SLUG_ALIASES = [
        'calyrex-shadow-rider' => 10194,
        'calyrex-ice-rider' => 10193,
    ];

    public function __construct(
        private readonly PokemonSpriteMappingRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array{inserted: int, updated: int, total: int}
     */
    public function sync(): array
    {
        $csvContent = $this->fetchCsv();
        $entries = $this->parseCsv($csvContent);

        // Add known aliases
        foreach (self::SLUG_ALIASES as $slug => $pokedexId) {
            $entries[$slug] = $pokedexId;
        }

        $existingMappings = $this->loadExistingMappings();

        $inserted = 0;
        $updated = 0;

        foreach ($entries as $slug => $pokedexId) {
            if (isset($existingMappings[$slug])) {
                if ($existingMappings[$slug]->getPokedexId() !== $pokedexId) {
                    $existingMappings[$slug]->setPokedexId($pokedexId);
                    ++$updated;
                }
            } else {
                $mapping = new PokemonSpriteMapping();
                $mapping->setSlug($slug);
                $mapping->setPokedexId($pokedexId);
                $this->entityManager->persist($mapping);
                ++$inserted;
            }
        }

        $this->entityManager->flush();

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'total' => \count($entries),
        ];
    }

    private function fetchCsv(): string
    {
        $response = $this->httpClient->request('GET', self::POKEAPI_CSV_URL, ['timeout' => 10]);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(\sprintf('Failed to fetch PokeAPI CSV: HTTP %d', $response->getStatusCode()));
        }

        return $response->getContent();
    }

    /**
     * Parse the CSV: columns are id, identifier, species_id, height, weight, base_experience, order, is_default.
     *
     * @return array<string, int> slug → pokedex ID
     */
    private function parseCsv(string $csvContent): array
    {
        $entries = [];
        $lines = explode("\n", $csvContent);

        foreach ($lines as $index => $line) {
            if (0 === $index || '' === trim($line)) {
                continue; // skip header and empty lines
            }

            $columns = str_getcsv($line, escape: '');
            if (\count($columns) < 2) {
                continue;
            }

            $pokedexId = (int) ($columns[0] ?? '0');
            $slug = (string) ($columns[1] ?? '');

            if ($pokedexId > 0 && '' !== $slug) {
                $entries[$slug] = $pokedexId;
            }
        }

        return $entries;
    }

    /**
     * @return array<string, PokemonSpriteMapping>
     */
    private function loadExistingMappings(): array
    {
        $mappings = [];

        foreach ($this->repository->findAll() as $mapping) {
            $mappings[$mapping->getSlug()] = $mapping;
        }

        return $mappings;
    }
}
