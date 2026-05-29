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
use App\Entity\BannedCardPrinting;
use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Repository\ChannelRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardControllerTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function persistCardIdentity(string $name): CardIdentity
    {
        $em = $this->getEntityManager();
        $identity = new CardIdentity();
        $identity->setName($name);
        $identity->setCategory('trainer');
        $em->persist($identity);
        $em->flush();

        return $identity;
    }

    private function persistCardPrinting(CardIdentity $identity, string $setCode, string $cardNumber, int $rarityTier = 3, ?string $imageUrl = null): CardPrinting
    {
        $em = $this->getEntityManager();
        $printing = new CardPrinting();
        $printing->setCardIdentity($identity);
        $printing->setTcgdexId($setCode.'-'.$cardNumber.'-'.bin2hex(random_bytes(2)));
        $printing->setSetCode($setCode);
        $printing->setCardNumber($cardNumber);
        $printing->setRarityTier($rarityTier);
        if (null !== $imageUrl) {
            $printing->setImageUrl($imageUrl);
        }
        $em->persist($printing);
        $em->flush();

        return $printing;
    }

    /**
     * @param list<array{setCode: string, cardNumber: string, printing?: CardPrinting}> $printings
     */
    private function persistBannedCard(
        string $cardName,
        array $printings,
        ?CardIdentity $identity = null,
        ?\DateTimeImmutable $effectiveDate = null,
        ?string $explanation = null,
        bool $deleted = false,
    ): BannedCard {
        $em = $this->getEntityManager();

        $card = new BannedCard();
        $card->setCardName($cardName);
        $card->setCardIdentity($identity);
        $card->setEffectiveDate($effectiveDate ?? new \DateTimeImmutable('2024-04-01'));
        $card->setSourceUrl('https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list');

        if (null !== $explanation) {
            $card->setExplanation($explanation);
        }

        if ($deleted) {
            $card->setDeletedAt(new \DateTimeImmutable());
        }

        foreach ($printings as $printingData) {
            $printing = new BannedCardPrinting();
            $printing->setSetCode($printingData['setCode']);
            $printing->setCardNumber($printingData['cardNumber']);
            if (isset($printingData['printing'])) {
                $printing->setCardPrinting($printingData['printing']);
            }
            $card->addPrinting($printing);
            $em->persist($printing);
        }

        $em->persist($card);
        $em->flush();

        return $card;
    }

    public function testListIsPubliclyAccessibleInEnglish(): void
    {
        $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Banned cards');
    }

    public function testListIsPubliclyAccessibleInFrench(): void
    {
        $this->client->request('GET', '/fr/banned-cards');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cartes bannies');
    }

    public function testListShowsActiveBannedCards(): void
    {
        $this->persistBannedCard('Forest of Giant Plants', [['setCode' => 'AOR', 'cardNumber' => '74']]);

        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        $trigger = $crawler->filter('.banned-card-trigger')->first();
        self::assertSame('Forest of Giant Plants', $trigger->attr('data-card-name'));
        $printings = json_decode((string) $trigger->attr('data-card-printings'), true);
        self::assertIsArray($printings);
        self::assertCount(1, $printings);
        self::assertSame(['setCode' => 'AOR', 'cardNumber' => '74'], $printings[0]);
    }

    public function testEachBannedCardIsOneTileEvenWithMultiplePrintings(): void
    {
        $identity = $this->persistCardIdentity('Archeops');
        $printingA = $this->persistCardPrinting($identity, 'NVI', '67', imageUrl: 'https://example/nvi/high.webp');
        $printingB = $this->persistCardPrinting($identity, 'DEX', '110', imageUrl: 'https://example/dex/high.webp');

        $this->persistBannedCard(
            'Archeops',
            [
                ['setCode' => 'NVI', 'cardNumber' => '67', 'printing' => $printingA],
                ['setCode' => 'DEX', 'cardNumber' => '110', 'printing' => $printingB],
            ],
            identity: $identity,
        );

        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        $triggers = $crawler->filter('.banned-card-trigger');
        self::assertSame(1, $triggers->count(), 'A single BannedCard parent must produce a single tile.');

        $printings = json_decode((string) $triggers->first()->attr('data-card-printings'), true);
        self::assertIsArray($printings);
        self::assertCount(2, $printings);
    }

    public function testListExcludesSoftDeletedParents(): void
    {
        $this->persistBannedCard('Active Card', [['setCode' => 'AOR', 'cardNumber' => '74']]);
        $this->persistBannedCard('Archived Card', [['setCode' => 'PHF', 'cardNumber' => '99']], deleted: true);

        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        $triggers = $crawler->filter('.banned-card-trigger');
        $names = [];
        foreach ($triggers as $trigger) {
            $names[] = $trigger->getAttribute('data-card-name');
        }
        self::assertContains('Active Card', $names);
        self::assertNotContains('Archived Card', $names);
    }

    public function testListEmptyStateShown(): void
    {
        $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-info');
    }

    public function testListRendersExplanationMarkdown(): void
    {
        $this->persistBannedCard(
            'Lysandre\'s Trump Card',
            [['setCode' => 'PHF', 'cardNumber' => '99']],
            explanation: "Resets **both** players' discard piles, breaking late-game tempo.",
        );

        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        $explanationAttribute = $crawler->filter('.banned-card-trigger')->attr('data-card-explanation');
        self::assertNotNull($explanationAttribute);
        self::assertStringContainsString('<strong>both</strong>', $explanationAttribute);
    }

    public function testListIncludesJsonLdItemList(): void
    {
        $this->persistBannedCard('Forest of Giant Plants', [['setCode' => 'AOR', 'cardNumber' => '74']]);

        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        $jsonLdScripts = $crawler->filter('script[type="application/ld+json"]');
        self::assertGreaterThan(0, $jsonLdScripts->count());

        $found = false;
        foreach ($jsonLdScripts as $script) {
            $content = $script->textContent;
            if (str_contains($content, '"ItemList"') && str_contains($content, 'Forest of Giant Plants')) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found);
    }

    public function testListRendersEditableIntroBlockWhenPagePresent(): void
    {
        $this->seedBannedCardsIntroPage();

        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        $intro = $crawler->filter('.card-body.cms-content');
        self::assertSame(1, $intro->count(), 'The editable intro block must render once when the Page exists.');
        self::assertStringContainsString('<strong>friendly</strong>', $intro->html());
    }

    public function testEditButtonHiddenForAnonymousVisitors(): void
    {
        $this->seedBannedCardsIntroPage();

        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        self::assertSame(
            0,
            $crawler->filter('a[href*="/admin/pages/"]')->count(),
            'Anonymous visitors must not see the intro edit button.',
        );
    }

    public function testEditButtonVisibleForCmsEditor(): void
    {
        $page = $this->seedBannedCardsIntroPage();

        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        $editLink = $crawler->filter('a[href="/admin/pages/'.$page->getId().'"]');
        self::assertSame(1, $editLink->count(), 'CMS editors must see an edit link pointing to the intro page.');
    }

    private function seedBannedCardsIntroPage(): Page
    {
        $em = $this->getEntityManager();

        /** @var ChannelRepository $channelRepository */
        $channelRepository = static::getContainer()->get(ChannelRepository::class);
        $channel = $channelRepository->findOneBy([]);
        self::assertNotNull($channel, 'A channel fixture must exist for this test.');

        $page = new Page();
        $page->setSlug('banned-cards-intro');
        $page->setChannel($channel);
        $page->setIsPublished(true);
        $page->setNoIndex(false);

        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('Banned cards');
        $translation->setContent('A **friendly** intro to the ban list.');
        $page->addTranslation($translation);

        $em->persist($page);
        $em->flush();

        return $page;
    }

    public function testListIncludesCanonicalAndHreflang(): void
    {
        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('link[rel="canonical"]')->count());
        self::assertGreaterThan(0, $crawler->filter('link[rel="alternate"][hreflang]')->count());
    }

    /**
     * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
     */
    public function testListEmitsEditorDefinedOpenGraphMetaTags(): void
    {
        $em = $this->getEntityManager();

        /** @var ChannelRepository $channelRepository */
        $channelRepository = static::getContainer()->get(ChannelRepository::class);
        $channel = $channelRepository->findOneBy([]);
        self::assertNotNull($channel);

        $page = new Page();
        $page->setSlug('banned-cards-intro');
        $page->setChannel($channel);
        $page->setIsPublished(true);
        $page->setNoIndex(false);
        $page->setOgImage('/uploads/banned-parent.png');

        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('Banned cards');
        $translation->setContent('intro');
        $translation->setOgImage('/uploads/banned-en.png');
        $translation->setOgDescription('Cards no longer legal in Expanded.');
        $page->addTranslation($translation);

        $em->persist($page);
        $em->flush();

        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        // Per-locale image wins over the parent default.
        $ogImage = $crawler->filter('meta[property="og:image"]')->attr('content');
        self::assertNotNull($ogImage);
        self::assertStringContainsString('/uploads/banned-en.png', $ogImage);
        // Description renders verbatim.
        $ogDescription = $crawler->filter('meta[property="og:description"]')->attr('content');
        self::assertSame('Cards no longer legal in Expanded.', $ogDescription);
    }

    /**
     * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
     */
    public function testListOmitsOpenGraphImageAndDescriptionWhenIntroFieldsBlank(): void
    {
        // No intro page seeded → resolver returns [null, null]; partial guards keep the tags off the page.
        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('meta[property="og:image"]')->count(), 'No og:image when no intro page is configured.');
        self::assertSame(0, $crawler->filter('meta[property="og:description"]')->count(), 'No og:description when no intro page is configured.');
    }
}
