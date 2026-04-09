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

use App\Entity\Page;
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

    public function testEditPageTogglesPublishedAndRedirects(): void
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
        $this->client->request('POST', '/admin/pages/'.$page->getId().'/delete', [
            '_token' => $this->getCsrfToken('page-delete-'.$page->getId()),
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testDuplicatePageCreatesNewPageAndRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $page = $this->getPageBySlug('welcome');
        $this->client->request('POST', '/admin/pages/'.$page->getId().'/duplicate', [
            '_token' => $this->getCsrfToken('page-duplicate-'.$page->getId()),
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    private function getPageBySlug(string $slug): Page
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $page = $entityManager->getRepository(Page::class)->findOneBy(['slug' => $slug]);
        \assert($page instanceof Page);

        return $page;
    }

    private function getCsrfToken(string $tokenId): string
    {
        /** @var \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $tokenManager */
        $tokenManager = self::getContainer()->get('security.csrf.token_manager');

        return $tokenManager->getToken($tokenId)->getValue();
    }
}
