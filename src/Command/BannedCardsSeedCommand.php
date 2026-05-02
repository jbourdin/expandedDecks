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

use App\Service\BannedCardSeedData;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Backfills historical effective dates, official-announcement URLs and short
 * explanations for every banned card known at the time of writing. Idempotent
 * — only fills fields that are still null, so admin edits are preserved.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
#[AsCommand(
    name: 'app:banned-cards:seed',
    description: 'Apply default ban metadata (effective date, source, explanation) to every active banned card.',
)]
class BannedCardsSeedCommand extends Command
{
    public function __construct(
        private readonly BannedCardSeedData $seedData,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        [$filled, $skipped] = $this->seedData->applyAll();

        $io->success(\sprintf('Seed applied: %d card(s) updated, %d skipped (already had data or no seed available).', $filled, $skipped));

        return Command::SUCCESS;
    }
}
