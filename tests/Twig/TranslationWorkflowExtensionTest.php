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

namespace App\Tests\Twig;

use App\Entity\Page;
use App\Entity\User;
use App\Security\TranslationTarget;
use App\Twig\Extension\TranslationWorkflowExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 */
class TranslationWorkflowExtensionTest extends TestCase
{
    public function testRegistersTheTranslatableLocalesFunction(): void
    {
        $extension = new TranslationWorkflowExtension($this->createStub(Security::class));

        $names = array_map(static fn ($function) => $function->getName(), $extension->getFunctions());
        self::assertSame(['translatable_locales'], $names);
    }

    public function testReturnsOnlyLocalesGrantedByTheVoter(): void
    {
        $user = new User();
        $user->setTranslationLocales(['fr', 'de']);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute, mixed $subject): bool => $subject instanceof TranslationTarget && 'fr' === $subject->locale,
        );

        $extension = new TranslationWorkflowExtension($security);

        self::assertSame(['fr'], $extension->translatableLocales(new Page()));
    }

    public function testAnonymousUserGetsNoLocales(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $extension = new TranslationWorkflowExtension($security);

        self::assertSame([], $extension->translatableLocales(new Page()));
    }
}
