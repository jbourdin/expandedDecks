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
 * Additional coverage tests for ProfileController uncovered branches.
 *
 * @see docs/features.md F1.3 — User profile
 * @see docs/features.md F1.8 — Account deletion & data export (GDPR)
 */
class ProfileControllerCoverageTest extends AbstractFunctionalTest
{
    /**
     * Submitting the profile edit form should update the user locale and
     * redirect back to the profile.
     */
    public function testProfileEditUpdatesLocaleAndRedirects(): void
    {
        $this->loginAs('staff1@example.com');

        $crawler = $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['profile_form[preferredLocale]'] = 'fr';
        $this->client->submit($form);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        // Verify the locale was saved
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->clear();
        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'staff1@example.com']);
        self::assertSame('fr', $user->getPreferredLocale());
    }

    /**
     * Saving the public author profile through the self-service form persists
     * the fields and splits sameAs one URL per line (F19.8).
     *
     * @see docs/features.md F19.8 — Author assignment
     */
    public function testProfileEditSavesPublicAuthorProfile(): void
    {
        $this->loginAs('staff2@example.com');

        $crawler = $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['profile_form[isPublicAuthor]']->tick();
        $form['profile_form[credential]'] = 'Format specialist';
        $form['profile_form[bio]'] = 'Bio from profile form.';
        $form['profile_form[sameAs]'] = "https://x.test\nhttps://y.test\n";
        $form['profile_form[primaryUrl]'] = 'https://example.test/me';
        $this->client->submit($form);

        self::assertResponseRedirects('/profile');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->clear();
        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'staff2@example.com']);
        self::assertTrue($user->isPublicAuthor());
        self::assertSame('Format specialist', $user->getCredential());
        self::assertSame('Bio from profile form.', $user->getBio());
        self::assertSame(['https://x.test', 'https://y.test'], $user->getSameAs());
        self::assertSame('https://example.test/me', $user->getPrimaryUrl());
    }

    /**
     * Data export for a user with decks, borrows, and engagements should
     * include all sections with data.
     */
    public function testExportIncludesAllSectionsForUserWithData(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/profile/export');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $content = $this->client->getResponse()->getContent();
        \assert(\is_string($content));

        /** @var array<string, mixed> $data */
        $data = json_decode($content, true);

        self::assertArrayHasKey('profile', $data);
        self::assertSame('admin@example.com', $data['profile']['email']);

        // Admin owns decks
        self::assertNotEmpty($data['decks']);

        // Admin has event engagements
        self::assertNotEmpty($data['eventEngagements']);
    }
}
