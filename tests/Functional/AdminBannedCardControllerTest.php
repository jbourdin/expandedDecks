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

use App\Entity\BannedCard;
use App\Entity\BannedCardPrinting;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F6.5 — Banned card list management
 * @see docs/features.md F6.14 — Banned cards public page
 */
class AdminBannedCardControllerTest extends AbstractFunctionalTest
{
    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin/banned-card');

        self::assertResponseRedirects('/login');
    }

    public function testListRequiresAdminRole(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/admin/banned-card');

        self::assertResponseStatusCodeSame(403);
    }

    public function testListAccessibleForAdmin(): void
    {
        $this->loginAs('admin@example.com');
        $this->persistBannedCard('Pikachu', 'LOT', '90');

        $this->client->request('GET', '/admin/banned-card');

        self::assertResponseIsSuccessful();
    }

    public function testListShowsActiveTabByDefault(): void
    {
        $this->loginAs('admin@example.com');
        $active = $this->persistBannedCard('Active Card', 'LOT', '90');
        $deleted = $this->persistBannedCard('Deleted Card', 'LOT', '91', deletedAt: new \DateTimeImmutable());

        $this->client->request('GET', '/admin/banned-card');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $active->getCardName());
        self::assertSelectorTextNotContains('body', $deleted->getCardName());
    }

    public function testListHistoryViewShowsDeletedCards(): void
    {
        $this->loginAs('admin@example.com');
        $active = $this->persistBannedCard('Active Card', 'LOT', '90');
        $deleted = $this->persistBannedCard('Deleted Card', 'LOT', '91', deletedAt: new \DateTimeImmutable());

        $this->client->request('GET', '/admin/banned-card?view=history');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $deleted->getCardName());
        self::assertSelectorTextNotContains('body', $active->getCardName());
    }

    public function testListIgnoresUnknownView(): void
    {
        $this->loginAs('admin@example.com');
        $this->persistBannedCard('Active Card', 'LOT', '90');

        $this->client->request('GET', '/admin/banned-card?view=garbage');

        // Should fall back to "active" view rendering successfully.
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Active Card');
    }

    public function testEditFormAccessible(): void
    {
        $this->loginAs('admin@example.com');
        $card = $this->persistBannedCard('Pikachu', 'LOT', '90');

        $this->client->request('GET', '/admin/banned-card/'.$card->getId().'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEditSavesAndRedirectsBackToEdit(): void
    {
        $this->loginAs('admin@example.com');
        $card = $this->persistBannedCard('Pikachu', 'LOT', '90');

        $crawler = $this->client->request('GET', '/admin/banned-card/'.$card->getId().'/edit');
        $form = $crawler->selectButton('Save')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/banned-card/'.$card->getId().'/edit');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testDeleteSoftDeletesAndRedirects(): void
    {
        $this->loginAs('admin@example.com');
        $card = $this->persistBannedCard('Pikachu', 'LOT', '90');

        $crawler = $this->client->request('GET', '/admin/banned-card/'.$card->getId().'/edit');
        $token = $crawler->filter('input[name="_token"][value]')->first()->attr('value');

        $this->client->request('POST', '/admin/banned-card/'.$card->getId().'/delete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/banned-card');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(BannedCard::class)->find($card->getId());
        self::assertInstanceOf(BannedCard::class, $reloaded);
        self::assertTrue($reloaded->isDeleted());
    }

    public function testDeleteWithInvalidCsrfShowsErrorAndDoesNotDelete(): void
    {
        $this->loginAs('admin@example.com');
        $card = $this->persistBannedCard('Pikachu', 'LOT', '90');

        $this->client->request('POST', '/admin/banned-card/'.$card->getId().'/delete', [
            '_token' => 'wrong',
        ]);

        self::assertResponseRedirects('/admin/banned-card/'.$card->getId().'/edit');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(BannedCard::class)->find($card->getId());
        self::assertInstanceOf(BannedCard::class, $reloaded);
        self::assertFalse($reloaded->isDeleted());
    }

    public function testRestoreClearsDeletedAtAndRedirectsBackToEdit(): void
    {
        $this->loginAs('admin@example.com');
        $card = $this->persistBannedCard('Pikachu', 'LOT', '90', deletedAt: new \DateTimeImmutable());

        $crawler = $this->client->request('GET', '/admin/banned-card/'.$card->getId().'/edit');
        $token = $crawler->filter('input[name="_token"][value]')->first()->attr('value');

        $this->client->request('POST', '/admin/banned-card/'.$card->getId().'/restore', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/banned-card/'.$card->getId().'/edit');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(BannedCard::class)->find($card->getId());
        self::assertInstanceOf(BannedCard::class, $reloaded);
        self::assertFalse($reloaded->isDeleted());
    }

    public function testRestoreWithInvalidCsrfRedirectsToHistoryWithError(): void
    {
        $this->loginAs('admin@example.com');
        $card = $this->persistBannedCard('Pikachu', 'LOT', '90', deletedAt: new \DateTimeImmutable());

        $this->client->request('POST', '/admin/banned-card/'.$card->getId().'/restore', [
            '_token' => 'wrong',
        ]);

        self::assertResponseRedirects('/admin/banned-card?view=history');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(BannedCard::class)->find($card->getId());
        self::assertInstanceOf(BannedCard::class, $reloaded);
        self::assertTrue($reloaded->isDeleted());
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function persistBannedCard(
        string $cardName,
        string $setCode,
        string $cardNumber,
        ?\DateTimeImmutable $deletedAt = null,
    ): BannedCard {
        $em = $this->getEntityManager();

        $card = new BannedCard();
        $card->setCardName($cardName);
        $card->setEffectiveDate(new \DateTimeImmutable('2024-04-01'));
        $card->setSourceUrl('https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list');
        if ($deletedAt instanceof \DateTimeImmutable) {
            $card->setDeletedAt($deletedAt);
        }

        $printing = new BannedCardPrinting();
        $printing->setSetCode($setCode);
        $printing->setCardNumber($cardNumber);
        $card->addPrinting($printing);

        $em->persist($printing);
        $em->persist($card);
        $em->flush();

        return $card;
    }
}
