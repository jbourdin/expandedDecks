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

namespace App\Tests\Service\CardIdentity;

use App\Entity\CardPrinting;
use App\Service\CardIdentity\CardCodeResolver;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\Tcgdex\TcgdexApiClient;
use App\Service\Tcgdex\TcgdexCard;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F18.32 — Card-fan OG image builder
 */
final class CardCodeResolverTest extends TestCase
{
    public function testParseCodeAcceptsDashSeparator(): void
    {
        self::assertSame(['LOR', '093'], CardCodeResolver::parseCode('LOR-093'));
    }

    public function testParseCodeAcceptsSpaceSeparator(): void
    {
        self::assertSame(['LOR', '093'], CardCodeResolver::parseCode('LOR 093'));
    }

    public function testParseCodeAcceptsUnderscoreSeparator(): void
    {
        self::assertSame(['LOR', '093'], CardCodeResolver::parseCode('LOR_093'));
    }

    public function testParseCodeUppercasesSetCode(): void
    {
        self::assertSame(['LOR', '093'], CardCodeResolver::parseCode('lor-093'));
    }

    public function testParseCodeTrimsSurroundingWhitespace(): void
    {
        self::assertSame(['LOR', '093'], CardCodeResolver::parseCode('  LOR-093  '));
    }

    public function testParseCodeRejectsMissingSeparator(): void
    {
        self::assertNull(CardCodeResolver::parseCode('LOR093'));
    }

    public function testParseCodeRejectsEmptyString(): void
    {
        self::assertNull(CardCodeResolver::parseCode(''));
    }

    public function testParseCodeRejectsExtraSegments(): void
    {
        self::assertNull(CardCodeResolver::parseCode('LOR-093-extra'));
    }

    public function testResolveReturnsPrintingForKnownCode(): void
    {
        $tcgdexCard = $this->createTcgdexCard();
        $printing = new CardPrinting();

        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn($tcgdexCard);

        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $identityResolver->method('resolveFromTcgdexCard')->willReturn($printing);

        $resolver = new CardCodeResolver($apiClient, $identityResolver);

        self::assertSame($printing, $resolver->resolve('LOR-093'));
    }

    public function testResolvePassesParsedSetCodeAndNumberToApiClient(): void
    {
        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->expects(self::once())
            ->method('findCard')
            ->with('LOR', '093')
            ->willReturn(null);

        $resolver = new CardCodeResolver($apiClient, $this->createStub(CardIdentityResolver::class));

        self::assertNull($resolver->resolve('lor 093'));
    }

    public function testResolveReturnsNullWhenCardUnknownToTcgdex(): void
    {
        $apiClient = $this->createStub(TcgdexApiClient::class);
        $apiClient->method('findCard')->willReturn(null);

        $resolver = new CardCodeResolver($apiClient, $this->createStub(CardIdentityResolver::class));

        self::assertNull($resolver->resolve('LOR-093'));
    }

    public function testResolveReturnsNullWithoutApiCallForUnparseableCode(): void
    {
        $apiClient = $this->createMock(TcgdexApiClient::class);
        $apiClient->expects(self::never())->method('findCard');

        $resolver = new CardCodeResolver($apiClient, $this->createStub(CardIdentityResolver::class));

        self::assertNull($resolver->resolve('not a valid code at all'));
    }

    private function createTcgdexCard(): TcgdexCard
    {
        return new TcgdexCard(
            id: 'swsh09-093',
            name: 'Some Card',
            category: 'Trainer',
            trainerType: 'Item',
            imageUrl: null,
            isExpandedLegal: true,
        );
    }
}
