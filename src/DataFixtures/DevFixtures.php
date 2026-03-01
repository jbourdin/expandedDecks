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
use App\Entity\EventEngagement;
use App\Entity\EventStaff;
use App\Entity\League;
use App\Entity\User;
use App\Enum\DeckStatus;
use App\Enum\EngagementState;
use App\Enum\ParticipationMode;
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
        $this->createOrganizer($manager);
        $borrower = $this->createBorrower($manager);
        $this->createUnverifiedUser($manager);
        $league = $this->createLeague($manager);
        $this->createEventToday($manager, $admin, $borrower, $league);
        $this->createEventInTwoMonths($manager, $admin, $league);
        $ironThorns = $this->createDeck($manager, $admin, 'Iron Thorns');
        $this->createIronThornsDeckVersion($manager, $ironThorns);
        $ancientBox = $this->createDeck($manager, $admin, 'Ancient Box');
        $this->createAncientBoxDeckVersion($manager, $ancientBox);

        $manager->flush();
    }

    private function createAdmin(ObjectManager $manager): User
    {
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setFirstName('Jean-Michel');
        $admin->setLastName('Admin');
        $admin->setScreenName('Admin');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password'));
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsVerified(true);
        $admin->setPreferredLocale('en');
        $admin->setTimezone('Europe/Paris');

        $manager->persist($admin);

        return $admin;
    }

    private function createOrganizer(ObjectManager $manager): User
    {
        $organizer = new User();
        $organizer->setEmail('organizer@example.com');
        $organizer->setFirstName('Bob');
        $organizer->setLastName('Martin');
        $organizer->setScreenName('Organizer');
        $organizer->setPassword($this->passwordHasher->hashPassword($organizer, 'password'));
        $organizer->setRoles(['ROLE_ORGANIZER']);
        $organizer->setIsVerified(true);
        $organizer->setPreferredLocale('en');
        $organizer->setTimezone('Europe/Paris');

        $manager->persist($organizer);

        return $organizer;
    }

    private function createBorrower(ObjectManager $manager): User
    {
        $borrower = new User();
        $borrower->setEmail('borrower@example.com');
        $borrower->setFirstName('Alice');
        $borrower->setLastName('Dupont');
        $borrower->setScreenName('Borrower');
        $borrower->setPassword($this->passwordHasher->hashPassword($borrower, 'password'));
        $borrower->setIsVerified(true);
        $borrower->setPreferredLocale('en');
        $borrower->setTimezone('Europe/Paris');

        $manager->persist($borrower);

        return $borrower;
    }

    private function createUnverifiedUser(ObjectManager $manager): User
    {
        $user = new User();
        $user->setEmail('unverified@example.com');
        $user->setFirstName('Charlie');
        $user->setLastName('Pending');
        $user->setScreenName('Unverified');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $user->setIsVerified(false);
        $user->setVerificationToken('test-verification-token');
        $user->setTokenExpiresAt(new \DateTimeImmutable('+1 day', new \DateTimeZone('UTC')));
        $user->setPreferredLocale('en');
        $user->setTimezone('UTC');

        $manager->persist($user);

        return $user;
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

    private function createEventToday(ObjectManager $manager, User $organizer, User $borrower, League $league): void
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

        $manager->persist($event);

        $engagement = new EventEngagement();
        $engagement->setEvent($event);
        $engagement->setUser($organizer);
        $engagement->setState(EngagementState::RegisteredPlaying);
        $engagement->setParticipationMode(ParticipationMode::Playing);
        $manager->persist($engagement);

        $staff = new EventStaff();
        $staff->setEvent($event);
        $staff->setUser($borrower);
        $staff->setAssignedBy($organizer);
        $manager->persist($staff);
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

        $manager->persist($event);

        $engagement = new EventEngagement();
        $engagement->setEvent($event);
        $engagement->setUser($organizer);
        $engagement->setState(EngagementState::RegisteredPlaying);
        $engagement->setParticipationMode(ParticipationMode::Playing);
        $manager->persist($engagement);
    }

    private function createDeck(ObjectManager $manager, User $owner, string $name): Deck
    {
        $deck = new Deck();
        $deck->setName($name);
        $deck->setOwner($owner);
        $deck->setFormat('Expanded');
        $deck->setStatus(DeckStatus::Available);

        $manager->persist($deck);

        return $deck;
    }

    private function createIronThornsDeckVersion(ObjectManager $manager, Deck $deck): void
    {
        $version = new DeckVersion();
        $version->setDeck($deck);
        $version->setVersionNumber(1);
        $version->setArchetype('iron-thorns-ex');
        $version->setArchetypeName('Iron Thorns ex');
        $version->setLanguages(['en']);
        $version->setRawList($this->getIronThornsRawDeckList());

        foreach ($this->getIronThornsDeckCards() as $cardData) {
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
    private function getIronThornsDeckCards(): array
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

    private function getIronThornsRawDeckList(): string
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

    private function createAncientBoxDeckVersion(ObjectManager $manager, Deck $deck): void
    {
        $version = new DeckVersion();
        $version->setDeck($deck);
        $version->setVersionNumber(1);
        $version->setArchetype('ancient-box');
        $version->setArchetypeName('Ancient Box');
        $version->setLanguages(['en']);
        $version->setRawList($this->getAncientBoxRawDeckList());

        foreach ($this->getAncientBoxDeckCards() as $cardData) {
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
    private function getAncientBoxDeckCards(): array
    {
        return [
            // Pokemon (13)
            ['name' => 'Flutter Mane', 'set' => 'TEF', 'number' => '78', 'quantity' => 4, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Roaring Moon', 'set' => 'TEF', 'number' => '109', 'quantity' => 4, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Roaring Moon ex', 'set' => 'PR-SV', 'number' => '67', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Great Tusk', 'set' => 'TEF', 'number' => '97', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Koraidon', 'set' => 'SSP', 'number' => '116', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Munkidori', 'set' => 'TWM', 'number' => '95', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Pecharunt ex', 'set' => 'SFA', 'number' => '85', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],

            // Trainer — Supporter (16)
            ['name' => "Professor Sada's Vitality", 'set' => 'PAR', 'number' => '170', 'quantity' => 4, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => "Explorer's Guidance", 'set' => 'TEF', 'number' => '147', 'quantity' => 4, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => "Boss's Orders", 'set' => 'PAL', 'number' => '172', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Surfer', 'set' => 'SSP', 'number' => '187', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => "Janine's Secret Art", 'set' => 'PRE', 'number' => '112', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => "Professor's Research", 'set' => 'PAF', 'number' => '87', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],

            // Trainer — Item (17)
            ['name' => 'Earthen Vessel', 'set' => 'PAR', 'number' => '163', 'quantity' => 4, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Nest Ball', 'set' => 'SVI', 'number' => '181', 'quantity' => 3, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Pokégear 3.0', 'set' => 'SVI', 'number' => '186', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Counter Catcher', 'set' => 'PAR', 'number' => '160', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Night Stretcher', 'set' => 'SFA', 'number' => '61', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Pal Pad', 'set' => 'SVI', 'number' => '182', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Superior Energy Retrieval', 'set' => 'PAL', 'number' => '189', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Super Rod', 'set' => 'PAL', 'number' => '188', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Brilliant Blender', 'set' => 'SSP', 'number' => '164', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'item'],

            // Trainer — Tool (5)
            ['name' => 'Ancient Booster Energy Capsule', 'set' => 'TEF', 'number' => '140', 'quantity' => 4, 'type' => 'trainer', 'subtype' => 'tool'],
            ['name' => 'Exp. Share', 'set' => 'SVI', 'number' => '174', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'tool'],

            // Trainer — Stadium (2)
            ['name' => 'Artazon', 'set' => 'PAL', 'number' => '171', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'stadium'],

            // Energy (7)
            ['name' => 'Darkness Energy', 'set' => 'SVE', 'number' => '7', 'quantity' => 7, 'type' => 'energy', 'subtype' => null],
        ];
    }

    private function getAncientBoxRawDeckList(): string
    {
        return <<<'PTCG'
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
            PTCG;
    }
}
