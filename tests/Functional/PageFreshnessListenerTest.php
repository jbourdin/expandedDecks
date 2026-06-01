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
use App\Entity\PageTranslation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F11.4 — CMS publication dates
 */
class PageFreshnessListenerTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function createPage(string $slug, bool $isPublished): Page
    {
        $em = $this->getEntityManager();

        $page = new Page();
        $page->setSlug($slug);
        $page->setIsPublished($isPublished);

        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('Freshness Subject');
        $translation->setContent('Initial content.');
        $page->addTranslation($translation);

        $em->persist($page);
        $em->persist($translation);
        $em->flush();

        return $page;
    }

    public function testEditingATranslationBumpsPublishedPageLastPublishedAt(): void
    {
        $em = $this->getEntityManager();

        $page = $this->createPage('freshness-published-page', true);

        // Re-read so we capture the listener's post-flush bump from the row.
        $em->refresh($page);
        $initialLastPublishedAt = $page->getLastPublishedAt();
        self::assertNotNull($initialLastPublishedAt);

        sleep(1);

        $translation = $page->getTranslation('en');
        self::assertNotNull($translation);
        $translation->setContent('Edited content.');
        $em->flush();

        $em->refresh($page);
        $bumpedLastPublishedAt = $page->getLastPublishedAt();

        self::assertNotNull($bumpedLastPublishedAt);
        self::assertGreaterThan($initialLastPublishedAt, $bumpedLastPublishedAt);
    }

    public function testEditingATranslationDoesNotBumpDraftPage(): void
    {
        $em = $this->getEntityManager();

        $page = $this->createPage('freshness-draft-page', false);

        $em->refresh($page);
        self::assertNull($page->getLastPublishedAt());

        sleep(1);

        $translation = $page->getTranslation('en');
        self::assertNotNull($translation);
        $translation->setContent('Edited draft content.');
        $em->flush();

        $em->refresh($page);
        self::assertNull($page->getLastPublishedAt());
    }
}
