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

use App\Message\EnrichDeckVersionMessage;
use App\Repository\DeckVersionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Redispatches enrichment messages for deck versions that are still pending or failed.
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
#[AsCommand(
    name: 'app:enrich:retry',
    description: 'Redispatch enrichment for deck versions that are pending or failed.',
)]
class EnrichRetryCommand extends Command
{
    public function __construct(
        private readonly DeckVersionRepository $versionRepo,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $versions = $this->versionRepo->findNotEnriched();

        if ([] === $versions) {
            $io->success('No deck versions need enrichment.');

            return Command::SUCCESS;
        }

        foreach ($versions as $version) {
            /** @var int $id */
            $id = $version->getId();
            $this->messageBus->dispatch(new EnrichDeckVersionMessage($id));

            $io->text(\sprintf(
                'Dispatched enrichment for DeckVersion #%d (deck "%s", v%d, status: %s).',
                $id,
                $version->getDeck()->getName(),
                $version->getVersionNumber(),
                $version->getEnrichmentStatus(),
            ));
        }

        $io->success(\sprintf('%d enrichment message(s) dispatched.', \count($versions)));

        return Command::SUCCESS;
    }
}
