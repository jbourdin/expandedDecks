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

namespace App\Tests\Functional;

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Service\CardIdentity\CardCodeResolver;
use App\Service\OgImage\CardFanImageGenerator;

/**
 * @see docs/features.md F18.32 — Card-fan OG image builder
 */
class OgImageBuilderControllerTest extends AbstractFunctionalTest
{
    public function testPageRequiresEditorRole(): void
    {
        $this->loginAs('borrower@example.com');
        $this->client->request('GET', '/admin/og-image-builder');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPageRendersForEditor(): void
    {
        // editor@example.com holds only ROLE_CMS_EDITOR (no ROLE_ADMIN), which is
        // the case the `^/admin/og-image-builder` firewall rule must allow through.
        $this->loginAs('editor@example.com');
        $this->client->request('GET', '/admin/og-image-builder');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'OG image builder');
        self::assertSelectorExists('.og-image-builder-root[data-generate-url]');
    }

    public function testGenerateRequiresEditorRole(): void
    {
        $this->loginAs('borrower@example.com');
        $this->client->request('POST', '/admin/og-image-builder/generate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['codes' => ['LOR-093', 'LOR-094']]));

        self::assertResponseStatusCodeSame(403);
    }

    public function testGenerateReturnsImageUrlAndCardStatuses(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->disableReboot();

        $resolver = $this->createStub(CardCodeResolver::class);
        $resolver->method('resolve')->willReturnCallback(
            static fn (string $code): ?CardPrinting => 'LOR-093' === $code ? self::createPrinting('Lost Vacuum') : null,
        );
        static::getContainer()->set(CardCodeResolver::class, $resolver);

        $generator = $this->createStub(CardFanImageGenerator::class);
        $generator->method('generate')->willReturn(self::createTinyPng());
        static::getContainer()->set(CardFanImageGenerator::class, $generator);

        $this->client->request('POST', '/admin/og-image-builder/generate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['codes' => ['LOR-093', 'XXX-999']]));

        self::assertResponseIsSuccessful();

        /** @var array{url: string, cards: list<array{code: string, status: string, name: string|null}>} $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertMatchesRegularExpression('#^/api/editor/image/[a-f0-9-]+\.png$#', $data['url']);
        self::assertSame('resolved', $data['cards'][0]['status']);
        self::assertSame('Lost Vacuum', $data['cards'][0]['name']);
        self::assertSame('not_found', $data['cards'][1]['status']);
        self::assertNull($data['cards'][1]['name']);
    }

    public function testGenerateRejectsWhenNoCodeResolves(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->disableReboot();

        $resolver = $this->createStub(CardCodeResolver::class);
        $resolver->method('resolve')->willReturn(null);
        static::getContainer()->set(CardCodeResolver::class, $resolver);

        $this->client->request('POST', '/admin/og-image-builder/generate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['codes' => ['XXX-998', 'XXX-999']]));

        self::assertResponseStatusCodeSame(422);

        /** @var array{error: string, cards: list<array{status: string}>} $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertSame('no_card_resolved', $data['error']);
        self::assertSame('not_found', $data['cards'][0]['status']);
    }

    public function testGenerateRejectsTooFewCodes(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('POST', '/admin/og-image-builder/generate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['codes' => ['LOR-093']]));

        self::assertResponseStatusCodeSame(422);

        /** @var array{error: string} $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertSame('invalid_card_count', $data['error']);
    }

    public function testGenerateRejectsTooManyCodes(): void
    {
        $this->loginAs('admin@example.com');

        $codes = array_map(static fn (int $index): string => 'LOR-09'.$index, range(0, 6));
        $this->client->request('POST', '/admin/og-image-builder/generate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['codes' => $codes]));

        self::assertResponseStatusCodeSame(422);
    }

    public function testGenerateRejectsMalformedPayload(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('POST', '/admin/og-image-builder/generate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['codes' => 'not-an-array']));

        self::assertResponseStatusCodeSame(400);
    }

    private static function createPrinting(string $cardName): CardPrinting
    {
        $identity = new CardIdentity();
        $identity->setName($cardName);

        $printing = new CardPrinting();
        $printing->setCardIdentity($identity);

        return $printing;
    }

    private static function createTinyPng(): string
    {
        $image = imagecreatetruecolor(10, 14);
        \assert(false !== $image);

        ob_start();
        imagepng($image);
        $data = ob_get_clean();
        \assert(false !== $data);

        return $data;
    }
}
