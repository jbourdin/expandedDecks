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
 * @see docs/features.md F4.16 — Lost & found deck alert
 */
class DeckFoundControllerTest extends AbstractFunctionalTest
{
    private function getDeckShortTag(string $name): string
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => $name]);

        return $deck->getShortTag();
    }

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

    public function testReportFoundDeckAsLoggedInUser(): void
    {
        $this->loginAs('borrower@example.com');

        // Iron Thorns is owned by admin, public
        $shortTag = $this->getDeckShortTag('Iron Thorns');

        // Visit the deck page first to initialize a session
        $this->client->request('GET', '/deck/'.$shortTag);
        $csrfToken = $this->getCsrfToken('deck-found-'.$shortTag);

        $this->client->request('POST', '/api/deck/'.$shortTag.'/found', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'message' => 'Found at table 5',
            'anonymous' => false,
            'captchaResponse' => '',
            'csrfToken' => $csrfToken,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        /** @var array<string, mixed> $response */
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($response['success']);
    }

    public function testReportFoundDeckAnonymously(): void
    {
        $shortTag = $this->getDeckShortTag('Iron Thorns');

        // Visit the deck page to get a session
        $this->client->request('GET', '/deck/'.$shortTag);
        $csrfToken = $this->getCsrfToken('deck-found-'.$shortTag);

        $this->client->request('POST', '/api/deck/'.$shortTag.'/found', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'message' => 'Left at reception desk',
            'anonymous' => true,
            'captchaResponse' => '',
            'csrfToken' => $csrfToken,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
    }

    public function testOwnerCannotReportOwnDeck(): void
    {
        $this->loginAs('admin@example.com');

        // Iron Thorns is owned by admin
        $shortTag = $this->getDeckShortTag('Iron Thorns');

        $this->client->request('GET', '/deck/'.$shortTag);
        $csrfToken = $this->getCsrfToken('deck-found-'.$shortTag);

        $this->client->request('POST', '/api/deck/'.$shortTag.'/found', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'message' => 'Test',
            'anonymous' => false,
            'captchaResponse' => '',
            'csrfToken' => $csrfToken,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(403);
    }

    public function testEmptyMessageIsRejected(): void
    {
        $shortTag = $this->getDeckShortTag('Iron Thorns');

        $this->client->request('GET', '/deck/'.$shortTag);
        $csrfToken = $this->getCsrfToken('deck-found-'.$shortTag);

        $this->client->request('POST', '/api/deck/'.$shortTag.'/found', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'message' => '',
            'anonymous' => false,
            'captchaResponse' => '',
            'csrfToken' => $csrfToken,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $shortTag = $this->getDeckShortTag('Iron Thorns');

        $this->client->request('POST', '/api/deck/'.$shortTag.'/found', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'message' => 'Test message',
            'anonymous' => false,
            'captchaResponse' => '',
            'csrfToken' => 'invalid-token',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }
}
