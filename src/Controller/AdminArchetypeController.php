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

use App\Entity\Archetype;
use App\Entity\ArchetypeTranslation;
use App\Form\ArchetypeFormType;
use App\Form\ArchetypeTranslationFormType;
use App\Repository\ArchetypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F2.6 — Archetype management
 * @see docs/features.md F2.18 — Admin archetype create/edit form
 * @see docs/features.md F9.6 — Archetype localization
 */
#[Route('/admin/archetypes')]
#[IsGranted('ROLE_ADMIN')]
class AdminArchetypeController extends AbstractAppController
{
    private const array SUPPORTED_LOCALES = ['en', 'fr'];

    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_archetype_list', methods: ['GET'])]
    public function list(ArchetypeRepository $archetypeRepository): Response
    {
        $archetypes = $archetypeRepository->findBy([], ['name' => 'ASC']);

        return $this->render('admin/archetype/list.html.twig', [
            'archetypes' => $archetypes,
        ]);
    }

    /**
     * @see docs/features.md F2.18 — Admin archetype create/edit form
     */
    #[Route('/new', name: 'app_admin_archetype_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ArchetypeRepository $archetypeRepository): Response
    {
        $archetype = new Archetype();
        $form = $this->createForm(ArchetypeFormType::class, $archetype);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePokemonSlugs($form, $archetype);
            $this->handlePlaystyleTags($form, $archetype);
            $this->em->persist($archetype);
            $this->em->flush();

            $this->addFlash('success', 'app.archetype.created', ['%name%' => $archetype->getName()]);

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        return $this->render('admin/archetype/new.html.twig', [
            'form' => $form,
            'existingTags' => $this->collectExistingTags($archetypeRepository),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_archetype_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Archetype $archetype, ArchetypeRepository $archetypeRepository): Response
    {
        $form = $this->createForm(ArchetypeFormType::class, $archetype);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePokemonSlugs($form, $archetype);
            $this->handlePlaystyleTags($form, $archetype);
            $this->em->flush();

            $this->addFlash('success', 'app.archetype.updated', ['%name%' => $archetype->getName()]);

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        $translationForms = $this->buildTranslationForms($archetype, $request);

        return $this->render('admin/archetype/edit.html.twig', [
            'archetype' => $archetype,
            'form' => $form,
            'existingTags' => $this->collectExistingTags($archetypeRepository),
            'translationForms' => $translationForms,
            'supportedLocales' => self::SUPPORTED_LOCALES,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_archetype_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Archetype $archetype): Response
    {
        if (!$this->isCsrfTokenValid('archetype-delete-'.$archetype->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        $archetype->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->addFlash('success', 'app.flash.archetype.deleted');

        return $this->redirectToRoute('app_admin_archetype_list');
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    #[Route('/{id}/translation/{locale}', name: 'app_admin_archetype_translation', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveTranslation(Request $request, Archetype $archetype, string $locale): Response
    {
        if (!\in_array($locale, self::SUPPORTED_LOCALES, true)) {
            throw $this->createNotFoundException();
        }

        $translation = $archetype->getTranslation($locale);
        $isNew = false;

        if (!$translation instanceof ArchetypeTranslation || $translation->getLocale() !== $locale) {
            $translation = new ArchetypeTranslation();
            $translation->setLocale($locale);
            $translation->setArchetype($archetype);
            $isNew = true;
        }

        $form = $this->createForm(ArchetypeTranslationFormType::class, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $archetype->addTranslation($translation);
                $this->em->persist($translation);
            }
            $this->em->flush();

            $this->addFlash('success', 'app.cms.translation_saved', ['%locale%' => strtoupper($locale)]);
        } else {
            $this->addFlash('danger', 'app.cms.translation_invalid');
        }

        return $this->redirect(
            $this->generateUrl('app_admin_archetype_edit', ['id' => $archetype->getId()]).'#pane-'.$locale
        );
    }

    /**
     * @param FormInterface<Archetype> $form
     */
    private function handlePokemonSlugs(FormInterface $form, Archetype $archetype): void
    {
        /** @var string|null $slugsJson */
        $slugsJson = $form->get('pokemonSlugs')->getData();

        if (null !== $slugsJson && '' !== $slugsJson) {
            /** @var list<string> $slugs */
            $slugs = json_decode($slugsJson, true);
            $archetype->setPokemonSlugs($slugs);
        } else {
            $archetype->setPokemonSlugs([]);
        }
    }

    /**
     * @see docs/features.md F2.15 — Archetype playstyle tags
     *
     * @param FormInterface<Archetype> $form
     */
    private function handlePlaystyleTags(FormInterface $form, Archetype $archetype): void
    {
        /** @var string|null $tagsJson */
        $tagsJson = $form->get('playstyleTags')->getData();

        if (null !== $tagsJson && '' !== $tagsJson) {
            /** @var list<string> $rawTags */
            $rawTags = json_decode($tagsJson, true);
            $normalized = array_values(array_unique(array_filter(array_map(self::normalizeTag(...), $rawTags))));
            $archetype->setPlaystyleTags($normalized);
        } else {
            $archetype->setPlaystyleTags([]);
        }
    }

    /**
     * Normalize a tag: only alphanumeric and spaces, title case.
     *
     * @see docs/features.md F2.15 — Archetype playstyle tags
     */
    private static function normalizeTag(string $tag): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9 ]/', '', $tag) ?? '';
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned) ?? '');

        return mb_convert_case($cleaned, \MB_CASE_TITLE);
    }

    /**
     * @return array<string, FormView>
     *
     * @see docs/features.md F9.6 — Archetype localization
     */
    private function buildTranslationForms(Archetype $archetype, Request $request): array
    {
        $forms = [];

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $translation = $archetype->getTranslation($locale);

            if (!$translation instanceof ArchetypeTranslation || $translation->getLocale() !== $locale) {
                $translation = new ArchetypeTranslation();
                $translation->setLocale($locale);
            }

            $form = $this->createForm(ArchetypeTranslationFormType::class, $translation, [
                'action' => $this->generateUrl('app_admin_archetype_translation', [
                    'id' => $archetype->getId(),
                    'locale' => $locale,
                ]),
            ]);

            $forms[$locale] = $form->createView();
        }

        return $forms;
    }

    /**
     * Collect all unique playstyle tags from existing archetypes.
     *
     * @return list<string>
     */
    private function collectExistingTags(ArchetypeRepository $archetypeRepository): array
    {
        $allTags = [];
        foreach ($archetypeRepository->findAll() as $archetype) {
            foreach ($archetype->getPlaystyleTags() as $tag) {
                $allTags[$tag] = true;
            }
        }
        $tags = array_keys($allTags);
        sort($tags);

        return $tags;
    }
}
