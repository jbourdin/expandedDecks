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

use App\Entity\BannedCard;
use App\Entity\BannedCardTranslation;
use App\Entity\StapleCard;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F9.17 — Translation workflow for banned & staple cards
 */
class CardTranslationTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function loginByEmail(string $email): User
    {
        $user = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
        \assert($user instanceof User);
        $this->client->loginUser($user);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['HTTP_X-CSRF-Token' => $this->ajaxCsrfToken(), 'CONTENT_TYPE' => 'application/json'];
    }

    private function createBannedCard(string $name, string $explanation): BannedCard
    {
        $em = $this->getEntityManager();
        $card = new BannedCard();
        $card->setCardName($name);
        $card->setExplanation($explanation);

        // The public listing inner-joins printings: a card without one never
        // renders there.
        $printing = new \App\Entity\BannedCardPrinting();
        $printing->setBannedCard($card);
        $printing->setSetCode('TST');
        $printing->setCardNumber('001');
        $card->addPrinting($printing);

        $em->persist($card);
        $em->persist($printing);
        $em->flush();

        return $card;
    }

    public function testBannedCardLifecycleLandsOnLiveRow(): void
    {
        $em = $this->getEntityManager();
        $card = $this->createBannedCard('Lysandre Prime', 'Banned for being degenerate.');
        $cardId = $card->getId();
        \assert(null !== $cardId);

        $this->loginByEmail('translator@example.com');
        $this->client->request('POST', \sprintf('/admin/translations/banned_card/%d/fr/draft', $cardId), [], [], $this->ajaxHeaders(), '{"fields":{"explanation":"Bannie pour dégénérescence."}}');
        self::assertResponseIsSuccessful();
        $this->client->request('POST', \sprintf('/admin/translations/banned_card/%d/fr/submit', $cardId), [], [], $this->ajaxHeaders(), '{}');
        self::assertResponseIsSuccessful();

        /** @var array{state: string} $submitData */
        $submitData = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('pending_review', $submitData['state']);

        $this->loginByEmail('admin@example.com');
        $revision = $em->getRepository(\App\Entity\BannedCardTranslationRevision::class)->findOneBy(['bannedCard' => $card, 'locale' => 'fr']);
        \assert($revision instanceof \App\Entity\BannedCardTranslationRevision);
        $revisionId = $revision->getId();
        \assert(null !== $revisionId);
        $this->client->request('POST', \sprintf('/admin/translations/banned_card/revisions/%d/approve', $revisionId), [], [], $this->ajaxHeaders(), '{}');
        self::assertResponseIsSuccessful();

        $em = $this->getEntityManager();
        $live = $em->getRepository(BannedCardTranslation::class)->findOneBy(['bannedCard' => $cardId, 'locale' => 'fr']);
        self::assertInstanceOf(BannedCardTranslation::class, $live);
        self::assertSame('Bannie pour dégénérescence.', $live->getExplanation());
        self::assertFalse($live->isSourceOutdated());

        // A later source edit flags the translation and localizedExplanation
        // keeps serving the FR text. The original $card was detached by the
        // client requests' kernel reboots — mutate a freshly loaded one.
        $em = $this->getEntityManager();
        $freshCard = $em->getRepository(BannedCard::class)->find($cardId);
        \assert($freshCard instanceof BannedCard);
        $freshCard->setExplanation('Banned for being VERY degenerate.');
        $em->flush();

        $freshLive = $em->getRepository(BannedCardTranslation::class)->findOneBy(['bannedCard' => $freshCard, 'locale' => 'fr']);
        self::assertInstanceOf(BannedCardTranslation::class, $freshLive);
        $em->refresh($freshLive);
        self::assertTrue($freshLive->isSourceOutdated());
        self::assertSame('Bannie pour dégénérescence.', $freshCard->localizedExplanation('fr'));
        self::assertSame('Banned for being VERY degenerate.', $freshCard->localizedExplanation('en'));
    }

    public function testQueueCardsTabListsWorkAndUntranslatedCards(): void
    {
        $em = $this->getEntityManager();
        $translator = $this->loginByEmail('translator@example.com');

        $bannedCard = $this->createBannedCard('Forest of Giant Plants', 'Enables degenerate turn-one combos.');

        $stapleCard = new StapleCard();
        $stapleCard->setCardName('Quick Ball');
        $stapleCard->setBucket('search');
        $stapleCard->setNote('Best Pokemon search in the format.');
        $em->persist($stapleCard);
        $em->flush();

        // The staple gets a pending FR draft; the banned card stays untouched.
        $stapleCardId = $stapleCard->getId();
        \assert(null !== $stapleCardId);
        $this->client->request('POST', \sprintf('/admin/translations/staple_card/%d/fr/draft', $stapleCardId), [], [], $this->ajaxHeaders(), '{"fields":{"note":"Meilleure recherche de Pokemon du format."}}');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/translations/queue-data');
        self::assertResponseIsSuccessful();
        /** @var array{contributor: array{cards: list<array<string, mixed>>}} $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $cards = $data['contributor']['cards'];

        $stapleRow = null;
        $bannedRow = null;
        foreach ($cards as $row) {
            if ('staple_card' === $row['contentType'] && $row['contentId'] === $stapleCardId) {
                $stapleRow = $row;
            }
            if ('banned_card' === $row['contentType'] && $row['contentId'] === $bannedCard->getId()) {
                $bannedRow = $row;
            }
        }

        self::assertNotNull($stapleRow, 'In-workflow staple card must appear in the cards tab.');
        self::assertSame('draft', $stapleRow['state']);
        self::assertSame('Quick Ball', $stapleRow['label']);

        self::assertNotNull($bannedRow, 'Untranslated banned card with source copy must appear as worklist.');
        self::assertNull($bannedRow['state']);
        self::assertFalse($bannedRow['sourceOutdated']);

        // Reviewer flavor never lists untranslated-only cards.
        $this->loginByEmail('admin@example.com');
        $this->client->request('GET', '/admin/translations/queue-data');
        /** @var array{reviewer: array{cards: list<array<string, mixed>>}} $reviewerData */
        $reviewerData = json_decode((string) $this->client->getResponse()->getContent(), true);
        foreach ($reviewerData['reviewer']['cards'] as $row) {
            self::assertFalse(null === $row['state'] && false === $row['sourceOutdated']);
        }
        self::assertNotNull($translator->getId());
    }

    public function testListingRendersLocalizedExplanationWithPreviewGating(): void
    {
        $em = $this->getEntityManager();
        $card = $this->createBannedCard('Shaymin Prime', 'Draw engine too efficient.');
        $cardId = $card->getId();
        \assert(null !== $cardId);

        $this->loginByEmail('translator@example.com');
        $this->client->request('POST', \sprintf('/admin/translations/banned_card/%d/fr/draft', $cardId), [], [], $this->ajaxHeaders(), '{"fields":{"explanation":"Moteur de pioche trop efficace."}}');
        self::assertResponseIsSuccessful();

        // The translator previews the draft on the FR listing.
        $crawler = $this->client->request('GET', '/fr/banned-cards?translationPreview=1');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Moteur de pioche trop efficace.', $crawler->html());

        // Without the param, live content (EN fallback) renders.
        $crawler = $this->client->request('GET', '/fr/banned-cards');
        self::assertStringNotContainsString('Moteur de pioche trop efficace.', $crawler->html());

        // Unauthorized users never see the draft, param or not.
        $this->loginByEmail('borrower@example.com');
        $crawler = $this->client->request('GET', '/fr/banned-cards?translationPreview=1');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Moteur de pioche trop efficace.', $crawler->html());
    }

    public function testOutdatedFlagReachesTheListingDataAttributes(): void
    {
        $em = $this->getEntityManager();
        $card = $this->createBannedCard('Outdated Prime', 'Original explanation.');

        $french = new BannedCardTranslation();
        $french->setBannedCard($card);
        $french->setLocale('fr');
        $french->setExplanation('Explication française.');
        $card->addTranslation($french);
        $em->persist($french);
        $em->flush();

        $card->setExplanation('Fresh explanation.');
        $em->flush();

        // The outdated flag is written via raw SQL (F9.15), bypassing the
        // identity map — clear it so the request hydrates fresh rows.
        $em->clear();

        $crawler = $this->client->request('GET', '/fr/banned-cards');
        self::assertResponseIsSuccessful();
        $trigger = $crawler->filter('[data-card-explanation-outdated="true"]');
        self::assertGreaterThanOrEqual(1, $trigger->count(), 'Outdated FR explanation must be flagged in the listing payload.');
    }
}
