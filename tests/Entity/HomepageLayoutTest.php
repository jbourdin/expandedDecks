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

namespace App\Tests\Entity;

use App\Entity\Channel;
use App\Entity\HomepageLayout;
use App\Entity\HomepageLayoutTranslation;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F10.3 — HomepageLayout entity and data model
 */
class HomepageLayoutTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $layout = new HomepageLayout();

        self::assertNull($layout->getId());
        self::assertSame([], $layout->getBlocks());
        self::assertFalse($layout->isPublished());
        self::assertInstanceOf(\DateTimeImmutable::class, $layout->getCreatedAt());
        self::assertNull($layout->getUpdatedAt());
        self::assertNull($layout->getChannel());
        self::assertCount(0, $layout->getTranslations());
    }

    public function testSetBlocks(): void
    {
        $layout = new HomepageLayout();
        $blocks = [['type' => 'hero', 'columnWidth' => null]];
        $layout->setBlocks($blocks);

        self::assertSame($blocks, $layout->getBlocks());
    }

    public function testSetIsPublished(): void
    {
        $layout = new HomepageLayout();
        $layout->setIsPublished(true);

        self::assertTrue($layout->isPublished());
    }

    public function testAddTranslation(): void
    {
        $layout = new HomepageLayout();
        $translation = new HomepageLayoutTranslation();
        $translation->setLocale('en');

        $layout->addTranslation($translation);

        self::assertCount(1, $layout->getTranslations());
        self::assertSame($layout, $translation->getHomepageLayout());
    }

    public function testAddTranslationDoesNotDuplicate(): void
    {
        $layout = new HomepageLayout();
        $translation = new HomepageLayoutTranslation();
        $translation->setLocale('en');

        $layout->addTranslation($translation);
        $layout->addTranslation($translation);

        self::assertCount(1, $layout->getTranslations());
    }

    public function testRemoveTranslation(): void
    {
        $layout = new HomepageLayout();
        $translation = new HomepageLayoutTranslation();
        $translation->setLocale('en');
        $layout->addTranslation($translation);

        $layout->removeTranslation($translation);

        self::assertCount(0, $layout->getTranslations());
    }

    public function testGetTranslationReturnsMatchingLocale(): void
    {
        $layout = new HomepageLayout();

        $translationEn = new HomepageLayoutTranslation();
        $translationEn->setLocale('en');
        $layout->addTranslation($translationEn);

        $translationFr = new HomepageLayoutTranslation();
        $translationFr->setLocale('fr');
        $layout->addTranslation($translationFr);

        self::assertSame($translationFr, $layout->getTranslation('fr'));
        self::assertSame($translationEn, $layout->getTranslation('en'));
    }

    public function testGetTranslationFallsBackToEnglish(): void
    {
        $layout = new HomepageLayout();

        $translationEn = new HomepageLayoutTranslation();
        $translationEn->setLocale('en');
        $layout->addTranslation($translationEn);

        self::assertSame($translationEn, $layout->getTranslation('de'));
    }

    public function testGetTranslationReturnsNullWhenNoMatch(): void
    {
        $layout = new HomepageLayout();

        self::assertNull($layout->getTranslation('en'));
    }

    public function testGetTranslationReturnsNullForEnglishWhenOnlyOtherLocale(): void
    {
        $layout = new HomepageLayout();

        $translationFr = new HomepageLayoutTranslation();
        $translationFr->setLocale('fr');
        $layout->addTranslation($translationFr);

        self::assertNull($layout->getTranslation('en'));
    }

    public function testSetChannel(): void
    {
        $layout = new HomepageLayout();
        $channel = (new Channel())->setCode('app')->setDomain('expanded-decks.wip');

        $result = $layout->setChannel($channel);

        self::assertSame($channel, $layout->getChannel());
        self::assertSame($layout, $result);
    }

    public function testLifecycleCallbacks(): void
    {
        $layout = new HomepageLayout();
        $layout->setCreatedAtValue();

        self::assertInstanceOf(\DateTimeImmutable::class, $layout->getCreatedAt());

        $layout->setUpdatedAtValue();

        self::assertInstanceOf(\DateTimeImmutable::class, $layout->getUpdatedAt());
    }
}
