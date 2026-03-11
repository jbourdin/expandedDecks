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
    public function new(Request $request): Response
    {
        $archetype = new Archetype();
        $form = $this->createForm(ArchetypeFormType::class, $archetype);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePokemonSlugs($form, $archetype);
            $this->em->persist($archetype);
            $this->em->flush();

            $this->addFlash('success', 'app.archetype.created', ['%name%' => $archetype->getName()]);

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        return $this->render('admin/archetype/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_archetype_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Archetype $archetype): Response
    {
        $form = $this->createForm(ArchetypeFormType::class, $archetype);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePokemonSlugs($form, $archetype);
            $this->em->flush();

            $this->addFlash('success', 'app.archetype.updated', ['%name%' => $archetype->getName()]);

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        return $this->render('admin/archetype/edit.html.twig', [
            'archetype' => $archetype,
            'form' => $form,
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
}
