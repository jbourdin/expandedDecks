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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Trigger an incremental sync of the TCGdex database via the API.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
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
        $this
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Sync mode: insert (default), update, or full.', 'insert')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Required for full mode (re-fetches every card).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $modeValue */
        $modeValue = $input->getOption('mode');
        $mode = SyncMode::tryFrom($modeValue);

        if (null === $mode) {
            $io->error(\sprintf('Invalid sync mode "%s". Valid modes: insert, update, full.', $modeValue));

            return Command::INVALID;
        }

        if (SyncMode::Full === $mode && !$input->getOption('force')) {
            $io->error('Full mode re-fetches every card from the API. Pass --force to confirm.');

            return Command::INVALID;
        }

        $queueDepth = $this->syncStatus->getQueueDepth();

        if ($queueDepth > 0) {
            $io->warning(\sprintf('A sync is already in progress (%d messages pending). Dispatching anyway.', $queueDepth));
        }

        $this->messageBus->dispatch(new SyncTcgdexSeriesMessage($mode));

        $io->success(\sprintf('TCGdex sync dispatched in "%s" mode. Run a worker to process: make worker.sync', $mode->value));

        $lastSync = $this->syncStatus->getLastSyncTimestamp();

        if (null !== $lastSync) {
            $io->text(\sprintf('Last completed sync: %s', $lastSync->format('Y-m-d H:i:s')));
        }

        return Command::SUCCESS;
    }
}
