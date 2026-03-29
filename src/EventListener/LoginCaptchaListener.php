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

namespace App\EventListener;

use App\Service\FriendlyCaptchaVerifier;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Verifies the Friendly Captcha response during login authentication.
 *
 * Runs at a high priority so the captcha is checked before credential validation,
 * preventing brute-force password guessing even with invalid captcha tokens.
 *
 * @see docs/features.md F12.4 — Bot protection with Friendly Captcha
 */
#[AsEventListener(event: CheckPassportEvent::class, priority: 512)]
class LoginCaptchaListener
{
    public function __construct(
        private readonly FriendlyCaptchaVerifier $verifier,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $captchaResponse = $request->request->getString('frc-captcha-response');

        if (!$this->verifier->verify($captchaResponse)) {
            throw new CustomUserMessageAuthenticationException('app.captcha.verification_failed');
        }
    }
}
