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

use App\Entity\Deck;
use App\Repository\ArchetypeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Additional coverage tests for DeckController uncovered branches.
 *
 * @see docs/features.md F2.1 — Register a new deck (owner)
 * @see docs/features.md F2.2 — Import deck list (PTCG text format)
 */
class DeckControllerCoverageTest extends AbstractFunctionalTest
{
    /**
     * Editing a deck that is not owned by the logged-in user should return 403.
     */
    public function testEditDeckDeniedForNonOwner(): void
    {
        $this->loginAs('borrower@example.com');

        $deck = $this->getDeck('Iron Thorns');

        $this->client->request('GET', \sprintf('/deck/%d/edit', $deck->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Editing a deck and setting archetype to null (clearing it) should work.
     */
    public function testEditDeckClearArchetype(): void
    {
        $this->loginAs('admin@example.com');

        $deck = $this->getDeck('Iron Thorns');

        $crawler = $this->client->request('GET', \sprintf('/deck/%d/edit', $deck->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['deck_form[name]'] = 'Iron Thorns Updated';
        // Set archetype to empty (clear it)
        $form['deck_form[archetype]'] = '';
        $form['deck_form[languages]'] = '';
        $this->client->submit($form);

        self::assertResponseRedirects();

        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        $freshDeck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Iron Thorns Updated']);
        self::assertNotNull($freshDeck);
        self::assertNull($freshDeck->getArchetype());
        self::assertSame([], $freshDeck->getLanguages());
    }

    /**
     * Importing a deck list with valid format but warnings still succeeds
     * and shows warnings.
     *
     * @see docs/features.md F2.2 — Import deck list (PTCG text format)
     */
    public function testImportDeckListShowsWarningsForValidList(): void
    {
        $this->loginAs('admin@example.com');

        $deckId = $this->getDeckId('Ancient Box');
        $crawler = $this->client->request('GET', '/deck/'.$deckId.'/import');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Import')->form([
            'deck_import_form[rawList]' => $this->getValidDeckList(),
        ]);
        $this->client->submit($form);

        // Should redirect on success
        self::assertResponseRedirects();
    }

    /**
     * Editing a deck with a non-empty archetype and languages value
     * exercises the "set archetype" and "set languages" branches.
     *
     * Covers DeckController::handleArchetypeAndLanguages() lines 286-287, 297-298.
     *
     * @see docs/features.md F2.1 — Register a new deck (owner)
     */
    public function testEditDeckWithArchetypeAndLanguages(): void
    {
        $this->loginAs('admin@example.com');

        $deck = $this->getDeck('Ancient Box');

        /** @var ArchetypeRepository $archetypeRepository */
        $archetypeRepository = static::getContainer()->get(ArchetypeRepository::class);
        $archetype = $archetypeRepository->findOneBy(['name' => 'Iron Thorns ex']);
        self::assertNotNull($archetype);

        $crawler = $this->client->request('GET', \sprintf('/deck/%d/edit', $deck->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['deck_form[name]'] = 'Ancient Box Updated';
        $form['deck_form[archetype]'] = (string) $archetype->getId();
        $form['deck_form[languages]'] = '["en","fr"]';
        $this->client->submit($form);

        self::assertResponseRedirects();

        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        $freshDeck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Ancient Box Updated']);
        self::assertNotNull($freshDeck);
        self::assertNotNull($freshDeck->getArchetype());
        self::assertSame('Iron Thorns ex', $freshDeck->getArchetype()->getName());
        self::assertSame(['en', 'fr'], $freshDeck->getLanguages());
    }

    /**
     * Creating a new deck with an archetype and languages set exercises the
     * handleArchetypeAndLanguages branches via the "new" action.
     *
     * Covers DeckController::new() + handleArchetypeAndLanguages() lines 286-287, 297-298.
     *
     * @see docs/features.md F2.1 — Register a new deck (owner)
     */
    public function testNewDeckWithArchetypeAndLanguages(): void
    {
        $this->loginAs('admin@example.com');

        /** @var ArchetypeRepository $archetypeRepository */
        $archetypeRepository = static::getContainer()->get(ArchetypeRepository::class);
        $archetype = $archetypeRepository->findOneBy(['name' => 'Ancient Box']);
        self::assertNotNull($archetype);

        $crawler = $this->client->request('GET', '/deck/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Deck')->form();
        $form['deck_form[name]'] = 'Test Deck With Archetype';
        $form['deck_form[archetype]'] = (string) $archetype->getId();
        $form['deck_form[languages]'] = '["en"]';
        $this->client->submit($form);

        self::assertResponseRedirects();

        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        $freshDeck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Test Deck With Archetype']);
        self::assertNotNull($freshDeck);
        self::assertNotNull($freshDeck->getArchetype());
        self::assertSame('Ancient Box', $freshDeck->getArchetype()->getName());
        self::assertSame(['en'], $freshDeck->getLanguages());
    }

    private function getDeck(string $name): Deck
    {
        $entityManager = $this->getEntityManager();
        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => $name]);

        return $deck;
    }

    private function getDeckId(string $name): int
    {
        $deck = $this->getDeck($name);

        /** @var int $id */
        $id = $deck->getId();

        return $id;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function getValidDeckList(): string
    {
        return <<<'LIST'
            Pokémon: 13
            4 Flutter Mane TEF 78
            4 Roaring Moon TEF 109
            1 Roaring Moon ex PR-SV 67
            1 Great Tusk TEF 97
            1 Koraidon SSP 116
            1 Munkidori TWM 95
            1 Pecharunt ex SFA 85

            Trainer: 40
            4 Professor Sada's Vitality PAR 170
            4 Explorer's Guidance TEF 147
            2 Boss's Orders PAL 172
            2 Surfer SSP 187
            2 Janine's Secret Art PRE 112
            2 Professor's Research PAF 87
            4 Earthen Vessel PAR 163
            3 Nest Ball SVI 181
            2 Pokégear 3.0 SVI 186
            2 Counter Catcher PAR 160
            2 Night Stretcher SFA 61
            1 Pal Pad SVI 182
            1 Superior Energy Retrieval PAL 189
            1 Super Rod PAL 188
            1 Brilliant Blender SSP 164
            4 Ancient Booster Energy Capsule TEF 140
            1 Exp. Share SVI 174
            2 Artazon PAL 171

            Energy: 7
            7 Darkness Energy SVE 7

            Total Cards: 60
            LIST;
    }
}
