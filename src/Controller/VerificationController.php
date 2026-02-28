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

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see docs/features.md F1.2 — Email verification
 */
class VerificationController extends AbstractController
{
    #[Route('/verify/resend', name: 'app_verify_resend', methods: ['GET', 'POST'])]
    public function resend(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        #[Autowire('%app.verification_token_ttl%')] int $tokenTtl,
        #[Autowire('%app.mail_sender%')] string $mailSender,
    ): Response {
        if ('POST' !== $request->getMethod()) {
            return $this->render('verification/resend.html.twig');
        }

        $email = $request->getPayload()->getString('email');
        $user = $userRepository->findOneBy(['email' => $email]);

        if (null !== $user && !$user->isVerified() && !$user->isAnonymized()) {
            $token = bin2hex(random_bytes(32));
            $user->setVerificationToken($token);
            $user->setTokenExpiresAt(new \DateTimeImmutable('+'.$tokenTtl.' seconds', new \DateTimeZone('UTC')));
            $entityManager->flush();

            $verificationUrl = $this->generateUrl('app_verify', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

            $emailMessage = (new TemplatedEmail())
                ->from(new Address($mailSender, 'Expanded Decks'))
                ->to($user->getEmail())
                ->subject('Verify your Expanded Decks account')
                ->htmlTemplate('email/verification.html.twig')
                ->context([
                    'user' => $user,
                    'verificationUrl' => $verificationUrl,
                    'expiresInHours' => (int) ($tokenTtl / 3600),
                ]);

            $mailer->send($emailMessage);
        }

        // Anti-enumeration: always show the same success message
        $this->addFlash('success', 'If an account exists with that email and is not yet verified, a new verification email has been sent.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify/{token}', name: 'app_verify', methods: ['GET'])]
    public function verify(
        string $token,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $userRepository->findOneBy(['verificationToken' => $token]);

        if (null === $user) {
            $this->addFlash('danger', 'Invalid verification link.');

            return $this->redirectToRoute('app_login');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if (null !== $user->getTokenExpiresAt() && $user->getTokenExpiresAt() < $now) {
            $this->addFlash('danger', 'This verification link has expired. Please request a new one.');

            return $this->redirectToRoute('app_verify_resend');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setTokenExpiresAt(null);
        $entityManager->flush();

        $this->addFlash('success', 'Your email has been verified. You can now log in.');

        return $this->redirectToRoute('app_login');
    }
}
