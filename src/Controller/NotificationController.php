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

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F8.4 — In-app notification center
 */
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractAppController
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($translator);
    }

    #[Route('/api/notifications', name: 'app_notification_api_list', methods: ['GET'])]
    public function apiList(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $notifications = $this->notificationRepository->findRecentByRecipient($user, 10);
        $unreadCount = $this->notificationRepository->countUnreadByRecipient($user);

        return $this->json([
            'unreadCount' => $unreadCount,
            'notifications' => array_map($this->serializeNotification(...), $notifications),
        ]);
    }

    #[Route('/api/notifications/{id}/read', name: 'app_notification_api_mark_read', methods: ['POST'])]
    public function apiMarkRead(Notification $notification): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($notification->getRecipient()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $notification->setIsRead(true);
        $this->entityManager->flush();

        $unreadCount = $this->notificationRepository->countUnreadByRecipient($user);

        return $this->json([
            'unreadCount' => $unreadCount,
            'notification' => $this->serializeNotification($notification),
        ]);
    }

    #[Route('/api/notifications/read-all', name: 'app_notification_api_mark_all_read', methods: ['POST'])]
    public function apiMarkAllRead(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->notificationRepository->markAllAsReadByRecipient($user);

        return $this->json(['unreadCount' => 0]);
    }

    #[Route('/notifications', name: 'app_notification_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $notifications = $this->notificationRepository->findByRecipientPaginated($user, $limit, $offset);
        $total = $this->notificationRepository->countByRecipient($user);

        $urls = [];
        foreach ($notifications as $notification) {
            $id = $notification->getId();
            if (null !== $id) {
                $urls[$id] = $this->resolveNotificationUrl($notification);
            }
        }

        return $this->render('notification/list.html.twig', [
            'notifications' => $notifications,
            'notificationUrls' => $urls,
            'currentPage' => $page,
            'totalPages' => (int) ceil($total / $limit),
            'total' => $total,
        ]);
    }

    #[Route('/notifications/{id}/go', name: 'app_notification_go', methods: ['GET'])]
    public function go(Notification $notification): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($notification->getRecipient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $this->entityManager->flush();
        }

        $url = $this->resolveNotificationUrl($notification);

        return $this->redirect($url ?? $this->generateUrl('app_notification_list'));
    }

    #[Route('/notifications/{id}/read', name: 'app_notification_mark_read', methods: ['POST'])]
    public function markRead(Notification $notification, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($notification->getRecipient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('notification_read_'.$notification->getId(), $request->request->getString('_token'))) {
            $notification->setIsRead(true);
            $this->entityManager->flush();
        }

        return $this->redirect($request->headers->get('referer', $this->generateUrl('app_notification_list')));
    }

    #[Route('/notifications/read-all', name: 'app_notification_mark_all_read', methods: ['POST'])]
    public function markAllRead(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('notification_read_all', $request->request->getString('_token'))) {
            $this->notificationRepository->markAllAsReadByRecipient($user);
        }

        return $this->redirect($request->headers->get('referer', $this->generateUrl('app_notification_list')));
    }

    #[Route('/notifications/{id}/delete', name: 'app_notification_delete', methods: ['POST'])]
    public function delete(Notification $notification, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($notification->getRecipient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('notification_delete_'.$notification->getId(), $request->request->getString('_token'))) {
            $this->entityManager->remove($notification);
            $this->entityManager->flush();
        }

        return $this->redirect($request->headers->get('referer', $this->generateUrl('app_notification_list')));
    }

    #[Route('/notifications/delete-read', name: 'app_notification_delete_read', methods: ['POST'])]
    public function deleteRead(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('notification_delete_read', $request->request->getString('_token'))) {
            $this->notificationRepository->deleteReadByRecipient($user);
        }

        return $this->redirect($request->headers->get('referer', $this->generateUrl('app_notification_list')));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNotification(Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'type' => $notification->getType()->value,
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'isRead' => $notification->isRead(),
            'createdAt' => $notification->getCreatedAt()->format('c'),
            'context' => $notification->getContext(),
            'url' => $this->resolveNotificationUrl($notification),
        ];
    }

    /**
     * Resolve the most actionable URL for a notification.
     *
     * Borrow-denied and borrow-cancelled link to the event borrowing section
     * so the user can browse alternatives. Actionable borrow types link to
     * the borrow actions panel. Informational borrow types link to the
     * borrow timeline. Event types link to the relevant event section
     * (staff, participation, or top of page).
     */
    private function resolveNotificationUrl(Notification $notification): ?string
    {
        $context = $notification->getContext();
        if (null === $context) {
            return null;
        }

        $type = $notification->getType();

        // Denied/cancelled borrows → event borrowing section to find alternatives
        if (\in_array($type, [NotificationType::BorrowDenied, NotificationType::BorrowCancelled], true)
            && isset($context['eventId'])) {
            return $this->generateUrl('app_event_show', ['id' => $context['eventId']]).'#borrowing';
        }

        // Actionable borrow types → borrow actions panel
        if (isset($context['borrowId']) && \in_array($type, [
            NotificationType::BorrowRequested,
            NotificationType::BorrowApproved,
            NotificationType::BorrowHandedOff,
            NotificationType::BorrowOverdue,
        ], true)) {
            return $this->generateUrl('app_borrow_show', ['id' => $context['borrowId']]).'#actions';
        }

        // Informational borrow types → borrow timeline
        if (isset($context['borrowId']) && $type->isBorrowType()) {
            return $this->generateUrl('app_borrow_show', ['id' => $context['borrowId']]).'#timeline';
        }

        // Staff assigned → staff section
        if (NotificationType::StaffAssigned === $type && isset($context['eventId'])) {
            return $this->generateUrl('app_event_show', ['id' => $context['eventId']]).'#staff';
        }

        // Event invited → participation section
        if (NotificationType::EventInvited === $type && isset($context['eventId'])) {
            return $this->generateUrl('app_event_show', ['id' => $context['eventId']]).'#participation';
        }

        // Other event types → event page top
        if (isset($context['eventId'])) {
            return $this->generateUrl('app_event_show', ['id' => $context['eventId']]);
        }

        return null;
    }
}
