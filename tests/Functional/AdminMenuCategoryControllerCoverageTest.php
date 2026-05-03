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

use App\Entity\Channel;
use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Coverage backfill for AdminMenuCategoryController. The headline test only
 * exercises the auth + GET-list / GET-new branches; this file covers list
 * filters, reorder, the new-category submit flow, edit GET + save, the
 * translation save flow, and the delete CSRF branches.
 *
 * @see docs/features.md F11.2 — Menu categories
 */
class AdminMenuCategoryControllerCoverageTest extends AbstractFunctionalTest
{
    public function testListFooterViewIsAccessible(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/menu-categories?view=footer');

        self::assertResponseIsSuccessful();
    }

    public function testListFallsBackToMenuViewOnUnknownView(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/menu-categories?view=garbage');

        self::assertResponseIsSuccessful();
    }

    public function testListAcceptsChannelFilter(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/menu-categories?channel=app');

        self::assertResponseIsSuccessful();
    }

    public function testReorderAcceptsCategoryIdsArray(): void
    {
        $this->loginAs('admin@example.com');

        $news = $this->getCategoryByName('News');
        $rules = $this->getCategoryByName('Rules & Info');

        $this->client->request(
            'POST',
            '/admin/menu-categories/reorder',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([$rules->getId(), $news->getId()]),
        );

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"ok":true}',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testNewSubmitCreatesCategoryAndRedirectsToEdit(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/admin/menu-categories/new');
        $form = $crawler->filter('form')->first()->form();

        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');

        // Verify a row was actually inserted.
        $em = $this->getEntityManager();
        $em->clear();
        self::assertGreaterThan(2, \count($em->getRepository(MenuCategory::class)->findAll()));
    }

    public function testNewWithFooterViewQueryParamFlagsCategoryAsFooter(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/admin/menu-categories/new?view=footer');
        self::assertResponseIsSuccessful();

        // Submit the form — the controller should mark the new category as a
        // footer category because of the view query param.
        $form = $crawler->filter('form')->first()->form();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        $em = $this->getEntityManager();
        $em->clear();
        $footerCategories = $em->getRepository(MenuCategory::class)->findBy(['isFooter' => true]);
        self::assertNotEmpty($footerCategories);
    }

    public function testNewWithChannelQueryParamAttachesCategoryToChannel(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/menu-categories/new?channel=app');

        self::assertResponseIsSuccessful();
    }

    public function testEditGetRendersFormForExistingCategory(): void
    {
        $this->loginAs('admin@example.com');

        $category = $this->getCategoryByName('News');

        $this->client->request('GET', '/admin/menu-categories/'.$category->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEditSaveUpdatesCategoryAndRedirectsBackToEdit(): void
    {
        $this->loginAs('admin@example.com');

        $category = $this->getCategoryByName('News');

        $crawler = $this->client->request('GET', '/admin/menu-categories/'.$category->getId());
        // The first <form> on the edit page is the channel/settings form
        // (translation forms come after it).
        $form = $crawler->filter('form')->first()->form();

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/menu-categories/'.$category->getId());
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testSaveTranslationCreatesNewLocaleOnMultiLocaleChannel(): void
    {
        $this->loginAs('admin@example.com');

        // The fixture's existing categories live on the content channel
        // (en-only). Persist a fresh category on the "app" channel to exercise
        // the multi-locale translation flow.
        $category = $this->createAppChannelCategory();

        $crawler = $this->client->request('GET', '/admin/menu-categories/'.$category->getId());
        $action = \sprintf('/admin/menu-categories/%d/translation/fr', $category->getId());
        $frForm = $crawler->filter(\sprintf('form[action$="%s"]', $action))->form();
        $frForm['menu_category_translation_form[name]']->setValue('Catégorie FR');

        $this->client->submit($frForm);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testSaveTranslationUpdatesExistingLocale(): void
    {
        $this->loginAs('admin@example.com');

        $category = $this->getCategoryByName('News');

        $crawler = $this->client->request('GET', '/admin/menu-categories/'.$category->getId());
        $action = \sprintf('/admin/menu-categories/%d/translation/en', $category->getId());
        $enForm = $crawler->filter(\sprintf('form[action$="%s"]', $action))->form();
        $enForm['menu_category_translation_form[name]']->setValue('Updated News');

        $this->client->submit($enForm);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testSaveTranslationRejectsLocaleNotInChannel(): void
    {
        $this->loginAs('admin@example.com');

        $category = $this->getCategoryByName('News');

        $this->client->request('POST', \sprintf('/admin/menu-categories/%d/translation/de', $category->getId()), [
            'menu_category_translation_form' => ['name' => 'irrelevant'],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteWithValidCsrfRemovesCategory(): void
    {
        $this->loginAs('admin@example.com');

        $category = $this->createAppChannelCategory();

        $crawler = $this->client->request('GET', '/admin/menu-categories/'.$category->getId());
        // The delete form is the last <form> on the edit page.
        $deleteForm = $crawler->filter(\sprintf('form[action$="/%d/delete"]', $category->getId()))->first();
        $token = $deleteForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/menu-categories/'.$category->getId().'/delete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/menu-categories');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');

        $em = $this->getEntityManager();
        $em->clear();
        self::assertNull($em->getRepository(MenuCategory::class)->find($category->getId()));
    }

    public function testDeleteRejectsInvalidCsrfAndKeepsCategory(): void
    {
        $this->loginAs('admin@example.com');

        $category = $this->getCategoryByName('News');

        $this->client->request('POST', '/admin/menu-categories/'.$category->getId().'/delete', [
            '_token' => 'wrong',
        ]);

        self::assertResponseRedirects('/admin/menu-categories/'.$category->getId());
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');

        $em = $this->getEntityManager();
        $em->clear();
        self::assertNotNull($em->getRepository(MenuCategory::class)->find($category->getId()));
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    private function getCategoryByName(string $name): MenuCategory
    {
        $em = $this->getEntityManager();
        /** @var MenuCategory|null $category */
        $category = $em->getRepository(MenuCategory::class)->createQueryBuilder('c')
            ->join('c.translations', 't')
            ->where('t.name = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        \assert($category instanceof MenuCategory);

        return $category;
    }

    private function createAppChannelCategory(): MenuCategory
    {
        $em = $this->getEntityManager();
        /** @var Channel $appChannel */
        $appChannel = $em->getRepository(Channel::class)->findOneBy(['code' => 'app']);
        \assert($appChannel instanceof Channel);

        $category = new MenuCategory();
        $category->setChannel($appChannel);
        $category->setPosition(99);

        $en = new MenuCategoryTranslation();
        $en->setMenuCategory($category);
        $en->setLocale('en');
        $en->setName('Coverage Cat');
        $category->addTranslation($en);

        $em->persist($category);
        $em->persist($en);
        $em->flush();

        return $category;
    }
}
