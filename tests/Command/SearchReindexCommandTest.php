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

use App\Command\SearchReindexCommand;
use App\Service\Search\SearchIndexer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
 */
class SearchReindexCommandTest extends TestCase
{
    public function testReindexSucceedsWhenMeiliSearchIsHealthy(): void
    {
        $indexer = $this->createStub(SearchIndexer::class);
        $indexer->method('isHealthy')->willReturn(true);
        $indexer->method('reindexAll')->willReturn([
            'archetypes' => 10,
            'pages' => 4,
            'events' => 6,
            'decks' => 20,
        ]);

        $tester = $this->createCommandTester($indexer);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('40 documents', $tester->getDisplay());
        self::assertStringContainsString('4 indexes', $tester->getDisplay());
    }

    public function testReindexSkipsWhenMeiliSearchIsUnreachable(): void
    {
        $indexer = $this->createStub(SearchIndexer::class);
        $indexer->method('isHealthy')->willReturn(false);

        $tester = $this->createCommandTester($indexer);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('not reachable', $tester->getDisplay());
    }

    private function createCommandTester(SearchIndexer $indexer): CommandTester
    {
        return new CommandTester(new SearchReindexCommand($indexer));
    }
}
