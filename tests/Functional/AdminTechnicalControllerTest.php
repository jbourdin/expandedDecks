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

class AdminTechnicalControllerTest extends AbstractFunctionalTest
{
    private function getCsrfToken(string $tokenId): string
    {
        $session = $this->client->getSession();
        self::assertNotNull($session, 'Session must exist — make a GET request first.');
        $session->start();

        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = static::getContainer()->get('request_stack');

        $syntheticRequest = new \Symfony\Component\HttpFoundation\Request();
        $syntheticRequest->setSession($session);
        $requestStack->push($syntheticRequest);

        try {
            /** @var \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $tokenManager */
            $tokenManager = static::getContainer()->get('security.csrf.token_manager');

            return $tokenManager->getToken($tokenId)->getValue();
        } finally {
            $requestStack->pop();
        }
    }

    public function testDashboardRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin/technical');

        self::assertResponseRedirects('/login');
    }

    public function testDashboardRequiresTechnicalAdminRole(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/admin/technical');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDashboardAccessibleForAdmin(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/technical');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardShowsSetMappingCount(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/admin/technical');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('html:contains("Set Mappings")')->count(), 'Dashboard should contain "Set Mappings" text.');
    }

    public function testSetMappingsRebuildRequiresCsrfToken(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('POST', '/admin/technical/set-mappings-rebuild', [
            '_token' => 'invalid-token',
        ]);

        // Invalid CSRF redirects back to dashboard with a flash
        self::assertResponseRedirects('/admin/technical');
    }

    public function testEnrichRetryRequiresAuthentication(): void
    {
        $this->client->request('POST', '/admin/technical/enrich-retry');

        self::assertResponseRedirects('/login');
    }

    public function testFlushReenrichRequiresAuthentication(): void
    {
        $this->client->request('POST', '/admin/technical/flush-reenrich');

        self::assertResponseRedirects('/login');
    }

    public function testReenrichCardRequiresAuthentication(): void
    {
        $this->client->request('POST', '/admin/technical/reenrich-card');

        self::assertResponseRedirects('/login');
    }

    public function testReenrichCardRequiresCsrfToken(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('POST', '/admin/technical/reenrich-card', [
            '_token' => 'invalid-token',
            'set_code' => 'TWM',
            'card_number' => '77',
        ]);

        self::assertResponseRedirects('/admin/technical');
    }

    public function testReenrichCardRejectsEmptyInputs(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/technical');
        $csrfToken = $this->getCsrfToken('technical-reenrich-card');

        $this->client->request('POST', '/admin/technical/reenrich-card', [
            '_token' => $csrfToken,
            'set_code' => '',
            'card_number' => '',
        ]);

        self::assertResponseRedirects('/admin/technical');
    }

    public function testReenrichCardShowsNoMatchForUnknownCard(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/technical');
        $csrfToken = $this->getCsrfToken('technical-reenrich-card');

        $this->client->request('POST', '/admin/technical/reenrich-card', [
            '_token' => $csrfToken,
            'set_code' => 'NONEXISTENT',
            'card_number' => '999',
        ]);

        self::assertResponseRedirects('/admin/technical');
    }

    public function testReenrichCardDispatchesForMatchingCard(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/technical');
        $csrfToken = $this->getCsrfToken('technical-reenrich-card');

        // TWM 77 = Iron Thorns ex — exists in fixture data
        $this->client->request('POST', '/admin/technical/reenrich-card', [
            '_token' => $csrfToken,
            'set_code' => 'TWM',
            'card_number' => '77',
        ]);

        self::assertResponseRedirects('/admin/technical');
    }

    public function testBannedCardsEnrichRequiresAuthentication(): void
    {
        $this->client->request('POST', '/admin/technical/banned-cards-enrich');

        self::assertResponseRedirects('/login');
    }

    public function testBannedCardsEnrichRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('POST', '/admin/technical/banned-cards-enrich', [
            '_token' => 'wrong',
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testBannedCardsEnrichSucceedsWithValidCsrf(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/technical');
        $csrfToken = $this->getCsrfToken('technical-banned-cards-enrich');

        $this->client->request('POST', '/admin/technical/banned-cards-enrich', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/admin/technical');
        $this->client->followRedirect();
        // No banned cards in fixtures -> "Linked 0 / 0" success flash, no warning.
        self::assertSelectorExists('.alert-success');
    }

    public function testBannedCardsEnrichAcceptsForceFlag(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/technical');
        $csrfToken = $this->getCsrfToken('technical-banned-cards-enrich');

        $this->client->request('POST', '/admin/technical/banned-cards-enrich', [
            '_token' => $csrfToken,
            'force' => '1',
        ]);

        self::assertResponseRedirects('/admin/technical');
    }
}
