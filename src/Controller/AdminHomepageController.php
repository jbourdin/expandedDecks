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

use App\Entity\HomepageLayout;
use App\Entity\HomepageLayoutTranslation;
use App\Repository\ChannelRepository;
use App\Repository\HomepageLayoutRepository;
use App\Service\Channel\ChannelContext;
use App\Service\HomepageRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F10.5 — Homepage block editor (admin UI)
 */
#[Route('/admin/homepage')]
#[IsGranted('ROLE_CMS_EDITOR')]
class AdminHomepageController extends AbstractAppController
{
    // Fallback when no channel is resolved (e.g. legacy null-channel layouts).
    // The editor and save endpoints normally use `Channel::getLocales()` so
    // each channel only edits the languages it actually serves.
    private const array FALLBACK_LOCALES = ['en'];

    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly HomepageLayoutRepository $layoutRepository,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_homepage_editor', methods: ['GET'])]
    public function editor(
        Request $request,
        ChannelRepository $channelRepository,
        ChannelContext $channelContext,
        \App\Repository\MenuCategoryRepository $menuCategoryRepository,
    ): Response {
        $channelCode = $request->query->getString('channel', '');
        $channel = '' !== $channelCode ? $channelRepository->findByCode($channelCode) : null;

        if (null === $channel) {
            $channel = $channelContext->getChannel();
        }

        $channels = $channelRepository->findAll();
        $layout = $this->layoutRepository->findPublished($channel);
        $locales = $channel->getLocales();

        // Categories used by the latestPages block selector — scoped to the editor's channel.
        $categories = [];
        foreach ($menuCategoryRepository->findAllOrdered($channel) as $category) {
            $categories[] = [
                'id' => $category->getId(),
                'name' => $category->getName('en'),
            ];
        }

        // Seed an empty entry for every channel locale so the React editor
        // always renders an input per active language, even when the layout
        // has never been saved yet. Existing translations for locales not in
        // the channel's current list are intentionally hidden (they remain in
        // the DB and are preserved on save).
        $meta = [];
        foreach ($locales as $locale) {
            $translation = $layout?->getTranslation($locale);
            $meta[$locale] = [
                'title' => $translation?->getTitle() ?? '',
                'ogDescription' => $translation?->getOgDescription() ?? '',
            ];
        }

        return $this->render('admin/homepage/editor.html.twig', [
            'layout' => $layout,
            'supportedLocales' => $locales,
            'channels' => $channels,
            'currentChannel' => $channel,
            'categories' => $categories,
            'meta' => $meta,
        ]);
    }

    #[Route('/save', name: 'app_admin_homepage_save', methods: ['POST'])]
    public function save(Request $request, ChannelRepository $channelRepository): JsonResponse
    {
        /** @var array{blocks: list<array<string, mixed>>, translations: array<string, array<int|string, array<string, mixed>>>, channelCode?: string, ogImage?: string|null, meta?: array<string, array{title?: string|null, ogDescription?: string|null}>} $payload */
        $payload = json_decode((string) $request->getContent(), true);

        $blocks = $payload['blocks'];
        $translations = $payload['translations'];
        $channelCode = $payload['channelCode'] ?? '';
        $channel = '' !== $channelCode ? $channelRepository->findByCode($channelCode) : null;
        $ogImage = $payload['ogImage'] ?? null;
        if (\is_string($ogImage)) {
            $ogImage = '' === trim($ogImage) ? null : $ogImage;
        }
        $meta = $payload['meta'] ?? [];

        $layout = $this->layoutRepository->findPublished($channel);

        if (null === $layout) {
            $layout = new HomepageLayout();
            $layout->setIsPublished(true);
            $layout->setChannel($channel);
            $this->entityManager->persist($layout);
        }

        $layout->setBlocks($blocks);
        $layout->setOgImage($ogImage);

        // Only persist translations for locales the channel actually serves —
        // this prevents the editor from creating phantom translation rows in
        // languages the channel does not display.
        $localesToUpdate = $channel?->getLocales() ?? self::FALLBACK_LOCALES;

        foreach ($localesToUpdate as $locale) {
            $translationEntity = $layout->getTranslation($locale);

            if (!$translationEntity instanceof HomepageLayoutTranslation || $translationEntity->getLocale() !== $locale) {
                $translationEntity = new HomepageLayoutTranslation();
                $translationEntity->setLocale($locale);
                $translationEntity->setHomepageLayout($layout);
                $layout->addTranslation($translationEntity);
                $this->entityManager->persist($translationEntity);
            }

            $translationEntity->setBlockTranslations($translations[$locale] ?? []);

            $localeMeta = $meta[$locale] ?? [];
            $title = $localeMeta['title'] ?? null;
            $ogDescription = $localeMeta['ogDescription'] ?? null;
            $translationEntity->setTitle(\is_string($title) && '' !== trim($title) ? $title : null);
            $translationEntity->setOgDescription(\is_string($ogDescription) && '' !== trim($ogDescription) ? $ogDescription : null);
        }

        $this->entityManager->flush();

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/preview', name: 'app_admin_homepage_preview', methods: ['POST'])]
    public function preview(Request $request, HomepageRenderer $homepageRenderer): Response
    {
        /** @var array{blocks: list<array<string, mixed>>, translations: array<string, array<int|string, array<string, mixed>>>} $payload */
        $payload = json_decode((string) $request->getContent(), true);

        $locale = $request->getLocale();

        // Build a transient layout for preview (not persisted)
        $layout = new HomepageLayout();
        $layout->setBlocks($payload['blocks']);

        foreach ($payload['translations'] as $translationLocale => $blockTranslations) {
            $translationEntity = new HomepageLayoutTranslation();
            $translationEntity->setLocale($translationLocale);
            $translationEntity->setBlockTranslations($blockTranslations);
            $layout->addTranslation($translationEntity);
        }

        $resolvedBlocks = $homepageRenderer->resolve($layout, $locale);

        return $this->render('home/blocks.html.twig', [
            'blocks' => $resolvedBlocks,
        ]);
    }
}
