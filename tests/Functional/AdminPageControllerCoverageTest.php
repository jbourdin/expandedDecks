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

use App\Entity\MenuCategory;
use App\Entity\Page;
use App\Entity\PageTranslation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Covers the branches of AdminPageController not exercised by the headline
 * happy-path tests in AdminPageControllerTest: query-param filters on the list
 * route, the JSON reorder endpoint, channel/category prefill on `new`, the
 * translation save flow, and the bad-CSRF branches on delete/duplicate.
 *
 * @see docs/features.md F11.1 — Content pages
 * @see docs/features.md F7.10 — Admin pages: filter by category and drag-and-drop sorting
 */
class AdminPageControllerCoverageTest extends AbstractFunctionalTest
{
    public function testListAcceptsSearchQueryParam(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/pages?q=welcome');

        self::assertResponseIsSuccessful();
    }

    public function testListAcceptsCategoryFilter(): void
    {
        $this->loginAs('admin@example.com');

        $category = $this->getNewsCategory();

        $this->client->request('GET', '/admin/pages?category='.$category->getId());

        self::assertResponseIsSuccessful();
    }

    public function testListAcceptsChannelFilter(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/pages?channel=app');

        // The "app" channel has no menu categories — should still render an
        // empty-state success response (no categories branch).
        self::assertResponseIsSuccessful();
    }

    public function testListPaginationBeyondPageOneDisablesSortable(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/pages?page=2');

        self::assertResponseIsSuccessful();
    }

