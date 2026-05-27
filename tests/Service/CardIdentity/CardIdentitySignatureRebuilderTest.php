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

namespace App\Tests\Service\CardIdentity;

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Entity\TcgdexCard;
use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use App\Repository\CardIdentityRepository;
use App\Service\CardIdentity\CardIdentitySignatureRebuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class CardIdentitySignatureRebuilderTest extends TestCase
{
    public function testSingleGroupIdentityIsUpdatedInPlace(): void
    {
        // All Sandile printings agree on Bite|20 → just update the identity's signature.
        $identity = $this->makeIdentity('Sandile', 70, attackSignature: 'Bite');
        $this->attachPrinting($identity, attacks: [
            ['name' => ['en' => 'Bite'], 'damage' => 20, 'cost' => ['Fighting', 'Colorless']],
        ]);
        $this->attachPrinting($identity, attacks: [
            ['name' => ['en' => 'Bite'], 'damage' => 20, 'cost' => ['Fighting', 'Colorless']],
        ]);

        $identityRepo = $this->createStub(CardIdentityRepository::class);
        $identityRepo->method('findBy')->willReturn([$identity]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');

        $rebuilder = new CardIdentitySignatureRebuilder($identityRepo, $em);
        $result = $rebuilder->rebuild();

        self::assertSame('Bite|20', $identity->getAttackSignature());
        self::assertSame(1, $result->updatedInPlace);
        self::assertSame(0, $result->splitAsPrimary);
        self::assertSame(0, $result->clonesCreated);
    }

    public function testMultiGroupIdentityClonesWhenNoExistingTarget(): void
    {
        // Sandile collapsed two printings under one identity: Bite|20 and Bite|30.
        // The 20-damage group is "primary" (larger or older); 30-damage splits off into a new clone.
        $identity = $this->makeIdentity('Sandile', 70, attackSignature: 'Bite');
        $printing20a = $this->attachPrinting($identity, id: 1, attacks: [
            ['name' => ['en' => 'Bite'], 'damage' => 20, 'cost' => ['Fighting', 'Colorless']],
        ]);
        $printing20b = $this->attachPrinting($identity, id: 2, attacks: [
            ['name' => ['en' => 'Bite'], 'damage' => 20, 'cost' => ['Fighting', 'Colorless']],
        ]);
        $printing30 = $this->attachPrinting($identity, id: 3, attacks: [
            ['name' => ['en' => 'Bite'], 'damage' => 30, 'cost' => ['Colorless', 'Colorless']],
        ]);

        $identityRepo = $this->createStub(CardIdentityRepository::class);
        $identityRepo->method('findBy')->willReturn([$identity]);
        $identityRepo->method('findBySignature')->willReturn(null);

        $persistedClones = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persistedClones): void {
                $persistedClones[] = $entity;
            },
        );
        $em->expects(self::once())->method('flush');

        $rebuilder = new CardIdentitySignatureRebuilder($identityRepo, $em);
        $result = $rebuilder->rebuild();

        self::assertSame('Bite|20', $identity->getAttackSignature(), 'majority group keeps the original identity');
        self::assertSame($identity, $printing20a->getCardIdentity());
        self::assertSame($identity, $printing20b->getCardIdentity());
        self::assertNotSame($identity, $printing30->getCardIdentity());
        self::assertSame('Bite|30', $printing30->getCardIdentity()->getAttackSignature());
        self::assertCount(1, $persistedClones);
        self::assertSame(1, $result->splitAsPrimary);
        self::assertSame(1, $result->clonesCreated);
        self::assertSame(1, $result->printingsRepointed);
    }

    public function testMultiGroupIdentityReusesExistingTargetWhenFound(): void
    {
        $sourceIdentity = $this->makeIdentity('Sandile', 70, attackSignature: 'Bite');
        $this->attachPrinting($sourceIdentity, id: 1, attacks: [
            ['name' => ['en' => 'Bite'], 'damage' => 20, 'cost' => ['Fighting', 'Colorless']],
        ]);
        $printing30 = $this->attachPrinting($sourceIdentity, id: 2, attacks: [
            ['name' => ['en' => 'Bite'], 'damage' => 30, 'cost' => ['Colorless', 'Colorless']],
        ]);

        $existingTarget = $this->makeIdentity('Sandile', 70, attackSignature: 'Bite|30');

        $identityRepo = $this->createStub(CardIdentityRepository::class);
        $identityRepo->method('findBy')->willReturn([$sourceIdentity]);
        $identityRepo->method('findBySignature')->willReturn($existingTarget);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');

        $rebuilder = new CardIdentitySignatureRebuilder($identityRepo, $em);
        $result = $rebuilder->rebuild();

        self::assertSame($existingTarget, $printing30->getCardIdentity());
        self::assertSame(1, $result->reusedExistingTarget);
        self::assertSame(0, $result->clonesCreated);
        self::assertSame(1, $result->printingsRepointed);
    }

    public function testPrintingWithoutLinkedTcgdexCardIsSkipped(): void
    {
        $identity = $this->makeIdentity('Ghost', 60, attackSignature: 'Sneak');
        $printing = new CardPrinting();
        $printing->setCardIdentity($identity);
        $printing->setTcgdexId('lost-001');
        $identity->addPrinting($printing);
        // tcgdexCard intentionally left null

        $identityRepo = $this->createStub(CardIdentityRepository::class);
        $identityRepo->method('findBy')->willReturn([$identity]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $rebuilder = new CardIdentitySignatureRebuilder($identityRepo, $em);
        $result = $rebuilder->rebuild();

        self::assertSame('Sneak', $identity->getAttackSignature(), 'untouched when no TCGdex data');
        self::assertSame(1, $result->skippedNoTcgdexData);
    }

    private function makeIdentity(string $name, int $hp, string $attackSignature): CardIdentity
    {
        $identity = new CardIdentity();
        $identity->setName($name);
        $identity->setCategory('pokemon');
        $identity->setHp($hp);
        $identity->setAbilitySignature('');
        $identity->setAbilityNames('');
        $identity->setAttackSignature($attackSignature);
        $identity->setAttackNames('Bite');
        $identity->setPokemonType('Darkness');
        $identity->setTrainerType(null);
        $identity->setRuleboxType(null);

        return $identity;
    }

    /**
     * @param list<array<string, mixed>> $attacks
     */
    private function attachPrinting(
        CardIdentity $identity,
        array $attacks = [],
        ?int $id = null,
    ): CardPrinting {
        $serie = new TcgdexSerie('test-serie');
        $serie->setName(['en' => 'Test Serie']);
        $set = new TcgdexSet('test-set', $serie);
        $set->setName(['en' => 'Test Set']);

        $printingId = $id ?? random_int(1000, 9999);
        $tcgdexEntity = new TcgdexCard('test-'.$printingId, $set, (string) $printingId);
        $tcgdexEntity->setName(['en' => $identity->getName()]);
        $tcgdexEntity->setCategory('Pokemon');
        $tcgdexEntity->setHp($identity->getHp());
        $tcgdexEntity->setAttacks($attacks);

        $printing = new CardPrinting();
        $printing->setCardIdentity($identity);
        $printing->setTcgdexId($tcgdexEntity->getId());
        $printing->setTcgdexCard($tcgdexEntity);

        if (null !== $id) {
            $reflection = new \ReflectionProperty(CardPrinting::class, 'id');
            $reflection->setValue($printing, $id);
        }

        $identity->addPrinting($printing);

        return $printing;
    }
}
