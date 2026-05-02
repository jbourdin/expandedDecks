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

use App\Service\BannedCardEnricher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Backfills CardPrinting + CardIdentity links on every active BannedCard via
 * TCGdex (local mirror first, HTTP fallback). Required after the F6.14 schema
 * migration so the public list can group rows by functional card identity.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
#[AsCommand(
    name: 'app:banned-cards:enrich',
    description: 'Resolve CardPrinting + CardIdentity for every active banned card via TCGdex.',
)]
class BannedCardsEnrichCommand extends Command
{
    public function __construct(
        private readonly BannedCardEnricher $cardEnricher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Re-enrich rows that already have a CardPrinting (drops the link first).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        [$linked, $unresolved] = $this->cardEnricher->enrichAllActive($force);
        $total = $linked + \count($unresolved);

        $io->title(\sprintf('Enriched %d active banned card(s)', $total));
        $io->success(\sprintf('Linked %d / %d banned card(s).', $linked, $total));

        if ([] !== $unresolved) {
            $io->warning(\sprintf('Could not resolve %d card(s) via TCGdex:', \count($unresolved)));
            $io->listing($unresolved);
        }

        return Command::SUCCESS;
    }
}
