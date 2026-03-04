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

use App\Entity\User;
use App\Form\ProfileFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * @see docs/features.md F1.3 — User profile
 */
#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractAppController
{
    /**
     * @see docs/features.md F1.3 — User profile
     */
    #[Route('', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em, LocaleSwitcher $localeSwitcher): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $locale = $user->getPreferredLocale();
            $request->setLocale($locale);
            $request->getSession()->set('_locale', $locale);
            $localeSwitcher->setLocale($locale);

            $this->addFlash('success', 'app.flash.profile.saved');

            return $this->redirectToRoute('app_profile_edit');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
        ]);
    }
}
