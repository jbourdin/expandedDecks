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

use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslation;
use App\Form\MenuCategoryFormType;
use App\Form\MenuCategoryTranslationFormType;
use App\Repository\MenuCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F11.2 — Menu categories
 */
#[Route('/admin/menu-categories')]
#[IsGranted('ROLE_CMS_EDITOR')]
class AdminMenuCategoryController extends AbstractAppController
{
    private const array SUPPORTED_LOCALES = ['en', 'fr'];

    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_menu_category_list', methods: ['GET'])]
    public function list(MenuCategoryRepository $repository): Response
    {
        return $this->render('admin/menu_category/list.html.twig', [
            'categories' => $repository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'app_admin_menu_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new MenuCategory();
        $form = $this->createForm(MenuCategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($category);
            $this->em->flush();

            $this->addFlash('success', 'app.cms.category_created');

            return $this->redirectToRoute('app_admin_menu_category_edit', ['id' => $category->getId()]);
        }

        return $this->render('admin/menu_category/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_menu_category_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, MenuCategory $category): Response
    {
        $form = $this->createForm(MenuCategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'app.cms.category_updated');

            return $this->redirectToRoute('app_admin_menu_category_edit', ['id' => $category->getId()]);
        }

        $translationForms = [];
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $translation = $category->getTranslation($locale);

            if (!$translation instanceof MenuCategoryTranslation || $translation->getLocale() !== $locale) {
                $translation = new MenuCategoryTranslation();
                $translation->setLocale($locale);
            }

            $translationForm = $this->createForm(MenuCategoryTranslationFormType::class, $translation, [
                'action' => $this->generateUrl('app_admin_menu_category_translation', [
                    'id' => $category->getId(),
                    'locale' => $locale,
                ]),
            ]);

            $translationForms[$locale] = $translationForm->createView();
        }

        return $this->render('admin/menu_category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
            'translationForms' => $translationForms,
            'supportedLocales' => self::SUPPORTED_LOCALES,
        ]);
    }

    #[Route('/{id}/translation/{locale}', name: 'app_admin_menu_category_translation', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveTranslation(Request $request, MenuCategory $category, string $locale): Response
    {
        if (!\in_array($locale, self::SUPPORTED_LOCALES, true)) {
            throw $this->createNotFoundException();
        }

        $translation = $category->getTranslation($locale);
        $isNew = false;

        if (!$translation instanceof MenuCategoryTranslation || $translation->getLocale() !== $locale) {
            $translation = new MenuCategoryTranslation();
            $translation->setLocale($locale);
            $translation->setMenuCategory($category);
            $isNew = true;
        }

        $form = $this->createForm(MenuCategoryTranslationFormType::class, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $category->addTranslation($translation);
                $this->em->persist($translation);
            }
            $this->em->flush();

            $this->addFlash('success', 'app.cms.translation_saved', ['%locale%' => strtoupper($locale)]);
        } else {
            $this->addFlash('danger', 'app.cms.translation_invalid');
        }

        return $this->redirect(
            $this->generateUrl('app_admin_menu_category_edit', ['id' => $category->getId()]).'#pane-'.$locale
        );
    }

    #[Route('/{id}/delete', name: 'app_admin_menu_category_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, MenuCategory $category): Response
    {
        if (!$this->isCsrfTokenValid('category-delete-'.$category->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_menu_category_edit', ['id' => $category->getId()]);
        }

        $this->em->remove($category);
        $this->em->flush();

        $this->addFlash('success', 'app.cms.category_deleted');

        return $this->redirectToRoute('app_admin_menu_category_list');
    }
}
