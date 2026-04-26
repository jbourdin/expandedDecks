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

use App\Repository\PokemonSpriteMappingRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves Pokemon sprites: slug → filesystem path, with CDN fetch on cache miss.
 *
 * Sprites are cached in var/cache/sprites/ for fast access by PDF generators
 * and the sprite proxy controller. On a cache miss (e.g. new container), the
 * sprite is fetched from our own CDN URL (Bunny edge cache), which in turn
 * may fetch from PokeAPI's GitHub raw URL on its first request.
 *
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
class SpriteResolver
{
    private const string POKEAPI_HOME_URL = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/home/%d.png';

    private string $cacheDirectory;

    /** @var array<string, ?int> in-memory slug→dex cache */
    private array $dexCache = [];

    public function __construct(
        private readonly PokemonSpriteMappingRepository $mappingRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectDir,
        private readonly ?string $spriteCdnBaseUrl = null,
    ) {
        $this->cacheDirectory = $this->projectDir.'/var/cache/sprites';
    }

    /**
     * Get the local filesystem path for a sprite, fetching it if needed.
     *
     * Returns null if the slug is not mapped or the fetch fails.
     */
    public function resolve(string $slug): ?string
    {
        $path = $this->getCachePath($slug);

        if (file_exists($path)) {
            return $path;
        }

        return $this->fetchAndCache($slug);
    }

    /**
     * Get the sprite image content as a base64 data URI for PDF embedding.
     */
    public function resolveAsDataUri(string $slug): ?string
    {
        $path = $this->resolve($slug);

        if (null === $path) {
            return null;
        }

        $content = file_get_contents($path);

        if (false === $content) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($content);
    }

    /**
     * Check if a sprite exists in the filesystem cache without fetching.
     */
    public function isCached(string $slug): bool
    {
        return file_exists($this->getCachePath($slug));
    }

    private function getCachePath(string $slug): string
    {
        return $this->cacheDirectory.'/'.$slug.'.png';
    }

    private function fetchAndCache(string $slug): ?string
    {
        $content = $this->fetchSpriteContent($slug);

        if (null === $content) {
            return null;
        }

        if (!is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0o777, true);
        }

        $path = $this->getCachePath($slug);
        file_put_contents($path, $content);

        return $path;
    }

    private function fetchSpriteContent(string $slug): ?string
    {
        // Try our own CDN first (fast, cached at edge)
        if (null !== $this->spriteCdnBaseUrl) {
            $content = $this->fetchUrl($this->spriteCdnBaseUrl.'/sprites/pokemon/'.$slug.'.png');
            if (null !== $content) {
                return $content;
            }
        }

        // Fall back to PokeAPI directly
        $pokedexId = $this->resolvePokedexId($slug);
        if (null === $pokedexId) {
            return null;
        }

        return $this->fetchUrl(\sprintf(self::POKEAPI_HOME_URL, $pokedexId));
    }

    private function resolvePokedexId(string $slug): ?int
    {
        if (\array_key_exists($slug, $this->dexCache)) {
            return $this->dexCache[$slug];
        }

        $this->dexCache[$slug] = $this->mappingRepository->findPokedexIdBySlug($slug);

        return $this->dexCache[$slug];
    }

    private function fetchUrl(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 5]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            return $response->getContent();
        } catch (\Throwable) {
            return null;
        }
    }
}
