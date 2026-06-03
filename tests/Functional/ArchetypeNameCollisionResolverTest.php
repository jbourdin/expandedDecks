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
use App\Service\ArchetypeNameCollisionResolver;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.31 — Rename soft-deleted name/slug conflicts on creation
 */
class ArchetypeNameCollisionResolverTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function getResolver(): ArchetypeNameCollisionResolver
    {
        /** @var ArchetypeNameCollisionResolver $resolver */
        $resolver = static::getContainer()->get(ArchetypeNameCollisionResolver::class);

        return $resolver;
    }

    /**
     * Persist a soft-deleted archetype with the given name and return it.
     */
    private function persistSoftDeleted(string $name): Archetype
    {
        $entityManager = $this->getEntityManager();

        $archetype = new Archetype();
        $archetype->setName($name);
        $archetype->setDeletedAt(new \DateTimeImmutable());
        $entityManager->persist($archetype);
        $entityManager->flush();

        return $archetype;
    }

    public function testNameCollisionWithSoftDeletedRowIsResolved(): void
    {
        $entityManager = $this->getEntityManager();
        $deleted = $this->persistSoftDeleted('Collision Box');
        $deletedId = $deleted->getId();

        // Recreate an archetype with the exact same name.
        $this->getResolver()->resolve('Collision Box');

        $fresh = new Archetype();
        $fresh->setName('Collision Box');
        $entityManager->persist($fresh);
        $entityManager->flush();

        // The new row keeps the canonical name and slug.
        self::assertSame('Collision Box', $fresh->getName());
        self::assertSame('collision-box', $fresh->getSlug());

        // The soft-deleted row was renamed out of the way but stays deleted.
        $entityManager->refresh($deleted);
        self::assertNotSame('Collision Box', $deleted->getName());
        self::assertStringStartsWith('Collision Box__deleted_', $deleted->getName());
        self::assertStringStartsWith('collision-box__deleted_', $deleted->getSlug());
        self::assertNotNull($deleted->getDeletedAt());
        self::assertSame($deletedId, $deleted->getId());
    }

    public function testSlugCollisionViaDifferingNamesIsResolved(): void
    {
        $entityManager = $this->getEntityManager();
        // Different display strings that slugify to the same value: the names do
        // not collide but the slugs (`gardevoir-ex`) do.
        $deleted = $this->persistSoftDeleted('Gardevoir EX');
        self::assertSame('gardevoir-ex', $deleted->getSlug());

        $this->getResolver()->resolve('Gardevoir ex');

        $fresh = new Archetype();
        $fresh->setName('Gardevoir ex');
        $entityManager->persist($fresh);
        $entityManager->flush();

        self::assertSame('gardevoir-ex', $fresh->getSlug());

        $entityManager->refresh($deleted);
        self::assertStringStartsWith('gardevoir-ex__deleted_', $deleted->getSlug());
    }

    public function testNoCollisionLeavesEverythingUnchanged(): void
    {
        $entityManager = $this->getEntityManager();

        // No soft-deleted row holds this name/slug — resolve is a no-op.
        $this->getResolver()->resolve('Brand New Archetype');

        $fresh = new Archetype();
        $fresh->setName('Brand New Archetype');
        $entityManager->persist($fresh);
        $entityManager->flush();

        self::assertSame('brand-new-archetype', $fresh->getSlug());
    }

    public function testResolveExcludesGivenIdSoItNeverRenamesItself(): void
    {
        // A live archetype being edited must not rename itself even if its own
        // name matches the lookup — excludeId guards the edit path.
        $entityManager = $this->getEntityManager();

        $archetype = new Archetype();
        $archetype->setName('Self Exclude Box');
        $entityManager->persist($archetype);
        $entityManager->flush();

        // Soft-delete it so it would otherwise be a rename candidate.
        $archetype->setDeletedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->getResolver()->resolve('Self Exclude Box', $archetype->getId());

        $entityManager->refresh($archetype);
        self::assertSame('Self Exclude Box', $archetype->getName());
        self::assertSame('self-exclude-box', $archetype->getSlug());
    }
}
