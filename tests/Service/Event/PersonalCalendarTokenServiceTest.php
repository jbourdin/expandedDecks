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

namespace App\Tests\Service\Event;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Event\PersonalCalendarTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F3.14 — iCal agenda feed
 */
final class PersonalCalendarTokenServiceTest extends TestCase
{
    public function testEnsureTokenAssignsAndPersistsWhenMissing(): void
    {
        $user = new User();
        self::assertNull($user->getCalendarToken());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $service = new PersonalCalendarTokenService($em, $this->createStub(UserRepository::class));

        $token = $service->ensureToken($user);

        self::assertNotEmpty($token);
        self::assertSame($token, $user->getCalendarToken());
        self::assertGreaterThanOrEqual(40, \strlen($token));
    }

    public function testEnsureTokenIsIdempotentWhenTokenAlreadyExists(): void
    {
        $user = new User();
        $user->setCalendarToken('existing-token-value');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $service = new PersonalCalendarTokenService($em, $this->createStub(UserRepository::class));

        self::assertSame('existing-token-value', $service->ensureToken($user));
    }

    public function testRegenerateTokenReplacesExistingToken(): void
    {
        $user = new User();
        $user->setCalendarToken('old-token');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $service = new PersonalCalendarTokenService($em, $this->createStub(UserRepository::class));

        $newToken = $service->regenerateToken($user);

        self::assertNotSame('old-token', $newToken);
        self::assertSame($newToken, $user->getCalendarToken());
    }

    public function testFindUserByTokenReturnsNullForEmptyToken(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::never())->method('findOneByCalendarToken');

        $service = new PersonalCalendarTokenService(
            $this->createStub(EntityManagerInterface::class),
            $repository,
        );

        self::assertNull($service->findUserByToken(''));
    }

    public function testFindUserByTokenDelegatesToRepository(): void
    {
        $expectedUser = new User();

        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())
            ->method('findOneByCalendarToken')
            ->with('abc-123')
            ->willReturn($expectedUser);

        $service = new PersonalCalendarTokenService(
            $this->createStub(EntityManagerInterface::class),
            $repository,
        );

        self::assertSame($expectedUser, $service->findUserByToken('abc-123'));
    }
}
