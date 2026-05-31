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

use App\Enum\SyncMode;
use App\Message\SyncTcgdexSeriesMessage;
use App\Service\Tcgdex\TcgdexSyncStatusService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Trigger an incremental gap-fill sync of the TCGdex database via the API.
 *
 * Catalogue-wide syncs always run in gap-fill mode: missing series/sets/cards are
 * created and existing cards acquire any locale they still lack. A targeted
 * force-update of a single set is exposed through the admin dashboard instead.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
 */
#[AsCommand(
    name: 'app:tcgdex:sync',
    description: 'Trigger an incremental sync of the TCGdex database via the API.',
)]
class SyncTcgdexCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly TcgdexSyncStatusService $syncStatus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $queueDepth = $this->syncStatus->getQueueDepth();

        if ($queueDepth > 0) {
            $io->warning(\sprintf('A sync is already in progress (%d messages pending). Dispatching anyway.', $queueDepth));
        }

        $this->messageBus->dispatch(new SyncTcgdexSeriesMessage(SyncMode::Sync));

        $io->success('TCGdex gap-fill sync dispatched. Run a worker to process: make worker.sync');

        $lastSync = $this->syncStatus->getLastSyncTimestamp();

        if (null !== $lastSync) {
            $io->text(\sprintf('Last completed sync: %s', $lastSync->format('Y-m-d H:i:s')));
        }

        return Command::SUCCESS;
    }
}
