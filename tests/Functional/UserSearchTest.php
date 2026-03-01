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
 * @see docs/features.md F3.5 — Assign event staff team
 */
class UserSearchTest extends AbstractFunctionalTest
{
    public function testSearchByScreenName(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/api/user/search?q=Organizer');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertCount(1, $data);
        self::assertSame('Organizer', $data[0]['screenName']);
    }

    public function testSearchByEmail(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/api/user/search?q=borrower@example');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertCount(1, $data);
        self::assertSame('Borrower', $data[0]['screenName']);
        self::assertSame('borrower@example.com', $data[0]['email']);
    }

    public function testSearchByPlayerId(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/api/user/search?q=PKM-ORG');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertCount(1, $data);
        self::assertSame('Organizer', $data[0]['screenName']);
        self::assertSame('PKM-ORG-001', $data[0]['playerId']);
    }

    public function testSearchByNumericPlayerId(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/api/user/search?q=101');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertCount(1, $data);
        self::assertSame('StaffOne', $data[0]['screenName']);
        self::assertSame('101', $data[0]['playerId']);
    }

    public function testSearchMinQueryLength(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/api/user/search?q=A');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertSame([], $data);
    }

    public function testSearchRequiresOrganizerRole(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/api/user/search?q=Admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testSearchExcludesAnonymized(): void
    {
        $this->loginAs('admin@example.com');

        // Anonymize the Borrower user
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $borrower = $em->getRepository(User::class)->findOneBy(['email' => 'borrower@example.com']);
        self::assertNotNull($borrower);
        $borrower->setIsAnonymized(true);
        $em->flush();

        $this->client->request('GET', '/api/user/search?q=Borrower');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertSame([], $data);
    }

    public function testSearchReturnsJsonFormat(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/api/user/search?q=Admin');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $data = $this->getJsonResponse();

        self::assertIsArray($data);
        self::assertNotEmpty($data);

        $user = $data[0];
        self::assertArrayHasKey('screenName', $user);
        self::assertArrayHasKey('email', $user);
        self::assertArrayHasKey('playerId', $user);
    }

    /**
     * @return list<array{screenName: string, email: string, playerId: string|null}>
     */
    private function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $data = json_decode($content, true);
        self::assertIsArray($data);

        return $data;
    }
}
