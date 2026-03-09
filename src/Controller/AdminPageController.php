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

use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Form\PageFormType;
use App\Form\PageTranslationFormType;
use App\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
    private const int PER_PAGE = 20;
    private const array SUPPORTED_LOCALES = ['en', 'fr'];

    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_page_list', methods: ['GET'])]
    public function list(Request $request, PageRepository $pageRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->getString('q');

        $queryBuilder = $pageRepository->createAdminListQueryBuilder($search);
        $queryBuilder->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($queryBuilder, fetchJoinCollection: true);
        $totalItems = \count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        return $this->render('admin/page/list.html.twig', [
            'contentPages' => $paginator,
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_admin_page_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $page = new Page();
        $form = $this->createForm(PageFormType::class, $page, [
            'locale' => $request->getLocale(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($page);
            $this->em->flush();

            $this->addFlash('success', 'app.cms.page_created');

            return $this->redirectToRoute('app_admin_page_edit', ['id' => $page->getId()]);
        }

        return $this->render('admin/page/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_page_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Page $page): Response
    {
        $form = $this->createForm(PageFormType::class, $page, [
            'locale' => $request->getLocale(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'app.cms.page_updated');

            return $this->redirectToRoute('app_admin_page_edit', ['id' => $page->getId()]);
        }

        $translationForms = $this->buildTranslationForms($page, $request);

        return $this->render('admin/page/edit.html.twig', [
            'page' => $page,
            'form' => $form,
            'translationForms' => $translationForms,
            'supportedLocales' => self::SUPPORTED_LOCALES,
        ]);
    }

    #[Route('/{id}/translation/{locale}', name: 'app_admin_page_translation', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveTranslation(Request $request, Page $page, string $locale): Response
    {
        if (!\in_array($locale, self::SUPPORTED_LOCALES, true)) {
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

        $form = $this->createForm(PageTranslationFormType::class, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $page->addTranslation($translation);
                $this->em->persist($translation);
            }
            $this->em->flush();

            $this->addFlash('success', 'app.cms.translation_saved', ['%locale%' => strtoupper($locale)]);
        } else {
            $this->addFlash('danger', 'app.cms.translation_invalid');
        }

        return $this->redirect(
            $this->generateUrl('app_admin_page_edit', ['id' => $page->getId()]).'#pane-'.$locale
        );
    }

    #[Route('/{id}/delete', name: 'app_admin_page_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Page $page): Response
    {
        if (!$this->isCsrfTokenValid('page-delete-'.$page->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_page_edit', ['id' => $page->getId()]);
        }

        $this->em->remove($page);
        $this->em->flush();

        $this->addFlash('success', 'app.cms.page_deleted');

        return $this->redirectToRoute('app_admin_page_list');
    }

    /**
     * @return array<string, \Symfony\Component\Form\FormView>
     */
    private function buildTranslationForms(Page $page, Request $request): array
    {
        $forms = [];

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $translation = $page->getTranslation($locale);

            if (!$translation instanceof PageTranslation || $translation->getLocale() !== $locale) {
                $translation = new PageTranslation();
                $translation->setLocale($locale);
            }

            $form = $this->createForm(PageTranslationFormType::class, $translation, [
                'action' => $this->generateUrl('app_admin_page_translation', [
                    'id' => $page->getId(),
                    'locale' => $locale,
                ]),
            ]);

            // Handle submission for the current locale
            if ($request->isMethod('POST') && $request->getPayload()->getString('_locale') === $locale) {
                $form->handleRequest($request);
            }

            $forms[$locale] = $form->createView();
        }

        return $forms;
    }
}
