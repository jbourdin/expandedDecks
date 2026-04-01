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
use App\Repository\HomepageLayoutRepository;
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
    private const array SUPPORTED_LOCALES = ['en', 'fr'];

    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly HomepageLayoutRepository $layoutRepository,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_homepage_editor', methods: ['GET'])]
    public function editor(): Response
    {
        $layout = $this->layoutRepository->findPublished();

        return $this->render('admin/homepage/editor.html.twig', [
            'layout' => $layout,
            'supportedLocales' => self::SUPPORTED_LOCALES,
        ]);
    }

    #[Route('/save', name: 'app_admin_homepage_save', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        /** @var array{blocks: list<array<string, mixed>>, translations: array<string, array<int|string, array<string, mixed>>>} $payload */
        $payload = json_decode((string) $request->getContent(), true);

        $blocks = $payload['blocks'];
        $translations = $payload['translations'];

        $layout = $this->layoutRepository->findPublished();

        if (null === $layout) {
            $layout = new HomepageLayout();
            $layout->setIsPublished(true);
            $this->entityManager->persist($layout);
        }

        $layout->setBlocks($blocks);

        // Update translations per locale
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $translationEntity = $layout->getTranslation($locale);

            if (!$translationEntity instanceof HomepageLayoutTranslation || $translationEntity->getLocale() !== $locale) {
                $translationEntity = new HomepageLayoutTranslation();
                $translationEntity->setLocale($locale);
                $translationEntity->setHomepageLayout($layout);
                $layout->addTranslation($translationEntity);
                $this->entityManager->persist($translationEntity);
            }

            $translationEntity->setBlockTranslations($translations[$locale] ?? []);
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
