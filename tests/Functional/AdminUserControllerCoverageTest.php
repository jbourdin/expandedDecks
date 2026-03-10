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
 * Additional coverage tests for AdminUserController uncovered branches.
 *
 * @see docs/features.md F7.2 — User management
 */
class AdminUserControllerCoverageTest extends AbstractFunctionalTest
{
    /**
     * Invalid CSRF on roles update should redirect with a danger flash.
     */
    public function testUpdateRolesInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $userId = $this->getUserId('lender@example.com');

        $this->client->request('POST', '/admin/users/'.$userId.'/roles', [
            '_token' => 'invalid-token',
            'roles' => ['ROLE_ORGANIZER'],
        ]);

        self::assertResponseRedirects('/admin/users/'.$userId);
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * Invalid CSRF on anonymize should redirect with a danger flash.
     */
    public function testAnonymizeInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $userId = $this->getUserId('staff2@example.com');

        $this->client->request('POST', '/admin/users/'.$userId.'/anonymize', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects('/admin/users/'.$userId);
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * Attempting to anonymize an already-anonymized user should show a
     * "already anonymized" warning.
     */
    public function testAnonymizeAlreadyAnonymizedShowsWarning(): void
    {
        $this->loginAs('admin@example.com');

        $userId = $this->getUserId('unverified@example.com');

        // Load the page BEFORE anonymizing — the form is present and holds a valid CSRF token
        $crawler = $this->client->request('GET', '/admin/users/'.$userId);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/anonymize"]');
        self::assertGreaterThan(0, $form->count(), 'Anonymize form should be present for non-anonymized user.');
        $token = $form->first()->filter('input[name="_token"]')->attr('value');
        self::assertNotNull($token);

        // Now anonymize the user behind the scenes (simulating a race condition or repeated submit)
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var User $user */
        $user = $entityManager->find(User::class, $userId);
        self::assertNotNull($user);
        $user->anonymize();
        $entityManager->flush();

        // POST with the previously-valid CSRF token — user is now already anonymized
        $this->client->request('POST', '/admin/users/'.$userId.'/anonymize', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/users/'.$userId);
        $crawler = $this->client->followRedirect();
        $html = $crawler->html();
        self::assertStringContainsString('already anonymized', strtolower($html));
    }

    /**
     * Search with no results still renders the list page.
     */
    public function testListWithSearchNoResults(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/users?q=zzzznonexistent');

        self::assertResponseIsSuccessful();
    }

    /**
     * Pagination with page number beyond data still renders correctly.
     */
    public function testListWithHighPageNumber(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/users?page=999');

        self::assertResponseIsSuccessful();
    }

    private function getUserId(string $email): int
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $id = $user->getId();
        \assert(null !== $id);

        return $id;
    }
}
