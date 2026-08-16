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

namespace App\Tests\Security;

use App\Entity\Archetype;
use App\Entity\Deck;
use App\Entity\Page;
use App\Entity\User;
use App\Security\TranslationTarget;
use App\Security\Voter\TranslationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * @see docs/features.md F9.8 — Translation roles & access
 */
class TranslationVoterTest extends TestCase
{
    private TranslationVoter $voter;

    protected function setUp(): void
    {
        $roleHierarchy = new RoleHierarchy([
            'ROLE_CMS_EDITOR' => ['ROLE_USER', 'ROLE_TRANSLATION_MODERATOR'],
            'ROLE_TRANSLATION_EDITOR' => ['ROLE_USER'],
            'ROLE_TRANSLATION_MODERATOR' => ['ROLE_USER'],
            'ROLE_ADMIN' => ['ROLE_CMS_EDITOR'],
        ]);

        $this->voter = new TranslationVoter($roleHierarchy, 'en');
    }

    /**
     * @param list<string> $roles
     * @param list<string> $translationLocales
     */
    private function tokenFor(array $roles, array $translationLocales): TokenInterface
    {
        $user = new User();
        $user->setRoles($roles);
        $user->setTranslationLocales($translationLocales);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $token->method('getRoleNames')->willReturn([...$roles, 'ROLE_USER']);

        return $token;
    }

    private function variant(): Deck
    {
        $variant = new Deck();
        $variant->setArchetype(new Archetype());
        $variant->setOwner(null);

        return $variant;
    }

    public function testTranslatorWithMatchingLocaleIsGranted(): void
    {
        $token = $this->tokenFor(['ROLE_TRANSLATION_EDITOR'], ['fr']);
        $target = new TranslationTarget(new Page(), 'fr');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $target, [TranslationVoter::TRANSLATE]));
    }

    public function testUserWithoutTranslationRoleIsDenied(): void
    {
        $token = $this->tokenFor([], ['fr']);
        $target = new TranslationTarget(new Page(), 'fr');

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $target, [TranslationVoter::TRANSLATE]));
    }

    public function testModeratorWithoutEditorRoleIsDenied(): void
    {
        // Roles compose: moderating does not imply authoring — including for
        // CMS editors, who only inherit the moderator role.
        $moderatorToken = $this->tokenFor(['ROLE_TRANSLATION_MODERATOR'], ['fr']);
        $cmsEditorToken = $this->tokenFor(['ROLE_CMS_EDITOR'], ['fr']);
        $target = new TranslationTarget(new Page(), 'fr');

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($moderatorToken, $target, [TranslationVoter::TRANSLATE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($cmsEditorToken, $target, [TranslationVoter::TRANSLATE]));
    }

    public function testLocaleOutsideAssignedListIsDenied(): void
    {
        $token = $this->tokenFor(['ROLE_TRANSLATION_EDITOR'], ['fr']);
        $target = new TranslationTarget(new Page(), 'de');

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $target, [TranslationVoter::TRANSLATE]));
    }

    public function testSourceLocaleIsDeniedEvenWhenAssigned(): void
    {
        // Source content is editor-managed: never a translation target, even
        // if the locale list was misconfigured to contain it.
        $token = $this->tokenFor(['ROLE_TRANSLATION_EDITOR'], ['en', 'fr']);
        $target = new TranslationTarget(new Page(), 'en');

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $target, [TranslationVoter::TRANSLATE]));
    }

    public function testArchetypeVariantIsGrantedUserDeckIsDenied(): void
    {
        $token = $this->tokenFor(['ROLE_TRANSLATION_EDITOR'], ['fr']);

        $variantTarget = new TranslationTarget($this->variant(), 'fr');
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $variantTarget, [TranslationVoter::TRANSLATE]));

        $userDeck = new Deck();
        $userDeck->setOwner(new User());
        $userDeckTarget = new TranslationTarget($userDeck, 'fr');
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $userDeckTarget, [TranslationVoter::TRANSLATE]));
    }

    public function testUnsupportedAttributeOrSubjectAbstains(): void
    {
        $token = $this->tokenFor(['ROLE_TRANSLATION_EDITOR'], ['fr']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, new Page(), [TranslationVoter::TRANSLATE]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, new TranslationTarget(new Page(), 'fr'), ['EDIT']));
    }
}