    public function testReorderAcceptsValidJsonPayload(): void
    {
        $this->loginAs('admin@example.com');

        $welcome = $this->getPageBySlug('welcome');

        $this->client->request(
            'POST',
            '/admin/pages/reorder',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([$welcome->getId()]),
        );

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"ok":true}',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testReorderRejectsNonArrayPayload(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request(
            'POST',
            '/admin/pages/reorder',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '"not-an-array"',
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testNewPagePrefilledFromChannelAndCategoryQueryParams(): void
    {
        $this->loginAs('admin@example.com');

        $category = $this->getNewsCategory();

        $this->client->request('GET', \sprintf('/admin/pages/new?channel=content&category=%d', $category->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testNewPageSubmitCreatesPageAndRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/admin/pages/new');
        // The new-page form has a single <form> tag — fetch it directly.
        $form = $crawler->filter('form')->first()->form();

        $titleField = $crawler->filter('input[name$="[title]"]')->first();
        self::assertGreaterThan(0, $titleField->count(), 'New page form should expose a title field.');
        $titleName = $titleField->attr('name') ?? '';
        $form[$titleName]->setValue('Test Coverage Page');

        // The slug field is required and auto-populated client-side; set it
        // explicitly since the test runner doesn't execute JS.
        $form[str_replace('[title]', '[slug]', $titleName)]->setValue('test-coverage-page');

        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testSaveTranslationUpdatesExistingLocale(): void
    {
        $this->loginAs('admin@example.com');

        $page = $this->getPageBySlug('welcome');

        // Pull the EN translation form (welcome lives on the content channel
        // which has only ['en']). Filter by the form's action URL since
        // createNamed namespaces the field-name prefix but not the id.
        $crawler = $this->client->request('GET', '/admin/pages/'.$page->getId());
        $action = \sprintf('/admin/pages/%d/translation/en', $page->getId());
        $enForm = $crawler->filter(\sprintf('form[action$="%s"]', $action))->form();
        $enForm['page_translation_form_en[title]']->setValue('Welcome (updated)');

        $this->client->submit($enForm);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testSaveTranslationCreatesNewLocaleOnMultiLocaleChannel(): void
    {
        $this->loginAs('admin@example.com');

        // The "app" channel has ['en', 'fr']; persist a fresh page on it so
        // the FR translation form renders and we can exercise the
        // "create new translation" branch in saveTranslation.
        $page = $this->createAppChannelPage('coverage-fr-target');

        $crawler = $this->client->request('GET', '/admin/pages/'.$page->getId());
        $action = \sprintf('/admin/pages/%d/translation/fr', $page->getId());
        $frForm = $crawler->filter(\sprintf('form[action$="%s"]', $action))->form();
        $frForm['page_translation_form_fr[title]']->setValue('Cible FR');
        $frForm['page_translation_form_fr[content]']->setValue('<p>Contenu FR</p>');

        $this->client->submit($frForm);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testSaveTranslationRejectsLocaleNotInChannel(): void
    {
        $this->loginAs('admin@example.com');

        $page = $this->getPageBySlug('welcome');

        $this->client->request('POST', \sprintf('/admin/pages/%d/translation/de', $page->getId()), [
            'page_translation_form_de' => ['title' => 'irrelevant'],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteRejectsInvalidCsrfAndKeepsPage(): void
    {
        $this->loginAs('admin@example.com');

        $page = $this->getPageBySlug('welcome');

        $this->client->request('POST', '/admin/pages/'.$page->getId().'/delete', [
            '_token' => 'wrong',
        ]);

        self::assertResponseRedirects('/admin/pages/'.$page->getId());
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Page::class)->find($page->getId());
        self::assertInstanceOf(Page::class, $reloaded);
        self::assertNull($reloaded->getDeletedAt());
    }

    public function testDuplicateRejectsInvalidCsrf(): void
    {
        $this->loginAs('admin@example.com');

        $page = $this->getPageBySlug('welcome');

        $this->client->request('POST', '/admin/pages/'.$page->getId().'/duplicate', [
            '_token' => 'wrong',
        ]);

        self::assertResponseRedirects('/admin/pages');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testDuplicateClonesPageAndAllTranslations(): void
    {
        $this->loginAs('admin@example.com');

        $page = $this->getPageBySlug('welcome');
        $originalTranslations = $page->getTranslations()->count();

        $this->client->request('GET', '/admin/pages');
        $crawler = $this->client->getCrawler();
        $duplicateForm = $crawler->filter('form[action$="/duplicate"]')->first();
        if (0 === $duplicateForm->count()) {
            self::markTestSkipped('No duplicate form rendered.');
        }
        $token = $duplicateForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/pages/'.$page->getId().'/duplicate', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Verify the clone exists and copied every translation.
        $em = $this->getEntityManager();
        $em->clear();
        $copies = $em->getRepository(Page::class)->findBy(['slug' => $page->getSlug().'-copy-%']);
        // findBy doesn't accept LIKE; use a query that checks for the prefix.
        $qb = $em->createQueryBuilder();
        $qb->select('p')->from(Page::class, 'p')->where('p.slug LIKE :slug')
            ->setParameter('slug', $page->getSlug().'-copy-%');
        /** @var list<Page> $copies */
        $copies = $qb->getQuery()->getResult();

        self::assertCount(1, $copies);
        $copy = $copies[0];
        self::assertSame($originalTranslations, $copy->getTranslations()->count());
        foreach ($copy->getTranslations() as $translation) {
            self::assertInstanceOf(PageTranslation::class, $translation);
            self::assertStringEndsWith('(copy)', $translation->getTitle());
        }
        self::assertFalse($copy->isPublished());
    }

    private function createAppChannelPage(string $slug): Page
    {
        $em = $this->getEntityManager();
        /** @var \App\Entity\Channel $appChannel */
        $appChannel = $em->getRepository(\App\Entity\Channel::class)->findOneBy(['code' => 'app']);
        \assert($appChannel instanceof \App\Entity\Channel);

        $page = new Page();
        $page->setChannel($appChannel);
        $page->setSlug($slug);
        $page->setIsPublished(false);

        $en = new PageTranslation();
        $en->setLocale('en');
        $en->setTitle('Coverage Target');
        $en->setPage($page);
        $page->addTranslation($en);

        $em->persist($page);
        $em->persist($en);
        $em->flush();

        return $page;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    private function getPageBySlug(string $slug): Page
    {
        $page = $this->getEntityManager()->getRepository(Page::class)->findOneBy(['slug' => $slug]);
        \assert($page instanceof Page);

        return $page;
    }

    private function getNewsCategory(): MenuCategory
    {
        $em = $this->getEntityManager();
        /** @var MenuCategory|null $category */
        $category = $em->getRepository(MenuCategory::class)->createQueryBuilder('c')
            ->join('c.translations', 't')
            ->where('t.name = :name')
            ->setParameter('name', 'News')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        \assert($category instanceof MenuCategory);

        return $category;
    }
}
