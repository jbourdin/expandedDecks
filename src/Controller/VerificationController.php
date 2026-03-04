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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F1.2 — Email verification
 */
class VerificationController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

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
        $this->addFlash('success', $this->translator->trans('app.flash.auth.verification_sent'));

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
            $this->addFlash('danger', $this->translator->trans('app.flash.auth.invalid_verification'));

            return $this->redirectToRoute('app_login');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if (null !== $user->getTokenExpiresAt() && $user->getTokenExpiresAt() < $now) {
            $this->addFlash('danger', $this->translator->trans('app.flash.auth.verification_expired'));

            return $this->redirectToRoute('app_verify_resend');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setTokenExpiresAt(null);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('app.flash.auth.email_verified'));

        return $this->redirectToRoute('app_login');
    }
}
