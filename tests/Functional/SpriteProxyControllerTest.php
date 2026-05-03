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

use App\Entity\PokemonSpriteMapping;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
class SpriteProxyControllerTest extends AbstractFunctionalTest
{
    private const string TEST_SLUG = 'test-sprite';

    /** @var list<string> sprite files written during the test, cleaned up in tearDown */
    private array $writtenSprites = [];

    protected function tearDown(): void
    {
        foreach ($this->writtenSprites as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->writtenSprites = [];

        parent::tearDown();
    }

    public function testPokemonReturnsCachedSpriteWithImagePngHeaders(): void
    {
        $this->writeSpriteToCache(self::TEST_SLUG, 'fake-png-bytes');

        $this->client->request('GET', '/sprites/pokemon/'.self::TEST_SLUG.'.png');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertSame('fake-png-bytes', $this->client->getResponse()->getContent());
        // Cache-Control is intentionally not asserted: Symfony's SessionListener
        // rewrites it to no-store/private for any request that touched the
        // session, which WebTestCase does on every dispatch.
    }

    public function testPokemonReturns404WhenSpriteCannotBeResolved(): void
    {
        // Slug not in mapping table and not in cache — resolver returns null.
        $this->client->request('GET', '/sprites/pokemon/phantom-pokemon-no-mapping.png');

        self::assertResponseStatusCodeSame(404);
    }

    public function testSlugsReturnsJsonListOfMappedSlugs(): void
    {
        $this->persistMapping('pikachu', 25);
        $this->persistMapping('bulbasaur', 1);

        $this->client->request('GET', '/api/sprites/slugs');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        /** @var list<string> $slugs */
        $slugs = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($slugs);
        self::assertContains('pikachu', $slugs);
        self::assertContains('bulbasaur', $slugs);
        // Repository orders alphabetically.
        self::assertSame('bulbasaur', $slugs[0]);
    }

    private function writeSpriteToCache(string $slug, string $content): void
    {
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        \assert(\is_string($projectDir));
        $cacheDir = $projectDir.'/var/cache/sprites';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0o777, true);
        }

        $path = $cacheDir.'/'.$slug.'.png';
        file_put_contents($path, $content);

        $this->writtenSprites[] = $path;
    }

    private function persistMapping(string $slug, int $pokedexId): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $mapping = new PokemonSpriteMapping();
        $mapping->setSlug($slug);
        $mapping->setPokedexId($pokedexId);
        $em->persist($mapping);
        $em->flush();
    }
}
