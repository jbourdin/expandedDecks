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
use App\Entity\Event;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Enum\DeckStatus;
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Extra coverage tests for EventController methods with remaining uncovered lines.
 *
 * Targets the resolvePlayableDeckVersion private method branches and
 * other edge cases in selectDeck, availableDecks, and walkUpSubmit.
 *
 * @see docs/features.md F3.7  — Register played deck for event
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 */
class EventControllerCoverageExtraTest extends AbstractFunctionalTest
{
    /**
     * Selecting an own deck that has "Lent" status should show danger flash
     * (resolvePlayableDeckVersion returns null for lent own decks).
     *
     * @see docs/features.md F3.7 — Register played deck for event
     */
    public function testSelectOwnDeckWithLentStatusShowsDanger(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFutureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        // Set deck status to Lent
        $entityManager = $this->getEntityManager();
        $deck->setStatus(DeckStatus::Lent);
        $entityManager->flush();

        // Get CSRF token
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'not available for selection');
    }

    /**
     * Selecting an own deck that has "Retired" status should show danger flash.
     *
     * @see docs/features.md F3.7 — Register played deck for event
     */
    public function testSelectOwnDeckWithRetiredStatusShowsDanger(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFutureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        // Set deck status to Retired
        $entityManager = $this->getEntityManager();
        $deck->setStatus(DeckStatus::Retired);
        $entityManager->flush();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'not available for selection');
    }

    /**
     * Selecting a borrowed deck with an approved borrow should succeed.
     *
     * @see docs/features.md F3.7 — Register played deck for event
     */
    public function testSelectBorrowedDeckWithApprovedBorrowSucceeds(): void
    {
        // borrower has an approved borrow for Ancient Box at today's event
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Ancient Box');

        // Verify the approved borrow exists
        /** @var BorrowRepository $borrowRepository */
        $borrowRepository = static::getContainer()->get(BorrowRepository::class);
        $borrow = $borrowRepository->findActiveBorrowForDeckAtEvent($deck, $event);
        self::assertNotNull($borrow);
        self::assertSame(BorrowStatus::Approved, $borrow->getStatus());

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Playing with');
    }

    /**
     * Selecting a deck that is borrowed by someone else (borrow exists but
     * borrower != current user) should show danger.
     *
     * @see docs/features.md F3.7 — Register played deck for event
     */
    public function testSelectBorrowedDeckByOtherBorrowerShowsDanger(): void
    {
        // staff1 has a pending delegated borrow for Regidrago at today's event
        // admin tries to select Regidrago — they're not the borrower
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Regidrago');

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'not available for selection');
    }

    /**
     * Clearing deck selection (deck_id=0) with no existing entry should
     * flash success without errors.
     *
     * Uses the future event because today's event has date-lock guards.
     *
     * @see docs/features.md F3.7 — Register played deck for event
     */
    public function testClearDeckSelectionSucceeds(): void
    {
        // Admin is not a participant on future event; need to register first
        $this->loginAs('admin@example.com');

        $event = $this->getFutureEvent();

        // Register admin as participant on the future event
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->getCsrfToken('participate-event-'.$event->getId());

        $this->client->request('POST', \sprintf('/event/%d/participate', $event->getId()), [
            '_token' => $csrfToken,
            'mode' => 'playing',
        ]);

        // Clear deck selection (no existing entry, just sends deck_id=0)
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => '0',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'cleared');
    }

    /**
     * Non-participant trying to select a deck should see a warning.
     *
     * Uses a generated CSRF token since non-participants don't have the
     * select-deck form on the page.
     *
     * @see docs/features.md F3.7 — Register played deck for event
     */
    public function testSelectDeckNonParticipantShowsWarning(): void
    {
        // borrower IS engaged in today's event — extract a valid CSRF token from the form
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        // Remove borrower's engagement so they become a non-participant
        $entityManager = $this->getEntityManager();
        $borrower = $entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'borrower@example.com']);
        self::assertNotNull($borrower);

        /** @var \App\Repository\EventEngagementRepository $engagementRepository */
        $engagementRepository = $entityManager->getRepository(\App\Entity\EventEngagement::class);
        $engagement = $engagementRepository->findOneBy([
            'event' => $event->getId(),
            'user' => $borrower->getId(),
        ]);
        self::assertNotNull($engagement);
        $entityManager->remove($engagement);
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => '1',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $crawler = $this->client->followRedirect();

        $html = $crawler->html();
        self::assertStringContainsString('must be a participant', strtolower($html));
    }

    /**
     * Non-engaged user browsing available decks should be redirected with
     * a message to register first.
     *
     * @see docs/features.md F4.1 — Request to borrow a deck
     */
    public function testAvailableDecksNonEngagedUserShowsWarning(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $crawler = $this->client->followRedirect();

        $html = $crawler->html();
        self::assertStringContainsString('Register as a participant', $html);
    }

    /**
     * Walk-up page should be denied for non-staff users.
     *
     * @see docs/features.md F4.12 — Walk-up lending (direct lend)
     */
    public function testWalkUpDeniedForNonStaff(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Walk-up cancelled event should redirect.
     *
     * @see docs/features.md F4.12 — Walk-up lending (direct lend)
     */
    public function testWalkUpCancelledEventRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $entityManager = $this->getEntityManager();
        $event->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Walk-up lending is not available');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function getFixtureEvent(): Event
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        $event = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        return $event;
    }

    private function getFutureEvent(): Event
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        $event = $eventRepository->findOneBy(['name' => 'Lyon Expanded Cup 2026']);
        self::assertNotNull($event);

        return $event;
    }

    private function getDeckByName(string $name): Deck
    {
        /** @var DeckRepository $deckRepository */
        $deckRepository = static::getContainer()->get(DeckRepository::class);
        $deck = $deckRepository->findOneBy(['name' => $name]);
        self::assertNotNull($deck);

        return $deck;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    /**
     * Extract CSRF token for select-deck form from the event show page.
     */
    private function extractSelectDeckCsrfToken(\Symfony\Component\DomCrawler\Crawler $crawler): string
    {
        $form = $crawler->filter('form[action*="select-deck"]');
        if (0 === $form->count()) {
            // Fall back to generating a token directly
            return $this->getCsrfToken('select-deck-0');
        }

        $token = $form->first()->filter('input[name="_token"]')->attr('value');
        self::assertNotNull($token);

        return $token;
    }

    /**
     * Generate a CSRF token using the client's session.
     */
    private function getCsrfToken(string $tokenId): string
    {
        $session = $this->client->getSession();
        self::assertNotNull($session, 'Session must exist — make a GET request first.');
        $session->start();

        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = static::getContainer()->get('request_stack');

        $syntheticRequest = new \Symfony\Component\HttpFoundation\Request();
        $syntheticRequest->setSession($session);
        $requestStack->push($syntheticRequest);

        try {
            /** @var \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $tokenManager */
            $tokenManager = static::getContainer()->get('security.csrf.token_manager');

            return $tokenManager->getToken($tokenId)->getValue();
        } finally {
            $requestStack->pop();
        }
    }
}
