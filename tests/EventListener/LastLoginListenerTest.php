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

use App\Entity\User;
use App\EventListener\LastLoginListener;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * @see docs/features.md F1.1 — Register a new account
 */
class LastLoginListenerTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $listener = new LastLoginListener($entityManager);

        self::assertInstanceOf(LastLoginListener::class, $listener);
    }

    public function testSetsLastLoginAtOnAppUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        self::assertNull($user->getLastLoginAt());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $listener = new LastLoginListener($entityManager);
        $event = $this->createLoginSuccessEvent($user);

        $listener($event);

        self::assertInstanceOf(\DateTimeImmutable::class, $user->getLastLoginAt());
    }

    public function testDoesNothingForNonAppUser(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('other@example.com');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $listener = new LastLoginListener($entityManager);
        $event = $this->createLoginSuccessEvent($user);

        $listener($event);
    }

    private function createLoginSuccessEvent(UserInterface $user): LoginSuccessEvent
    {
        $authenticator = $this->createStub(AuthenticatorInterface::class);
        $token = $this->createStub(TokenInterface::class);
        $passport = new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), static fn (): UserInterface => $user),
        );

        return new LoginSuccessEvent(
            $authenticator,
            $passport,
            $token,
            new Request(),
            new Response(),
            'main',
        );
    }
}
