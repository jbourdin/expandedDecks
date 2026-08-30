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

namespace App\Security\Voter;

use App\Entity\Deck;
use App\Entity\User;
use App\Security\TranslationTarget;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Grants translation access on a (content, target locale) pair.
 *
 * A user may translate when ALL of the following hold:
 * - they have `ROLE_TRANSLATION_EDITOR` (directly or via the hierarchy);
 * - the target locale is in their `translationLocales` list;
 * - the target locale is not the source locale (source content is
 *   editor-managed, translators read it but never write it);
 * - for decks, the deck is an archetype variant — user decks are user data
 *   and never enter the workflow.
 *
 * @see docs/features.md F9.8 — Translation roles & access
 * @see docs/technicalities/translation_workflow.md
 *
 * @extends Voter<string, TranslationTarget>
 */
final class TranslationVoter extends Voter
{
    public const string TRANSLATE = 'TRANSLATE';

    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $sourceLocale,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::TRANSLATE === $attribute && $subject instanceof TranslationTarget;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());
        if (!\in_array('ROLE_TRANSLATION_EDITOR', $reachableRoles, true)) {
            return false;
        }

        if ($subject->locale === $this->sourceLocale) {
            return false;
        }

        if (!$user->canTranslateInto($subject->locale)) {
            return false;
        }

        if ($subject->content instanceof Deck && !$subject->content->isArchetypeVariant()) {
            return false;
        }

        return true;
    }
}
