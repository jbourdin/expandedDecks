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
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see docs/features.md F1.1 — Register a new account
 * @see docs/features.md F1.2 — Email verification
 */
class RegistrationController extends AbstractAppController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        #[Autowire('%app.verification_token_ttl%')] int $tokenTtl,
        #[Autowire('%app.mail_sender%')] string $mailSender,
    ): Response {
        $targetPath = $request->query->getString('_target_path');

        if ($this->getUser()) {
            if ('' !== $targetPath && $this->isSafeRedirectPath($targetPath)) {
                return $this->redirect($targetPath);
            }

            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            $token = bin2hex(random_bytes(32));
            $user->setVerificationToken($token);
            $user->setTokenExpiresAt(new \DateTimeImmutable('+'.$tokenTtl.' seconds', new \DateTimeZone('UTC')));

            $entityManager->persist($user);
            $entityManager->flush();

            $verificationUrl = $this->generateUrl('app_verify', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

            $email = (new TemplatedEmail())
                ->from(new Address($mailSender, 'Expanded Decks'))
                ->to($user->getEmail())
                ->subject('Verify your Expanded Decks account')
                ->htmlTemplate('email/verification.html.twig')
                ->context([
                    'user' => $user,
                    'verificationUrl' => $verificationUrl,
                    'expiresInHours' => (int) ($tokenTtl / 3600),
                ]);

            $mailer->send($email);

            $this->addFlash('success', 'app.flash.auth.account_created');

            $loginParams = ('' !== $targetPath) ? ['_target_path' => $targetPath] : [];

            return $this->redirectToRoute('app_login', $loginParams);
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    private function isSafeRedirectPath(string $path): bool
    {
        return str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, '://');
    }
}
