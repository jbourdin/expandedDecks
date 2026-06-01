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
use App\Entity\ArchetypeTranslation;
use App\Entity\Deck;
use App\Enum\DeckFormat;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.27 — Archetype publication dates
 */
class ArchetypeFreshnessListenerTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    public function testCreatingAVariantBumpsArchetypeLastPublishedAt(): void
    {
        $em = $this->getEntityManager();

        $archetype = new Archetype();
        $archetype->setName('Freshness Variant Subject');
        $archetype->setIsPublished(true);
        $translation = new ArchetypeTranslation();
        $translation->setArchetype($archetype);
        $translation->setLocale('en');
        $translation->setName('Freshness Variant Subject');
        $archetype->addTranslation($translation);
        $em->persist($archetype);
        $em->persist($translation);
        $em->flush();

        // Re-read so we capture the listener's post-flush bump from the row.
        $em->refresh($archetype);
        $initialLastPublishedAt = $archetype->getLastPublishedAt();
        self::assertNotNull($initialLastPublishedAt);

        // Make sure the variant's bump can be distinguished from the archetype's own.
        sleep(1);

        $variant = new Deck();
        $variant->setName('Variant A');
        $variant->setArchetype($archetype);
        $variant->setOwner(null);
        $variant->setFormat(DeckFormat::Expanded);
        $em->persist($variant);
        $em->flush();

        $em->refresh($archetype);
        $bumpedLastPublishedAt = $archetype->getLastPublishedAt();

        self::assertNotNull($bumpedLastPublishedAt);
        self::assertGreaterThan($initialLastPublishedAt, $bumpedLastPublishedAt);
    }

    public function testOwnedDeckDoesNotBumpArchetype(): void
    {
        $em = $this->getEntityManager();

        $archetype = $em->getRepository(Archetype::class)->findOneBy(['slug' => 'regidrago']);
        self::assertNotNull($archetype);

        $em->refresh($archetype);
        $before = $archetype->getLastPublishedAt();
        self::assertNotNull($before);

        sleep(1);

        // Owned deck (created with a real owner) — should NOT bump the archetype.
        $owner = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@expandeddecks.local'])
            ?? $em->getRepository(\App\Entity\User::class)->createQueryBuilder('u')->setMaxResults(1)->getQuery()->getOneOrNullResult();
        self::assertNotNull($owner);

        $deck = new Deck();
        $deck->setName('Owned, non-variant Regidrago');
        $deck->setArchetype($archetype);
        $deck->setOwner($owner);
        $deck->setFormat(DeckFormat::Expanded);
        $em->persist($deck);
        $em->flush();

        $em->refresh($archetype);
        self::assertEquals($before, $archetype->getLastPublishedAt());
    }

    /**
     * A drag-and-drop variant reorder only moves `position`; it must touch
     * neither the archetype's freshness signal nor the deck's own `updatedAt`.
     *
     * @see docs/features.md F18.19 — Archetype variant ordering
     */
    public function testReorderingAVariantDoesNotBumpTimestamps(): void
    {
        $em = $this->getEntityManager();

        $archetype = $em->getRepository(Archetype::class)->findOneBy(['slug' => 'regidrago']);
        self::assertNotNull($archetype);
        $em->refresh($archetype);
        $archetypeBefore = $archetype->getLastPublishedAt();
        self::assertNotNull($archetypeBefore);

        $variant = $em->getRepository(Deck::class)->findOneBy(['archetype' => $archetype, 'owner' => null]);
        self::assertNotNull($variant);
        $deckBefore = $variant->getUpdatedAt();

        sleep(1);

        $variant->setPosition($variant->getPosition() + 100);
        $em->flush();

        $em->refresh($archetype);
        $em->refresh($variant);

        self::assertEquals($archetypeBefore, $archetype->getLastPublishedAt());
        self::assertEquals($deckBefore, $variant->getUpdatedAt());
    }

    /**
     * Editing a variant's content (not its position) is real activity and must
     * still bump both the archetype's freshness signal and the deck timestamp.
     *
     * @see docs/features.md F18.19 — Archetype variant ordering
     */
    public function testEditingAVariantStillBumpsTimestamps(): void
    {
        $em = $this->getEntityManager();

        $archetype = $em->getRepository(Archetype::class)->findOneBy(['slug' => 'regidrago']);
        self::assertNotNull($archetype);
        $em->refresh($archetype);
        $archetypeBefore = $archetype->getLastPublishedAt();
        self::assertNotNull($archetypeBefore);

        $variant = $em->getRepository(Deck::class)->findOneBy(['archetype' => $archetype, 'owner' => null]);
        self::assertNotNull($variant);

        sleep(1);

        $variant->setName($variant->getName().' (edited)');
        $em->flush();

        $em->refresh($archetype);
        $em->refresh($variant);

        self::assertGreaterThan($archetypeBefore, $archetype->getLastPublishedAt());
        self::assertNotNull($variant->getUpdatedAt());
    }

    /**
     * Editing a localized name/description dirties only the translation child,
     * never the owning archetype — the freshness signal must still refresh.
     *
     * @see docs/features.md F2.27 — Archetype publication dates
     */
    public function testEditingATranslationBumpsArchetypeLastPublishedAt(): void
    {
        $em = $this->getEntityManager();

        $archetype = $em->getRepository(Archetype::class)->findOneBy(['slug' => 'regidrago']);
        self::assertNotNull($archetype);
        $em->refresh($archetype);
        $archetypeBefore = $archetype->getLastPublishedAt();
        self::assertNotNull($archetypeBefore);

        $translation = $archetype->getTranslation('en');
        self::assertInstanceOf(ArchetypeTranslation::class, $translation);

        sleep(1);

        $translation->setDescription(($translation->getDescription() ?? '').' (edited)');
        $em->flush();

        $em->refresh($archetype);

        self::assertGreaterThan($archetypeBefore, $archetype->getLastPublishedAt());
    }
}
