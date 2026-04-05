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

use App\Entity\TcgdexCard;
use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Import card data from the tcgdex/cards-database git repository into local tcgdex_* tables.
 *
 * Populates TcgdexSerie, TcgdexSet, and TcgdexCard tables, enabling local-first card resolution
 * without depending on the TCGdex API.
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
#[AsCommand(
    name: 'app:tcgdex:import',
    description: 'Import card data from the tcgdex/cards-database git repository.',
)]
class ImportTcgdexDatabaseCommand extends Command
{
    private const string DEFAULT_REPO_PATH = 'var/tcgdex-repo';
    private const string GIT_REPO_URL = 'https://github.com/tcgdex/cards-database.git';
    private const int BATCH_SIZE = 200;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('repo-path', null, InputOption::VALUE_REQUIRED, 'Path to the local tcgdex/cards-database clone.', self::DEFAULT_REPO_PATH)
            ->addOption('clone', null, InputOption::VALUE_NONE, 'Clone or pull the repository before importing.')
            ->addOption('truncate', null, InputOption::VALUE_NONE, 'Truncate all tcgdex_* tables before importing (full reload).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $repoPathOption */
        $repoPathOption = $input->getOption('repo-path');
        $repoPath = $this->resolveRepoPath($repoPathOption);
        $shouldClone = (bool) $input->getOption('clone');
        $shouldTruncate = (bool) $input->getOption('truncate');

        if ($shouldClone && !$this->cloneOrPullRepository($repoPath, $io)) {
            return Command::FAILURE;
        }

        if (!is_dir($repoPath.'/data')) {
            $io->error(\sprintf('Repository not found at %s. Use --clone to download it.', $repoPath));

            return Command::FAILURE;
        }

        if ($shouldTruncate) {
            $this->truncateTables($io);
        }

        $io->section('Extracting card data');

        $extractorPath = $this->projectDir.'/scripts/tcgdex-extract.ts';
        $process = new Process(['npx', 'tsx', $extractorPath, $repoPath]);
        $process->setTimeout(600);

        $counts = ['serie' => 0, 'set' => 0, 'card_created' => 0, 'card_skipped' => 0, 'errors' => 0];
        $batchCount = 0;
        $buffer = '';

        $process->start();

        foreach ($process as $type => $data) {
            if (Process::ERR === $type) {
                $io->text('<comment>'.$data.'</comment>');

                continue;
            }

            $buffer .= $data;
            $lines = explode("\n", $buffer);
            $buffer = (string) array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);

                if ('' === $line) {
                    continue;
                }

                /** @var array<string, mixed>|null $record */
                $record = json_decode($line, true);

                if (null === $record || !isset($record['type'])) {
                    ++$counts['errors'];

                    continue;
                }

                $recordType = $this->extractString($record, 'type') ?? '';

                match ($recordType) {
                    'serie' => $this->processSerie($record, $counts),
                    'set' => $this->processSet($record, $counts),
                    'card' => $this->processCard($record, $counts),
                    default => $counts['errors']++,
                };

                ++$batchCount;

                if (0 === $batchCount % self::BATCH_SIZE) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();

                    if ($output->isVerbose()) {
                        $io->text(\sprintf('Processed %d records...', $batchCount));
                    }
                }
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $process->wait();

