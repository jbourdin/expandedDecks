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

namespace App\Twig\Extension;

use App\Entity\Archetype;
use App\Entity\BannedCard;
use App\Entity\Deck;
use App\Entity\MenuCategory;
use App\Entity\Page;
use App\Entity\StapleCard;
use App\Entity\User;
use App\Security\TranslationTarget;
use App\Security\Voter\TranslationVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Template helpers for the translation entry points (US-T1 / US-T2):
 * `translatable_locales(content)` lists the target locales the current user
 * may translate the given content into — one item renders a direct Translate
 * action, several render a contextual language choice.
 *
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 */
class TranslationWorkflowExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('translatable_locales', $this->translatableLocales(...)),
        ];
    }

    /**
     * @return list<string>
     */
    public function translatableLocales(Page|Archetype|MenuCategory|Deck|BannedCard|StapleCard $content): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $locales = [];
        foreach ($user->getTranslationLocales() as $locale) {
            if ($this->security->isGranted(TranslationVoter::TRANSLATE, new TranslationTarget($content, $locale))) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }
}
