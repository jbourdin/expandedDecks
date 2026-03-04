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

use App\Form\ResetPasswordFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F1.7 — Password reset
 */
class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        #[Autowire('%app.reset_token_ttl%')] int $tokenTtl,
        #[Autowire('%app.mail_sender%')] string $mailSender,
    ): Response {
        if ('POST' !== $request->getMethod()) {
            return $this->render('password_reset/forgot.html.twig');
        }

        $email = $request->getPayload()->getString('email');
        $user = $userRepository->findOneBy(['email' => $email]);

        if (null !== $user && $user->isVerified() && !$user->isAnonymized()) {
            $token = bin2hex(random_bytes(32));
            $user->setResetToken($token);
            $user->setResetTokenExpiresAt(new \DateTimeImmutable('+'.$tokenTtl.' seconds', new \DateTimeZone('UTC')));
            $entityManager->flush();

            $resetUrl = $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

            $emailMessage = (new TemplatedEmail())
                ->from(new Address($mailSender, 'Expanded Decks'))
                ->to($user->getEmail())
                ->subject('Reset your Expanded Decks password')
                ->htmlTemplate('email/password_reset.html.twig')
                ->context([
                    'user' => $user,
                    'resetUrl' => $resetUrl,
                    'expiresInMinutes' => (int) ($tokenTtl / 60),
                ]);

            $mailer->send($emailMessage);
        }

        // Anti-enumeration: always show the same success message
        $this->addFlash('success', $this->translator->trans('app.flash.auth.reset_link_sent'));

        return $this->redirectToRoute('app_login');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $userRepository->findOneBy(['resetToken' => $token]);

        if (null === $user) {
            $this->addFlash('danger', $this->translator->trans('app.flash.auth.invalid_reset_link'));

            return $this->redirectToRoute('app_login');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if (null !== $user->getResetTokenExpiresAt() && $user->getResetTokenExpiresAt() < $now) {
            $this->addFlash('danger', $this->translator->trans('app.flash.auth.reset_link_expired'));

            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $entityManager->flush();

            $this->addFlash('success', $this->translator->trans('app.flash.auth.password_reset'));

            return $this->redirectToRoute('app_login');
        }

        return $this->render('password_reset/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }
}
