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

namespace App\Tests\Command;

use App\Command\SpritesSyncMappingCommand;
use App\Entity\PokemonSpriteMapping;
use App\Repository\PokemonSpriteMappingRepository;
use App\Service\Sprite\SpriteMappingSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
class SpritesSyncMappingCommandTest extends TestCase
{
    public function testCommandReportsCountsOnSuccess(): void
    {
        $csv = "id,identifier\n25,pikachu\n";

        $repository = $this->createStub(PokemonSpriteMappingRepository::class);
        $repository->method('findAll')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new SpriteMappingSyncService(
            $repository,
            $entityManager,
            new MockHttpClient([new MockResponse($csv)]),
        );

        $tester = new CommandTester(new SpritesSyncMappingCommand($service));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        // 1 CSV row + 2 SLUG_ALIASES = 3 inserted.
        self::assertStringContainsString('3 inserted', $display);
        self::assertStringContainsString('0 updated', $display);
        self::assertStringContainsString('3 total', $display);
    }

    public function testCommandReturnsFailureWhenSyncThrows(): void
    {
        $repository = $this->createStub(PokemonSpriteMappingRepository::class);
        $repository->method('findAll')->willReturn([new PokemonSpriteMapping()]);

        $service = new SpriteMappingSyncService(
            $repository,
            $this->createStub(EntityManagerInterface::class),
            new MockHttpClient([new MockResponse('', ['http_code' => 500])]),
        );

        $tester = new CommandTester(new SpritesSyncMappingCommand($service));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('HTTP 500', $tester->getDisplay());
    }
}
