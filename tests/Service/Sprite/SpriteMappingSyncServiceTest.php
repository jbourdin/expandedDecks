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

use App\Entity\PokemonSpriteMapping;
use App\Repository\PokemonSpriteMappingRepository;
use App\Service\Sprite\SpriteMappingSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
class SpriteMappingSyncServiceTest extends TestCase
{
    public function testSyncInsertsNewMappingsAndAppliesAliases(): void
    {
        $csv = "id,identifier,species_id,height,weight,base_experience,order,is_default\n"
            ."1,bulbasaur,1,7,69,64,1,1\n"
            ."25,pikachu,25,4,60,112,32,1\n";

        $client = new MockHttpClient([new MockResponse($csv)]);

        $repository = $this->createStub(PokemonSpriteMappingRepository::class);
        $repository->method('findAll')->willReturn([]);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects(self::once())->method('flush');

        $service = new SpriteMappingSyncService($repository, $entityManager, $client);
        $result = $service->sync();

        // 2 from CSV + 2 SLUG_ALIASES (calyrex-shadow-rider, calyrex-ice-rider)
        self::assertSame(4, $result['inserted']);
        self::assertSame(0, $result['updated']);
        self::assertSame(4, $result['total']);
        self::assertCount(4, $persisted);

        $slugs = array_map(static fn (PokemonSpriteMapping $m): string => $m->getSlug(), $persisted);
        self::assertContains('bulbasaur', $slugs);
        self::assertContains('pikachu', $slugs);
        self::assertContains('calyrex-shadow-rider', $slugs);
        self::assertContains('calyrex-ice-rider', $slugs);
    }

    public function testSyncUpdatesPokedexIdWhenChanged(): void
    {
        $csv = "id,identifier\n25,pikachu\n";

        $existing = new PokemonSpriteMapping();
        $existing->setSlug('pikachu');
        $existing->setPokedexId(999); // wrong value, should be corrected to 25

        $repository = $this->createStub(PokemonSpriteMappingRepository::class);
        $repository->method('findAll')->willReturn([$existing]);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects(self::once())->method('flush');

        $service = new SpriteMappingSyncService(
            $repository,
            $entityManager,
            new MockHttpClient([new MockResponse($csv)]),
        );

        $result = $service->sync();

        // pikachu updates the existing row; the two calyrex aliases are new inserts.
        self::assertSame(1, $result['updated']);
        self::assertSame(2, $result['inserted']);
        self::assertSame(25, $existing->getPokedexId());
        self::assertCount(2, $persisted);
    }

    public function testSyncSkipsRowsWithMatchingPokedexId(): void
    {
        $csv = "id,identifier\n25,pikachu\n";

        $existing = new PokemonSpriteMapping();
        $existing->setSlug('pikachu');
        $existing->setPokedexId(25); // already matches CSV

        $repository = $this->createStub(PokemonSpriteMappingRepository::class);
        $repository->method('findAll')->willReturn([$existing]);

        $service = new SpriteMappingSyncService(
            $repository,
            $this->createStub(EntityManagerInterface::class),
            new MockHttpClient([new MockResponse($csv)]),
        );

        $result = $service->sync();

        self::assertSame(0, $result['updated']);
    }

    public function testSyncThrowsOnNon200CsvFetch(): void
    {
        $service = new SpriteMappingSyncService(
            $this->createStub(PokemonSpriteMappingRepository::class),
            $this->createStub(EntityManagerInterface::class),
            new MockHttpClient([new MockResponse('', ['http_code' => 500])]),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');

        $service->sync();
    }

    public function testParseCsvSkipsEmptyAndMalformedLines(): void
    {
        // Header + empty line + valid + 1-column malformed + zero pokedex id + valid.
        $csv = "id,identifier\n"
            ."\n"
            ."1,bulbasaur\n"
            ."only_one_column\n"
            ."0,zero_dex\n"
            ."25,pikachu\n";

        $client = new MockHttpClient([new MockResponse($csv)]);

        $repository = $this->createStub(PokemonSpriteMappingRepository::class);
        $repository->method('findAll')->willReturn([]);

        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $service = new SpriteMappingSyncService($repository, $entityManager, $client);
        $result = $service->sync();

        // Only bulbasaur + pikachu pass parsing + 2 alias inserts.
        $slugs = array_map(static fn (PokemonSpriteMapping $m): string => $m->getSlug(), $persisted);
        self::assertContains('bulbasaur', $slugs);
        self::assertContains('pikachu', $slugs);
        self::assertNotContains('only_one_column', $slugs);
        self::assertNotContains('zero_dex', $slugs);
        self::assertSame(4, $result['inserted']);
    }
}
