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

namespace App\Command;

use App\Service\Search\SearchIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rebuilds all MeiliSearch indexes from the database.
 *
 * Run on every cold start (via docker-entrypoint.sh) to rebuild the
 * ephemeral search index, or manually when data needs a full re-sync.
 *
 * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
 */
#[AsCommand(
    name: 'app:search:reindex',
    description: 'Rebuild all MeiliSearch indexes from the database.',
)]
class SearchReindexCommand extends Command
{
    public function __construct(
        private readonly SearchIndexer $searchIndexer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->searchIndexer->isHealthy()) {
            $io->warning('MeiliSearch is not reachable — skipping reindex.');

            return Command::SUCCESS;
        }

        $io->title('Rebuilding MeiliSearch indexes');

        $counts = $this->searchIndexer->reindexAll();

        $io->table(
            ['Index', 'Documents'],
            array_map(
                static fn (string $index, int $count): array => [$index, (string) $count],
                array_keys($counts),
                array_values($counts),
            ),
        );

        $total = array_sum($counts);
        $io->success(\sprintf('Indexed %d documents across %d indexes.', $total, \count($counts)));

        return Command::SUCCESS;
    }
}
