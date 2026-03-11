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

use App\Entity\Archetype;
use App\Entity\Borrow;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Entity\Event;
use App\Entity\EventDeckEntry;
use App\Entity\EventDeckRegistration;
use App\Entity\EventEngagement;
use App\Entity\EventStaff;
use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslation;
use App\Entity\Notification;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Enum\DeckStatus;
use App\Enum\EngagementState;
use App\Enum\EventVisibility;
use App\Enum\NotificationType;
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
        $organizer = $this->createOrganizer($manager);
        $borrower = $this->createBorrower($manager);
        $staff1 = $this->createStaff1($manager);
        $staff2 = $this->createStaff2($manager);
        $lender = $this->createLender($manager);
        $this->createUnverifiedUser($manager);
        $todayEvent = $this->createEventToday($manager, $admin, $borrower, $staff1);
        $futureEvent = $this->createEventInTwoMonths($manager, $admin, $lender, $staff1, $staff2);
        $this->createInvitationalEvent($manager, $organizer, $admin, $staff2);
        $this->createDraftEvent($manager, $organizer, $admin);

        // Create archetypes
        $archetypeIronThorns = $this->createArchetype($manager, 'Iron Thorns ex');
        $archetypeAncientBox = $this->createArchetype($manager, 'Ancient Box');
        $archetypeRegidrago = $this->createArchetype($manager, 'Regidrago');
        $archetypeLugia = $this->createArchetype($manager, 'Lugia Archeops');

        $ironThorns = $this->createDeck($manager, $admin, 'Iron Thorns');
        $ironThorns->setArchetype($archetypeIronThorns);
        $ironThorns->setLanguages(['en']);
        $ironThorns->setPublic(true);
        $this->createIronThornsDeckVersion($manager, $ironThorns);
        $this->createIronThornsDeckVersionTwo($manager, $ironThorns);
        $this->createIronThornsDeckVersionThree($manager, $ironThorns);

        $ancientBox = $this->createDeck($manager, $admin, 'Ancient Box');
        $ancientBox->setArchetype($archetypeAncientBox);
        $ancientBox->setLanguages(['en']);
        $this->createAncientBoxDeckVersion($manager, $ancientBox);

        $lenderDeck = $this->createDeck($manager, $lender, 'Regidrago');
        $lenderDeck->setArchetype($archetypeRegidrago);
        $lenderDeck->setLanguages(['en']);
        $lenderDeck->setPublic(true);
        $this->createRegidragoDeckVersion($manager, $lenderDeck);
        $this->createRegidragoDeckVersionTwo($manager, $lenderDeck);

        $borrowerDeck = $this->createDeck($manager, $borrower, 'Lugia Archeops');
        $borrowerDeck->setArchetype($archetypeLugia);
        $borrowerDeck->setLanguages(['en']);
        $this->createLugiaArcheopsDeckVersion($manager, $borrowerDeck);

        $manager->flush();

        $pendingBorrow = $this->createBorrowFixtures($manager, $todayEvent, $futureEvent, $borrower, $lender, $admin, $ironThorns, $ancientBox, $lenderDeck, $staff1);
        $this->createDeckRegistrations($manager, $todayEvent, $ironThorns, $ancientBox, $lenderDeck);
        $this->createFinishedEvent($manager, $admin, $borrower, $staff1, $ironThorns, $ancientBox, $lenderDeck);

        $manager->flush();

        $this->createAdminNotifications($manager, $admin, $borrower, $todayEvent, $pendingBorrow);
        $this->createCmsFixtures($manager);

        $manager->flush();
    }

    private function createAdmin(ObjectManager $manager): User
    {
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setFirstName('Jean-Michel');
        $admin->setLastName('Admin');
        $admin->setScreenName('Admin');
        $admin->setPlayerId('007');
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
        $organizer->setPlayerId('PKM-ORG-001');
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
        $borrower->setPlayerId('201');
        $borrower->setPassword($this->passwordHasher->hashPassword($borrower, 'password'));
        $borrower->setIsVerified(true);
        $borrower->setPreferredLocale('en');
        $borrower->setTimezone('Europe/Paris');

        $manager->persist($borrower);

        return $borrower;
    }

    private function createStaff1(ObjectManager $manager): User
    {
        $user = new User();
        $user->setEmail('staff1@example.com');
        $user->setFirstName('Diana');
        $user->setLastName('Rousseau');
        $user->setScreenName('StaffOne');
        $user->setPlayerId('101');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $user->setIsVerified(true);
        $user->setPreferredLocale('en');
        $user->setTimezone('Europe/Paris');

        $manager->persist($user);

        return $user;
    }

    private function createStaff2(ObjectManager $manager): User
    {
        $user = new User();
        $user->setEmail('staff2@example.com');
        $user->setFirstName('Ethan');
        $user->setLastName('Moreau');
        $user->setScreenName('StaffTwo');
        $user->setPlayerId('102');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $user->setIsVerified(true);
        $user->setPreferredLocale('en');
        $user->setTimezone('Europe/Paris');

        $manager->persist($user);

        return $user;
    }

    private function createLender(ObjectManager $manager): User
    {
        $user = new User();
        $user->setEmail('lender@example.com');
        $user->setFirstName('Fiona');
        $user->setLastName('Leclerc');
        $user->setScreenName('Lender');
        $user->setPlayerId('301');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $user->setIsVerified(true);
        $user->setPreferredLocale('en');
        $user->setTimezone('Europe/Paris');

        $manager->persist($user);

        return $user;
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

    private function createEventToday(ObjectManager $manager, User $organizer, User $borrower, User $staff1): Event
    {
        $event = new Event();
        $event->setName('Expanded Weekly #42');
        $event->setDate(new \DateTimeImmutable('today'));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('12 Rue de la République, 69001 Lyon, France');
        $event->setOrganizer($organizer);
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

        $borrowerEngagement = new EventEngagement();
        $borrowerEngagement->setEvent($event);
        $borrowerEngagement->setUser($borrower);
        $borrowerEngagement->setState(EngagementState::RegisteredPlaying);
        $borrowerEngagement->setParticipationMode(ParticipationMode::Playing);
        $manager->persist($borrowerEngagement);

        $staff = new EventStaff();
        $staff->setEvent($event);
        $staff->setUser($borrower);
        $staff->setAssignedBy($organizer);
        $manager->persist($staff);

        $staff1Engagement = new EventEngagement();
        $staff1Engagement->setEvent($event);
        $staff1Engagement->setUser($staff1);
        $staff1Engagement->setState(EngagementState::RegisteredPlaying);
        $staff1Engagement->setParticipationMode(ParticipationMode::Playing);
        $manager->persist($staff1Engagement);

        $staff1Assignment = new EventStaff();
        $staff1Assignment->setEvent($event);
        $staff1Assignment->setUser($staff1);
        $staff1Assignment->setAssignedBy($organizer);
        $manager->persist($staff1Assignment);

        return $event;
    }

    private function createEventInTwoMonths(ObjectManager $manager, User $organizer, User $lender, User $staff1, User $staff2): Event
    {
        $event = new Event();
        $event->setName('Lyon Expanded Cup 2026');
        $event->setDate(new \DateTimeImmutable('+2 months', new \DateTimeZone('Europe/Paris')));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('12 Rue de la République, 69001 Lyon, France');
        $event->setOrganizer($organizer);
        $event->setRegistrationLink('https://pokemon-lyon.example.com/events/cup-2026');
        $event->setTournamentStructure(TournamentStructure::SwissTopCut);
        $event->setFormat('Expanded');

        $manager->persist($event);

        $engagement = new EventEngagement();
        $engagement->setEvent($event);
        $engagement->setUser($organizer);
        $engagement->setState(EngagementState::RegisteredSpectating);
        $engagement->setParticipationMode(ParticipationMode::Spectating);
        $manager->persist($engagement);

        $lenderEngagement = new EventEngagement();
        $lenderEngagement->setEvent($event);
        $lenderEngagement->setUser($lender);
        $lenderEngagement->setState(EngagementState::RegisteredPlaying);
        $lenderEngagement->setParticipationMode(ParticipationMode::Playing);
        $manager->persist($lenderEngagement);

        $staff1Assignment = new EventStaff();
        $staff1Assignment->setEvent($event);
        $staff1Assignment->setUser($staff1);
        $staff1Assignment->setAssignedBy($organizer);
        $manager->persist($staff1Assignment);

        $staff2Assignment = new EventStaff();
        $staff2Assignment->setEvent($event);
        $staff2Assignment->setUser($staff2);
        $staff2Assignment->setAssignedBy($organizer);
        $manager->persist($staff2Assignment);

        return $event;
    }

    /**
     * @see docs/features.md F3.13 — Player engagement states
     */
    private function createInvitationalEvent(ObjectManager $manager, User $organizer, User $admin, User $staff2): Event
    {
        $event = new Event();
        $event->setName('Invitation-Only Expanded Meetup');
        $event->setDate(new \DateTimeImmutable('+3 weeks', new \DateTimeZone('Europe/Paris')));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('Private Game Room, Paris');
        $event->setOrganizer($organizer);
        $event->setRegistrationLink('https://pokemon-paris.example.com/meetup');
        $event->setTournamentStructure(TournamentStructure::Swiss);
        $event->setFormat('Expanded');
        $event->setIsInvitationOnly(true);

        $manager->persist($event);

        // Organizer registers as player
        $organizerEngagement = new EventEngagement();
        $organizerEngagement->setEvent($event);
        $organizerEngagement->setUser($organizer);
        $organizerEngagement->setState(EngagementState::RegisteredPlaying);
        $organizerEngagement->setParticipationMode(ParticipationMode::Playing);
        $manager->persist($organizerEngagement);

        // Admin is invited
        $adminEngagement = new EventEngagement();
        $adminEngagement->setEvent($event);
        $adminEngagement->setUser($admin);
        $adminEngagement->setState(EngagementState::Invited);
        $adminEngagement->setInvitedBy($organizer);
        $manager->persist($adminEngagement);

        // Staff2 assigned as staff
        $staffAssignment = new EventStaff();
        $staffAssignment->setEvent($event);
        $staffAssignment->setUser($staff2);
        $staffAssignment->setAssignedBy($organizer);
        $manager->persist($staffAssignment);

        return $event;
    }

    /**
     * @see docs/features.md F3.11 — Event visibility
     */
    private function createDraftEvent(ObjectManager $manager, User $organizer, User $admin): Event
    {
        $event = new Event();
        $event->setName('Draft Event — Not Yet Published');
        $event->setDate(new \DateTimeImmutable('+5 weeks', new \DateTimeZone('Europe/Paris')));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('TBD');
        $event->setOrganizer($organizer);
        $event->setRegistrationLink('https://pokemon-paris.example.com/draft');
        $event->setFormat('Expanded');
        $event->setVisibility(EventVisibility::Draft);

        $manager->persist($event);

        // Admin is invited so they can see it
        $adminEngagement = new EventEngagement();
        $adminEngagement->setEvent($event);
        $adminEngagement->setUser($admin);
        $adminEngagement->setState(EngagementState::Invited);
        $adminEngagement->setInvitedBy($organizer);
        $manager->persist($adminEngagement);

        return $event;
    }

    /**
     * @see docs/features.md F3.17 — Tournament Results
     */
    private function createFinishedEvent(ObjectManager $manager, User $admin, User $borrower, User $staff1, Deck $ironThorns, Deck $ancientBox, Deck $regidrago): void
    {
        $event = new Event();
        $event->setName('Past Expanded Weekly #40');
        $event->setDate(new \DateTimeImmutable('-2 weeks', new \DateTimeZone('Europe/Paris')));
        $event->setTimezone('Europe/Paris');
        $event->setLocation('12 Rue de la République, 69001 Lyon, France');
        $event->setOrganizer($admin);
        $event->setRegistrationLink('https://pokemon-lyon.example.com/events/40');
        $event->setTournamentStructure(TournamentStructure::Swiss);
        $event->setFormat('Expanded');
        $event->setFinishedAt(new \DateTimeImmutable('-2 weeks +6 hours', new \DateTimeZone('Europe/Paris')));

        $manager->persist($event);

        // Engagements
        $adminEngagement = new EventEngagement();
        $adminEngagement->setEvent($event);
        $adminEngagement->setUser($admin);
        $adminEngagement->setState(EngagementState::RegisteredPlaying);
        $adminEngagement->setParticipationMode(ParticipationMode::Playing);
        $manager->persist($adminEngagement);

        $borrowerEngagement = new EventEngagement();
        $borrowerEngagement->setEvent($event);
        $borrowerEngagement->setUser($borrower);
        $borrowerEngagement->setState(EngagementState::RegisteredPlaying);
        $borrowerEngagement->setParticipationMode(ParticipationMode::Playing);
        $manager->persist($borrowerEngagement);

        $staff1Engagement = new EventEngagement();
        $staff1Engagement->setEvent($event);
        $staff1Engagement->setUser($staff1);
        $staff1Engagement->setState(EngagementState::RegisteredPlaying);
        $staff1Engagement->setParticipationMode(ParticipationMode::Playing);
        $manager->persist($staff1Engagement);

        $staffAssignment = new EventStaff();
        $staffAssignment->setEvent($event);
        $staffAssignment->setUser($staff1);
        $staffAssignment->setAssignedBy($admin);
        $manager->persist($staffAssignment);

        // Deck entries with results
        $ironThornsVersion = $ironThorns->getCurrentVersion();
        \assert(null !== $ironThornsVersion);
        $ancientBoxVersion = $ancientBox->getCurrentVersion();
        \assert(null !== $ancientBoxVersion);
        $regidragoVersion = $regidrago->getCurrentVersion();
        \assert(null !== $regidragoVersion);

        $entry1 = new EventDeckEntry();
        $entry1->setEvent($event);
        $entry1->setPlayer($admin);
        $entry1->setDeckVersion($ironThornsVersion);
        $entry1->setFinalPlacement(1);
        $entry1->setMatchRecord('3-0-0');
        $manager->persist($entry1);

        $entry2 = new EventDeckEntry();
        $entry2->setEvent($event);
        $entry2->setPlayer($borrower);
        $entry2->setDeckVersion($ancientBoxVersion);
        $entry2->setFinalPlacement(2);
        $entry2->setMatchRecord('2-1-0');
        $manager->persist($entry2);

        $entry3 = new EventDeckEntry();
        $entry3->setEvent($event);
        $entry3->setPlayer($staff1);
        $entry3->setDeckVersion($regidragoVersion);
        $entry3->setFinalPlacement(3);
        $entry3->setMatchRecord('1-2-0');
        $manager->persist($entry3);
    }

    private function createArchetype(ObjectManager $manager, string $name): Archetype
    {
        $archetype = new Archetype();
        $archetype->setName($name);

        $manager->persist($archetype);

        return $archetype;
    }

    private function createDeck(ObjectManager $manager, User $owner, string $name, DeckStatus $status = DeckStatus::Available): Deck
    {
        $deck = new Deck();
        $deck->setName($name);
        $deck->setOwner($owner);
        $deck->setFormat('Expanded');
        $deck->setStatus($status);

        $manager->persist($deck);

        return $deck;
    }

    private function createIronThornsDeckVersion(ObjectManager $manager, Deck $deck): void
    {
        $version = new DeckVersion();
        $version->setDeck($deck);
        $version->setVersionNumber(1);
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

    /**
     * @see docs/features.md F2.9 — Deck version history
     */
    private function createIronThornsDeckVersionTwo(ObjectManager $manager, Deck $deck): void
    {
        $version = new DeckVersion();
        $version->setDeck($deck);
        $version->setVersionNumber(2);

        // V2 changes vs V1: removed Megaton Blower SSP 182, added Crushing Hammer,
        // changed Plumeria quantity 4->3, changed Enhanced Hammer quantity 2->3
        $cardsVersion2 = $this->getIronThornsDeckCards();

        // Remove Megaton Blower
        $cardsVersion2 = array_filter($cardsVersion2, static fn (array $card): bool => 'Megaton Blower' !== $card['name']);
        $cardsVersion2 = array_values($cardsVersion2);

        // Add Crushing Hammer
        $cardsVersion2[] = ['name' => 'Crushing Hammer', 'set' => 'SSH', 'number' => '159', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'item'];

        foreach ($cardsVersion2 as $cardData) {
            $card = new DeckCard();
            $card->setCardName($cardData['name']);
            $card->setSetCode($cardData['set']);
            $card->setCardNumber($cardData['number']);

            // Apply quantity changes
            $quantity = $cardData['quantity'];
            if ('Plumeria' === $cardData['name']) {
                $quantity = 3;
            }
            if ('Enhanced Hammer' === $cardData['name']) {
                $quantity = 3;
            }

            $card->setQuantity($quantity);
            $card->setCardType($cardData['type']);
            $card->setTrainerSubtype($cardData['subtype']);

            $version->addCard($card);
        }

        $manager->persist($version);

        $deck->setCurrentVersion($version);
    }

    /**
     * @see docs/features.md F2.9 — Deck version history
     */
    private function createIronThornsDeckVersionThree(ObjectManager $manager, Deck $deck): void
    {
        $version = new DeckVersion();
        $version->setDeck($deck);
        $version->setVersionNumber(3);

        // V3 changes vs V2: removed Stealthy Hood, removed Capture Energy,
        // added 2 Lightning Energy, changed Chaotic Swell 3->2, changed VS Seeker 3->4
        $cardsVersion3 = $this->getIronThornsDeckCards();

        // Remove Megaton Blower (same as v2), Stealthy Hood, Capture Energy
        $cardsVersion3 = array_filter(
            $cardsVersion3,
            static fn (array $card): bool => !\in_array($card['name'], ['Megaton Blower', 'Stealthy Hood', 'Capture Energy'], true),
        );
        $cardsVersion3 = array_values($cardsVersion3);

        // Add Crushing Hammer (same as v2)
        $cardsVersion3[] = ['name' => 'Crushing Hammer', 'set' => 'SSH', 'number' => '159', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'item'];
        // Add Lightning Energy
        $cardsVersion3[] = ['name' => 'Lightning Energy', 'set' => 'SVE', 'number' => '4', 'quantity' => 2, 'type' => 'energy', 'subtype' => null];

        foreach ($cardsVersion3 as $cardData) {
            $card = new DeckCard();
            $card->setCardName($cardData['name']);
            $card->setSetCode($cardData['set']);
            $card->setCardNumber($cardData['number']);

            $quantity = $cardData['quantity'];
            if ('Plumeria' === $cardData['name']) {
                $quantity = 3; // same as v2
            }
            if ('Enhanced Hammer' === $cardData['name']) {
                $quantity = 3; // same as v2
            }
            if ('Chaotic Swell' === $cardData['name']) {
                $quantity = 2; // was 3 in v1/v2
            }
            if ('VS Seeker' === $cardData['name']) {
                $quantity = 4; // was 3 in v1/v2
            }

            $card->setQuantity($quantity);
            $card->setCardType($cardData['type']);
            $card->setTrainerSubtype($cardData['subtype']);

            $version->addCard($card);
        }

        $manager->persist($version);

        $deck->setCurrentVersion($version);
    }

    /**
     * @see docs/features.md F2.9 — Deck version history
     */
    private function createRegidragoDeckVersionTwo(ObjectManager $manager, Deck $deck): void
    {
        $version = new DeckVersion();
        $version->setDeck($deck);
        $version->setVersionNumber(2);

        // V2 changes vs V1: removed Wobbuffet, removed Leafy Camo Poncho,
        // added Giratina VSTAR, added Float Stone,
        // changed Guzma 2->1, changed Professor's Research 3->4
        $cardsVersion2 = $this->getRegidragoDeckCards();

        $cardsVersion2 = array_filter(
            $cardsVersion2,
            static fn (array $card): bool => !\in_array($card['name'], ['Wobbuffet', 'Leafy Camo Poncho'], true),
        );
        $cardsVersion2 = array_values($cardsVersion2);

        $cardsVersion2[] = ['name' => 'Giratina VSTAR', 'set' => 'LOR', 'number' => '131', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null];
        $cardsVersion2[] = ['name' => 'Float Stone', 'set' => 'PLF', 'number' => '99', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'tool'];

        foreach ($cardsVersion2 as $cardData) {
            $card = new DeckCard();
            $card->setCardName($cardData['name']);
            $card->setSetCode($cardData['set']);
            $card->setCardNumber($cardData['number']);

            $quantity = $cardData['quantity'];
            if ('Guzma' === $cardData['name']) {
                $quantity = 1; // was 2
            }
            if ("Professor's Research" === $cardData['name']) {
                $quantity = 4; // was 3
            }

            $card->setQuantity($quantity);
            $card->setCardType($cardData['type']);
            $card->setTrainerSubtype($cardData['subtype']);

            $version->addCard($card);
        }

        $manager->persist($version);

        $deck->setCurrentVersion($version);
    }

    private function createAncientBoxDeckVersion(ObjectManager $manager, Deck $deck): void
    {
        $version = new DeckVersion();
        $version->setDeck($deck);
        $version->setVersionNumber(1);
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

    private function createRegidragoDeckVersion(ObjectManager $manager, Deck $deck): void
    {
        $version = new DeckVersion();
        $version->setDeck($deck);
        $version->setVersionNumber(1);
        $version->setRawList($this->getRegidragoRawDeckList());

        foreach ($this->getRegidragoDeckCards() as $cardData) {
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
    private function getRegidragoDeckCards(): array
    {
        return [
            // Pokemon (19)
            ['name' => 'Regidrago V', 'set' => 'SIT', 'number' => '135', 'quantity' => 4, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Regidrago VSTAR', 'set' => 'SIT', 'number' => '136', 'quantity' => 3, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Dialga-GX', 'set' => 'UPR', 'number' => '100', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Kyurem', 'set' => 'SFA', 'number' => '47', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Dragapult ex', 'set' => 'TWM', 'number' => '130', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Salamence ex', 'set' => 'JTG', 'number' => '114', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Noivern-GX', 'set' => 'BUS', 'number' => '99', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Noivern ex', 'set' => 'PAL', 'number' => '153', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Dedenne-GX', 'set' => 'UNB', 'number' => '57', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Crobat V', 'set' => 'DAA', 'number' => '104', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Tapu Lele-GX', 'set' => 'GRI', 'number' => '60', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Latias ex', 'set' => 'SSP', 'number' => '76', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Budew', 'set' => 'PRE', 'number' => '4', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Wobbuffet', 'set' => 'PHF', 'number' => '36', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],

            // Trainer — Supporter (11)
            ['name' => "Professor's Research", 'set' => 'JTG', 'number' => '155', 'quantity' => 3, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Guzma', 'set' => 'BUS', 'number' => '115', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'N', 'set' => 'FCO', 'number' => '105', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Iono', 'set' => 'PAL', 'number' => '185', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Marnie', 'set' => 'SSH', 'number' => '169', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Crispin', 'set' => 'SCR', 'number' => '133', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Raihan', 'set' => 'EVS', 'number' => '152', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Faba', 'set' => 'LOT', 'number' => '173', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],

            // Trainer — Item (16)
            ['name' => 'Mysterious Treasure', 'set' => 'FLI', 'number' => '113', 'quantity' => 4, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Quick Ball', 'set' => 'FST', 'number' => '237', 'quantity' => 4, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'VS Seeker', 'set' => 'PHF', 'number' => '109', 'quantity' => 3, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Battle Compressor', 'set' => 'PHF', 'number' => '92', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Hisuian Heavy Ball', 'set' => 'ASR', 'number' => '146', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Megaton Blower', 'set' => 'SSP', 'number' => '182', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Super Rod', 'set' => 'PAL', 'number' => '188', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'item'],

            // Trainer — Tool (2)
            ['name' => 'Muscle Band', 'set' => 'XY', 'number' => '121', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'tool'],
            ['name' => 'Leafy Camo Poncho', 'set' => 'SIT', 'number' => '160', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'tool'],

            // Trainer — Stadium (2)
            ['name' => 'Parallel City', 'set' => 'BKT', 'number' => '145', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'stadium'],
            ['name' => 'Path to the Peak', 'set' => 'CRE', 'number' => '148', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'stadium'],

            // Energy (10)
            ['name' => 'Double Dragon Energy', 'set' => 'ROS', 'number' => '97', 'quantity' => 4, 'type' => 'energy', 'subtype' => null],
            ['name' => 'Grass Energy', 'set' => 'SVE', 'number' => '1', 'quantity' => 4, 'type' => 'energy', 'subtype' => null],
            ['name' => 'Fire Energy', 'set' => 'SVE', 'number' => '2', 'quantity' => 2, 'type' => 'energy', 'subtype' => null],
        ];
    }

    private function getRegidragoRawDeckList(): string
    {
        return <<<'PTCG'
            Pokémon: 19
            4 Regidrago V SIT 135
            3 Regidrago VSTAR SIT 136
            1 Dialga-GX UPR 100
            1 Kyurem SFA 47
            1 Dragapult ex TWM 130
            1 Salamence ex JTG 114
            1 Noivern-GX BUS 99
            1 Noivern ex PAL 153
            1 Dedenne-GX UNB 57
            1 Crobat V DAA 104
            1 Tapu Lele-GX GRI 60
            1 Latias ex SSP 76
            1 Budew PRE 4
            1 Wobbuffet PHF 36

            Trainer: 31
            3 Professor's Research JTG 155
            2 Guzma BUS 115
            1 N FCO 105
            1 Iono PAL 185
            1 Marnie SSH 169
            1 Crispin SCR 133
            1 Raihan EVS 152
            1 Faba LOT 173
            4 Mysterious Treasure FLI 113
            4 Quick Ball FST 237
            3 VS Seeker PHF 109
            2 Battle Compressor PHF 92
            1 Hisuian Heavy Ball ASR 146
            1 Megaton Blower SSP 182
            1 Super Rod PAL 188
            1 Muscle Band XY 121
            1 Leafy Camo Poncho SIT 160
            1 Parallel City BKT 145
            1 Path to the Peak CRE 148

            Energy: 10
            4 Double Dragon Energy ROS 97
            4 Grass Energy SVE 1
            2 Fire Energy SVE 2

            Total Cards: 60
            PTCG;
    }

    private function createLugiaArcheopsDeckVersion(ObjectManager $manager, Deck $deck): void
    {
        $version = new DeckVersion();
        $version->setDeck($deck);
        $version->setVersionNumber(1);
        $version->setRawList($this->getLugiaArcheopsRawDeckList());

        foreach ($this->getLugiaArcheopsDeckCards() as $cardData) {
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
    private function getLugiaArcheopsDeckCards(): array
    {
        return [
            // Pokemon (16)
            ['name' => 'Lugia V', 'set' => 'SIT', 'number' => '138', 'quantity' => 3, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Lugia VSTAR', 'set' => 'SIT', 'number' => '139', 'quantity' => 2, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Archeops', 'set' => 'SIT', 'number' => '147', 'quantity' => 3, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Garchomp & Giratina-GX', 'set' => 'UNM', 'number' => '146', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Naganadel & Guzzlord-GX', 'set' => 'CEC', 'number' => '158', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Garchomp V', 'set' => 'ASR', 'number' => '117', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Koraidon ex', 'set' => 'TEF', 'number' => '120', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Kyurem', 'set' => 'SFA', 'number' => '47', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Dedenne-GX', 'set' => 'UNB', 'number' => '57', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Crobat V', 'set' => 'DAA', 'number' => '104', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Tapu Lele-GX', 'set' => 'GRI', 'number' => '60', 'quantity' => 1, 'type' => 'pokemon', 'subtype' => null],

            // Trainer — Supporter (8)
            ['name' => 'Wally', 'set' => 'ROS', 'number' => '94', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Thorton', 'set' => 'LOR', 'number' => '167', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Professor Juniper', 'set' => 'PLB', 'number' => '84', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'N', 'set' => 'FCO', 'number' => '105', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Marnie', 'set' => 'SSH', 'number' => '169', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Guzma', 'set' => 'BUS', 'number' => '115', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],
            ['name' => 'Faba', 'set' => 'LOT', 'number' => '173', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'supporter'],

            // Trainer — Item (22)
            ['name' => 'Quick Ball', 'set' => 'FST', 'number' => '237', 'quantity' => 4, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Ultra Ball', 'set' => 'SVI', 'number' => '196', 'quantity' => 4, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Battle Compressor', 'set' => 'PHF', 'number' => '92', 'quantity' => 4, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'VS Seeker', 'set' => 'PHF', 'number' => '109', 'quantity' => 3, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => "Trainers' Mail", 'set' => 'ROS', 'number' => '92', 'quantity' => 3, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Special Charge', 'set' => 'STS', 'number' => '105', 'quantity' => 3, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Field Blower', 'set' => 'GRI', 'number' => '125', 'quantity' => 2, 'type' => 'trainer', 'subtype' => 'item'],
            ['name' => 'Secret Box', 'set' => 'TWM', 'number' => '163', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'item'],

            // Trainer — Tool (1)
            ['name' => 'Stealthy Hood', 'set' => 'UNB', 'number' => '186', 'quantity' => 1, 'type' => 'trainer', 'subtype' => 'tool'],

            // Trainer — Stadium (3)
            ['name' => 'Silent Lab', 'set' => 'PRC', 'number' => '140', 'quantity' => 3, 'type' => 'trainer', 'subtype' => 'stadium'],

            // Energy (8)
            ['name' => 'Double Colorless Energy', 'set' => 'SUM', 'number' => '136', 'quantity' => 4, 'type' => 'energy', 'subtype' => null],
            ['name' => 'Double Dragon Energy', 'set' => 'ROS', 'number' => '97', 'quantity' => 4, 'type' => 'energy', 'subtype' => null],
        ];
    }

    private function getLugiaArcheopsRawDeckList(): string
    {
        return <<<'PTCG'
            Pokémon: 16
            3 Lugia V SIT 138
            2 Lugia VSTAR SIT 139
            3 Archeops SIT 147
            1 Garchomp & Giratina-GX UNM 146
            1 Naganadel & Guzzlord-GX CEC 158
            1 Garchomp V ASR 117
            1 Koraidon ex TEF 120
            1 Kyurem SFA 47
            1 Dedenne-GX UNB 57
            1 Crobat V DAA 104
            1 Tapu Lele-GX GRI 60

            Trainer: 36
            2 Wally ROS 94
            1 Thorton LOR 167
            1 Professor Juniper PLB 84
            1 N FCO 105
            1 Marnie SSH 169
            1 Guzma BUS 115
            1 Faba LOT 173
            4 Quick Ball FST 237
            4 Ultra Ball SVI 196
            4 Battle Compressor PHF 92
            3 VS Seeker PHF 109
            3 Trainers' Mail ROS 92
            3 Special Charge STS 105
            2 Field Blower GRI 125
            1 Secret Box TWM 163
            1 Stealthy Hood UNB 186
            3 Silent Lab PRC 140

            Energy: 8
            4 Double Colorless Energy SUM 136
            4 Double Dragon Energy ROS 97

            Total Cards: 60
            PTCG;
    }

    /**
     * @see docs/features.md F4.8 — Staff-delegated lending
     */
    private function createDeckRegistrations(ObjectManager $manager, Event $event, Deck $ironThorns, Deck $ancientBox, Deck $regidrago): void
    {
        $registration = new EventDeckRegistration();
        $registration->setEvent($event);
        $registration->setDeck($ironThorns);
        $registration->setDelegateToStaff(true);
        $manager->persist($registration);

        $ancientBoxReg = new EventDeckRegistration();
        $ancientBoxReg->setEvent($event);
        $ancientBoxReg->setDeck($ancientBox);
        $ancientBoxReg->setDelegateToStaff(false);
        $manager->persist($ancientBoxReg);

        $regidragoReg = new EventDeckRegistration();
        $regidragoReg->setEvent($event);
        $regidragoReg->setDeck($regidrago);
        $regidragoReg->setDelegateToStaff(true);
        $manager->persist($regidragoReg);
    }

    /**
     * @see docs/features.md F4.1 — Request to borrow a deck
     * @see docs/features.md F4.9 — Staff deck custody tracking
     * @see docs/features.md F4.10 — Owner borrow inbox
     */
    /**
     * @return Borrow the pending borrow on today's event (for notification fixtures)
     */
    private function createBorrowFixtures(ObjectManager $manager, Event $todayEvent, Event $futureEvent, User $borrower, User $lender, User $admin, Deck $ironThorns, Deck $ancientBox, Deck $lenderDeck, User $staff1): Borrow
    {
        $ironThornsVersion = $ironThorns->getCurrentVersion();
        \assert(null !== $ironThornsVersion);

        $ancientBoxVersion = $ancientBox->getCurrentVersion();
        \assert(null !== $ancientBoxVersion);

        // --- Today's event: 1 pending + 1 approved (borrower borrows from admin) ---

        $pendingBorrow = new Borrow();
        $pendingBorrow->setDeck($ironThorns);
        $pendingBorrow->setDeckVersion($ironThornsVersion);
        $pendingBorrow->setBorrower($borrower);
        $pendingBorrow->setEvent($todayEvent);
        $pendingBorrow->setNotes('Need it for round 1');
        $manager->persist($pendingBorrow);

        $approvedBorrow = new Borrow();
        $approvedBorrow->setDeck($ancientBox);
        $approvedBorrow->setDeckVersion($ancientBoxVersion);
        $approvedBorrow->setBorrower($borrower);
        $approvedBorrow->setEvent($todayEvent);
        $approvedBorrow->setStatus(BorrowStatus::Approved);
        $approvedBorrow->setApprovedAt(new \DateTimeImmutable());
        $approvedBorrow->setApprovedBy($admin);
        $manager->persist($approvedBorrow);

        // --- Future event: 1 pending (lender borrows Iron Thorns from admin) ---
        // Gives 2 total pending borrows across events to exercise inbox grouping.
        // Note: Ancient Box is kept free at this event for EventControllerTest scenarios.

        $pendingBorrow2 = new Borrow();
        $pendingBorrow2->setDeck($ironThorns);
        $pendingBorrow2->setDeckVersion($ironThornsVersion);
        $pendingBorrow2->setBorrower($lender);
        $pendingBorrow2->setEvent($futureEvent);
        $pendingBorrow2->setNotes('Would love to try this deck at Lyon');
        $manager->persist($pendingBorrow2);

        // --- Today's event: 1 delegated pending (staff1 borrows Regidrago from lender) ---
        $lenderDeckVersion = $lenderDeck->getCurrentVersion();
        \assert(null !== $lenderDeckVersion);

        $delegatedBorrow = new Borrow();
        $delegatedBorrow->setDeck($lenderDeck);
        $delegatedBorrow->setDeckVersion($lenderDeckVersion);
        $delegatedBorrow->setBorrower($staff1);
        $delegatedBorrow->setEvent($todayEvent);
        $delegatedBorrow->setIsDelegatedToStaff(true);
        $delegatedBorrow->setNotes('Delegated to staff for event');
        $manager->persist($delegatedBorrow);

        // Deck stays Available — approval no longer sets Reserved (per-event concern)

        return $pendingBorrow;
    }

    private function createAdminNotifications(ObjectManager $manager, User $admin, User $borrower, Event $event, Borrow $pendingBorrow): void
    {
        $n1 = new Notification();
        $n1->setRecipient($admin);
        $n1->setType(NotificationType::BorrowRequested);
        $n1->setTitle('New borrow request from '.$borrower->getScreenName());
        $n1->setMessage($borrower->getScreenName().' wants to borrow Iron Thorns for '.$event->getName().'.');
        $n1->setContext([
            'borrowId' => $pendingBorrow->getId(),
            'deckId' => $pendingBorrow->getDeck()->getId(),
            'eventId' => $event->getId(),
        ]);
        $manager->persist($n1);

        $n2 = new Notification();
        $n2->setRecipient($admin);
        $n2->setType(NotificationType::StaffAssigned);
        $n2->setTitle('You were assigned as staff');
        $n2->setMessage('You have been assigned as staff for '.$event->getName().'.');
        $n2->setContext(['eventId' => $event->getId()]);
        $manager->persist($n2);

        $n3 = new Notification();
        $n3->setRecipient($admin);
        $n3->setType(NotificationType::EventInvited);
        $n3->setTitle('You are invited to an event');
        $n3->setMessage('You have been invited to '.$event->getName().'. Check it out!');
        $n3->setContext(['eventId' => $event->getId()]);
        $n3->setIsRead(true);
        $manager->persist($n3);
    }

    private function createCmsFixtures(ObjectManager $manager): void
    {
        // --- Menu categories ---

        $newsCategory = new MenuCategory();
        $newsCategory->setPosition(1);
        $manager->persist($newsCategory);

        $newsEn = new MenuCategoryTranslation();
        $newsEn->setMenuCategory($newsCategory);
        $newsEn->setLocale('en');
        $newsEn->setName('News');
        $newsCategory->addTranslation($newsEn);
        $manager->persist($newsEn);

        $newsFr = new MenuCategoryTranslation();
        $newsFr->setMenuCategory($newsCategory);
        $newsFr->setLocale('fr');
        $newsFr->setName('Actualités');
        $newsCategory->addTranslation($newsFr);
        $manager->persist($newsFr);

        $rulesCategory = new MenuCategory();
        $rulesCategory->setPosition(2);
        $manager->persist($rulesCategory);

        $rulesEn = new MenuCategoryTranslation();
        $rulesEn->setMenuCategory($rulesCategory);
        $rulesEn->setLocale('en');
        $rulesEn->setName('Rules & Info');
        $rulesCategory->addTranslation($rulesEn);
        $manager->persist($rulesEn);

        $rulesFr = new MenuCategoryTranslation();
        $rulesFr->setMenuCategory($rulesCategory);
        $rulesFr->setLocale('fr');
        $rulesFr->setName('Règles & Infos');
        $rulesCategory->addTranslation($rulesFr);
        $manager->persist($rulesFr);

        // --- Welcome page (shown on homepage) ---

        $welcomePage = new Page();
        $welcomePage->setSlug('welcome');
        $welcomePage->setIsPublished(true);
        $manager->persist($welcomePage);

        $welcomeEn = new PageTranslation();
        $welcomeEn->setPage($welcomePage);
        $welcomeEn->setLocale('en');
        $welcomeEn->setTitle('Welcome to Expanded Decks');
        $welcomeEn->setContent(<<<'MD'
## Your shared deck library

Expanded Decks makes it easy to **manage a shared library of physical Pokemon TCG decks** for your local community. Register your decks, lend them for events, and keep track of who has what.

### How it works

1. **Register your decks** — import your deck list in PTCG text format, validated against TCGdex
2. **Share for events** — other players can request to borrow a deck for upcoming events
3. **Track everything** — see who borrowed what, when, and get notified at every step

Jump in and explore the library!
MD);
        $welcomeEn->setMetaTitle('Expanded Decks — Shared Pokemon TCG Deck Library');
        $welcomeEn->setMetaDescription('Manage a shared library of physical Pokemon TCG decks for your local community. Lend decks for events and track borrowing.');
        $welcomePage->addTranslation($welcomeEn);
        $manager->persist($welcomeEn);

        $welcomeFr = new PageTranslation();
        $welcomeFr->setPage($welcomePage);
        $welcomeFr->setLocale('fr');
        $welcomeFr->setTitle('Bienvenue sur Expanded Decks');
        $welcomeFr->setSlug('bienvenue');
        $welcomeFr->setContent(<<<'MD'
## Votre bibliothèque de decks partagée

Expanded Decks facilite la **gestion d'une bibliothèque partagée de decks physiques Pokemon TCG** pour votre communauté locale. Enregistrez vos decks, prêtez-les pour des événements et suivez qui a quoi.

### Comment ça marche

1. **Enregistrez vos decks** — importez votre liste au format texte PTCG, validée via TCGdex
2. **Partagez pour les événements** — d'autres joueurs peuvent demander à emprunter un deck
3. **Suivez tout** — voyez qui a emprunté quoi, quand, et recevez des notifications à chaque étape

Plongez et explorez la bibliothèque !
MD);
        $welcomeFr->setMetaTitle('Expanded Decks — Bibliothèque de decks Pokemon TCG partagée');
        $welcomeFr->setMetaDescription('Gérez une bibliothèque partagée de decks physiques Pokemon TCG pour votre communauté. Prêtez des decks pour des événements.');
        $welcomePage->addTranslation($welcomeFr);
        $manager->persist($welcomeFr);

        // --- News articles ---

        $news1 = new Page();
        $news1->setSlug('season-2026-kickoff');
        $news1->setMenuCategory($newsCategory);
        $news1->setIsPublished(true);
        $manager->persist($news1);

        $news1En = new PageTranslation();
        $news1En->setPage($news1);
        $news1En->setLocale('en');
        $news1En->setTitle('Season 2026 is here!');
        $news1En->setContent(<<<'MD'
The new Pokemon TCG season is upon us! Here's what's new for the Expanded format community:

## New rotation policy

The Expanded format continues to include all cards from **Black & White** onward. No rotation this year — your favorite decks are safe.

## Upcoming events

We have several League Challenges and local tournaments lined up. Check the [events page](/events) to see what's coming up near you.

## New deck additions

Our library has grown to over **20 decks** available for borrowing. If you have decks you'd like to share, register them and help the community!
MD);
        $news1->addTranslation($news1En);
        $manager->persist($news1En);

        $news1Fr = new PageTranslation();
        $news1Fr->setPage($news1);
        $news1Fr->setLocale('fr');
        $news1Fr->setTitle('La saison 2026 est lancée !');
        $news1Fr->setSlug('lancement-saison-2026');
        $news1Fr->setContent(<<<'MD'
La nouvelle saison Pokemon TCG est là ! Voici les nouveautés pour la communauté Expanded :

## Nouvelle politique de rotation

Le format Expanded continue d'inclure toutes les cartes depuis **Noir & Blanc**. Pas de rotation cette année — vos decks préférés sont en sécurité.

## Événements à venir

Plusieurs League Challenges et tournois locaux sont prévus. Consultez la [page événements](/events) pour voir ce qui se passe près de chez vous.

## Nouveaux decks ajoutés

Notre bibliothèque compte désormais plus de **20 decks** disponibles à l'emprunt. Si vous avez des decks à partager, enregistrez-les et aidez la communauté !
MD);
        $news1->addTranslation($news1Fr);
        $manager->persist($news1Fr);

        $news2 = new Page();
        $news2->setSlug('borrowing-guide');
        $news2->setMenuCategory($newsCategory);
        $news2->setIsPublished(true);
        $manager->persist($news2);

        $news2En = new PageTranslation();
        $news2En->setPage($news2);
        $news2En->setLocale('en');
        $news2En->setTitle('How to borrow a deck for your next event');
        $news2En->setContent(<<<'MD'
New to Expanded Decks? Here's a quick guide on how to borrow a deck for your next event.

## Step 1: Find a deck

Browse the [deck catalog](/decks) and find a deck that suits your playstyle. Each deck page shows the full card list and availability status.

## Step 2: Request to borrow

On the deck page, select the event you want to borrow for and click **Request**. The deck owner will be notified.

## Step 3: Pick up at the event

Once approved, meet the deck owner (or their designated staff) at the event to collect the deck. The handoff is tracked in the system.

## Step 4: Return after the event

After playing, return the deck to the owner or staff. Everyone gets notified when the deck is back.

That's it! Happy playing.
MD);
        $news2->addTranslation($news2En);
        $manager->persist($news2En);

        $news3 = new Page();
        $news3->setSlug('march-league-challenge');
        $news3->setMenuCategory($newsCategory);
        $news3->setIsPublished(true);
        $manager->persist($news3);

        $news3En = new PageTranslation();
        $news3En->setPage($news3);
        $news3En->setLocale('en');
        $news3En->setTitle('March League Challenge recap');
        $news3En->setContent(<<<'MD'
Our March League Challenge was a blast! 16 players battled it out in Swiss rounds.

## Top 4

1. **Lugia VSTAR / Archeops** — piloted by Alex
2. **Mew VMAX / Genesect V** — piloted by Jordan
3. **Gardevoir ex** — piloted by Sam
4. **Lost Zone Box** — piloted by Casey

Congratulations to all participants! See you at the next event.
MD);
        $news3->addTranslation($news3En);
        $manager->persist($news3En);

        $news3Fr = new PageTranslation();
        $news3Fr->setPage($news3);
        $news3Fr->setLocale('fr');
        $news3Fr->setTitle('Résumé du League Challenge de mars');
        $news3Fr->setSlug('league-challenge-mars');
        $news3Fr->setContent(<<<'MD'
Notre League Challenge de mars était génial ! 16 joueurs se sont affrontés en rondes suisses.

## Top 4

1. **Lugia VSTAR / Archeops** — piloté par Alex
2. **Mew VMAX / Genesect V** — piloté par Jordan
3. **Gardevoir ex** — piloté par Sam
4. **Lost Zone Box** — piloté par Casey

Félicitations à tous les participants ! À bientôt au prochain événement.
MD);
        $news3->addTranslation($news3Fr);
        $manager->persist($news3Fr);

        $news4 = new Page();
        $news4->setSlug('new-decks-february');
        $news4->setMenuCategory($newsCategory);
        $news4->setIsPublished(true);
        $manager->persist($news4);

        $news4En = new PageTranslation();
        $news4En->setPage($news4);
        $news4En->setLocale('en');
        $news4En->setTitle('5 new decks added to the library');
        $news4En->setContent(<<<'MD'
We're excited to announce that five new decks have been added to our shared library:

- **Charizard ex / Pidgeot ex** — aggressive fire build
- **Iron Hands ex** — Future Box variant
- **Roaring Moon ex** — Ancient turbo
- **Snorlax Stall** — the classic wall
- **Raging Bolt ex / Ogerpon ex** — energy acceleration combo

All are available for borrowing at upcoming events. Check the [deck catalog](/decks) for details!
MD);
        $news4->addTranslation($news4En);
        $manager->persist($news4En);

        $news5 = new Page();
        $news5->setSlug('label-printing-live');
        $news5->setMenuCategory($newsCategory);
        $news5->setIsPublished(true);
        $manager->persist($news5);

        $news5En = new PageTranslation();
        $news5En->setPage($news5);
        $news5En->setLocale('en');
        $news5En->setTitle('Zebra label printing is now live');
        $news5En->setContent(<<<'MD'
Deck owners can now print Zebra labels directly from the deck page! Each label includes:

- Deck name and short tag
- QR code for quick scanning at events
- Owner name and deck archetype

Just click **Print Label** on any deck you own. You'll need a Zebra printer connected via PrintNode.
MD);
        $news5->addTranslation($news5En);
        $manager->persist($news5En);

        $news6 = new Page();
        $news6->setSlug('community-guidelines');
        $news6->setMenuCategory($newsCategory);
        $news6->setIsPublished(true);
        $manager->persist($news6);

        $news6En = new PageTranslation();
        $news6En->setPage($news6);
        $news6En->setLocale('en');
        $news6En->setTitle('Community guidelines update');
        $news6En->setContent(<<<'MD'
We've updated our community guidelines to better reflect how the platform works. Key points:

- Be respectful to deck owners and fellow borrowers
- Report any issues with borrowed decks promptly
- Organizers should verify deck returns before closing events
- Repeated no-shows may result in borrowing restrictions

Read the full [borrowing rules](/pages/borrowing-rules) for details.
MD);
        $news6->addTranslation($news6En);
        $manager->persist($news6En);

        $news7 = new Page();
        $news7->setSlug('spring-tournament-series');
        $news7->setMenuCategory($newsCategory);
        $news7->setIsPublished(true);
        $manager->persist($news7);

        $news7En = new PageTranslation();
        $news7En->setPage($news7);
        $news7En->setLocale('en');
        $news7En->setTitle('Spring Tournament Series announced');
        $news7En->setContent(<<<'MD'
We're kicking off a Spring Tournament Series with three events over April and May!

## Schedule

- **April 5** — League Challenge at the usual venue
- **April 19** — Special side event with promo prizes
- **May 10** — Season finale with top cut

Registration opens one week before each event. Deck borrowing will be available for all three.
MD);
        $news7->addTranslation($news7En);
        $manager->persist($news7En);

        $news8 = new Page();
        $news8->setSlug('deck-enrichment-update');
        $news8->setMenuCategory($newsCategory);
        $news8->setIsPublished(true);
        $manager->persist($news8);

        $news8En = new PageTranslation();
        $news8En->setPage($news8);
        $news8En->setLocale('en');
        $news8En->setTitle('Card images now auto-loaded from TCGdex');
        $news8En->setContent(<<<'MD'
Great news for deck browsing! Card images are now automatically fetched from TCGdex when a deck list is imported.

This means every card in a deck shows its actual artwork — making it much easier to browse and identify cards at a glance.

The enrichment happens in the background, so it may take a few seconds after importing a new deck list.
MD);
        $news8->addTranslation($news8En);
        $manager->persist($news8En);

        // --- Rules page (in Rules & Info category) ---

        $rulesPage = new Page();
        $rulesPage->setSlug('borrowing-rules');
        $rulesPage->setMenuCategory($rulesCategory);
        $rulesPage->setIsPublished(true);
        $manager->persist($rulesPage);

        $rulesPageEn = new PageTranslation();
        $rulesPageEn->setPage($rulesPage);
        $rulesPageEn->setLocale('en');
        $rulesPageEn->setTitle('Borrowing Rules');
        $rulesPageEn->setContent(<<<'MD'
Please follow these rules when borrowing decks from the shared library:

1. **Return on time** — Decks must be returned before the end of the event or as agreed with the owner
2. **Handle with care** — These are real cards that belong to someone. Keep them sleeved and protected
3. **No modifications** — Do not add, remove, or swap cards without the owner's permission
4. **Report damage** — If any card is damaged during your use, inform the owner immediately
5. **One deck per event** — You may borrow one deck per event unless the owner agrees otherwise

Repeated violations may result in borrowing restrictions. Thank you for respecting the community!
MD);
        $rulesPage->addTranslation($rulesPageEn);
        $manager->persist($rulesPageEn);

        $rulesPageFr = new PageTranslation();
        $rulesPageFr->setPage($rulesPage);
        $rulesPageFr->setLocale('fr');
        $rulesPageFr->setTitle('Règles d\'emprunt');
        $rulesPageFr->setSlug('regles-emprunt');
        $rulesPageFr->setContent(<<<'MD'
Veuillez respecter ces règles lors de l'emprunt de decks de la bibliothèque partagée :

1. **Rendez à l'heure** — Les decks doivent être rendus avant la fin de l'événement ou selon l'accord avec le propriétaire
2. **Manipulez avec soin** — Ce sont de vraies cartes qui appartiennent à quelqu'un. Gardez-les protégées
3. **Pas de modifications** — N'ajoutez, ne retirez ou n'échangez pas de cartes sans l'accord du propriétaire
4. **Signalez les dégâts** — Si une carte est endommagée, informez immédiatement le propriétaire
5. **Un deck par événement** — Vous pouvez emprunter un deck par événement sauf accord du propriétaire

Les violations répétées peuvent entraîner des restrictions d'emprunt. Merci de respecter la communauté !
MD);
        $rulesPage->addTranslation($rulesPageFr);
        $manager->persist($rulesPageFr);

        // --- Draft page (unpublished, for testing editor preview) ---

        $draftPage = new Page();
        $draftPage->setSlug('upcoming-features');
        $draftPage->setIsPublished(false);
        $draftPage->setNoIndex(true);
        $manager->persist($draftPage);

        $draftEn = new PageTranslation();
        $draftEn->setPage($draftPage);
        $draftEn->setLocale('en');
        $draftEn->setTitle('Upcoming Features (Draft)');
        $draftEn->setContent(<<<'MD'
> **This page is a draft and not visible to regular users.**

## Planned features

- Zebra label printing for deck boxes
- QR code scanning for deck identification
- iCal calendar feeds for events
- Visual deck list with card mosaic

Stay tuned!
MD);
        $draftPage->addTranslation($draftEn);
        $manager->persist($draftEn);
    }
}
