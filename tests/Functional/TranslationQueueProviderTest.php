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
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\User;
use App\Enum\DeckFormat;
use App\Service\Translation\TranslationDraftProvider;
use App\Service\Translation\TranslationQueueProvider;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F9.12 — Translation queue
 */
class TranslationQueueProviderTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function getQueueProvider(): TranslationQueueProvider
    {
        /** @var TranslationQueueProvider $provider */
        $provider = static::getContainer()->get(TranslationQueueProvider::class);

        return $provider;
    }

    private function userByEmail(string $email): User
    {
        $user = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
        \assert($user instanceof User);

        return $user;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function findRow(array $rows, int $contentId, string $locale): ?array
    {
        foreach ($rows as $row) {
            if ($row['contentId'] === $contentId && $row['locale'] === $locale) {
                return $row;
            }
        }

        return null;
    }

    public function testContributorSeesOwnWorkAndOutdatedInAssignedLocales(): void
    {
        $em = $this->getEntityManager();
        $translator = $this->userByEmail('translator@example.com');
        $this->client->loginUser($translator);

        // A page with an FR translation that goes stale.
        $page = new Page();
        $page->setSlug('queue-contributor');
        $source = new PageTranslation();
        $source->setPage($page);
        $source->setLocale('en');
        $source->setTitle('Queue source');
        $source->setContent('English content');
        $french = new PageTranslation();
        $french->setPage($page);
        $french->setLocale('fr');
        $french->setTitle('Titre');
        $french->setContent('Contenu');
        $em->persist($page);
        $em->persist($source);
        $em->persist($french);
        $em->flush();

        $source->setContent('Changed English content');
        $em->flush();

        // Plus a pending draft of their own on another page.
        $draftPage = new Page();
        $draftPage->setSlug('queue-contributor-draft');
        $draftSource = new PageTranslation();
        $draftSource->setPage($draftPage);
        $draftSource->setLocale('en');
        $draftSource->setTitle('Draft queue source');
        $draftSource->setContent('English content');
        $em->persist($draftPage);
        $em->persist($draftSource);
        $em->flush();

        /** @var TranslationDraftProvider $draftProvider */
        $draftProvider = static::getContainer()->get(TranslationDraftProvider::class);
        $draftProvider->findOrCreateDraft($draftPage, 'fr');
        $em->flush();

        $queue = $this->getQueueProvider()->contributorQueue($translator);

        $pageId = $page->getId();
        $draftPageId = $draftPage->getId();
        \assert(null !== $pageId && null !== $draftPageId);

        $outdatedRow = self::findRow($queue['pages'], $pageId, 'fr');
        self::assertNotNull($outdatedRow, 'Outdated FR translation must appear for a translator assigned FR.');
        self::assertTrue($outdatedRow['sourceOutdated']);
        self::assertSame('Queue source', $outdatedRow['label']);

        $draftRow = self::findRow($queue['pages'], $draftPageId, 'fr');
        self::assertNotNull($draftRow);
        self::assertSame('draft', $draftRow['state']);
    }

    public function testReviewerSeesPendingSubmissionsAndVariantAggregation(): void
    {
        $em = $this->getEntityManager();
        $translator = $this->userByEmail('translator@example.com');
        $admin = $this->userByEmail('admin@example.com');
        $this->client->loginUser($translator);

        $archetype = new Archetype();
        $archetype->setName('Queue Aggregation Subject');
        $archetypeSource = new ArchetypeTranslation();
        $archetypeSource->setArchetype($archetype);
        $archetypeSource->setLocale('en');
        $archetypeSource->setName('Queue Aggregation Subject');
        $em->persist($archetype);
        $em->persist($archetypeSource);

        $variant = new Deck();
        $variant->setName('Queue variant');
        $variant->setArchetype($archetype);
        $variant->setOwner(null);
        $variant->setFormat(DeckFormat::Expanded);
        $variant->setNotes('English variant notes');
        $em->persist($variant);
        $em->flush();

        /** @var TranslationDraftProvider $draftProvider */
        $draftProvider = static::getContainer()->get(TranslationDraftProvider::class);
        /** @var \App\Service\Translation\TranslationReviewService $reviewService */
        $reviewService = static::getContainer()->get(\App\Service\Translation\TranslationReviewService::class);

        $variantDraft = $draftProvider->findOrCreateDraft($variant, 'fr');
        \assert($variantDraft instanceof \App\Entity\DeckTranslationRevision);
        $variantDraft->setNotes('Notes FR');
        $reviewService->submit($variantDraft);

        $queue = $this->getQueueProvider()->reviewerQueue($admin);

        $archetypeId = $archetype->getId();
        \assert(null !== $archetypeId);

        // The variant's pending work aggregates on the archetype row — no
        // stand-alone deck rows anywhere.
        $row = self::findRow($queue['archetypes'], $archetypeId, 'fr');
        self::assertNotNull($row, 'Variant work must surface as an archetype context row.');
        self::assertSame(1, $row['pendingVariants']);
        self::assertSame('Queue Aggregation Subject', $row['label']);
    }

    public function testOutdatedRowsSurfaceForEveryContentType(): void
    {
        $em = $this->getEntityManager();
        $admin = $this->userByEmail('admin@example.com');
        $translator = $this->userByEmail('translator@example.com');
        $this->client->loginUser($translator);

        // Archetype with an FR translation.
        $archetype = new Archetype();
        $archetype->setName('Queue Outdated Archetype');
        $archetypeSource = new ArchetypeTranslation();
        $archetypeSource->setArchetype($archetype);
        $archetypeSource->setLocale('en');
        $archetypeSource->setName('Queue Outdated Archetype');
        $archetypeFrench = new ArchetypeTranslation();
        $archetypeFrench->setArchetype($archetype);
        $archetypeFrench->setLocale('fr');
        $archetypeFrench->setName('Archétype à mettre à jour');
        $em->persist($archetype);
        $em->persist($archetypeSource);
        $em->persist($archetypeFrench);

        // Menu category with an FR translation.
        $menuCategory = new \App\Entity\MenuCategory();
        $menuCategorySource = new \App\Entity\MenuCategoryTranslation();
        $menuCategorySource->setMenuCategory($menuCategory);
        $menuCategorySource->setLocale('en');
        $menuCategorySource->setName('Queue Outdated Guides');
        $menuCategoryFrench = new \App\Entity\MenuCategoryTranslation();
        $menuCategoryFrench->setMenuCategory($menuCategory);
        $menuCategoryFrench->setLocale('fr');
        $menuCategoryFrench->setName('Guides à mettre à jour');
        $em->persist($menuCategory);
        $em->persist($menuCategorySource);
        $em->persist($menuCategoryFrench);

        // Variant with an FR notes translation.
        $variant = new Deck();
        $variant->setName('Queue outdated variant');
        $variant->setArchetype($archetype);
        $variant->setOwner(null);
        $variant->setFormat(DeckFormat::Expanded);
        $variant->setNotes('English variant notes');
        $em->persist($variant);
        $variantFrench = new \App\Entity\DeckTranslation();
        $variantFrench->setDeck($variant);
        $variantFrench->setLocale('fr');
        $variantFrench->setNotes('Notes FR');
        $em->persist($variantFrench);
        $em->flush();

        // Every source moves on: all three FR rows flip to outdated.
        $archetypeSource->setDescription('Fresh description');
        $menuCategorySource->setName('Queue Outdated Guides v2');
        $variant->setNotes('Fresh English variant notes');
        $em->flush();

        $queue = $this->getQueueProvider()->reviewerQueue($admin);

        $archetypeId = $archetype->getId();
        $menuCategoryId = $menuCategory->getId();
        \assert(null !== $archetypeId && null !== $menuCategoryId);

        $archetypeRow = self::findRow($queue['archetypes'], $archetypeId, 'fr');
        self::assertNotNull($archetypeRow);
        self::assertTrue($archetypeRow['sourceOutdated']);
        self::assertSame(1, $archetypeRow['outdatedVariants']);
        self::assertSame('Queue Outdated Archetype', $archetypeRow['label']);

        $menuCategoryRow = self::findRow($queue['menuCategories'], $menuCategoryId, 'fr');
        self::assertNotNull($menuCategoryRow);
        self::assertTrue($menuCategoryRow['sourceOutdated']);
    }

    public function testContributorWithoutLocalesSeesNoOutdatedRows(): void
    {
        $admin = $this->userByEmail('admin@example.com');
        $queue = $this->getQueueProvider()->contributorQueue($admin);

        self::assertSame(['pages', 'archetypes', 'menuCategories', 'bannedCards', 'stapleCards'], array_keys($queue));
        foreach (['pages', 'archetypes', 'menuCategories', 'bannedCards', 'stapleCards'] as $tab) {
            foreach ($queue[$tab] as $row) {
                self::assertFalse($row['sourceOutdated'], 'Admin has no translation locales: outdated rows must not leak.');
            }
        }
    }
}
