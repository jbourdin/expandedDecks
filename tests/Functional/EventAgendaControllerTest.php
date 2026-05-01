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
use App\Repository\UserRepository;
use App\Service\Event\PersonalCalendarTokenService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F3.14 — iCal agenda feed
 */
class EventAgendaControllerTest extends AbstractFunctionalTest
{
    public function testAgendaPageRequiresLogin(): void
    {
        $this->client->request('GET', '/event/agenda');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testAgendaPageRendersAndAssignsTokenOnFirstVisit(): void
    {
        $this->loginAs('admin@example.com');

        $admin = $this->getUserByEmail('admin@example.com');
        self::assertNull($admin->getCalendarToken(), 'Token should start unset.');

        $this->client->request('GET', '/event/agenda');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My agenda');

        $admin = $this->getUserByEmail('admin@example.com');
        self::assertNotNull($admin->getCalendarToken());
        self::assertGreaterThanOrEqual(40, \strlen((string) $admin->getCalendarToken()));
    }

    public function testRegenerateTokenIssuesNewTokenAndInvalidatesPrevious(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event/agenda');
        self::assertResponseIsSuccessful();

        $original = $this->getUserByEmail('admin@example.com')->getCalendarToken();
        self::assertNotNull($original);

        // Submit the regenerate form by extracting its CSRF token from the rendered page.
        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form[action$="/event/agenda/regenerate-token"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/event/agenda');

        $regenerated = $this->getUserByEmail('admin@example.com')->getCalendarToken();
        self::assertNotNull($regenerated);
        self::assertNotSame($original, $regenerated);

        // Old token's feed URL must now 404.
        $this->client->request('GET', \sprintf('/calendar/event/%s.ics', $original));
        self::assertResponseStatusCodeSame(404);

        // New token's feed URL works without authentication.
        $this->client->request('GET', \sprintf('/calendar/event/%s.ics', $regenerated));
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('BEGIN:VCALENDAR', (string) $this->client->getResponse()->getContent());
    }

    public function testAnonymousIcalFeedReturnsCalendarForKnownToken(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/event/agenda');
        $token = $this->getUserByEmail('admin@example.com')->getCalendarToken();
        self::assertNotNull($token);

        // Switch to a fresh anonymous client.
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->request('GET', \sprintf('/calendar/event/%s.ics', $token));

        self::assertResponseIsSuccessful();
        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        self::assertStringContainsString('text/calendar', $contentType);
    }

    public function testIcalFeedReturns404ForUnknownToken(): void
    {
        $this->client->request('GET', '/calendar/event/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.ics');

        self::assertResponseStatusCodeSame(404);
    }

    public function testRegenerateRejectsInvalidCsrfToken(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/event/agenda');
        $original = $this->getUserByEmail('admin@example.com')->getCalendarToken();

        $this->client->request('POST', '/event/agenda/regenerate-token', [
            '_token' => 'definitely-not-the-csrf-token',
        ]);

        self::assertResponseRedirects('/event/agenda');
        self::assertSame($original, $this->getUserByEmail('admin@example.com')->getCalendarToken());
    }

    public function testTokenServiceFindsUserByToken(): void
    {
        /** @var PersonalCalendarTokenService $tokenService */
        $tokenService = static::getContainer()->get(PersonalCalendarTokenService::class);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $admin = $this->getUserByEmail('admin@example.com');
        $token = $tokenService->ensureToken($admin);
        $em->flush();

        $resolved = $tokenService->findUserByToken($token);
        self::assertNotNull($resolved);
        self::assertSame('admin@example.com', $resolved->getEmail());
    }

    private function getUserByEmail(string $email): User
    {
        /** @var UserRepository $repository */
        $repository = static::getContainer()->get(UserRepository::class);

        $user = $repository->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }
}
