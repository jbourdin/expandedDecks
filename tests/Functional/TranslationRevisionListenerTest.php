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
use App\Entity\DeckTranslation;
use App\Entity\DeckTranslationRevision;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\PageTranslationRevision;
use App\Entity\User;
use App\Enum\DeckFormat;
use App\Enum\TranslationRevisionState;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F9.7 — Translation foundation
 */
class TranslationRevisionListenerTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    /**
     * @return array{Page, PageTranslation}
     */
    private function createPageWithSourceTranslation(EntityManagerInterface $em, string $slug): array
    {
        $page = new Page();
        $page->setSlug($slug);

        $sourceTranslation = new PageTranslation();
        $sourceTranslation->setPage($page);
        $sourceTranslation->setLocale('en');
        $sourceTranslation->setTitle('Revision test');
        $sourceTranslation->setContent('English content');

        $em->persist($page);
        $em->persist($sourceTranslation);
        $em->flush();

        return [$page, $sourceTranslation];
    }

    public function testSourceSaveCreatesAValidatedSourceRevision(): void
    {
        $em = $this->getEntityManager();
        [$page, $sourceTranslation] = $this->createPageWithSourceTranslation($em, 'revision-source-save');

        $revisions = $em->getRepository(PageTranslationRevision::class)->findBy(['page' => $page]);

        self::assertCount(1, $revisions);
        self::assertSame('en', $revisions[0]->getLocale());
        self::assertSame(TranslationRevisionState::Validated, $revisions[0]->getState());
        self::assertNull($revisions[0]->getSourceRevision());
        self::assertSame('Revision test', $revisions[0]->getTitle());
        self::assertSame('English content', $revisions[0]->getContent());
        self::assertFalse($sourceTranslation->isSourceOutdated());
    }

    public function testTranslationRevisionLinksTheLatestSourceRevision(): void
    {
        $em = $this->getEntityManager();
        [$page] = $this->createPageWithSourceTranslation($em, 'revision-linking');

        $frenchTranslation = new PageTranslation();
        $frenchTranslation->setPage($page);
        $frenchTranslation->setLocale('fr');
        $frenchTranslation->setTitle('Test de révision');
        $frenchTranslation->setContent('Contenu français');
        $em->persist($frenchTranslation);
        $em->flush();

        $revisions = $em->getRepository(PageTranslationRevision::class)->findBy(['page' => $page], ['id' => 'ASC']);
        self::assertCount(2, $revisions);
        [$sourceRevision, $frenchRevision] = $revisions;

        self::assertSame('fr', $frenchRevision->getLocale());
        self::assertSame($sourceRevision, $frenchRevision->getSourceRevision());
    }

    public function testSourceUpdateFlagsSiblingTranslationsOutdated(): void
    {
        $em = $this->getEntityManager();
        [$page, $sourceTranslation] = $this->createPageWithSourceTranslation($em, 'revision-outdating');

        $frenchTranslation = new PageTranslation();
        $frenchTranslation->setPage($page);
        $frenchTranslation->setLocale('fr');
        $frenchTranslation->setTitle('Titre');
        $frenchTranslation->setContent('Contenu');
        $em->persist($frenchTranslation);
        $em->flush();
        self::assertFalse($frenchTranslation->isSourceOutdated());

        $sourceTranslation->setContent('Updated English content');
        $em->flush();

        $em->refresh($frenchTranslation);
        $em->refresh($sourceTranslation);
        self::assertTrue($frenchTranslation->isSourceOutdated());
        self::assertFalse($sourceTranslation->isSourceOutdated());

        // A later French rework references the LATEST source revision.
        $frenchTranslation->setContent('Contenu retravaillé');
        $em->flush();

        $revisions = $em->getRepository(PageTranslationRevision::class)->findBy(['page' => $page], ['id' => 'ASC']);
        self::assertCount(4, $revisions);
        $latestSourceRevision = $revisions[2];
        $latestFrenchRevision = $revisions[3];
        self::assertSame('en', $latestSourceRevision->getLocale());
        self::assertSame('fr', $latestFrenchRevision->getLocale());
        self::assertSame($latestSourceRevision, $latestFrenchRevision->getSourceRevision());
    }

    public function testNonTranslatableOnlyChangeProducesNoRevision(): void
    {
        $em = $this->getEntityManager();
        [$page, $sourceTranslation] = $this->createPageWithSourceTranslation($em, 'revision-og-exempt');

        $sourceTranslation->setOgImage('/images/social-card.png');
        $em->flush();

        // A no-op save must stay silent too.
        $em->flush();

        $revisions = $em->getRepository(PageTranslationRevision::class)->findBy(['page' => $page]);
        self::assertCount(1, $revisions);
    }

    public function testVariantNotesAreSnapshottedUserDeckNotesAreNot(): void
    {
        $em = $this->getEntityManager();

        $archetype = new Archetype();
        $archetype->setName('Revision Snapshot Subject');
        $em->persist($archetype);

        $variant = new Deck();
        $variant->setName('Variant with notes');
        $variant->setArchetype($archetype);
        $variant->setOwner(null);
        $variant->setFormat(DeckFormat::Expanded);
        $variant->setNotes('English variant notes');
        $em->persist($variant);

        $owner = $em->getRepository(User::class)->createQueryBuilder('u')->setMaxResults(1)->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(User::class, $owner);

        $userDeck = new Deck();
        $userDeck->setName('User deck with notes');
        $userDeck->setOwner($owner);
        $userDeck->setFormat(DeckFormat::Expanded);
        $userDeck->setNotes('Personal notes');
        $em->persist($userDeck);

        $em->flush();

        $variantRevisions = $em->getRepository(DeckTranslationRevision::class)->findBy(['deck' => $variant]);
        self::assertCount(1, $variantRevisions);
        self::assertSame('en', $variantRevisions[0]->getLocale());
        self::assertSame('English variant notes', $variantRevisions[0]->getNotes());

        self::assertCount(0, $em->getRepository(DeckTranslationRevision::class)->findBy(['deck' => $userDeck]));

        // A French DeckTranslation row references the variant's source revision.
        $frenchNotes = new DeckTranslation();
        $frenchNotes->setDeck($variant);
        $frenchNotes->setLocale('fr');
        $frenchNotes->setNotes('Notes de variante en français');
        $em->persist($frenchNotes);
        $em->flush();

        $allRevisions = $em->getRepository(DeckTranslationRevision::class)->findBy(['deck' => $variant], ['id' => 'ASC']);
        self::assertCount(2, $allRevisions);
        self::assertSame('fr', $allRevisions[1]->getLocale());
        self::assertSame($allRevisions[0], $allRevisions[1]->getSourceRevision());

        // Updating the variant's source notes flags the French row.
        $variant->setNotes('Updated English variant notes');
        $em->flush();

        $em->refresh($frenchNotes);
        self::assertTrue($frenchNotes->isSourceOutdated());
    }

    public function testSameFlushSourceAndTranslationLinkInFlushRevision(): void
    {
        $em = $this->getEntityManager();

        $page = new Page();
        $page->setSlug('revision-same-flush');

        $sourceTranslation = new PageTranslation();
        $sourceTranslation->setPage($page);
        $sourceTranslation->setLocale('en');
        $sourceTranslation->setTitle('Same flush');
        $sourceTranslation->setContent('English content');

        $frenchTranslation = new PageTranslation();
        $frenchTranslation->setPage($page);
        $frenchTranslation->setLocale('fr');
        $frenchTranslation->setTitle('Même flush');
        $frenchTranslation->setContent('Contenu français');

        $em->persist($page);
        $em->persist($sourceTranslation);
        $em->persist($frenchTranslation);
        $em->flush();

        $revisions = $em->getRepository(PageTranslationRevision::class)->findBy(['page' => $page], ['id' => 'ASC']);
        self::assertCount(2, $revisions);
        self::assertSame('en', $revisions[0]->getLocale());
        self::assertSame($revisions[0], $revisions[1]->getSourceRevision());
    }
}
