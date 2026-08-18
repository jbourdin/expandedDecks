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

namespace App\Service\Channel;

use App\Entity\Channel;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Decides who may see a channel's DRAFT locales (F9.16): translators assigned
 * the locale, translation moderators (CMS editors and admins inherit the
 * role), nobody else. Published locales are visible to everyone.
 *
 * Callers on anonymous-cacheable paths must gate calls on an existing session
 * cookie themselves: this service consults the security token, which would
 * otherwise start a session.
 *
 * @see docs/features.md F9.16 — Channel locale management
 */
final readonly class ChannelLocaleVisibility
{
    public function __construct(
        private Security $security,
        private RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    public function canSeeDraftLocale(Channel $channel, string $locale): bool
    {
        if (!\in_array($locale, $channel->getDraftLocales(), true)) {
            return false;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());
        if (\in_array('ROLE_TRANSLATION_MODERATOR', $reachableRoles, true)) {
            return true;
        }

        return \in_array('ROLE_TRANSLATION_EDITOR', $reachableRoles, true)
            && $user->canTranslateInto($locale);
    }

    /**
     * Draft locales of the channel the current user may browse.
     *
     * @return list<string>
     */
    public function visibleDraftLocales(Channel $channel): array
    {
        return array_values(array_filter(
            $channel->getDraftLocales(),
            fn (string $locale): bool => $this->canSeeDraftLocale($channel, $locale),
        ));
    }
}
