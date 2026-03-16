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
use PHPUnit\Framework\MockObject\Stub;
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
    private UserPasswordHasherInterface&Stub $passwordHasher;
    private ValidatorInterface&Stub $validator;

    protected function setUp(): void
    {
        $this->passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-password');
        $this->validator = $this->createStub(ValidatorInterface::class);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
    }

    private function createCommandTester(EntityManagerInterface $entityManager): CommandTester
    {
        $command = new CreateAdminCommand(
            $entityManager,
            $this->passwordHasher,
            $this->validator,
        );

        return new CommandTester($command);
    }

    public function testSuccessfulAdminCreation(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
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
        $entityManager->expects(self::once())->method('flush');

        $tester = $this->createCommandTester($entityManager);
        $tester->setInputs([
            'admin@test.com',
            'AdminUser',
            'John',
            'Doe',
            '',       // player ID — skip
            'en',
            'Secret1!',
            'Secret1!',
        ]);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('created successfully', $tester->getDisplay());
    }

    public function testCreationWithPlayerId(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static function (User $user): bool {
                return 'PLAYER-123' === $user->getPlayerId();
            }));

        $tester = $this->createCommandTester($entityManager);
        $tester->setInputs([
            'player@test.com',
            'PlayerAdmin',
            'Jane',
            'Smith',
            'PLAYER-123',
            'fr',
            'Secret1!',
            'Secret1!',
        ]);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testPasswordMismatchReturnsFailure(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $tester = $this->createCommandTester($entityManager);
        $tester->setInputs([
            'admin@test.com',
            'AdminUser',
            'John',
            'Doe',
            '',
            'en',
            'password1',
            'password2',
        ]);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Passwords do not match', $tester->getDisplay());
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
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList([$violation]));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $command = new CreateAdminCommand(
            $entityManager,
            $this->passwordHasher,
            $validator,
        );
        $tester = new CommandTester($command);

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
