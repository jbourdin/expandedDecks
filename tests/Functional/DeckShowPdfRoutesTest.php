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

use App\Entity\Deck;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Coverage for the PDF routes and the technical re-enrich action on
 * DeckShowController. The existing tests focus on the HTML show route;
 * the label / decklist / re-enrich endpoints are owner- or role-gated and
 * each has its own access-denied / not-found branches.
 *
 * @see docs/features.md F5.7 — PDF label card (home printing)
 * @see docs/features.md F5.13 — Printable A4 decklist PDF
 */
class DeckShowPdfRoutesTest extends AbstractFunctionalTest
{
    public function testLabelPdfReturnsPdfForOwner(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', \sprintf('/deck/%s/label.pdf', $shortTag));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
    }

    public function testLabelPdfDeniedForNonOwner(): void
    {
        $this->loginAs('borrower@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', \sprintf('/deck/%s/label.pdf', $shortTag));

        self::assertResponseStatusCodeSame(403);
    }

    public function testLabelFoldablePdfReturnsPdfForOwner(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', \sprintf('/deck/%s/label-foldable.pdf', $shortTag));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
    }

    public function testLabelFoldablePdfDeniedForNonOwner(): void
    {
        $this->loginAs('borrower@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', \sprintf('/deck/%s/label-foldable.pdf', $shortTag));

        self::assertResponseStatusCodeSame(403);
    }

    public function testDecklistPdfReturnsPersonalPdfForOwner(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', \sprintf('/deck/%s/decklist.pdf', $shortTag));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
    }

    public function testDecklistPdfReturnsAnonymousVariantWithQueryFlag(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', \sprintf('/deck/%s/decklist.pdf?anonymous=1', $shortTag));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
    }

    public function testDecklistPdfDeniedForNonOwner(): void
    {
        $this->loginAs('borrower@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('GET', \sprintf('/deck/%s/decklist.pdf', $shortTag));

        self::assertResponseStatusCodeSame(403);
    }

    public function testReEnrichRequiresTechnicalAdminRole(): void
    {
        $this->loginAs('borrower@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('POST', \sprintf('/deck/%s/re-enrich', $shortTag), ['_token' => 'irrelevant']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testReEnrichRequiresValidCsrf(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Iron Thorns');
        $this->client->request('POST', \sprintf('/deck/%s/re-enrich', $shortTag), ['_token' => 'wrong']);

        // Invalid CSRF -> AccessDeniedException -> 403.
        self::assertResponseStatusCodeSame(403);
    }

    private function getDeckShortTag(string $name): string
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $deck = $em->getRepository(Deck::class)->findOneBy(['name' => $name]);
        \assert($deck instanceof Deck);

        return $deck->getShortTag();
    }
}
