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
use App\Form\ArchetypeFormType;
use App\Repository\ArchetypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F2.6 — Archetype management
 * @see docs/features.md F2.18 — Admin archetype create/edit form
 */
#[Route('/admin/archetypes')]
#[IsGranted('ROLE_ADMIN')]
class AdminArchetypeController extends AbstractAppController
{
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

        return $this->render('admin/archetype/edit.html.twig', [
            'archetype' => $archetype,
            'form' => $form,
            'existingTags' => $this->collectExistingTags($archetypeRepository),
        ]);
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