        if (!$process->isSuccessful()) {
            $io->error('Extractor script failed: '.$process->getErrorOutput());

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Import complete: %d series, %d sets, %d cards created, %d cards skipped, %d errors.',
            $counts['serie'],
            $counts['set'],
            $counts['card_created'],
            $counts['card_skipped'],
            $counts['errors'],
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, int>   $counts
     */
    private function processSerie(array $record, array &$counts): void
    {
        $id = $this->extractString($record, 'id');

        if (null === $id || '' === $id) {
            ++$counts['errors'];

            return;
        }

        /** @var array<string, mixed> $serieName */
        $serieName = $this->extractArray($record, 'name');

        $existing = $this->entityManager->find(TcgdexSerie::class, $id);

        if (null !== $existing) {
            $existing->setName($serieName);

            return;
        }

        $serie = new TcgdexSerie($id);
        $serie->setName($serieName);

        $this->entityManager->persist($serie);
        ++$counts['serie'];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, int>   $counts
     */
    private function processSet(array $record, array &$counts): void
    {
        $id = $this->extractString($record, 'id');
        $serieId = $this->extractString($record, 'serieId');

        if (null === $id || '' === $id || null === $serieId || '' === $serieId) {
            ++$counts['errors'];

            return;
        }

        $existing = $this->entityManager->find(TcgdexSet::class, $id);

        if (null !== $existing) {
            $this->updateSet($existing, $record);

            return;
        }

        $serie = $this->entityManager->find(TcgdexSerie::class, $serieId);

        if (null === $serie) {
            ++$counts['errors'];

            return;
        }

        $set = new TcgdexSet($id, $serie);
        $this->updateSet($set, $record);

        $this->entityManager->persist($set);
        ++$counts['set'];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function updateSet(TcgdexSet $set, array $record): void
    {
        /** @var array<string, mixed> $setName */
        $setName = $this->extractArray($record, 'name');
        $set->setName($setName);
        $set->setPtcgCode($this->extractString($record, 'ptcgCode'));
        $set->setOfficialCardCount($this->extractInt($record, 'officialCardCount'));
        $set->setCardmarketId($this->extractInt($record, 'cardmarketId'));
        $set->setTcgplayerId($this->extractInt($record, 'tcgplayerId'));

        $releaseDate = $this->extractString($record, 'releaseDate');

        if (null !== $releaseDate) {
            try {
                $set->setReleaseDate(new \DateTimeImmutable($releaseDate));
            } catch (\Exception) {
                // Invalid date — leave null
            }
        }
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, int>   $counts
     */
    private function processCard(array $record, array &$counts): void
    {
        $id = $this->extractString($record, 'id');
        $setId = $this->extractString($record, 'setId');
        $localId = $this->extractString($record, 'localId');

        if (null === $id || '' === $id || null === $setId || null === $localId) {
            ++$counts['errors'];

            return;
        }

        $existing = $this->entityManager->find(TcgdexCard::class, $id);

        if (null !== $existing) {
            ++$counts['card_skipped'];

            return;
        }

        $set = $this->entityManager->find(TcgdexSet::class, $setId);

        if (null === $set) {
            ++$counts['errors'];

            return;
        }

        /** @var array<string, mixed> $cardName */
        $cardName = $this->extractArray($record, 'name');
        /** @var list<array<string, mixed>> $abilities */
        $abilities = $this->extractArray($record, 'abilities');
        /** @var list<array<string, mixed>> $attacks */
        $attacks = $this->extractArray($record, 'attacks');

        $card = new TcgdexCard($id, $set, $localId);
        $card->setName($cardName);
        $card->setCategory($this->extractString($record, 'category') ?? '');
        $card->setHp($this->extractInt($record, 'hp'));
        $card->setTrainerType($this->extractString($record, 'trainerType'));
        $card->setEnergyType($this->extractString($record, 'energyType'));
        $card->setRarity($this->extractString($record, 'rarity'));
        $card->setIsExpandedLegal((bool) ($record['isExpandedLegal'] ?? false));
        $card->setAbilities($abilities);
        $card->setAttacks($attacks);
        $card->setStage($this->extractString($record, 'stage'));
        $card->setRetreat($this->extractInt($record, 'retreat'));
        $card->setRegulationMark($this->extractString($record, 'regulationMark'));
        $card->setIllustrator($this->extractString($record, 'illustrator'));
        $card->setCardmarketProductId($this->extractInt($record, 'cardmarketProductId'));
        $card->setTcgplayerProductId($this->extractInt($record, 'tcgplayerProductId'));

        $effect = $record['effect'] ?? null;
        /** @var array<string, mixed>|null $effectArray */
        $effectArray = \is_array($effect) ? $effect : null;
        $card->setEffect($effectArray);

        $evolveFrom = $record['evolveFrom'] ?? null;
        /** @var array<string, mixed>|null $evolveFromArray */
        $evolveFromArray = \is_array($evolveFrom) ? $evolveFrom : null;
        $card->setEvolveFrom($evolveFromArray);

        $types = $record['types'] ?? [];
        /** @var list<string> $typesArray */
        $typesArray = \is_array($types) ? array_values(array_filter($types, 'is_string')) : [];
        $card->setTypes($typesArray);

        $this->entityManager->persist($card);
        ++$counts['card_created'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractString(array $data, string $key): ?string
    {
        return isset($data[$key]) && \is_string($data[$key]) ? $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractInt(array $data, string $key): ?int
    {
        return isset($data[$key]) && \is_int($data[$key]) ? $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string|int, mixed>
     */
    private function extractArray(array $data, string $key): array
    {
        return isset($data[$key]) && \is_array($data[$key]) ? $data[$key] : [];
    }

    private function resolveRepoPath(string $repoPath): string
    {
        if (str_starts_with($repoPath, '/')) {
            return $repoPath;
        }

        return $this->projectDir.'/'.$repoPath;
    }

    private function cloneOrPullRepository(string $repoPath, SymfonyStyle $io): bool
    {
        if (is_dir($repoPath.'/.git')) {
            $io->section('Updating existing repository');

            $process = new Process(['git', '-C', $repoPath, 'pull', '--ff-only']);
            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful()) {
                $io->warning('Git pull failed, continuing with existing checkout: '.$process->getErrorOutput());
            } else {
                $io->text('Repository updated.');
            }

            return true;
        }

        $io->section('Cloning tcgdex/cards-database');

        $process = new Process(['git', 'clone', '--depth', '1', self::GIT_REPO_URL, $repoPath]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            $io->error('Git clone failed: '.$process->getErrorOutput());

            return false;
        }

        $io->text('Repository cloned.');

        return true;
    }

    private function truncateTables(SymfonyStyle $io): void
    {
        $io->section('Truncating tcgdex_* tables');

        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('TRUNCATE TABLE tcgdex_card');
        $connection->executeStatement('TRUNCATE TABLE tcgdex_set');
        $connection->executeStatement('TRUNCATE TABLE tcgdex_serie');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $io->text('Tables truncated.');
    }
}
