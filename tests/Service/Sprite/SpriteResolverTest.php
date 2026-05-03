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

namespace App\Tests\Service\Sprite;

use App\Repository\PokemonSpriteMappingRepository;
use App\Service\Sprite\SpriteResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
class SpriteResolverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/sprite-resolver-test-'.uniqid('', true);
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testResolveReturnsCachedPathWhenFileExists(): void
    {
        // Pre-populate the cache.
        $cacheDir = $this->tempDir.'/var/cache/sprites';
        mkdir($cacheDir, 0o777, true);
        $cached = $cacheDir.'/pikachu.png';
        file_put_contents($cached, 'fake-png-bytes');

        $resolver = new SpriteResolver(
            $this->createStub(PokemonSpriteMappingRepository::class),
            new MockHttpClient(),
            $this->tempDir,
        );

        self::assertSame($cached, $resolver->resolve('pikachu'));
    }

    public function testResolveFetchesFromCdnFirstThenCachesLocally(): void
    {
        $client = new MockHttpClient([new MockResponse('cdn-png-bytes')]);

        $resolver = new SpriteResolver(
            $this->createStub(PokemonSpriteMappingRepository::class),
            $client,
            $this->tempDir,
            'https://cdn.example.com',
        );

        $path = $resolver->resolve('pikachu');

        self::assertNotNull($path);
        self::assertSame($this->tempDir.'/var/cache/sprites/pikachu.png', $path);
        self::assertSame('cdn-png-bytes', file_get_contents($path));
    }

    public function testResolveFallsBackToPokeapiWhenCdnFailsAndPokedexIdKnown(): void
    {
        // Two HTTP calls: CDN 404, PokeAPI 200.
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('pokeapi-png-bytes'),
        ]);

        $repository = $this->createStub(PokemonSpriteMappingRepository::class);
        $repository->method('findPokedexIdBySlug')->willReturn(25);

        $resolver = new SpriteResolver($repository, $client, $this->tempDir, 'https://cdn.example.com');

        $path = $resolver->resolve('pikachu');

        self::assertNotNull($path);
        self::assertSame('pokeapi-png-bytes', file_get_contents($path));
    }

    public function testResolveReturnsNullWhenCdnFailsAndPokedexIdMissing(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
        ]);

        $repository = $this->createStub(PokemonSpriteMappingRepository::class);
        $repository->method('findPokedexIdBySlug')->willReturn(null);

        $resolver = new SpriteResolver($repository, $client, $this->tempDir, 'https://cdn.example.com');

        self::assertNull($resolver->resolve('phantom-pokemon'));
    }

    public function testResolveSkipsCdnWhenNoCdnBaseUrlConfigured(): void
    {
        // No CDN URL → goes straight to PokeAPI; only one HTTP call expected.
        $client = new MockHttpClient([new MockResponse('pokeapi-png-bytes')]);

        $repository = $this->createStub(PokemonSpriteMappingRepository::class);
        $repository->method('findPokedexIdBySlug')->willReturn(25);

        $resolver = new SpriteResolver($repository, $client, $this->tempDir);

        $path = $resolver->resolve('pikachu');

        self::assertNotNull($path);
        self::assertSame('pokeapi-png-bytes', file_get_contents($path));
    }

    public function testResolveSwallowsHttpExceptionsAndReturnsNull(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new \RuntimeException('network down');
        });

        $repository = $this->createStub(PokemonSpriteMappingRepository::class);
        $repository->method('findPokedexIdBySlug')->willReturn(null);

        $resolver = new SpriteResolver($repository, $client, $this->tempDir, 'https://cdn.example.com');

        self::assertNull($resolver->resolve('pikachu'));
    }

    public function testResolveAsDataUriEncodesContentAsBase64Png(): void
    {
        $client = new MockHttpClient([new MockResponse('png-bytes-here')]);

        $resolver = new SpriteResolver(
            $this->createStub(PokemonSpriteMappingRepository::class),
            $client,
            $this->tempDir,
            'https://cdn.example.com',
        );

        $dataUri = $resolver->resolveAsDataUri('pikachu');

        self::assertSame('data:image/png;base64,'.base64_encode('png-bytes-here'), $dataUri);
    }

    public function testResolveAsDataUriReturnsNullWhenResolveFails(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['http_code' => 404])]);

        $repository = $this->createStub(PokemonSpriteMappingRepository::class);
        $repository->method('findPokedexIdBySlug')->willReturn(null);

        $resolver = new SpriteResolver($repository, $client, $this->tempDir, 'https://cdn.example.com');

        self::assertNull($resolver->resolveAsDataUri('phantom-pokemon'));
    }

    public function testIsCachedReturnsTrueOnlyWhenFilePresent(): void
    {
        $resolver = new SpriteResolver(
            $this->createStub(PokemonSpriteMappingRepository::class),
            new MockHttpClient(),
            $this->tempDir,
        );

        self::assertFalse($resolver->isCached('pikachu'));

        $cacheDir = $this->tempDir.'/var/cache/sprites';
        mkdir($cacheDir, 0o777, true);
        file_put_contents($cacheDir.'/pikachu.png', 'x');

        self::assertTrue($resolver->isCached('pikachu'));
    }

    public function testPokedexIdLookupIsMemoizedAcrossCalls(): void
    {
        // First call hits the repo, second uses the in-memory cache.
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('first'),
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('second'),
        ]);

        $repository = $this->createMock(PokemonSpriteMappingRepository::class);
        $repository->expects(self::once())->method('findPokedexIdBySlug')->with('pikachu')->willReturn(25);

        $resolver = new SpriteResolver($repository, $client, $this->tempDir, 'https://cdn.example.com');

        // Two calls to the same slug — repository should only be hit once.
        $resolver->resolve('pikachu');
        // Wipe the cached file so resolve has to fetch again, exercising the
        // pokedex-id memoization branch.
        unlink($this->tempDir.'/var/cache/sprites/pikachu.png');
        $resolver->resolve('pikachu');
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $full = $path.'/'.$entry;
            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}
