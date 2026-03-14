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
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F7.2 — User management
 */
#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractAppController
{
    private const int PER_PAGE = 20;

    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_user_list', methods: ['GET'])]
    public function list(Request $request, UserRepository $userRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->getString('q');

        $qb = $userRepository->createAdminListQueryBuilder($search);
        $qb->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($qb, fetchJoinCollection: false);
        $totalItems = \count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        return $this->render('admin/user/list.html.twig', [
            'users' => $paginator,
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
            'availableRoles' => $this->getAssignableRoles(),
        ]);
    }

    #[Route('/{id}/roles', name: 'app_admin_user_roles', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateRoles(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('user-roles-'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()]);
        }

        /** @var list<string> $roles */
        $roles = $request->getPayload()->all('roles');
        $assignable = $this->getAssignableRoles();
        $roles = array_values(array_intersect($roles, $assignable));

        $user->setRoles($roles);
        $this->em->flush();

        $this->addFlash('success', 'app.admin.user.roles_updated');

        return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/disable', name: 'app_admin_user_disable', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function disable(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('user-disable-'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()]);
        }

        if (null !== $user->getDeletedAt()) {
            $user->setDeletedAt(null);
            $this->em->flush();
            $this->addFlash('success', 'app.admin.user.enabled');
        } else {
            $user->setDeletedAt(new \DateTimeImmutable());
            $this->em->flush();
            $this->addFlash('success', 'app.admin.user.disabled');
        }

        return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/anonymize', name: 'app_admin_user_anonymize', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function anonymize(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('user-anonymize-'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()]);
        }

        if ($user->isAnonymized()) {
            $this->addFlash('warning', 'app.admin.user.already_anonymized');

            return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()]);
        }

        $user->anonymize();

        $this->em->flush();

        $this->addFlash('success', 'app.admin.user.anonymized');

        return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()]);
    }

    /**
     * @return list<string>
     */
    private function getAssignableRoles(): array
    {
        return ['ROLE_ADMIN', 'ROLE_ORGANIZER', 'ROLE_CMS_EDITOR', 'ROLE_ARCHETYPE_EDITOR', 'ROLE_TECHNICAL_ADMIN'];
    }
}
