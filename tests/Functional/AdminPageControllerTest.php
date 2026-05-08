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

use App\Constants\ListingIntroPage;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Repository\ChannelRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdminPageControllerTest extends AbstractFunctionalTest
{
    public function testPageListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin/pages');

        self::assertResponseRedirects('/login');
    }

    public function testPageListRequiresCmsEditorRole(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/admin/pages');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPageListAccessibleForAdmin(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/pages');

        self::assertResponseIsSuccessful();
    }

    public function testNewPageFormAccessible(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/pages/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEditPageSavesAndRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $page = $this->getPageBySlug('welcome');
        $crawler = $this->client->request('GET', '/admin/pages/'.$page->getId());

        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testDeletePageSoftDeletesAndRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $page = $this->getPageBySlug('upcoming-features');

        // Load the edit page to get a valid CSRF token from the rendered form
        $crawler = $this->client->request('GET', '/admin/pages/'.$page->getId());
        $token = $crawler->filter('input[name="_token"][value]')->attr('value');

        $this->client->request('POST', '/admin/pages/'.$page->getId().'/delete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testDuplicatePageCreatesAndRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $page = $this->getPageBySlug('welcome');

        // Load the page list to get a valid CSRF token from the rendered duplicate form
        $this->client->request('GET', '/admin/pages');
        $crawler = $this->client->getCrawler();
        $duplicateForm = $crawler->filter('form[action$="/duplicate"]')->first();

        if (0 === $duplicateForm->count()) {
            self::markTestSkipped('No duplicate form found on page list.');
        }

        $token = $duplicateForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/pages/'.$page->getId().'/duplicate', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testListingIntroSlugsAreHiddenFromAdminList(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        /** @var ChannelRepository $channelRepository */
        $channelRepository = self::getContainer()->get(ChannelRepository::class);
        $channel = $channelRepository->findOneBy([]);
        self::assertNotNull($channel);

        $reserved = new Page();
        $reserved->setSlug(ListingIntroPage::BANNED_CARDS_SLUG);
        $reserved->setChannel($channel);
        $reserved->setIsPublished(true);

        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('Banned cards intro');
        $translation->setContent('Hidden from admin list.');
        $reserved->addTranslation($translation);

        $entityManager->persist($reserved);
        $entityManager->flush();

        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', '/admin/pages');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            ListingIntroPage::BANNED_CARDS_SLUG,
            $crawler->filter('body')->html(),
            'Reserved listing intro slugs must not appear in the admin pages list.',
        );
    }

    public function testListingIntroEditFormIsStillReachableViaDirectUrl(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        /** @var ChannelRepository $channelRepository */
        $channelRepository = self::getContainer()->get(ChannelRepository::class);
        $channel = $channelRepository->findOneBy([]);
        self::assertNotNull($channel);

        $reserved = new Page();
        $reserved->setSlug(ListingIntroPage::STAPLE_CARDS_SLUG);
        $reserved->setChannel($channel);
        $reserved->setIsPublished(true);

        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('Staple cards intro');
        $translation->setContent('Reachable via the in-page edit button.');
        $reserved->addTranslation($translation);

        $entityManager->persist($reserved);
        $entityManager->flush();

        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/pages/'.$reserved->getId());

        self::assertResponseIsSuccessful();
    }

    public function testListingIntroEditFormHidesDangerZone(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        /** @var ChannelRepository $channelRepository */
        $channelRepository = self::getContainer()->get(ChannelRepository::class);
        $channel = $channelRepository->findOneBy([]);
        self::assertNotNull($channel);

        $reserved = new Page();
        $reserved->setSlug(ListingIntroPage::BANNED_CARDS_SLUG);
        $reserved->setChannel($channel);
        $reserved->setIsPublished(true);

        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('Banned cards intro');
        $translation->setContent('Body.');
        $reserved->addTranslation($translation);

        $entityManager->persist($reserved);
        $entityManager->flush();

        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', '/admin/pages/'.$reserved->getId());

        self::assertResponseIsSuccessful();
        self::assertCount(
            0,
            $crawler->filter('form[action$="/delete"]'),
            'Reserved listing-intro pages must not render the delete form.',
        );
    }

    public function testDeleteOnListingIntroPageIsRefused(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        /** @var ChannelRepository $channelRepository */
        $channelRepository = self::getContainer()->get(ChannelRepository::class);
        $channel = $channelRepository->findOneBy([]);
        self::assertNotNull($channel);

        $reserved = new Page();
        $reserved->setSlug(ListingIntroPage::BANNED_CARDS_SLUG);
        $reserved->setChannel($channel);
        $reserved->setIsPublished(true);

        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('Banned cards intro');
        $translation->setContent('Body.');
        $reserved->addTranslation($translation);

        $entityManager->persist($reserved);
        $entityManager->flush();

        $reservedId = $reserved->getId();
        self::assertNotNull($reservedId);

        $this->loginAs('admin@example.com');

        // The reserved-slug guard runs before the CSRF check, so we can probe
        // it without a session-bound token. This also covers the case where
        // a CLI/curl request bypasses the rendered form entirely.
        $this->client->request('POST', '/admin/pages/'.$reservedId.'/delete', ['_token' => 'irrelevant']);

        self::assertResponseRedirects('/admin/pages/'.$reservedId);

        $entityManager->clear();
        $stillThere = $entityManager->getRepository(Page::class)->find($reservedId);
        self::assertInstanceOf(Page::class, $stillThere);
        self::assertNull($stillThere->getDeletedAt(), 'Reserved listing-intro page must not be soft-deleted.');
    }

    private function getPageBySlug(string $slug): Page
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $page = $entityManager->getRepository(Page::class)->findOneBy(['slug' => $slug]);
        \assert($page instanceof Page);

        return $page;
    }
}
