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

namespace App\Tests\Service\Tcgdex;

use App\Entity\TcgdexCard;
use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use App\Service\Tcgdex\TcgdexCardHydrator;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
final class TcgdexCardHydratorTest extends TestCase
{
    private TcgdexCardHydrator $hydrator;
    private TcgdexSet $set;

    protected function setUp(): void
    {
        $this->hydrator = new TcgdexCardHydrator();
        $this->set = new TcgdexSet('sv05', new TcgdexSerie('sv'));
    }

    /** @return array<string, mixed> */
    private function createApiCardData(): array
    {
        return [
            'id' => 'sv05-001',
            'localId' => '001',
            'name' => 'Exeggcute',
            'category' => 'Pokemon',
            'hp' => 50,
            'rarity' => 'Common',
            'stage' => 'Basic',
            'types' => ['Grass'],
            'retreat' => 1,
            'regulationMark' => 'G',
            'illustrator' => 'Mitsuhiro Arita',
            'image' => 'https://assets.tcgdex.net/en/sv/sv05/001',
            'legal' => ['expanded' => true, 'standard' => true],
            'abilities' => [
                ['name' => 'Propagation', 'effect' => 'Put this card from discard to hand.', 'type' => 'Ability'],
            ],
            'attacks' => [
                ['name' => 'Seed Bomb', 'effect' => '', 'damage' => 20, 'cost' => ['Grass']],
            ],
            'effect' => null,
            'evolveFrom' => null,
            'trainerType' => null,
            'energyType' => null,
        ];
    }

    public function testHydrateFromApiResponseSetsAllFields(): void
    {
        $card = $this->hydrator->hydrateFromApiResponse($this->createApiCardData(), $this->set);

        self::assertSame('sv05-001', $card->getId());
        self::assertSame('001', $card->getLocalId());
        self::assertSame(['en' => 'Exeggcute'], $card->getName());
        self::assertSame('Pokemon', $card->getCategory());
        self::assertSame(50, $card->getHp());
        self::assertSame('Common', $card->getRarity());
        self::assertSame('Basic', $card->getStage());
        self::assertSame(['Grass'], $card->getTypes());
        self::assertSame(1, $card->getRetreat());
        self::assertSame('G', $card->getRegulationMark());
        self::assertSame('Mitsuhiro Arita', $card->getIllustrator());
        self::assertSame('https://assets.tcgdex.net/en/sv/sv05/001', $card->getImageBaseUrl());
        self::assertTrue($card->isExpandedLegal());
    }

    public function testHydrateFromApiResponseWrapsAbilitiesInMultilingualFormat(): void
    {
        $card = $this->hydrator->hydrateFromApiResponse($this->createApiCardData(), $this->set);

        $abilities = $card->getAbilities();
        self::assertCount(1, $abilities);
        self::assertSame(['en' => 'Propagation'], $abilities[0]['name']);
        self::assertSame(['en' => 'Put this card from discard to hand.'], $abilities[0]['effect']);
        self::assertSame('Ability', $abilities[0]['type']);
    }

    public function testHydrateFromApiResponseWrapsAttacksInMultilingualFormat(): void
    {
        $card = $this->hydrator->hydrateFromApiResponse($this->createApiCardData(), $this->set);

        $attacks = $card->getAttacks();
        self::assertCount(1, $attacks);
        self::assertSame(['en' => 'Seed Bomb'], $attacks[0]['name']);
        self::assertSame(['Grass'], $attacks[0]['cost']);
        self::assertSame(20, $attacks[0]['damage']);
    }

    public function testHydrateFromApiResponseThrowsOnMissingId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->hydrator->hydrateFromApiResponse(['localId' => '001'], $this->set);
    }

    public function testHydrateFromApiResponseThrowsOnMissingLocalId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->hydrator->hydrateFromApiResponse(['id' => 'sv05-001'], $this->set);
    }

    public function testUpdateFromApiResponseUpdatesExistingCard(): void
    {
        $card = new TcgdexCard('sv05-001', $this->set, '001');
        $card->setName(['en' => 'Old Name']);
        $card->setCategory('Pokemon');
        $card->setImageBaseUrl(null);

        $this->hydrator->updateFromApiResponse($card, $this->createApiCardData());

        self::assertSame(['en' => 'Exeggcute'], $card->getName());
        self::assertSame('https://assets.tcgdex.net/en/sv/sv05/001', $card->getImageBaseUrl());
        self::assertSame(50, $card->getHp());
        self::assertTrue($card->isExpandedLegal());
    }

    public function testHydrateFromNdjsonRecordSetsAllFields(): void
    {
        $record = [
            'id' => 'sv05-001',
            'localId' => '001',
            'name' => ['en' => 'Exeggcute', 'fr' => 'Noeunoeuf'],
            'category' => 'Pokemon',
            'hp' => 50,
            'rarity' => 'Common',
            'stage' => 'Basic',
            'types' => ['Grass'],
            'retreat' => 1,
            'regulationMark' => 'G',
            'illustrator' => 'Mitsuhiro Arita',
            'isExpandedLegal' => true,
            'abilities' => [
                ['name' => ['en' => 'Propagation', 'fr' => 'Propagation'], 'effect' => ['en' => 'Do stuff.'], 'type' => 'Ability'],
            ],
            'attacks' => [],
        ];

        $card = $this->hydrator->hydrateFromNdjsonRecord($record, $this->set);

        self::assertSame('sv05-001', $card->getId());
        self::assertSame(['en' => 'Exeggcute', 'fr' => 'Noeunoeuf'], $card->getName());
        self::assertSame('Pokemon', $card->getCategory());
        self::assertSame(50, $card->getHp());
        self::assertTrue($card->isExpandedLegal());
        self::assertNull($card->getImageBaseUrl()); // NDJSON doesn't set imageBaseUrl
    }

    public function testHydrateFromNdjsonRecordThrowsOnMissingId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->hydrator->hydrateFromNdjsonRecord(['localId' => '001'], $this->set);
    }

    public function testGetImageUrlPrefersImageBaseUrl(): void
    {
        $card = new TcgdexCard('sv05-001', $this->set, '001');
        $card->setImageBaseUrl('https://assets.tcgdex.net/en/sv/sv05/001');

        self::assertSame('https://assets.tcgdex.net/en/sv/sv05/001/high.webp', $card->getImageUrl());
    }

    public function testGetImageUrlFallsBackToComputedUrl(): void
    {
        $card = new TcgdexCard('sv05-001', $this->set, '001');

        self::assertSame('https://assets.tcgdex.net/en/sv/sv05/001/high.webp', $card->getImageUrl());
    }
}
