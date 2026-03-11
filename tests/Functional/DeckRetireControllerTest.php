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
use App\Enum\DeckStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.7 — Retire / reactivate a deck
 */
class DeckRetireControllerTest extends AbstractFunctionalTest
{
    public function testRetireDeckAsOwner(): void
    {
        $this->loginAs('admin@example.com');

        $deck = $this->getDeck('Iron Thorns');
        $crawler = $this->client->request('GET', '/deck/'.$deck->getShortTag());
        $form = $crawler->filter('button.btn-outline-danger')->closest('form');
        $this->client->submit($form->form());

        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Iron Thorns has a pending borrow in fixtures, so we get info flash with cancellation count
        self::assertSelectorExists('.alert-info, .alert-success');
    }

    public function testReactivateDeckAsOwner(): void
    {
        $this->loginAs('admin@example.com');

        $deck = $this->getDeck('Iron Thorns');
        $deck->setStatus(DeckStatus::Retired);
        $this->getEntityManager()->flush();

        $crawler = $this->client->request('GET', '/deck/'.$deck->getShortTag());
        $form = $crawler->filter('button.btn-outline-success')->closest('form');
        $this->client->submit($form->form());

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'reactivated');
    }

    public function testCannotRetireLentDeck(): void
    {
        $this->loginAs('admin@example.com');

        $deck = $this->getDeck('Iron Thorns');
        $deck->setStatus(DeckStatus::Lent);
        $this->getEntityManager()->flush();

        // Lent deck should not show the retire button
        $this->client->request('GET', '/deck/'.$deck->getShortTag());
        self::assertSelectorNotExists('button.btn-outline-danger');
    }

    public function testRetireDeniedForNonOwner(): void
    {
        $this->loginAs('borrower@example.com');

        $deck = $this->getDeck('Iron Thorns');
        $this->client->request('POST', '/deck/'.$deck->getId().'/toggle-retired', [
            '_token' => 'dummy',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRetireRedirectsForAnonymous(): void
    {
        $deck = $this->getDeck('Iron Thorns');
        $this->client->request('POST', '/deck/'.$deck->getId().'/toggle-retired');

        self::assertResponseRedirects('/login');
    }

    public function testRetireDeniedWithInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');

        $deck = $this->getDeck('Iron Thorns');
        $this->client->request('POST', '/deck/'.$deck->getId().'/toggle-retired', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRetireButtonVisibleOnDeckShowForOwner(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeck('Iron Thorns')->getShortTag();
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button.btn-outline-danger');
    }

    public function testReactivateButtonVisibleOnRetiredDeck(): void
    {
        $this->loginAs('admin@example.com');

        $deck = $this->getDeck('Iron Thorns');
        $deck->setStatus(DeckStatus::Retired);
        $this->getEntityManager()->flush();

        $this->client->request('GET', '/deck/'.$deck->getShortTag());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button.btn-outline-success');
    }

    public function testRetireAutoCancelsPendingBorrows(): void
    {
        $this->loginAs('admin@example.com');

        // Iron Thorns has a pending borrow in fixtures
        $deck = $this->getDeck('Iron Thorns');
        $crawler = $this->client->request('GET', '/deck/'.$deck->getShortTag());
        $form = $crawler->filter('button.btn-outline-danger')->closest('form');
        $this->client->submit($form->form());

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-info', 'cancelled');
    }

    public function testRetireButtonNotVisibleForNonOwner(): void
    {
        $this->loginAs('borrower@example.com');

        $shortTag = $this->getDeck('Iron Thorns')->getShortTag();
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('button.btn-outline-danger');
    }

    private function getDeck(string $name): Deck
    {
        /** @var Deck $deck */
        $deck = $this->getEntityManager()->getRepository(Deck::class)->findOneBy(['name' => $name]);

        return $deck;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }
}
