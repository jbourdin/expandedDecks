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

namespace App\Tests\EventListener;

use App\EventListener\LoginCaptchaListener;
use App\Service\FriendlyCaptchaVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * @see docs/features.md F12.4 — Bot protection with Friendly Captcha
 */
class LoginCaptchaListenerTest extends TestCase
{
    public function testDoesNothingWhenNoCurrentRequest(): void
    {
        $verifier = $this->createMock(FriendlyCaptchaVerifier::class);
        $verifier->expects(self::never())->method('verify');

        $requestStack = new RequestStack();

        $listener = new LoginCaptchaListener($verifier, $requestStack);
        $listener($this->createCheckPassportEvent());

        // No exception = success
        self::assertTrue(true);
    }

    public function testAcceptsWhenVerifierReturnsTrue(): void
    {
        $verifier = $this->createStub(FriendlyCaptchaVerifier::class);
        $verifier->method('verify')->willReturn(true);

        $request = new Request(request: ['frc-captcha-response' => 'valid-token']);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $listener = new LoginCaptchaListener($verifier, $requestStack);
        $listener($this->createCheckPassportEvent());

        self::assertTrue(true);
    }

    public function testThrowsWhenVerifierRejectsCaptcha(): void
    {
        $verifier = $this->createMock(FriendlyCaptchaVerifier::class);
        $verifier->expects(self::once())
            ->method('verify')
            ->with('')
            ->willReturn(false);

        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $listener = new LoginCaptchaListener($verifier, $requestStack);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('app.captcha.verification_failed');

        $listener($this->createCheckPassportEvent());
    }

    public function testPassesCaptchaResponseFromRequest(): void
    {
        $verifier = $this->createMock(FriendlyCaptchaVerifier::class);
        $verifier->expects(self::once())
            ->method('verify')
            ->with('my-captcha-token')
            ->willReturn(true);

        $request = new Request(request: ['frc-captcha-response' => 'my-captcha-token']);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $listener = new LoginCaptchaListener($verifier, $requestStack);
        $listener($this->createCheckPassportEvent());
    }

    private function createCheckPassportEvent(): CheckPassportEvent
    {
        $passport = new SelfValidatingPassport(new UserBadge('test@example.com'));

        return new CheckPassportEvent(
            $this->createStub(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class),
            $passport,
        );
    }
}
