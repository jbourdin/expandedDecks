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

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Interactively creates an admin user with ROLE_ADMIN.
 *
 * @see docs/features.md F14.7 — Create admin user command
 */
#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user interactively.',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create Admin User');

        /** @var string $email */
        $email = $io->ask('Email', null, self::notBlank(...));
        /** @var string $screenName */
        $screenName = $io->ask('Screen name', null, self::notBlank(...));
        /** @var string $firstName */
        $firstName = $io->ask('First name', null, self::notBlank(...));
        /** @var string $lastName */
        $lastName = $io->ask('Last name', null, self::notBlank(...));
        $playerId = $io->ask('Player ID (optional, press Enter to skip)');
        /** @var string $locale */
        $locale = $io->choice('Preferred locale', ['en', 'fr'], 'en');
        /** @var string $password */
        $password = $io->askHidden('Password', self::notBlank(...));
        /** @var string $passwordConfirm */
        $passwordConfirm = $io->askHidden('Confirm password', self::notBlank(...));

        if ($password !== $passwordConfirm) {
            $io->error('Passwords do not match.');

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setScreenName($screenName);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPlayerId(\is_string($playerId) && '' !== $playerId ? $playerId : null);
        $user->setPreferredLocale($locale);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsVerified(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error($violation->getPropertyPath().': '.$violation->getMessage());
            }

            return Command::FAILURE;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf('Admin user "%s" (%s) created successfully.', $screenName, $email));

        return Command::SUCCESS;
    }

    private static function notBlank(mixed $value): string
    {
        if (!\is_string($value) || '' === trim($value)) {
            throw new \RuntimeException('This value cannot be blank.');
        }

        return $value;
    }
}
