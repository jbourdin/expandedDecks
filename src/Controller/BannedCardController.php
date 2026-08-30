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

namespace App\Controller;

use App\Constants\ListingIntroPage;
use App\Entity\BannedCardTranslationRevision;
use App\Repository\BannedCardRepository;
use App\Repository\PageRepository;
use App\Routing\LocaleRequirement;
use App\Service\ArchetypeDescriptionRenderer;
use App\Service\BannedCardImageResolver;
use App\Service\Channel\ChannelContext;
use App\Service\MarkdownRenderer;
use App\Service\Seo\MetaDescriptionResolver;
use App\Service\Seo\OgMetaResolver;
use App\Service\Translation\TranslationPreviewResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public-facing list of cards currently banned in the Expanded format.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardController extends AbstractController
{
    /**
     * @see docs/features.md F6.14 — Banned cards public page
     */
    #[Route('/{_locale}/banned-cards', name: 'app_banned_card_list', methods: ['GET'], requirements: ['_locale' => LocaleRequirement::PATTERN], priority: 10)]
    public function list(
        Request $request,
        ChannelContext $channelContext,
        BannedCardRepository $bannedCardRepository,
        BannedCardImageResolver $imageResolver,
        MarkdownRenderer $markdownRenderer,
        PageRepository $pageRepository,
        ArchetypeDescriptionRenderer $contentRenderer,
        OgMetaResolver $ogMetaResolver,
        MetaDescriptionResolver $metaDescriptionResolver,
        TranslationPreviewResolver $translationPreviewResolver,
    ): Response {
        $bannedCards = $bannedCardRepository->findActiveOrderedByEffectiveDate();
        $locale = $request->getLocale();

        // Draft-translation preview (F9.17): pending revisions render in
        // place of the live explanations for authorized users only.
        $translationPreview = $request->query->getBoolean('translationPreview');

        $imageUrls = [];
        $renderedExplanations = [];
        $outdatedExplanations = [];
        foreach ($bannedCards as $card) {
            $id = $card->getId();
            if (null === $id) {
                continue;
            }

            $imageUrls[$id] = $imageResolver->resolveForBan($card);

            // Localized explanation with source fallback (F9.17); the
            // denormalized flag drives the reader-facing notice (F9.14).
            $explanation = $card->localizedExplanation($locale);
            if ($translationPreview && $translationPreviewResolver->canPreview($card, $locale)) {
                $pending = $translationPreviewResolver->pendingRevision($card, $locale);
                if ($pending instanceof BannedCardTranslationRevision) {
                    $explanation = $pending->getExplanation();
                }
            }
            if (null !== $explanation && '' !== trim($explanation)) {
                $renderedExplanations[$id] = $markdownRenderer->render($explanation);
            }
            $cardTranslation = $card->translationFor($locale);
            $outdatedExplanations[$id] = null !== $cardTranslation && $cardTranslation->isSourceOutdated();
        }

        $introPage = $pageRepository->findBySlug(ListingIntroPage::BANNED_CARDS_SLUG, $channelContext->getChannel());
        $introTranslation = $introPage?->getDisplayTranslation($locale);
        $introContent = $introTranslation?->getContent() ?? '';
        $introHtml = '' !== trim($introContent) ? $contentRenderer->render($introContent, $locale) : null;

        $ogMeta = null !== $introPage
            ? $ogMetaResolver->resolveForPage($introPage, $locale)
            : ['image' => null, 'description' => null];
        $metaDescription = null !== $introPage ? $metaDescriptionResolver->resolveForPage($introPage, $locale) : null;

        return $this->render('banned_card/list.html.twig', [
            'bannedCards' => $bannedCards,
            'imageUrls' => $imageUrls,
            'renderedExplanations' => $renderedExplanations,
            'outdatedExplanations' => $outdatedExplanations,
            'introHtml' => $introHtml,
            'introPage' => $introPage,
            'ogImage' => $ogMeta['image'],
            'ogDescription' => $ogMeta['description'],
            'metaDescription' => $metaDescription,
        ]);
    }
}
