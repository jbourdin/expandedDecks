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

use App\Service\BannedCardsSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI wrapper for the banned cards sync service.
 *
 * @see docs/features.md F6.5 — Banned card list management
 */
#[AsCommand(
    name: 'app:banned-cards:sync',
    description: 'Sync the Expanded banned card list from the official Pokemon website.',
)]
class BannedCardsSyncCommand extends Command
{
    public function __construct(
        private readonly BannedCardsSyncService $bannedCardsSyncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Fetching banned card list from pokemon.com');

        $result = $this->bannedCardsSyncService->sync();

        foreach ($result->warnings as $warning) {
            $io->warning($warning);
        }

        if (!$result->success) {
            $io->error($result->error ?? 'Sync failed.');

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Sync complete: %d added, %d removed, %d unchanged.',
            $result->added,
            $result->removed,
            $result->unchanged,
        ));

        return Command::SUCCESS;
    }
}
