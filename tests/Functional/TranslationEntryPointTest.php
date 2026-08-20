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
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Translate entry points on secondary views: every place a content can be
 * edited from also offers translation (US-T1 follow-up).
 *
 * @see docs/features.md F9.10 — Contextual translation view (entry points)
 */
class TranslationEntryPointTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function loginByEmail(string $email): User
    {
        $user = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
        \assert($user instanceof User);
        $this->client->loginUser($user);

        return $user;
    }

    private function publishedArchetypeId(): int
    {
        $archetype = $this->getEntityManager()->getRepository(Archetype::class)->findOneBy(['isPublished' => true]);
        \assert($archetype instanceof Archetype);
        $id = $archetype->getId();
        \assert(null !== $id);

        return $id;
    }

    public function testArchetypeCatalogOffersTranslateToTranslators(): void
    {
        $archetypeId = $this->publishedArchetypeId();

        $this->loginByEmail('translator@example.com');
        $crawler = $this->client->request('GET', '/en/archetypes');
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(
            1,
            $crawler->filter(\sprintf('a[href="/admin/translations/archetype/%d/fr"]', $archetypeId))->count(),
            'Translator must see the translate entry on the public catalog.',
        );

        // A user without translation rights sees no translate action.
        $this->loginByEmail('borrower@example.com');
        $crawler = $this->client->request('GET', '/en/archetypes');
        self::assertSame(0, $crawler->filter('a[href*="/admin/translations/"]')->count());
    }

    public function testAdminArchetypeListOffersTranslateToEditorTranslators(): void
    {
        $archetypeId = $this->publishedArchetypeId();

        // The admin list requires the editor role; the translate entry only
        // appears for editors who are also translators for a locale.
        $em = $this->getEntityManager();
        $translator = $em->getRepository(User::class)->findOneBy(['email' => 'translator@example.com']);
        \assert($translator instanceof User);
        $translator->setRoles([...$translator->getRoles(), 'ROLE_ARCHETYPE_EDITOR']);
        $em->flush();

        $this->client->loginUser($translator);
        $crawler = $this->client->request('GET', '/admin/archetypes');
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(
            1,
            $crawler->filter(\sprintf('a[href="/admin/translations/archetype/%d/fr"]', $archetypeId))->count(),
            'Editor-translator must see the translate entry on the admin list.',
        );
    }
}
