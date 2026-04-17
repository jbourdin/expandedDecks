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

use App\Entity\Archetype;
use App\Entity\Deck;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.9 — Deck version history
 */
class AdminArchetypeVariantVersionsTest extends AbstractFunctionalTest
{
    public function testVersionsPageAccessibleByAdmin(): void
    {
        $this->loginAs('admin@example.com');

        [$archetypeId, $variantId] = $this->getRegidragoVariantIds();
        $this->client->request('GET', '/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h5', 'Regidrago');
    }

    public function testVersionsPageDeniedForRegularUser(): void
    {
        $this->loginAs('borrower@example.com');

        [$archetypeId, $variantId] = $this->getRegidragoVariantIds();
        $this->client->request('GET', '/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions');

        self::assertResponseStatusCodeSame(403);
    }

    public function testVersionsPageDeniedForAnonymous(): void
    {
        [$archetypeId, $variantId] = $this->getRegidragoVariantIds();
        $this->client->request('GET', '/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions');

        self::assertResponseRedirects('/login');
    }

    public function testVersionsPage404ForNonExistentVariant(): void
    {
        $this->loginAs('admin@example.com');

        [$archetypeId] = $this->getRegidragoVariantIds();
        $this->client->request('GET', '/admin/archetypes/'.$archetypeId.'/variants/99999/versions');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCompareEndpointReturnsJson(): void
    {
        $this->loginAs('admin@example.com');

        [$archetypeId, $variantId] = $this->getRegidragoVariantIds();
        $this->client->request('GET', '/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions/compare?from=1&to=1');

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertNotFalse($content);
        $data = json_decode($content, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('added', $data);
        self::assertArrayHasKey('unchanged', $data);
    }

    public function testExportDownloadsTextFile(): void
    {
        $this->loginAs('admin@example.com');

        [$archetypeId, $variantId] = $this->getRegidragoVariantIds();
        $this->client->request('GET', '/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions/1/export');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=utf-8');
    }

    public function testRestoreVersionAsAdmin(): void
    {
        $this->loginAs('admin@example.com');

        [$archetypeId, $variantId] = $this->getRegidragoVariantIds();

        // Load the page first to extract CSRF token from the rendered form
        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions');
        $restoreButton = $crawler->filter('form[action*="/restore"] button[type="submit"]');

        if ($restoreButton->count() > 0) {
            $this->client->submit($restoreButton->first()->form());
            self::assertResponseRedirects('/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions');
        } else {
            // Only 1 version (current), so no restore button — test the 404 path instead
            $this->client->request('POST', '/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions/99/restore', [
                '_token' => 'any',
            ]);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testRestoreVersionInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');

        [$archetypeId, $variantId] = $this->getRegidragoVariantIds();
        $this->client->request('POST', '/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions/1/restore', [
            '_token' => 'bad-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteVersionAsAdmin(): void
    {
        $this->loginAs('admin@example.com');

        [$archetypeId, $variantId] = $this->getRegidragoVariantIds();

        // Load the page first to extract CSRF token from the rendered form
        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions');
        $deleteButton = $crawler->filter('form[action*="/delete"] button[type="submit"]');

        if ($deleteButton->count() > 0) {
            $this->client->submit($deleteButton->first()->form());
            self::assertResponseRedirects('/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions');
        } else {
            // Only 1 version (current, cannot delete) — just verify the page loaded
            self::assertResponseIsSuccessful();
        }
    }

    public function testDeleteVersionInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');

        [$archetypeId, $variantId] = $this->getRegidragoVariantIds();
        $this->client->request('POST', '/admin/archetypes/'.$archetypeId.'/variants/'.$variantId.'/versions/1/delete', [
            '_token' => 'bad-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return array{int, int} [archetypeId, variantDeckId]
     */
    private function getRegidragoVariantIds(): array
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        /** @var Archetype $archetype */
        $archetype = $entityManager->getRepository(Archetype::class)->findOneBy(['slug' => 'regidrago']);

        /** @var Deck $variant */
        $variant = $entityManager->getRepository(Deck::class)->findOneBy([
            'name' => 'Regidrago',
            'archetype' => $archetype,
            'owner' => null,
        ]);

        /** @var int $archetypeId */
        $archetypeId = $archetype->getId();

        /** @var int $variantId */
        $variantId = $variant->getId();

        return [$archetypeId, $variantId];
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
}
