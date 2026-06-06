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

namespace App\Tests\Command;

use App\Command\GenerateCardFanOgImageCommand;
use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Entity\Deck;
use App\Repository\DeckRepository;
use App\Service\CardIdentity\CardCodeResolver;
use App\Service\OgImage\CardFanImageGenerator;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see docs/features.md F18.32 — Card-fan OG image builder
 */
final class GenerateCardFanOgImageCommandTest extends TestCase
{
    public function testGeneratesImageAndAssignsDeckOgImage(): void
    {
        $deck = new Deck();
        $deck->setName('Regidrago');

        $deckRepository = $this->createStub(DeckRepository::class);
        $deckRepository->method('findOneBy')->willReturn($deck);

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects(self::once())
            ->method('write')
            ->with(
                self::matchesRegularExpression('/^[a-f0-9]{32}\.png$/'),
                'png-bytes',
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $tester = new CommandTester($this->buildCommand(
            resolvesTo: $this->createPrinting('Regidrago VSTAR'),
            imageData: 'png-bytes',
            storage: $storage,
            deckRepository: $deckRepository,
            entityManager: $entityManager,
        ));
        $tester->execute(['codes' => ['SIT-136', 'UPR-100'], '--deck' => 'Regidrago']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('ogImage updated', $tester->getDisplay());
        self::assertSame('/api/editor/image/stub.png', $deck->getOgImage());
    }

    public function testSkipsUnresolvedCodesButStillGenerates(): void
    {
        $resolver = $this->createStub(CardCodeResolver::class);
        $resolver->method('resolve')->willReturnCallback(
            fn (string $code): ?CardPrinting => 'SIT-136' === $code ? $this->createPrinting('Regidrago VSTAR') : null,
        );

        $tester = new CommandTester($this->buildCommand(resolver: $resolver));
        $tester->execute(['codes' => ['SIT-136', 'XXX-999']]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('Could not resolve "XXX-999"', $display);
        self::assertStringContainsString('Card fan generated from 1 card(s)', $display);
    }

    public function testFailsWhenNoCodeResolves(): void
    {
        $tester = new CommandTester($this->buildCommand(resolvesTo: null));
        $tester->execute(['codes' => ['XXX-999']]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('No card code could be resolved.', $tester->getDisplay());
    }

    public function testFailsWhenDeckNotFound(): void
    {
        $deckRepository = $this->createStub(DeckRepository::class);
        $deckRepository->method('findOneBy')->willReturn(null);

        $tester = new CommandTester($this->buildCommand(
            resolvesTo: $this->createPrinting('Regidrago VSTAR'),
            deckRepository: $deckRepository,
        ));
        $tester->execute(['codes' => ['SIT-136'], '--deck' => 'Unknown Deck']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Deck "Unknown Deck" not found.', $tester->getDisplay());
    }

    private function buildCommand(
        ?CardPrinting $resolvesTo = null,
        string $imageData = 'png-bytes',
        ?FilesystemOperator $storage = null,
        ?DeckRepository $deckRepository = null,
        ?EntityManagerInterface $entityManager = null,
        ?CardCodeResolver $resolver = null,
    ): GenerateCardFanOgImageCommand {
        if (!$resolver instanceof CardCodeResolver) {
            $resolver = $this->createStub(CardCodeResolver::class);
            $resolver->method('resolve')->willReturn($resolvesTo);
        }

        $generator = $this->createStub(CardFanImageGenerator::class);
        $generator->method('generate')->willReturn($imageData);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/api/editor/image/stub.png');

        return new GenerateCardFanOgImageCommand(
            $resolver,
            $generator,
            $storage ?? $this->createStub(FilesystemOperator::class),
            $deckRepository ?? $this->createStub(DeckRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $urlGenerator,
        );
    }

    private function createPrinting(string $cardName): CardPrinting
    {
        $identity = new CardIdentity();
        $identity->setName($cardName);

        $printing = new CardPrinting();
        $printing->setCardIdentity($identity);

        return $printing;
    }
}
