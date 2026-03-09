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
use App\Enum\NotificationType;
use App\Form\ProfileFormType;
use App\Repository\BorrowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

    /**
     * @see docs/features.md F8.3 — Notification preferences
     */
    #[Route('/notifications', name: 'app_profile_notifications', methods: ['GET', 'POST'])]
    public function notifications(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $token = $request->request->getString('_token');
            if (!$this->isCsrfTokenValid('notification_preferences', $token)) {
                $this->addFlash('danger', 'app.flash.invalid_csrf');

                return $this->redirectToRoute('app_profile_notifications');
            }

            /** @var array<string, array<string, string>> $submitted */
            $submitted = $request->request->all('preferences');

            foreach (NotificationType::cases() as $type) {
                $typePrefs = $submitted[$type->value] ?? [];
                $user->setNotificationPreference($type, 'email', isset($typePrefs['email']));
                $user->setNotificationPreference($type, 'inApp', isset($typePrefs['inApp']));
            }

            $em->flush();

            $this->addFlash('success', 'app.flash.notification_preferences.saved');

            return $this->redirectToRoute('app_profile_notifications');
        }

        return $this->render('profile/notifications.html.twig', [
            'preferences' => $user->getNotificationPreferences(),
            'borrowTypes' => [
                NotificationType::BorrowRequested,
                NotificationType::BorrowApproved,
                NotificationType::BorrowDenied,
                NotificationType::BorrowHandedOff,
                NotificationType::BorrowReturned,
                NotificationType::BorrowOverdue,
                NotificationType::BorrowCancelled,
            ],
            'eventTypes' => [
                NotificationType::StaffAssigned,
                NotificationType::EventUpdated,
                NotificationType::EventCancelled,
                NotificationType::EventInvited,
                NotificationType::EventReminder,
            ],
        ]);
    }

    /**
     * @see docs/features.md F1.8 — Account deletion & data export (GDPR)
     */
    #[Route('/request-deletion', name: 'app_profile_request_deletion', methods: ['POST'])]
    public function requestDeletion(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        BorrowRepository $borrowRepository,
        #[Autowire('%app.deletion_token_ttl%')] int $tokenTtl,
        #[Autowire('%app.mail_sender%')] string $mailSender,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('profile-delete', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_profile_edit');
        }

        if ($borrowRepository->hasUnsettledBorrows($user)) {
            $this->addFlash('danger', 'app.profile.unsettled_borrows');

            return $this->redirectToRoute('app_profile_edit');
        }

        $token = bin2hex(random_bytes(32));
        $user->setDeletionToken($token);
        $user->setDeletionTokenExpiresAt(new \DateTimeImmutable('+'.$tokenTtl.' seconds', new \DateTimeZone('UTC')));
        $em->flush();

        $confirmUrl = $this->generateUrl('app_confirm_deletion', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $locale = $user->getPreferredLocale();

        $emailMessage = (new TemplatedEmail())
            ->from(new Address($mailSender, 'Expanded Decks'))
            ->to($user->getEmail())
            ->subject($this->translator->trans('app.email.deletion_subject', [], null, $locale))
            ->htmlTemplate('email/account_deletion.html.twig')
            ->context([
                'user' => $user,
                'confirmUrl' => $confirmUrl,
                'expiresInHours' => (int) ($tokenTtl / 3600),
                'locale' => $locale,
            ]);

        $mailer->send($emailMessage);

        $this->addFlash('success', 'app.profile.deletion_requested');

        return $this->redirectToRoute('app_profile_edit');
    }

    /**
     * @see docs/features.md F1.8 — Account deletion & data export (GDPR)
     */
    #[Route('/export', name: 'app_profile_export', methods: ['GET'])]
    public function export(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = [
            'profile' => [
                'email' => $user->getEmail(),
                'screenName' => $user->getScreenName(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'playerId' => $user->getPlayerId(),
                'preferredLocale' => $user->getPreferredLocale(),
                'timezone' => $user->getTimezone(),
                'createdAt' => $user->getCreatedAt()->format('c'),
                'lastLoginAt' => $user->getLastLoginAt()?->format('c'),
            ],
            'decks' => array_map(static fn ($deck) => [
                'id' => $deck->getId(),
                'name' => $deck->getName(),
                'format' => $deck->getFormat(),
                'status' => $deck->getStatus()->value,
                'createdAt' => $deck->getCreatedAt()->format('c'),
                'versions' => array_map(static fn ($version) => [
                    'versionNumber' => $version->getVersionNumber(),
                    'rawList' => $version->getRawList(),
                    'createdAt' => $version->getCreatedAt()->format('c'),
                ], $deck->getVersions()->toArray()),
            ], $user->getOwnedDecks()->toArray()),
            'borrows' => array_map(static fn ($borrow) => [
                'id' => $borrow->getId(),
                'deckName' => $borrow->getDeck()->getName(),
                'eventName' => $borrow->getEvent()->getName(),
                'status' => $borrow->getStatus()->value,
                'requestedAt' => $borrow->getRequestedAt()->format('c'),
            ], $user->getBorrowRequests()->toArray()),
            'eventEngagements' => array_map(static fn ($engagement) => [
                'eventName' => $engagement->getEvent()->getName(),
                'state' => $engagement->getState()->value,
                'participationMode' => $engagement->getParticipationMode()?->value,
                'createdAt' => $engagement->getCreatedAt()->format('c'),
            ], $user->getEventEngagements()->toArray()),
            'staffAssignments' => array_map(static fn ($staff) => [
                'eventName' => $staff->getEvent()->getName(),
                'assignedAt' => $staff->getAssignedAt()->format('c'),
            ], $user->getStaffAssignments()->toArray()),
        ];

        $json = json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        $response = new Response(
            $json,
            Response::HTTP_OK,
            ['Content-Type' => 'application/json']
        );
        $response->headers->set('Content-Disposition', 'attachment; filename="expanded-decks-export.json"');

        return $response;
    }
}
