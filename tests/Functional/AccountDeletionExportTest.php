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

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F1.8 — Account deletion & data export (GDPR)
 */
class AccountDeletionExportTest extends AbstractFunctionalTest
{
    public function testExportRequiresAuthentication(): void
    {
        $this->client->request('GET', '/profile/export');

        self::assertResponseRedirects('/login');
    }

    public function testExportReturnsJson(): void
    {
        $this->loginAs('lender@example.com');

        $this->client->request('GET', '/profile/export');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $disposition = $this->client->getResponse()->headers->get('Content-Disposition');
        self::assertNotNull($disposition);
        self::assertStringContainsString('attachment', $disposition);

        $content = $this->client->getResponse()->getContent();
        \assert(\is_string($content));

        /** @var array<string, mixed> $data */
        $data = json_decode($content, true);

        self::assertArrayHasKey('profile', $data);
        self::assertArrayHasKey('decks', $data);
        self::assertArrayHasKey('borrows', $data);
        self::assertArrayHasKey('eventEngagements', $data);
        self::assertArrayHasKey('staffAssignments', $data);
        self::assertSame('lender@example.com', $data['profile']['email']);
    }

    public function testDeletionRequestRequiresAuthentication(): void
    {
        $this->client->request('POST', '/profile/request-deletion');

        self::assertResponseRedirects('/login');
    }

    public function testDeletionRequestStoresToken(): void
    {
        // organizer has no active borrows (not a borrower, no lent decks)
        $this->loginAs('organizer@example.com');

        $crawler = $this->client->request('GET', '/profile');
        $token = $crawler->filter('form[action*="request-deletion"] input[name="_token"]')->attr('value');
        \assert(\is_string($token));

        $this->client->request('POST', '/profile/request-deletion', ['_token' => $token]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'confirmation email');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'organizer@example.com']);
        self::assertNotNull($user->getDeletionToken());
        self::assertNotNull($user->getDeletionTokenExpiresAt());
    }

    public function testDeletionRequestRejectsInvalidCsrf(): void
    {
        $this->loginAs('organizer@example.com');

        $this->client->request('POST', '/profile/request-deletion', ['_token' => 'invalid']);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testDeletionRequestBlockedByUnsettledBorrows(): void
    {
        // lender has active borrows in fixtures (as borrower and as deck owner)
        $this->loginAs('lender@example.com');

        $crawler = $this->client->request('GET', '/profile');
        $token = $crawler->filter('form[action*="request-deletion"] input[name="_token"]')->attr('value');
        \assert(\is_string($token));

        $this->client->request('POST', '/profile/request-deletion', ['_token' => $token]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testConfirmDeletionDisablesAccount(): void
    {
        $this->loginAs('organizer@example.com');

        $crawler = $this->client->request('GET', '/profile');
        $token = $crawler->filter('form[action*="request-deletion"] input[name="_token"]')->attr('value');
        \assert(\is_string($token));

        $this->client->request('POST', '/profile/request-deletion', ['_token' => $token]);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'organizer@example.com']);
        $deletionToken = $user->getDeletionToken();
        \assert(\is_string($deletionToken));
        $userId = $user->getId();

        // Confirm deletion
        $this->client->followRedirects();
        $this->client->request('GET', '/confirm-deletion/'.$deletionToken);
        $this->client->followRedirects(false);

        $html = $this->client->getResponse()->getContent();
        \assert(\is_string($html));
        self::assertStringContainsString('anonymized', strtolower($html));

        // Verify the user is disabled and anonymized
        $em->clear();
        /** @var User $deletedUser */
        $deletedUser = $em->getRepository(User::class)->find($userId);
        self::assertNotNull($deletedUser->getDeletedAt());
        self::assertTrue($deletedUser->isAnonymized());
        self::assertNull($deletedUser->getDeletionToken());
        self::assertStringStartsWith('anonymized-', $deletedUser->getEmail());
    }

    public function testConfirmDeletionWithInvalidToken(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', '/confirm-deletion/invalid-token');
        $this->client->followRedirects(false);

        $html = $this->client->getResponse()->getContent();
        \assert(\is_string($html));
        self::assertStringContainsString('invalid', strtolower($html));
    }

    public function testConfirmDeletionWithExpiredToken(): void
    {
        $this->loginAs('organizer@example.com');

        $crawler = $this->client->request('GET', '/profile');
        $token = $crawler->filter('form[action*="request-deletion"] input[name="_token"]')->attr('value');
        \assert(\is_string($token));

        $this->client->request('POST', '/profile/request-deletion', ['_token' => $token]);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'organizer@example.com']);
        $deletionToken = $user->getDeletionToken();
        \assert(\is_string($deletionToken));

        $user->setDeletionTokenExpiresAt(new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC')));
        $em->flush();

        $this->client->followRedirects();
        $this->client->request('GET', '/confirm-deletion/'.$deletionToken);
        $this->client->followRedirects(false);

        $html = $this->client->getResponse()->getContent();
        \assert(\is_string($html));
        self::assertStringContainsString('expired', strtolower($html));
    }

    public function testProfileShowsExportAndDeleteButtons(): void
    {
        $this->loginAs('lender@example.com');

        $crawler = $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        $html = $crawler->html();
        self::assertStringContainsString('Export my data', $html);
        self::assertStringContainsString('Delete my account', $html);
        self::assertStringContainsString('Danger zone', $html);
    }
}
