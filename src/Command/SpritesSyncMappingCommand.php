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

use App\Service\Sprite\SpriteMappingSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sync the Pokemon sprite slug→dex mapping from PokeAPI's CSV data.
 *
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
#[AsCommand(
    name: 'app:sprites:sync-mapping',
    description: 'Sync the Pokemon sprite slug-to-dex mapping from PokeAPI CSV data.',
)]
class SpritesSyncMappingCommand extends Command
{
    public function __construct(
        private readonly SpriteMappingSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Fetching PokeAPI pokemon.csv');

        try {
            $result = $this->syncService->sync();
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Sprite mapping synced: %d inserted, %d updated, %d total entries.',
            $result['inserted'],
            $result['updated'],
            $result['total'],
        ));

        return Command::SUCCESS;
    }
}
