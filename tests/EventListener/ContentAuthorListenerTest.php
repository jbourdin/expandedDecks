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

use App\Entity\Archetype;
use App\Entity\Deck;
use App\Entity\Page;
use App\Entity\User;
use App\EventListener\ContentAuthorListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @see docs/features.md F19.8 — Author assignment
 */
final class ContentAuthorListenerTest extends TestCase
{
    public function testStampsNewArchetypeWithCurrentUser(): void
    {
        $user = new User();
        $archetype = new Archetype();

        $this->listenerFor($user)->prePersist($this->prePersistArgs($archetype));

        self::assertSame($user, $archetype->getAuthor());
    }

    public function testStampsNewPageWithCurrentUser(): void
    {
        $user = new User();
        $page = new Page();

        $this->listenerFor($user)->prePersist($this->prePersistArgs($page));

        self::assertSame($user, $page->getAuthor());
    }

    public function testStampsArchetypeVariantDeck(): void
    {
        $user = new User();
        $variant = (new Deck())->setArchetype(new Archetype()); // owner stays null => variant

        $this->listenerFor($user)->prePersist($this->prePersistArgs($variant));

        self::assertSame($user, $variant->getAuthor());
    }

    public function testIgnoresOwnerOwnedDeck(): void
    {
        $user = new User();
        $ownedDeck = (new Deck())->setOwner(new User());

        $this->listenerFor($user)->prePersist($this->prePersistArgs($ownedDeck));

        self::assertNull($ownedDeck->getAuthor());
    }

    public function testDoesNotOverwriteExistingAuthor(): void
    {
        $original = new User();
        $archetype = (new Archetype())->setAuthor($original);

        // A different "current user" (e.g. an admin editing) must not take over.
        $this->listenerFor(new User())->prePersist($this->prePersistArgs($archetype));

        self::assertSame($original, $archetype->getAuthor());
    }

    public function testSkipsWhenNoAuthenticatedUser(): void
    {
        $archetype = new Archetype();

        $this->listenerFor(null)->prePersist($this->prePersistArgs($archetype));

        self::assertNull($archetype->getAuthor());
    }

    private function listenerFor(?User $user): ContentAuthorListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new ContentAuthorListener($security);
    }

    private function prePersistArgs(object $entity): PrePersistEventArgs
    {
        return new PrePersistEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }
}
