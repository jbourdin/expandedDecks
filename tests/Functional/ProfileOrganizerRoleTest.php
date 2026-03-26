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
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests for the self-service organizer role toggle on the profile page.
 *
 * @see docs/features.md F1.3 — User profile
 */
class ProfileOrganizerRoleTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function getUserByEmail(string $email): User
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        return $user;
    }

    /**
     * A regular user should see the organizer checkbox unchecked.
     */
    public function testRegularUserSeesUncheckedOrganizerCheckbox(): void
    {
        $this->loginAs('borrower@example.com');

        $crawler = $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $checkbox = $crawler->filter('#profile_form_organizerRole');
        self::assertCount(1, $checkbox, 'Organizer role checkbox should be present');
        self::assertEmpty($checkbox->attr('checked'), 'Checkbox should be unchecked for regular user');
        self::assertEmpty($checkbox->attr('disabled'), 'Checkbox should not be disabled for regular user');
    }

    /**
     * A regular user can activate the organizer role via the profile checkbox.
     */
    public function testRegularUserCanActivateOrganizerRole(): void
    {
        $this->loginAs('borrower@example.com');

        $user = $this->getUserByEmail('borrower@example.com');
        self::assertNotContains('ROLE_ORGANIZER', $user->getRoles());

        $crawler = $this->client->request('GET', '/profile');
        $form = $crawler->selectButton('Save')->form();
        $form['profile_form[organizerRole]']->tick();
        $this->client->submit($form);

        self::assertResponseRedirects('/profile');

        $user = $this->getUserByEmail('borrower@example.com');
        self::assertContains('ROLE_ORGANIZER', $user->getRoles());
    }

    /**
     * A user with the organizer role and no active events can deactivate it.
     */
    public function testOrganizerWithoutActiveEventsCanDeactivateRole(): void
    {
        // lender has no organizer role — give them one first, with no events
        $this->loginAs('lender@example.com');

        // Activate the role
        $crawler = $this->client->request('GET', '/profile');
        $form = $crawler->selectButton('Save')->form();
        $form['profile_form[organizerRole]']->tick();
        $this->client->submit($form);

        $user = $this->getUserByEmail('lender@example.com');
        self::assertContains('ROLE_ORGANIZER', $user->getRoles());

        // Now deactivate it
        $crawler = $this->client->followRedirect();
        $form = $crawler->selectButton('Save')->form();
        $form['profile_form[organizerRole]']->untick();
        $this->client->submit($form);

        $user = $this->getUserByEmail('lender@example.com');
        self::assertNotContains('ROLE_ORGANIZER', $user->getRoles());
    }

    /**
     * An organizer with active events sees the checkbox checked and disabled.
     */
    public function testOrganizerWithActiveEventsHasLockedCheckbox(): void
    {
        $this->loginAs('organizer@example.com');

        $crawler = $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $checkbox = $crawler->filter('#profile_form_organizerRole');
        self::assertCount(1, $checkbox);
        self::assertSame('disabled', $checkbox->attr('disabled'), 'Checkbox should be disabled for organizer with active events');
    }

    /**
     * An organizer with active events cannot remove the role even by submitting manually.
     */
    public function testOrganizerWithActiveEventsCannotRemoveRole(): void
    {
        $this->loginAs('organizer@example.com');

        $crawler = $this->client->request('GET', '/profile');
        $form = $crawler->selectButton('Save')->form();
        // Disabled fields are not submitted, simulating a forced POST without the checkbox
        $this->client->submit($form);

        $user = $this->getUserByEmail('organizer@example.com');
        self::assertContains('ROLE_ORGANIZER', $user->getRoles(), 'Organizer role should not be removed when events are active');
    }

    /**
     * An admin sees the organizer checkbox checked and disabled.
     */
    public function testAdminSeesCheckedAndDisabledCheckbox(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $checkbox = $crawler->filter('#profile_form_organizerRole');
        self::assertCount(1, $checkbox);
        self::assertSame('disabled', $checkbox->attr('disabled'), 'Checkbox should be disabled for admin');
    }

    /**
     * Activating the organizer role does not log the user out.
     */
    public function testRoleChangeDoesNotLogOut(): void
    {
        $this->loginAs('borrower@example.com');

        $crawler = $this->client->request('GET', '/profile');
        $form = $crawler->selectButton('Save')->form();
        $form['profile_form[organizerRole]']->tick();
        $this->client->submit($form);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        // User should still be authenticated — accessing /profile should not redirect to login
        $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
    }

    /**
     * EventRepository::hasActiveEventsAsOrganizer returns true for an organizer
     * with active events.
     */
    public function testHasActiveEventsAsOrganizerReturnsTrue(): void
    {
        $user = $this->getUserByEmail('organizer@example.com');

        /** @var EventRepository $repository */
        $repository = static::getContainer()->get(EventRepository::class);

        self::assertTrue($repository->hasActiveEventsAsOrganizer($user));
    }

    /**
     * EventRepository::hasActiveEventsAsOrganizer returns false for a user
     * who is not an organizer of any event.
     */
    public function testHasActiveEventsAsOrganizerReturnsFalse(): void
    {
        $user = $this->getUserByEmail('borrower@example.com');

        /** @var EventRepository $repository */
        $repository = static::getContainer()->get(EventRepository::class);

        self::assertFalse($repository->hasActiveEventsAsOrganizer($user));
    }
}
