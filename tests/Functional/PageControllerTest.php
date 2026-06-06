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
use App\Repository\MenuCategoryRepository;
use App\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;

class PageControllerTest extends AbstractFunctionalTest
{
    /** Pages are on the content channel. */
    private const array CONTENT_HOST = ['HTTP_HOST' => 'expandedtalks.wip'];

    public function testPublishedPageIsAccessible(): void
    {
        $this->client->request('GET', '/en/pages/welcome', server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
    }

    public function testUnpublishedPageReturns404ForAnonymous(): void
    {
        $this->client->request('GET', '/en/pages/upcoming-features', server: self::CONTENT_HOST);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnpublishedPageReturns404ForAnonymousWithPreview(): void
    {
        $this->client->request('GET', '/en/pages/upcoming-features?preview=true', server: self::CONTENT_HOST);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnpublishedPageAccessibleByEditorWithPreview(): void
    {
        $this->client->request('GET', '/login', server: self::CONTENT_HOST);
        $this->client->submitForm('Login', [
            '_email' => 'admin@example.com',
            '_password' => 'password',
        ]);
        $this->client->request('GET', '/en/pages/upcoming-features?preview=true', server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-warning');
    }

    public function testUnpublishedPageReturns404ForEditorWithoutPreview(): void
    {
        $this->client->request('GET', '/login', server: self::CONTENT_HOST);
        $this->client->submitForm('Login', [
            '_email' => 'admin@example.com',
            '_password' => 'password',
        ]);
        $this->client->request('GET', '/en/pages/upcoming-features', server: self::CONTENT_HOST);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCategoryPageIsAccessible(): void
    {
        /** @var MenuCategoryRepository $repository */
        $repository = static::getContainer()->get(MenuCategoryRepository::class);
        $category = $repository->findOneBy([]);
        self::assertNotNull($category);

        $this->client->request('GET', \sprintf('/en/pages/category/%d', $category->getId()), server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
    }

    public function testNonExistentPageReturns404(): void
    {
        $this->client->request('GET', '/en/pages/nonexistent-slug', server: self::CONTENT_HOST);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCategoryFeedReturnsValidRss(): void
    {
        $category = $this->findNewsCategory();

        $this->client->request('GET', \sprintf('/en/pages/category/%d/feed.xml', $category->getId()), server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($xml);
        self::assertSame('en', (string) $xml->channel->language);
        // Channel title carries the channel brand for feed readers.
        self::assertStringContainsString('Expanded Talks', (string) $xml->channel->title);
        // Themed channels expose their icon as the RSS channel image
        // (Encore versions the filename, hence the loose match).
        self::assertStringContainsString('apple_touch_icon', (string) $xml->channel->image->url);
        self::assertStringEndsWith('.png', (string) $xml->channel->image->url);
        self::assertGreaterThan(0, \count($xml->channel->item));
    }

    public function testCategoryFeedOrdersItemsNewestFirst(): void
    {
        $category = $this->findNewsCategory();

        $this->client->request('GET', \sprintf('/en/pages/category/%d/feed.xml', $category->getId()), server: self::CONTENT_HOST);

        $titles = $this->feedItemTitles();

        // March recap was published 2 months ago, season kickoff 5 months ago.
        $marchPosition = array_search('March League Challenge recap', $titles, true);
        $seasonPosition = array_search('Season 2026 is here!', $titles, true);
        self::assertIsInt($marchPosition);
        self::assertIsInt($seasonPosition);
        self::assertLessThan($seasonPosition, $marchPosition);
    }

    public function testCategoryFeedExcludesUnpublishedAndOtherCategoryPages(): void
    {
        $category = $this->findNewsCategory();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $draftPage = new Page();
        $draftPage->setChannel($category->getChannel());
        $draftPage->setSlug('feed-draft-page');
        $draftPage->setMenuCategory($category);
        $draftPage->setIsPublished(false);
        $draftTranslation = new PageTranslation();
        $draftTranslation->setPage($draftPage);
        $draftTranslation->setLocale('en');
        $draftTranslation->setTitle('Unpublished feed entry');
        $draftTranslation->setContent('Draft content that must stay out of the feed.');
        $draftPage->addTranslation($draftTranslation);
        $entityManager->persist($draftPage);
        $entityManager->persist($draftTranslation);
        $entityManager->flush();

        $this->client->request('GET', \sprintf('/en/pages/category/%d/feed.xml', $category->getId()), server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('Unpublished feed entry', $content);
        // borrowing-rules belongs to the Rules & Info category.
        self::assertStringNotContainsString('/pages/borrowing-rules', $content);
    }

    public function testCategoryFeedEscapesSpecialCharacters(): void
    {
        $category = $this->findNewsCategory();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $page = new Page();
        $page->setChannel($category->getChannel());
        $page->setSlug('feed-escaping-page');
        $page->setMenuCategory($category);
        $page->setIsPublished(true);
        $translation = new PageTranslation();
        $translation->setPage($page);
        $translation->setLocale('en');
        $translation->setTitle('Cats & "Dogs" <Test>');
        $translation->setContent('Ampersands & angle <brackets> must survive the round-trip.');
        $page->addTranslation($translation);
        $entityManager->persist($page);
        $entityManager->persist($translation);
        $entityManager->flush();

        $this->client->request('GET', \sprintf('/en/pages/category/%d/feed.xml', $category->getId()), server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
        $titles = $this->feedItemTitles();
        self::assertContains('Cats & "Dogs" <Test>', $titles);
    }

    public function testCategoryFeedUnknownCategoryReturns404(): void
    {
        $this->client->request('GET', '/en/pages/category/999999/feed.xml', server: self::CONTENT_HOST);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCategoryFeedFrenchLocale(): void
    {
        $category = $this->findNewsCategory();

        // The fixture content channel is EN-only, and LocaleListener constrains
        // the route locale to the channel's locales. Enable FR for this test
        // (rolled back with the test transaction).
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $channel = $category->getChannel();
        self::assertNotNull($channel);
        $channel->setLocales(['en', 'fr']);
        $entityManager->flush();

        $this->client->request('GET', \sprintf('/fr/pages/category/%d/feed.xml', $category->getId()), server: self::CONTENT_HOST);

        self::assertResponseIsSuccessful();
        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($xml);
        self::assertSame('fr', (string) $xml->channel->language);
        self::assertStringContainsString('derniers articles', (string) $xml->channel->description);
    }

    private function findNewsCategory(): MenuCategory
    {
        /** @var PageRepository $pageRepository */
        $pageRepository = static::getContainer()->get(PageRepository::class);
        $newsPage = $pageRepository->findBySlug('season-2026-kickoff');
        self::assertNotNull($newsPage);
        $category = $newsPage->getMenuCategory();
        self::assertNotNull($category);

        return $category;
    }

    /**
     * @return list<string>
     */
    private function feedItemTitles(): array
    {
        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($xml);

        $titles = [];
        foreach ($xml->channel->item as $item) {
            $titles[] = (string) $item->title;
        }

        return $titles;
    }
}
