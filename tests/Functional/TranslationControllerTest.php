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
use App\Entity\PageTranslationRevision;
use App\Entity\User;
use App\Enum\DeckFormat;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 * @see docs/features.md F9.11 — Moderation review mode
 * @see docs/features.md F9.12 — Translation queue
 */
class TranslationControllerTest extends AbstractFunctionalTest
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

    /**
     * A channel that serves French — required for the reader-notice test,
     * which renders the FR version of a page.
     */
    private function frenchServingChannel(): \App\Entity\Channel
    {
        $channels = $this->getEntityManager()->getRepository(\App\Entity\Channel::class)->findAll();
        foreach ($channels as $channel) {
            if (\in_array('fr', $channel->getLocales(), true)) {
                return $channel;
            }
        }

        self::fail('No fixture channel serves French.');
    }

    private function createPageWithSource(string $slug): Page
    {
        $em = $this->getEntityManager();

        $page = new Page();
        $page->setChannel($this->frenchServingChannel());
        $page->setSlug($slug);

        $sourceTranslation = new PageTranslation();
        $sourceTranslation->setPage($page);
        $sourceTranslation->setLocale('en');
        $sourceTranslation->setTitle('Controller test');
        $sourceTranslation->setContent('English content');
        $page->addTranslation($sourceTranslation);

        $em->persist($page);
        $em->persist($sourceTranslation);
        $em->flush();

        return $page;
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['HTTP_X-CSRF-Token' => $this->ajaxCsrfToken(), 'CONTENT_TYPE' => 'application/json'];
    }

    public function testQueuePageRequiresTranslationRole(): void
    {
        $this->loginByEmail('borrower@example.com');
        $this->client->request('GET', '/admin/translations');

        self::assertResponseStatusCodeSame(403);
    }

    public function testQueuePageRendersForTranslator(): void
    {
        $this->loginByEmail('translator@example.com');
        $this->client->request('GET', '/admin/translations');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#translation-queue-root');
    }

    public function testQueueDataExposesFlavorsByRole(): void
    {
        $this->loginByEmail('translator@example.com');
        $this->client->request('GET', '/admin/translations/queue-data');
        self::assertResponseIsSuccessful();
        /** @var array{contributor: ?array<string, mixed>, reviewer: ?array<string, mixed>} $translatorData */
        $translatorData = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertNotNull($translatorData['contributor']);
        self::assertNull($translatorData['reviewer']);

        $this->loginByEmail('admin@example.com');
        $this->client->request('GET', '/admin/translations/queue-data');
        /** @var array{contributor: ?array<string, mixed>, reviewer: ?array<string, mixed>} $adminData */
        $adminData = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertNull($adminData['contributor']);
        self::assertNotNull($adminData['reviewer']);
    }

    public function testEditViewRendersSectionsForTranslator(): void
    {
        $page = $this->createPageWithSource('controller-edit-view');
        $this->loginByEmail('translator@example.com');

        $pageId = $page->getId();
        \assert(null !== $pageId);
        $crawler = $this->client->request('GET', \sprintf('/admin/translations/page/%d/fr', $pageId));

        self::assertResponseIsSuccessful();
        $root = $crawler->filter('#translation-editor-root');
        self::assertSame(1, $root->count());
        /** @var list<array<string, mixed>> $sections */
        $sections = json_decode($root->attr('data-sections') ?? '[]', true);
        self::assertCount(1, $sections);
        self::assertSame('page', $sections[0]['contentType']);
        self::assertSame('true', $root->attr('data-can-translate'));
        // A brand-new translation has nothing pinned yet: never stale (US-T5
        // only concerns existing work on an older source).
        self::assertFalse($sections[0]['sourceStale']);
    }

    public function testEditViewDeniedForUnrelatedUser(): void
    {
        $page = $this->createPageWithSource('controller-edit-denied');
        $this->loginByEmail('borrower@example.com');

        $pageId = $page->getId();
        \assert(null !== $pageId);
        $this->client->request('GET', \sprintf('/admin/translations/page/%d/fr', $pageId));

        self::assertResponseStatusCodeSame(403);
    }

    public function testArchetypeContextIncludesVariantSections(): void
    {
        $em = $this->getEntityManager();

        $archetype = new Archetype();
        $archetype->setName('Controller Context Subject');
        $archetypeSource = new ArchetypeTranslation();
        $archetypeSource->setArchetype($archetype);
        $archetypeSource->setLocale('en');
        $archetypeSource->setName('Controller Context Subject');
        $em->persist($archetype);
        $em->persist($archetypeSource);

        $variant = new Deck();
        $variant->setName('Context variant');
        $variant->setArchetype($archetype);
        $variant->setOwner(null);
        $variant->setFormat(DeckFormat::Expanded);
        $variant->setNotes('English variant notes');
        $em->persist($variant);
        $em->flush();

        $this->loginByEmail('translator@example.com');
        $archetypeId = $archetype->getId();
        \assert(null !== $archetypeId);
        $crawler = $this->client->request('GET', \sprintf('/admin/translations/archetype/%d/fr', $archetypeId));

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $sections */
        $sections = json_decode($crawler->filter('#translation-editor-root')->attr('data-sections') ?? '[]', true);
        self::assertCount(2, $sections);
        self::assertSame('archetype', $sections[0]['contentType']);
        self::assertSame('deck', $sections[1]['contentType']);
        self::assertSame('Context variant', $sections[1]['variantName']);
    }

    public function testDraftAutosaveThenSubmitLifecycleOverHttp(): void
    {
        $page = $this->createPageWithSource('controller-lifecycle');
        $this->loginByEmail('translator@example.com');
        $pageId = $page->getId();
        \assert(null !== $pageId);

        // Autosave creates the draft and applies only Translatable fields.
        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/draft', $pageId), [], [], $this->ajaxHeaders(), json_encode([
            'fields' => ['title' => 'Titre HTTP', 'content' => 'Contenu HTTP', 'locale' => 'de'],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        /** @var array{revisionId: int, state: string, applied: list<string>} $draftData */
        $draftData = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('draft', $draftData['state']);
        self::assertSame(['title', 'content'], $draftData['applied']);

        // Submit moves it to pending review.
        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/submit', $pageId), [], [], $this->ajaxHeaders(), '{}');
        self::assertResponseIsSuccessful();
        /** @var array{state: string} $submitData */
        $submitData = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('pending_review', $submitData['state']);

        // Approval by a moderator lands it on the live row.
        $this->loginByEmail('admin@example.com');
        $this->client->request('POST', \sprintf('/admin/translations/page/revisions/%d/approve', $draftData['revisionId']), [], [], $this->ajaxHeaders(), '{}');
        self::assertResponseIsSuccessful();

        $em = $this->getEntityManager();
        $live = $em->getRepository(PageTranslation::class)->findOneBy(['page' => $page, 'locale' => 'fr']);
        self::assertInstanceOf(PageTranslation::class, $live);
        self::assertSame('Titre HTTP', $live->getTitle());
    }

    public function testStaleSubmitReturnsConflictWithTranslatedError(): void
    {
        $page = $this->createPageWithSource('controller-stale');
        $this->loginByEmail('translator@example.com');
        $pageId = $page->getId();
        \assert(null !== $pageId);

        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/draft', $pageId), [], [], $this->ajaxHeaders(), json_encode([
            'fields' => ['title' => 'Basé sur v1'],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        // The source moves on.
        $em = $this->getEntityManager();
        $sourceTranslation = $em->getRepository(PageTranslation::class)->findOneBy(['page' => $page, 'locale' => 'en']);
        \assert($sourceTranslation instanceof PageTranslation);
        $sourceTranslation->setContent('Moved on');
        $em->flush();

        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/submit', $pageId), [], [], $this->ajaxHeaders(), '{}');
        self::assertResponseStatusCodeSame(409);
        /** @var array{error: string} $errorData */
        $errorData = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertNotSame('', $errorData['error']);
        self::assertStringNotContainsString('app.translation', $errorData['error'], 'Error must be translated, not a raw key.');
    }

    public function testRejectAndReworkOverHttp(): void
    {
        $page = $this->createPageWithSource('controller-reject');
        $this->loginByEmail('translator@example.com');
        $pageId = $page->getId();
        \assert(null !== $pageId);

        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/draft', $pageId), [], [], $this->ajaxHeaders(), json_encode([
            'fields' => ['title' => 'Brouillon'],
        ], \JSON_THROW_ON_ERROR));
        /** @var array{revisionId: int} $draftData */
        $draftData = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/submit', $pageId), [], [], $this->ajaxHeaders(), '{}');

        $this->loginByEmail('admin@example.com');
        $this->client->request('POST', \sprintf('/admin/translations/page/revisions/%d/reject', $draftData['revisionId']), [], [], $this->ajaxHeaders(), json_encode([
            'comment' => 'Tutoiement requis.',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $this->loginByEmail('translator@example.com');
        $this->client->request('POST', \sprintf('/admin/translations/page/revisions/%d/rework', $draftData['revisionId']), [], [], $this->ajaxHeaders(), '{}');
        self::assertResponseIsSuccessful();

        $revision = $this->getEntityManager()->getRepository(PageTranslationRevision::class)->find($draftData['revisionId']);
        self::assertInstanceOf(PageTranslationRevision::class, $revision);
        self::assertSame('Tutoiement requis.', $revision->getReviewComment());
        self::assertSame('draft', $revision->getState()->value);
    }

    public function testMenuCategoryEditViewRendersSingleSection(): void
    {
        $em = $this->getEntityManager();

        $menuCategory = new \App\Entity\MenuCategory();
        $menuCategorySource = new \App\Entity\MenuCategoryTranslation();
        $menuCategorySource->setMenuCategory($menuCategory);
        $menuCategorySource->setLocale('en');
        $menuCategorySource->setName('Controller Guides');
        $em->persist($menuCategory);
        $em->persist($menuCategorySource);
        $em->flush();

        $this->loginByEmail('translator@example.com');
        $menuCategoryId = $menuCategory->getId();
        \assert(null !== $menuCategoryId);
        $crawler = $this->client->request('GET', \sprintf('/admin/translations/menu_category/%d/fr', $menuCategoryId));

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $sections */
        $sections = json_decode($crawler->filter('#translation-editor-root')->attr('data-sections') ?? '[]', true);
        self::assertCount(1, $sections);
        self::assertSame('menu_category', $sections[0]['contentType']);
        /** @var list<array<string, mixed>> $fields */
        $fields = $sections[0]['fields'];
        self::assertSame('name', $fields[0]['name']);
        self::assertSame('Controller Guides', $fields[0]['source']);
    }

    public function testSubmitWithoutPendingRevisionReturnsNotFound(): void
    {
        $page = $this->createPageWithSource('controller-no-pending');
        $this->loginByEmail('translator@example.com');
        $pageId = $page->getId();
        \assert(null !== $pageId);

        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/submit', $pageId), [], [], $this->ajaxHeaders(), '{}');
        self::assertResponseStatusCodeSame(404);
    }

    public function testApproveFromInvalidStateReturnsConflict(): void
    {
        $page = $this->createPageWithSource('controller-invalid-state');
        $this->loginByEmail('translator@example.com');
        $pageId = $page->getId();
        \assert(null !== $pageId);

        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/draft', $pageId), [], [], $this->ajaxHeaders(), '{"fields":{"title":"Brouillon"}}');
        /** @var array{revisionId: int} $draftData */
        $draftData = json_decode((string) $this->client->getResponse()->getContent(), true);

        // A never-submitted draft cannot be approved (no-shortcut invariant).
        $this->loginByEmail('admin@example.com');
        $this->client->request('POST', \sprintf('/admin/translations/page/revisions/%d/approve', $draftData['revisionId']), [], [], $this->ajaxHeaders(), '{}');
        self::assertResponseStatusCodeSame(409);
    }

    public function testUnknownContentReturnsNotFound(): void
    {
        $this->loginByEmail('translator@example.com');
        $this->client->request('GET', '/admin/translations/page/999999/fr');

        self::assertResponseStatusCodeSame(404);
    }

    public function testEditViewDeniedOnUnassignedLocale(): void
    {
        $page = $this->createPageWithSource('controller-wrong-locale');
        $this->loginByEmail('translator@example.com');
        $pageId = $page->getId();
        \assert(null !== $pageId);

        // The translator holds FR only; DE is neither granted nor moderated.
        $this->client->request('GET', \sprintf('/admin/translations/page/%d/de', $pageId));
        self::assertResponseStatusCodeSame(403);
    }

    public function testSubmitAndReviewRequireCsrfToken(): void
    {
        $page = $this->createPageWithSource('controller-csrf-submit');
        $this->loginByEmail('translator@example.com');
        $pageId = $page->getId();
        \assert(null !== $pageId);

        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/submit', $pageId), [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        self::assertResponseStatusCodeSame(403);

        $this->loginByEmail('admin@example.com');
        $this->client->request('POST', '/admin/translations/page/revisions/1/approve', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDoubleSubmitReturnsConflict(): void
    {
        $page = $this->createPageWithSource('controller-double-submit');
        $this->loginByEmail('translator@example.com');
        $pageId = $page->getId();
        \assert(null !== $pageId);

        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/draft', $pageId), [], [], $this->ajaxHeaders(), '{"fields":{"title":"Une fois"}}');
        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/submit', $pageId), [], [], $this->ajaxHeaders(), '{}');
        self::assertResponseIsSuccessful();

        // Already pending: the submit transition is not enabled anymore.
        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/submit', $pageId), [], [], $this->ajaxHeaders(), '{}');
        self::assertResponseStatusCodeSame(409);
    }

    public function testUnknownRevisionReturnsNotFound(): void
    {
        $this->loginByEmail('admin@example.com');
        $this->client->request('POST', '/admin/translations/page/revisions/999999/approve', [], [], $this->ajaxHeaders(), '{}');

        self::assertResponseStatusCodeSame(404);
    }

    public function testEditViewExposesThePendingDraft(): void
    {
        $page = $this->createPageWithSource('controller-pending-view');
        $this->loginByEmail('translator@example.com');
        $pageId = $page->getId();
        \assert(null !== $pageId);

        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/draft', $pageId), [], [], $this->ajaxHeaders(), '{"fields":{"title":"Brouillon visible"}}');

        $crawler = $this->client->request('GET', \sprintf('/admin/translations/page/%d/fr', $pageId));
        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $sections */
        $sections = json_decode($crawler->filter('#translation-editor-root')->attr('data-sections') ?? '[]', true);
        self::assertSame('draft', $sections[0]['state']);
        /** @var list<array<string, mixed>> $fields */
        $fields = $sections[0]['fields'];
        self::assertSame('Brouillon visible', $fields[0]['target']);
        // The draft is pinned to the latest source: not stale yet.
        self::assertFalse($sections[0]['sourceStale']);

        // The source moves on: the pending draft is now genuinely stale.
        $em = $this->getEntityManager();
        $sourceTranslation = $em->getRepository(PageTranslation::class)->findOneBy(['page' => $page, 'locale' => 'en']);
        \assert($sourceTranslation instanceof PageTranslation);
        $sourceTranslation->setContent('Moved after draft creation');
        $em->flush();

        $crawler = $this->client->request('GET', \sprintf('/admin/translations/page/%d/fr', $pageId));
        /** @var list<array<string, mixed>> $staleSections */
        $staleSections = json_decode($crawler->filter('#translation-editor-root')->attr('data-sections') ?? '[]', true);
        self::assertTrue($staleSections[0]['sourceStale']);
    }

    public function testDraftRequiresCsrfToken(): void
    {
        $page = $this->createPageWithSource('controller-csrf');
        $this->loginByEmail('translator@example.com');
        $pageId = $page->getId();
        \assert(null !== $pageId);

        $this->client->request('POST', \sprintf('/admin/translations/page/%d/fr/draft', $pageId), [], [], ['CONTENT_TYPE' => 'application/json'], '{"fields":{}}');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDeckDraftDeniedOnUserDeck(): void
    {
        $em = $this->getEntityManager();
        $owner = $em->getRepository(User::class)->createQueryBuilder('u')->setMaxResults(1)->getQuery()->getOneOrNullResult();
        \assert($owner instanceof User);

        $userDeck = new Deck();
        $userDeck->setName('Controller user deck');
        $userDeck->setOwner($owner);
        $userDeck->setFormat(DeckFormat::Expanded);
        $userDeck->setNotes('Personal notes');
        $em->persist($userDeck);
        $em->flush();

        $this->loginByEmail('translator@example.com');
        $deckId = $userDeck->getId();
        \assert(null !== $deckId);
        $this->client->request('POST', \sprintf('/admin/translations/deck/%d/fr/draft', $deckId), [], [], $this->ajaxHeaders(), '{"fields":{"notes":"x"}}');

        self::assertResponseStatusCodeSame(403);
    }

    public function testTranslateActionAppearsOnPublicPageForTranslator(): void
    {
        $em = $this->getEntityManager();
        $page = $this->createPageWithSource('controller-entry-point');
        $page->setIsPublished(true);
        $em->flush();

        $host = ['HTTP_HOST' => $page->getChannel()?->getDomain() ?? 'localhost'];

        $this->loginByEmail('translator@example.com');
        $crawler = $this->client->request('GET', '/en/pages/controller-entry-point', server: $host);

        self::assertResponseIsSuccessful();
        $pageId = $page->getId();
        \assert(null !== $pageId);
        $link = $crawler->filter(\sprintf('a[href="/admin/translations/page/%d/fr"]', $pageId));
        self::assertSame(1, $link->count(), 'Translator must see the translate entry point (US-T1).');

        // A user without translation rights sees no translate action.
        $this->loginByEmail('borrower@example.com');
        $crawler = $this->client->request('GET', '/en/pages/controller-entry-point', server: $host);
        self::assertSame(0, $crawler->filter(\sprintf('a[href="/admin/translations/page/%d/fr"]', $pageId))->count());
    }

    public function testOutdatedNoticeAppearsForReaders(): void
    {
        $em = $this->getEntityManager();
        $page = $this->createPageWithSource('controller-reader-notice');
        $page->setIsPublished(true);

        $frenchTranslation = new PageTranslation();
        $frenchTranslation->setPage($page);
        $frenchTranslation->setLocale('fr');
        $frenchTranslation->setTitle('Titre');
        $frenchTranslation->setContent('Contenu');
        $page->addTranslation($frenchTranslation);
        $em->persist($frenchTranslation);
        $em->flush();

        $host = ['HTTP_HOST' => $page->getChannel()?->getDomain() ?? 'localhost'];

        // Not outdated yet: no notice.
        $crawler = $this->client->request('GET', '/fr/pages/controller-reader-notice', server: $host);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('.alert-info .bi-clock-history')->count());

        // Source moves: the FR page shows the notice with the EN link.
        $sourceTranslation = $em->getRepository(PageTranslation::class)->findOneBy(['page' => $page, 'locale' => 'en']);
        \assert($sourceTranslation instanceof PageTranslation);
        $sourceTranslation->setContent('Fresh English content');
        $em->flush();

        $crawler = $this->client->request('GET', '/fr/pages/controller-reader-notice', server: $host);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('.alert-info .bi-clock-history')->count());
        self::assertSame(1, $crawler->filter('.alert-info a.alert-link[href="/en/pages/controller-reader-notice"]')->count());
    }
}
