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

namespace App\Tests\Command;

use App\Command\CreateAdminCommand;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @see docs/features.md F14.7 — Create admin user command
 */
final class CreateAdminCommandTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private ValidatorInterface&MockObject $validator;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-password');
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $command = new CreateAdminCommand(
            $this->entityManager,
            $this->passwordHasher,
            $this->validator,
        );

        $this->tester = new CommandTester($command);
    }

    public function testSuccessfulAdminCreation(): void
    {
        $this->entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static function (User $user): bool {
                return 'admin@test.com' === $user->getEmail()
                    && 'AdminUser' === $user->getScreenName()
                    && 'John' === $user->getFirstName()
                    && 'Doe' === $user->getLastName()
                    && null === $user->getPlayerId()
                    && 'en' === $user->getPreferredLocale()
                    && \in_array('ROLE_ADMIN', $user->getRoles(), true)
                    && $user->isVerified()
                    && 'hashed-password' === $user->getPassword();
            }));
        $this->entityManager->expects(self::once())->method('flush');

        $this->tester->setInputs([
            'admin@test.com',
            'AdminUser',
            'John',
            'Doe',
            '',       // player ID — skip
            'en',
            'Secret1!',
            'Secret1!',
        ]);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('created successfully', $this->tester->getDisplay());
    }

    public function testCreationWithPlayerId(): void
    {
        $this->entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static function (User $user): bool {
                return 'PLAYER-123' === $user->getPlayerId();
            }));

        $this->tester->setInputs([
            'player@test.com',
            'PlayerAdmin',
            'Jane',
            'Smith',
            'PLAYER-123',
            'fr',
            'Secret1!',
            'Secret1!',
        ]);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testPasswordMismatchReturnsFailure(): void
    {
        $this->entityManager->expects(self::never())->method('persist');

        $this->tester->setInputs([
            'admin@test.com',
            'AdminUser',
            'John',
            'Doe',
            '',
            'en',
            'password1',
            'password2',
        ]);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Passwords do not match', $this->tester->getDisplay());
    }

    public function testNotBlankRejectsEmptyString(): void
    {
        $method = new \ReflectionMethod(CreateAdminCommand::class, 'notBlank');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be blank');

        $method->invoke(null, '');
    }

    public function testNotBlankRejectsNull(): void
    {
        $method = new \ReflectionMethod(CreateAdminCommand::class, 'notBlank');

        $this->expectException(\RuntimeException::class);

        $method->invoke(null, null);
    }

    public function testNotBlankReturnsValidString(): void
    {
        $method = new \ReflectionMethod(CreateAdminCommand::class, 'notBlank');

        self::assertSame('hello', $method->invoke(null, 'hello'));
    }

    public function testValidationErrorReturnsFailure(): void
    {
        $violation = new ConstraintViolation(
            'This value is not a valid email.',
            null,
            [],
            null,
            'email',
            'invalid',
        );
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([$violation]));

        $command = new CreateAdminCommand(
            $this->entityManager,
            $this->passwordHasher,
            $this->validator,
        );
        $tester = new CommandTester($command);

        $this->entityManager->expects(self::never())->method('persist');

        $tester->setInputs([
            'invalid',
            'AdminUser',
            'John',
            'Doe',
            '',
            'en',
            'Secret1!',
            'Secret1!',
        ]);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('email', $tester->getDisplay());
        self::assertStringContainsString('not a valid email', $tester->getDisplay());
    }
}
