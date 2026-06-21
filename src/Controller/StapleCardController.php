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

use App\Constants\CardHotness;
use App\Constants\ListingIntroPage;
use App\Constants\StapleCardBucket;
use App\Repository\PageRepository;
use App\Repository\StapleCardRepository;
use App\Service\ArchetypeDescriptionRenderer;
use App\Service\Channel\ChannelContext;
use App\Service\MarkdownRenderer;
use App\Service\Seo\MetaDescriptionResolver;
use App\Service\Seo\OgMetaResolver;
use App\Service\StapleCardImageResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public-facing list of staple cards — editor-curated must-haves grouped by card type.
 *
 * @see docs/features.md F6.15 — Staple cards
 */
class StapleCardController extends AbstractController
{
    #[Route('/{_locale}/staple-cards', name: 'app_staple_card_list', methods: ['GET'], requirements: ['_locale' => 'en|fr'], priority: 10)]
    public function list(
        Request $request,
        ChannelContext $channelContext,
        StapleCardRepository $stapleCardRepository,
        StapleCardImageResolver $imageResolver,
        MarkdownRenderer $markdownRenderer,
        PageRepository $pageRepository,
        ArchetypeDescriptionRenderer $contentRenderer,
        OgMetaResolver $ogMetaResolver,
        MetaDescriptionResolver $metaDescriptionResolver,
    ): Response {
        if (!$channelContext->getChannel()->getEnableStaples()) {
            throw new NotFoundHttpException('Staple cards are not enabled on this channel.');
        }

        $minHotness = $request->query->getInt('minHotness', CardHotness::STAPLE_THRESHOLD);
        if ($minHotness < 1) {
            $minHotness = 1;
        } elseif ($minHotness > 10) {
            $minHotness = 10;
        }

        $locale = $request->getLocale();

        $cardsByBucket = $stapleCardRepository->findActiveGroupedByBucket(StapleCardBucket::ORDER, $minHotness);
        $imageUrls = [];
        $renderedNotes = [];

        foreach ($cardsByBucket as $cards) {
            foreach ($cards as $card) {
                $id = $card->getId();
                if (null === $id) {
                    continue;
                }

                $imageUrls[$id] = $imageResolver->resolveForStaple($card, $locale);

                $note = $card->getNote();
                if (null !== $note && '' !== trim($note)) {
                    $renderedNotes[$id] = $markdownRenderer->render($note);
                }
            }
        }

        $introPage = $pageRepository->findBySlug(ListingIntroPage::STAPLE_CARDS_SLUG, $channelContext->getChannel());
        $introTranslation = $introPage?->getDisplayTranslation($locale);
        $introContent = $introTranslation?->getContent() ?? '';
        $introHtml = '' !== trim($introContent) ? $contentRenderer->render($introContent, $locale) : null;

        $ogMeta = null !== $introPage
            ? $ogMetaResolver->resolveForPage($introPage, $locale)
            : ['image' => null, 'description' => null];

        return $this->render('staple_card/list.html.twig', [
            'cardsByBucket' => $cardsByBucket,
            'buckets' => StapleCardBucket::ORDER,
            'imageUrls' => $imageUrls,
            'renderedNotes' => $renderedNotes,
            'minHotness' => $minHotness,
            'introHtml' => $introHtml,
            'introPage' => $introPage,
            'ogImage' => $ogMeta['image'],
            'ogDescription' => $ogMeta['description'],
            'metaDescription' => null !== $introPage
                ? $metaDescriptionResolver->resolveForPage($introPage, $locale)
                : null,
        ]);
    }
}
