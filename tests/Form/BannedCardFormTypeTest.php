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

namespace App\Tests\Form;

use App\Entity\BannedCard;
use App\Form\BannedCardFormType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * @see docs/features.md F6.14 — Banned cards public page
 */
#[AllowMockObjectsWithoutExpectations]
class BannedCardFormTypeTest extends TypeTestCase
{
    public function testFormBindsToBannedCardClass(): void
    {
        $form = $this->factory->create(BannedCardFormType::class);

        self::assertSame(BannedCard::class, $form->getConfig()->getDataClass());
    }

    public function testFormExposesAllExpectedFields(): void
    {
        $form = $this->factory->create(BannedCardFormType::class);

        self::assertTrue($form->has('cardName'));
        self::assertTrue($form->has('effectiveDate'));
        self::assertTrue($form->has('sourceUrl'));
        self::assertTrue($form->has('explanation'));
    }

    public function testSubmittingValidPayloadMapsToEntity(): void
    {
        $card = new BannedCard();
        $form = $this->factory->create(BannedCardFormType::class, $card);

        $form->submit([
            'cardName' => 'Pikachu',
            'effectiveDate' => '2024-04-01',
            'sourceUrl' => 'https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list',
            'explanation' => 'Some rationale',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('Pikachu', $card->getCardName());
        self::assertInstanceOf(\DateTimeImmutable::class, $card->getEffectiveDate());
        self::assertSame('2024-04-01', $card->getEffectiveDate()->format('Y-m-d'));
        self::assertSame('https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list', $card->getSourceUrl());
        self::assertSame('Some rationale', $card->getExplanation());
    }

    public function testSubmittingWithEmptyOptionalFieldsClearsThem(): void
    {
        $card = new BannedCard();
        $card->setSourceUrl('https://existing.example.com');
        $card->setExplanation('previous');

        $form = $this->factory->create(BannedCardFormType::class, $card);
        $form->submit([
            'cardName' => 'Pikachu',
            'effectiveDate' => '',
            'sourceUrl' => '',
            'explanation' => '',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertNull($card->getEffectiveDate());
        self::assertNull($card->getSourceUrl());
        self::assertNull($card->getExplanation());
    }
}
