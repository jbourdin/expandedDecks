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
 * @see docs/features.md F7.2 — User management
 */
class AdminUserTest extends AbstractFunctionalTest
{
    public function testListRequiresAdmin(): void
    {
        $this->loginAs('organizer@example.com');

        $this->client->request('GET', '/admin/users');

        self::assertResponseStatusCodeSame(403);
    }

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin/users');

        self::assertResponseRedirects('/login');
    }

    public function testAdminCanAccessList(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table.table-themed');
        self::assertStringContainsString('User management', $crawler->html());
    }

    public function testListShowsUsers(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/admin/users');

        $rows = $crawler->filter('table.table-themed tbody tr');
        self::assertGreaterThanOrEqual(1, $rows->count());
    }

    public function testListSearchFilters(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/admin/users?q=lender');

        self::assertResponseIsSuccessful();
        $html = $crawler->html();
        self::assertStringContainsString('lender@example.com', $html);
    }

    public function testShowDisplaysUserDetails(): void
    {
        $this->loginAs('admin@example.com');

        $userId = $this->getUserId('lender@example.com');
        $crawler = $this->client->request('GET', '/admin/users/'.$userId);

        self::assertResponseIsSuccessful();
        $html = $crawler->html();
        self::assertStringContainsString('lender@example.com', $html);
        self::assertStringContainsString('Roles', $html);
    }

    public function testRoleAssignment(): void
    {
        $this->loginAs('admin@example.com');

        $userId = $this->getUserId('lender@example.com');
        $crawler = $this->client->request('GET', '/admin/users/'.$userId);

        self::assertResponseIsSuccessful();

        $token = $crawler->filter('form[action*="/roles"] input[name="_token"]')->attr('value');
        \assert(\is_string($token));

        $this->client->request('POST', '/admin/users/'.$userId.'/roles', [
            '_token' => $token,
            'roles' => ['ROLE_ORGANIZER'],
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Roles updated');
    }

    public function testDisableAndEnable(): void
    {
        $this->loginAs('admin@example.com');

        $userId = $this->getUserId('lender@example.com');
        $crawler = $this->client->request('GET', '/admin/users/'.$userId);

        // Disable
        $token = $crawler->filter('form[action*="/disable"] input[name="_token"]')->attr('value');
        \assert(\is_string($token));

        $this->client->request('POST', '/admin/users/'.$userId.'/disable', ['_token' => $token]);

        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'disabled');
        self::assertSelectorTextContains('.badge.bg-danger', 'Disabled');

        // Re-enable
        $token = $crawler->filter('form[action*="/disable"] input[name="_token"]')->attr('value');
        \assert(\is_string($token));

        $this->client->request('POST', '/admin/users/'.$userId.'/disable', ['_token' => $token]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'enabled');
    }

    public function testAnonymize(): void
    {
        $this->loginAs('admin@example.com');

        $userId = $this->getUserId('unverified@example.com');
        $crawler = $this->client->request('GET', '/admin/users/'.$userId);

        $token = $crawler->filter('form[action*="/anonymize"] input[name="_token"]')->attr('value');
        \assert(\is_string($token));

        $this->client->request('POST', '/admin/users/'.$userId.'/anonymize', ['_token' => $token]);

        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'anonymized');
        self::assertSelectorTextContains('.badge.bg-dark', 'Anonymized');
    }

    public function testInvalidCsrfTokenRejected(): void
    {
        $this->loginAs('admin@example.com');

        $userId = $this->getUserId('lender@example.com');

        $this->client->request('POST', '/admin/users/'.$userId.'/disable', ['_token' => 'invalid']);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    private function getUserId(string $email): int
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $id = $user->getId();
        \assert(null !== $id);

        return $id;
    }
}
