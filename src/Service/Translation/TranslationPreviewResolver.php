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

namespace App\Service\Translation;

use App\Entity\Archetype;
use App\Entity\BannedCard;
use App\Entity\Deck;
use App\Entity\MenuCategory;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\PageTranslationRevision;
use App\Entity\StapleCard;
use App\Entity\TranslationRevisionInterface;
use App\Security\TranslationTarget;
use App\Security\Voter\TranslationVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Draft-translation preview on public pages (`?translationPreview=1`):
 * renders the pending (not-yet-validated) revision's content in the real
 * template, for the people working on it — translators granted the target
 * locale and moderators. Everyone else keeps the live content, param or not.
 *
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 */
final readonly class TranslationPreviewResolver
{
    public function __construct(
        private TranslationDraftProvider $draftProvider,
        private Security $security,
        #[Autowire('%kernel.default_locale%')]
        private string $sourceLocale,
    ) {
    }

    public function canPreview(Page|Archetype|MenuCategory|Deck|BannedCard|StapleCard $content, string $locale): bool
    {
        if ($locale === $this->sourceLocale) {
            return false;
        }

        return $this->security->isGranted('ROLE_TRANSLATION_MODERATOR')
            || $this->security->isGranted(TranslationVoter::TRANSLATE, new TranslationTarget($content, $locale));
    }

    public function pendingRevision(Page|Archetype|MenuCategory|Deck|BannedCard|StapleCard $content, string $locale): ?TranslationRevisionInterface
    {
        return $this->draftProvider->findPending($content, $locale);
    }

    /**
     * Transient translation carrying the pending revision's fields — never
     * persisted, purely a rendering vehicle.
     */
    public function previewPageTranslation(Page $page, string $locale): ?PageTranslation
    {
        $pending = $this->pendingRevision($page, $locale);
        if (!$pending instanceof PageTranslationRevision) {
            return null;
        }

        $preview = new PageTranslation();
        $preview->setPage($page);
        $preview->setLocale($locale);
        $pending->applyTo($preview);

        return $preview;
    }
}
