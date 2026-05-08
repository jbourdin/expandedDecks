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
use App\Entity\Channel;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Form\PageFormType;
use App\Form\PageTranslationFormType;
use App\Repository\ChannelRepository;
use App\Repository\MenuCategoryRepository;
use App\Repository\PageRepository;
use App\Service\Channel\ChannelContext;
use App\Twig\Runtime\MenuRuntime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F11.1 — Content pages
 */
#[Route('/admin/pages')]
#[IsGranted('ROLE_CMS_EDITOR')]
class AdminPageController extends AbstractAppController
{
    private const int PER_PAGE_CATEGORY = 50;

    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($translator);
    }

    /**
     * @see docs/features.md F7.10 — Admin pages: filter by category and drag-and-drop sorting
     */
    /**
     * @see docs/features.md F7.10 — Admin pages: filter by category and drag-and-drop sorting
     * @see docs/features.md F18.8 — Add channel association to MenuCategory
     */
    #[Route('', name: 'app_admin_page_list', methods: ['GET'])]
    public function list(
        Request $request,
        PageRepository $pageRepository,
        MenuCategoryRepository $menuCategoryRepository,
        ChannelRepository $channelRepository,
        ChannelContext $channelContext,
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->getString('q');
        $categoryRaw = $request->query->getString('category');
        $categoryId = '' !== $categoryRaw ? (int) $categoryRaw : 0;

        $channelCode = $request->query->getString('channel', '');
        $currentChannel = '' !== $channelCode ? $channelRepository->findByCode($channelCode) : null;

        if (null === $currentChannel) {
            $currentChannel = $channelContext->getChannel();
        }

        $channels = $channelRepository->findAll();

        $categories = $menuCategoryRepository->findAllOrdered($currentChannel);

        $category = null;
        if ($categoryId > 0) {
            $category = $menuCategoryRepository->find($categoryId);
        }

        // Default to first category if none selected and categories exist
        if (null === $category && [] !== $categories) {
            $category = $categories[0];
        }

        $supportedLocales = $currentChannel->getLocales();

        // No categories on this channel → no pages to show
        if ([] === $categories) {
            return $this->render('admin/page/list.html.twig', [
                'contentPages' => [],
                'totalItems' => 0,
                'currentPage' => 1,
                'totalPages' => 1,
                'search' => $search,
                'supportedLocales' => $supportedLocales,
                'categories' => [],
                'currentCategory' => null,
                'sortableEnabled' => false,
                'channels' => $channels,
                'currentChannel' => $currentChannel,
            ]);
        }

        $perPage = self::PER_PAGE_CATEGORY;

        $queryBuilder = $pageRepository->createAdminListQueryBuilder($search, $category);
        $queryBuilder->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($queryBuilder, fetchJoinCollection: true);
        $totalItems = \count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $sortableEnabled = null !== $category && 1 === $page;

        return $this->render('admin/page/list.html.twig', [
            'contentPages' => $paginator,
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'supportedLocales' => $supportedLocales,
            'categories' => $categories,
            'currentCategory' => $category,
            'sortableEnabled' => $sortableEnabled,
            'channels' => $channels,
            'currentChannel' => $currentChannel,
        ]);
    }

    /**
     * @see docs/features.md F7.10 — Admin pages: filter by category and drag-and-drop sorting
     */
    #[Route('/reorder', name: 'app_admin_page_reorder', methods: ['POST'])]
    public function reorder(Request $request, PageRepository $pageRepository, MenuRuntime $menuRuntime): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return $this->json(['error' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        $pageIds = [];
        foreach ($payload as $id) {
            if (is_numeric($id)) {
                $pageIds[] = (int) $id;
            }
        }
        $pageRepository->reorderPages($pageIds);

        $menuRuntime->invalidateCache();

        return $this->json(['ok' => true]);
    }

    #[Route('/new', name: 'app_admin_page_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        MenuRuntime $menuRuntime,
        ChannelRepository $channelRepository,
        MenuCategoryRepository $menuCategoryRepository,
    ): Response {
        $page = new Page();

        $channelCode = $request->query->getString('channel');
        if ('' !== $channelCode) {
            $channel = $channelRepository->findByCode($channelCode);
            if ($channel instanceof Channel) {
                $page->setChannel($channel);
            }
        }

        $categoryRaw = $request->query->getString('category');
        $categoryId = '' !== $categoryRaw ? (int) $categoryRaw : 0;
        if ($categoryId > 0) {
            $category = $menuCategoryRepository->find($categoryId);
            if (null !== $category) {
                $page->setMenuCategory($category);
            }
        }

        $form = $this->createForm(PageFormType::class, $page, [
            'locale' => $request->getLocale(),
            'is_creation' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $title */
            $title = $form->get('title')->getData();

            $translation = new PageTranslation();
            $translation->setLocale('en');
            $translation->setTitle($title);
            $translation->setPage($page);
            $page->addTranslation($translation);

            $this->em->persist($page);
            $this->em->flush();
            $menuRuntime->invalidateCache();

            $this->addFlash('success', 'app.cms.page_created');

            return $this->redirectToRoute('app_admin_page_edit', ['id' => $page->getId()]);
        }

        return $this->render('admin/page/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_page_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Page $page, MenuRuntime $menuRuntime, ChannelContext $channelContext): Response
    {
        $isListingIntro = ListingIntroPage::isListingSlug($page->getSlug());

        $form = $this->createForm(PageFormType::class, $page, [
            'locale' => $request->getLocale(),
            'is_listing_intro' => $isListingIntro,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $menuRuntime->invalidateCache();

            $this->addFlash('success', 'app.cms.page_updated');

            return $this->redirectToRoute('app_admin_page_edit', ['id' => $page->getId()]);
        }

        $channel = $page->getChannel() ?? $channelContext->getChannel();
        $supportedLocales = $channel->getLocales();

        $translationForms = $this->buildTranslationForms($page, $request, $supportedLocales);

        return $this->render('admin/page/edit.html.twig', [
            'page' => $page,
            'form' => $form,
            'translationForms' => $translationForms,
            'supportedLocales' => $supportedLocales,
            'isListingIntro' => $isListingIntro,
        ]);
    }

    #[Route('/{id}/translation/{locale}', name: 'app_admin_page_translation', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveTranslation(Request $request, Page $page, string $locale, MenuRuntime $menuRuntime, ChannelContext $channelContext): Response
    {
        $channel = $page->getChannel() ?? $channelContext->getChannel();
        if (!\in_array($locale, $channel->getLocales(), true)) {
            throw $this->createNotFoundException();
        }

        $translation = $page->getTranslation($locale);
        $isNew = false;

        if (!$translation instanceof PageTranslation || $translation->getLocale() !== $locale) {
            $translation = new PageTranslation();
            $translation->setLocale($locale);
            $translation->setPage($page);
            $isNew = true;
        }

        $form = $this->container->get('form.factory')->createNamed(
            'page_translation_form_'.$locale,
            PageTranslationFormType::class,
            $translation,
            [
                'is_listing_intro' => ListingIntroPage::isListingSlug($page->getSlug()),
            ],
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $page->addTranslation($translation);
                $this->em->persist($translation);
            }
            $this->em->flush();
            $menuRuntime->invalidateCache();

            $this->addFlash('success', 'app.cms.translation_saved', ['%locale%' => strtoupper($locale)]);
        } else {
            $this->addFlash('danger', 'app.cms.translation_invalid');
        }

        return $this->redirect(
            $this->generateUrl('app_admin_page_edit', ['id' => $page->getId()]).'#pane-'.$locale
        );
    }

    #[Route('/{id}/delete', name: 'app_admin_page_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Page $page, MenuRuntime $menuRuntime): Response
    {
        if (ListingIntroPage::isListingSlug($page->getSlug())) {
            $this->addFlash('danger', 'app.flash.page.cannot_delete_listing_intro');

            return $this->redirectToRoute('app_admin_page_edit', ['id' => $page->getId()]);
        }

        if (!$this->isCsrfTokenValid('page-delete-'.$page->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_page_edit', ['id' => $page->getId()]);
        }

        $page->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();
        $menuRuntime->invalidateCache();

        $this->addFlash('success', 'app.flash.page.deleted');

        return $this->redirectToRoute('app_admin_page_list');
    }

    #[Route('/{id}/duplicate', name: 'app_admin_page_duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Request $request, Page $page, MenuRuntime $menuRuntime): Response
    {
        if (!$this->isCsrfTokenValid('page-duplicate-'.$page->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_page_list');
        }

        $duplicate = new Page();
        $duplicate->setSlug($page->getSlug().'-copy-'.bin2hex(random_bytes(3)));
        $duplicate->setMenuCategory($page->getMenuCategory());
        $duplicate->setIsPublished(false);
        $duplicate->setOgImage($page->getOgImage());
        $duplicate->setNoIndex($page->isNoIndex());

        foreach ($page->getTranslations() as $translation) {
            $duplicateTranslation = new PageTranslation();
            $duplicateTranslation->setPage($duplicate);
            $duplicateTranslation->setLocale($translation->getLocale());
            $duplicateTranslation->setTitle($translation->getTitle().' (copy)');
            $duplicateTranslation->setContent($translation->getContent());
            $duplicate->addTranslation($duplicateTranslation);
        }

        $this->em->persist($duplicate);
        $this->em->flush();
        $menuRuntime->invalidateCache();

        $this->addFlash('success', 'app.cms.page_duplicated');

        return $this->redirectToRoute('app_admin_page_edit', ['id' => $duplicate->getId()]);
    }

    /**
     * @param list<string> $locales
     *
     * @return array<string, \Symfony\Component\Form\FormView>
     */
    private function buildTranslationForms(Page $page, Request $request, array $locales): array
    {
        $forms = [];
        $isListingIntro = ListingIntroPage::isListingSlug($page->getSlug());

        foreach ($locales as $locale) {
            $translation = $page->getTranslation($locale);

            if (!$translation instanceof PageTranslation || $translation->getLocale() !== $locale) {
                $translation = new PageTranslation();
                $translation->setLocale($locale);
            }

            $form = $this->container->get('form.factory')->createNamed(
                'page_translation_form_'.$locale,
                PageTranslationFormType::class,
                $translation,
                [
                    'action' => $this->generateUrl('app_admin_page_translation', [
                        'id' => $page->getId(),
                        'locale' => $locale,
                    ]),
                    'is_listing_intro' => $isListingIntro,
                ],
            );

            // Handle submission for the current locale
            if ($request->isMethod('POST') && $request->getPayload()->getString('_locale') === $locale) {
                $form->handleRequest($request);
            }

            $forms[$locale] = $form->createView();
        }

        return $forms;
    }
}
