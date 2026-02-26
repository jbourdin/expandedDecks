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

namespace App\DataFixtures;

use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Entity\Event;
use App\Entity\League;
use App\Entity\User;
use App\Enum\DeckStatus;
use App\Enum\TournamentStructure;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DevFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->createAdmin($manager);
        $this->createBorrower($manager);
        $league = $this->createLeague($manager);
        $this->createEventToday($manager, $admin, $league);
        $this->createEventInTwoMonths($manager, $admin, $league);
        $deck = $this->createDeck($manager, $admin);
        $this->createDeckVersion($manager, $deck);

        $manager->flush();
    }

    private function createAdmin(ObjectManager $manager): User
    {
        $admin = new User();
        $admin->setEmail('jbourdin@gmail.com');
        $admin->setScreenName('Admin');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password'));
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsVerified(true);
        $admin->setPreferredLocale('en');
        $admin->setTimezone('Europe/Paris');

        $manager->persist($admin);

        return $admin;
    }

    private function createBorrower(ObjectManager $manager): User
    {
        $borrower = new User();
        $borrower->setEmail('borrower@example.com');
        $borrower->setScreenName('Borrower');
        $borrower->setPassword($this->passwordHasher->hashPassword($borrower, 'password'));
        $borrower->setIsVerified(true);
        $borrower->setPreferredLocale('en');
        $borrower->setTimezone('Europe/Paris');

        $manager->persist($borrower);

        return $borrower;
    }

    private function createLeague(ObjectManager $manager): League
    {
        $league = new League();
        $league->setName('Ligue des Professeurs Développeurs');
        $league->setWebsite('https://pokemon-lyon.example.com');
        $league->setAddress('12 Rue de la République, 69001 Lyon, France');

        $manager->persist($league);

        return $league;
    }

    private function createEventToday(ObjectManager $manager, User $organizer, League $league): void
    {
        $event = new Event();
        $event->setName('Expanded Weekly #42');
        $event->setDate(new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('12 Rue de la République, 69001 Lyon, France');
        $event->setOrganizer($organizer);
        $event->setLeague($league);
        $event->setRegistrationLink('https://pokemon-lyon.example.com/events/42');
        $event->setTournamentStructure(TournamentStructure::Swiss);
        $event->setFormat('Expanded');
        $event->addParticipant($organizer);

        $manager->persist($event);
    }

    private function createEventInTwoMonths(ObjectManager $manager, User $organizer, League $league): void
    {
        $event = new Event();
        $event->setName('Lyon Expanded Cup 2026');
        $event->setDate(new \DateTimeImmutable('+2 months', new \DateTimeZone('Europe/Paris')));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('12 Rue de la République, 69001 Lyon, France');
        $event->setOrganizer($organizer);
        $event->setLeague($league);
        $event->setRegistrationLink('https://pokemon-lyon.example.com/events/cup-2026');
        $event->setTournamentStructure(TournamentStructure::SwissTopCut);
        $event->setFormat('Expanded');
        $event->addParticipant($organizer);

        $manager->persist($event);
    }

    private function createDeck(ObjectManager $manager, User $owner): Deck
    {
        $deck = new Deck();
        $deck->setName('Iron Thorns');
        $deck->setOwner($owner);
        $deck->setFormat('Expanded');
        $deck->setStatus(DeckStatus::Available);

        $manager->persist($deck);

        return $deck;
    }

    private function createDeckVersion(ObjectManager $manager, Deck $deck): void
    {
        $version = new DeckVersion();
        $version->setDeck($deck);
        $version->setVersionNumber(1);
        $version->setArchetype('iron-thorns-ex');
        $version->setArchetypeName('Iron Thorns ex');
        $version->setLanguages(['en']);
        $version->setRawList($this->getRawDeckList());

        foreach ($this->getDeckCards() as $cardData) {
            $card = new DeckCard();
            $card->setCardName($cardData['name']);
            $card->setSetCode($cardData['set']);
            $card->setCardNumber($cardData['number']);
            $card->setQuantity($cardData['quantity']);
            $card->setCardType($cardData['type']);
            $card->setTrainerSubtype($cardData['subtype']);

            $version->addCard($card);
        }

        $manager->persist($version);

        $deck->setCurrentVersion($version);
    }

    /**
     * @return list<array{name: string, set: string, number: string, quantity: int, type: string, subtype: string|null}>
     */
    private function getDeckCards(): array
    {
        return [
            // Pokemon (4)
            ['name' => 'Iron Thorns ex', 'set' => 'TWM', 'number' => '77', 'quantity' => 3, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Iron Thorns ex', 'set' => 'PRE', 'number' => '32', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],

            // Trainer — Supporter (25)
            ['name' => 'Plumeria', 'set' => 'BUS', 'number' => '120', 'quantity' => 4, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Eri', 'set' => 'TEF', 'number' => '146', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Sidney', 'set' => 'FST', 'number' => '241', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Guzma & Hala', 'set' => 'CEC', 'number' => '193', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Guzma', 'set' => 'BUS', 'number' => '115', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Lusamine', 'set' => 'CIN', 'number' => '96', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Team Flare Grunt', 'set' => 'GEN', 'number' => '73', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Cynthia & Caitlin', 'set' => 'CEC', 'number' => '189', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Cynthia', 'set' => 'UPR', 'number' => '119', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Faba', 'set' => 'LOT', 'number' => '173', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'N', 'set' => 'NVI', 'number' => '92', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Bellelba & Brycen-Man', 'set' => 'CEC', 'number' => '186', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Penny', 'set' => 'SVI', 'number' => '183', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],

            // Trainer — Item (16)
            ['name' => "Trainers' Mail", 'set' => 'ROS', 'number' => '92', 'quantity' => 4, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'VS Seeker', 'set' => 'PHF', 'number' => '109', 'quantity' => 3, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Tag Call', 'set' => 'CEC', 'number' => '206', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Enhanced Hammer', 'set' => 'TWM', 'number' => '148', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Heavy Ball', 'set' => 'BKT', 'number' => '140', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Nest Ball', 'set' => 'PAF', 'number' => '84', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Megaton Blower', 'set' => 'SSP', 'number' => '182', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'item'],

            // Trainer — Tool (3)
            ['name' => 'Future Booster Energy Capsule', 'set' => 'TEF', 'number' => '149', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'tool'],
            ['name' => 'Stealthy Hood', 'set' => 'UNB', 'number' => '186', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'tool'],
            ['name' => 'Tool Jammer', 'set' => 'BST', 'number' => '136', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'tool'],

            // Trainer — Stadium (3)
            ['name' => 'Chaotic Swell', 'set' => 'CEC', 'number' => '187', 'quantity' => 3, 'type' => 'trainer', 'subtype' => 'stadium'],
            ['name' => 'Jubilife Village', 'set' => 'ASR', 'number' => '148', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'stadium'],
            ['name' => 'Thunder Mountain Prism Star', 'set' => 'LOT', 'number' => '191', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'stadium'],

            // Energy (9)
            ['name' => 'Speed Lightning Energy', 'set' => 'RCL', 'number' => '173', 'quantity' => 4, 'type' => 'energy', 'subtype' => null],
            ['name' => 'Double Colorless Energy', 'set' => 'SLG', 'number' => '69', 'quantity' => 4, 'type' => 'energy', 'subtype' => null],
            ['name' => 'Capture Energy', 'set' => 'RCL', 'number' => '171', 'quantity' => 1, 'type' => 'energy', 'subtype' => null],
        ];
    }

    private function getRawDeckList(): string
    {
        return <<<'PTCG'
            Pokémon: 4
            3 Iron Thorns ex TWM 77
            1 Iron Thorns ex PRE 32

            Trainer: 47
            4 Plumeria BUS 120
            2 Eri TEF 146
            2 Sidney FST 241
            2 Guzma & Hala CEC 193
            2 Guzma BUS 115
            2 Lusamine CIN 96
            2 Team Flare Grunt GEN 73
            1 Cynthia & Caitlin CEC 189
            1 Cynthia UPR 119
            1 Faba LOT 173
            1 N NVI 92
            1 Bellelba & Brycen-Man CEC 186
            1 Penny SVI 183
            4 Trainers' Mail ROS 92
            3 VS Seeker PHF 109
            2 Tag Call CEC 206
            2 Enhanced Hammer TWM 148
            2 Heavy Ball BKT 140
            2 Nest Ball PAF 84
            1 Megaton Blower SSP 182
            2 Future Booster Energy Capsule TEF 149
            1 Stealthy Hood UNB 186
            1 Tool Jammer BST 136
            3 Chaotic Swell CEC 187
            1 Jubilife Village ASR 148
            1 Thunder Mountain Prism Star LOT 191

            Energy: 9
            4 Speed Lightning Energy RCL 173
            4 Double Colorless Energy SLG 69
            1 Capture Energy RCL 171

            Total Cards: 60
            PTCG;
    }
}
