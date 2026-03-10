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

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Coverage for UserRepository: findByMultiField, searchDeckOwners, findOneByVerificationToken, findOneByResetToken.
 *
 * @see docs/features.md F3.5 — Assign event staff team
 * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
 * @see docs/features.md F1.2 — Email verification
 * @see docs/features.md F1.7 — Password reset
 */
class UserRepositoryCoverageTest extends AbstractFunctionalTest
{
    private function getUserRepository(): UserRepository
    {
        /** @var UserRepository $repository */
        $repository = static::getContainer()->get(UserRepository::class);

        return $repository;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    // ---------------------------------------------------------------
    // findByMultiField
    // ---------------------------------------------------------------

    public function testFindByMultiFieldFindsUserByScreenName(): void
    {
        $repository = $this->getUserRepository();

        $user = $repository->findByMultiField('Admin');

        self::assertNotNull($user);
        self::assertSame('admin@example.com', $user->getEmail());
    }

    public function testFindByMultiFieldFindsUserByEmail(): void
    {
        $repository = $this->getUserRepository();

        $user = $repository->findByMultiField('borrower@example.com');

        self::assertNotNull($user);
        self::assertSame('Borrower', $user->getScreenName());
    }

    public function testFindByMultiFieldFindsUserByPlayerId(): void
    {
        $repository = $this->getUserRepository();

        // Admin has playerId '007'
        $user = $repository->findByMultiField('007');

        self::assertNotNull($user);
        self::assertSame('admin@example.com', $user->getEmail());
    }

    public function testFindByMultiFieldReturnsNullWhenNoMatch(): void
    {
        $repository = $this->getUserRepository();

        $user = $repository->findByMultiField('nonexistent-user-xyz');

        self::assertNull($user);
    }

    public function testFindByMultiFieldPrioritizesScreenNameOverEmail(): void
    {
        $repository = $this->getUserRepository();

        // "Organizer" matches screenName first
        $user = $repository->findByMultiField('Organizer');

        self::assertNotNull($user);
        self::assertSame('organizer@example.com', $user->getEmail());
    }

    // ---------------------------------------------------------------
    // searchDeckOwners
    // ---------------------------------------------------------------

    public function testSearchDeckOwnersFindsUsersWithPublicDecks(): void
    {
        $repository = $this->getUserRepository();

        // Admin owns "Iron Thorns" which is public
        $results = $repository->searchDeckOwners('Admin');

        self::assertNotEmpty($results, 'Should find users who own public decks.');
        $emails = array_map(static fn (User $user): string => $user->getEmail(), $results);
        self::assertContains('admin@example.com', $emails);
    }

    public function testSearchDeckOwnersExcludesUsersWithOnlyPrivateDecks(): void
    {
        $repository = $this->getUserRepository();

        // Borrower owns "Lugia Archeops" which is NOT public (default)
        $results = $repository->searchDeckOwners('Borrower');

        $emails = array_map(static fn (User $user): string => $user->getEmail(), $results);
        self::assertNotContains('borrower@example.com', $emails, 'Users with only private decks should be excluded.');
    }

    public function testSearchDeckOwnersReturnsEmptyForNoMatch(): void
    {
        $repository = $this->getUserRepository();

        $results = $repository->searchDeckOwners('NonExistentUserXYZ');

        self::assertEmpty($results, 'Should return empty for a query matching no deck owners.');
    }

    public function testSearchDeckOwnersRespectsLimit(): void
    {
        $repository = $this->getUserRepository();

        $results = $repository->searchDeckOwners('', 1);

        self::assertLessThanOrEqual(1, \count($results), 'Should respect the limit parameter.');
    }

    public function testSearchDeckOwnersExcludesAnonymizedUsers(): void
    {
        $repository = $this->getUserRepository();
        $entityManager = $this->getEntityManager();

        // Anonymize the lender who owns the public "Regidrago" deck
        $lender = $entityManager->getRepository(User::class)->findOneBy(['email' => 'lender@example.com']);
        self::assertNotNull($lender);
        $lender->anonymize();
        $entityManager->flush();

        $results = $repository->searchDeckOwners('Lender');

        $screenNames = array_map(static fn (User $user): string => $user->getScreenName(), $results);
        self::assertNotContains('Lender', $screenNames, 'Anonymized users should not appear in deck owner search.');
    }

    // ---------------------------------------------------------------
    // findOneByVerificationToken
    // ---------------------------------------------------------------

    /**
     * @see docs/features.md F1.2 — Email verification
     */
    public function testFindOneByVerificationTokenReturnsUserWithMatchingToken(): void
    {
        $repository = $this->getUserRepository();

        // Unverified user has verificationToken = 'test-verification-token' in fixtures
        $user = $repository->findOneByVerificationToken('test-verification-token');

        self::assertNotNull($user, 'Should find the user with the given verification token.');
        self::assertSame('unverified@example.com', $user->getEmail());
    }

    /**
     * @see docs/features.md F1.2 — Email verification
     */
    public function testFindOneByVerificationTokenReturnsNullForNonExistentToken(): void
    {
        $repository = $this->getUserRepository();

        $user = $repository->findOneByVerificationToken('non-existent-token-xyz');

        self::assertNull($user, 'Should return null when no user matches the verification token.');
    }

    // ---------------------------------------------------------------
    // findOneByResetToken
    // ---------------------------------------------------------------

    /**
     * @see docs/features.md F1.7 — Password reset
     */
    public function testFindOneByResetTokenReturnsUserWithMatchingToken(): void
    {
        $repository = $this->getUserRepository();
        $entityManager = $this->getEntityManager();

        // Set up a reset token on an existing user
        $admin = $entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin);
        $admin->setResetToken('test-reset-token-abc');
        $admin->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $entityManager->flush();

        $user = $repository->findOneByResetToken('test-reset-token-abc');

        self::assertNotNull($user, 'Should find the user with the given reset token.');
        self::assertSame('admin@example.com', $user->getEmail());
    }

    /**
     * @see docs/features.md F1.7 — Password reset
     */
    public function testFindOneByResetTokenReturnsNullForNonExistentToken(): void
    {
        $repository = $this->getUserRepository();

        $user = $repository->findOneByResetToken('non-existent-reset-token-xyz');

        self::assertNull($user, 'Should return null when no user matches the reset token.');
    }
}
