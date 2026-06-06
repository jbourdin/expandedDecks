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

use App\Entity\CardPrinting;
use App\Repository\DeckRepository;
use App\Service\CardIdentity\CardCodeResolver;
use App\Service\OgImage\CardFanImageGenerator;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generates a card-fan OG image from card codes, like the admin OG image
 * builder does, and optionally assigns it as a deck's ogImage.
 *
 * Used by the fixtures pipeline (`make fixtures`) to give a Regidrago variant
 * a realistic card-fan OG image in dev. The output filename is derived from
 * the codes, so repeated runs overwrite the same file instead of piling up.
 *
 * @see docs/features.md F18.32 — Card-fan OG image builder
 * @see docs/technicalities/og_image_builder.md
 */
#[AsCommand(
    name: 'app:og-image:card-fan',
    description: 'Generate a card-fan OG image from card codes, optionally assigning it to a deck.',
)]
class GenerateCardFanOgImageCommand extends Command
{
    public function __construct(
        private readonly CardCodeResolver $cardCodeResolver,
        private readonly CardFanImageGenerator $cardFanImageGenerator,
        private readonly FilesystemOperator $editorUploadStorage,
        private readonly DeckRepository $deckRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('codes', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Card codes (e.g. SIT-136 UPR-100 SFA-47)')
            ->addOption('deck', null, InputOption::VALUE_REQUIRED, 'Deck name whose ogImage should point at the generated file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<string> $codes */
        $codes = $input->getArgument('codes');

        $printings = [];
        foreach ($codes as $code) {
            $printing = $this->cardCodeResolver->resolve($code);

            if ($printing instanceof CardPrinting) {
                $printings[] = $printing;
                $io->text(\sprintf('Resolved %s — %s', $code, $printing->getCardIdentity()->getName()));
            } else {
                $io->warning(\sprintf('Could not resolve "%s" — skipping.', $code));
            }
        }

        if ([] === $printings) {
            $io->error('No card code could be resolved.');

            return Command::FAILURE;
        }

        $imageData = $this->cardFanImageGenerator->generate($printings);

        // Deterministic filename so re-running (e.g. on every `make fixtures`)
        // overwrites the previous file instead of accumulating orphans. The
        // md5 hex digest matches the serving route's filename requirements.
        $filename = md5(implode('|', $codes)).'.png';
        $this->editorUploadStorage->write($filename, $imageData);

        $url = $this->urlGenerator->generate('app_editor_image_show', ['filename' => $filename]);
        $io->text(\sprintf('Card fan written to %s', $url));

        $deckName = $input->getOption('deck');
        if (\is_string($deckName) && '' !== $deckName) {
            $deck = $this->deckRepository->findOneBy(['name' => $deckName]);

            if (null === $deck) {
                $io->error(\sprintf('Deck "%s" not found.', $deckName));

                return Command::FAILURE;
            }

            $deck->setOgImage($url);
            $this->entityManager->flush();
            $io->text(\sprintf('Deck "%s" ogImage updated.', $deckName));
        }

        $io->success(\sprintf('Card fan generated from %d card(s): %s', \count($printings), $url));

        return Command::SUCCESS;
    }
}
