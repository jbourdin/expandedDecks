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

    private function persistBannedCard(string $name, string $setCode, string $cardNumber, ?string $explanation = null, bool $deleted = false): BannedCard
    {
        $em = $this->getEntityManager();

        $card = new BannedCard();
        $card->setCardName($name);
        $card->setSetCode($setCode);
        $card->setCardNumber($cardNumber);
        $card->setEffectiveDate(new \DateTimeImmutable('2024-04-01'));
        $card->setSourceUrl('https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list');

        if (null !== $explanation) {
            $card->setExplanation($explanation);
        }

        if ($deleted) {
            $card->setDeletedAt(new \DateTimeImmutable());
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
        $this->persistBannedCard('Forest of Giant Plants', 'AOR', '74');

        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('.banned-card-trigger')->count());
        $trigger = $crawler->filter('.banned-card-trigger')->first();
        self::assertSame('Forest of Giant Plants', $trigger->attr('data-card-name'));
        self::assertSame('AOR 74', $trigger->attr('data-card-set'));
    }

    public function testListExcludesSoftDeletedCards(): void
    {
        $this->persistBannedCard('Active Card', 'AOR', '74');
        $this->persistBannedCard('Archived Card', 'PHF', '99', deleted: true);

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
            'PHF',
            '99',
            "Resets **both** players' discard piles, breaking late-game tempo.",
        );

        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        $explanationAttribute = $crawler->filter('.banned-card-trigger')->attr('data-card-explanation');
        self::assertNotNull($explanationAttribute);
        self::assertStringContainsString('<strong>both</strong>', $explanationAttribute);
    }

    public function testListIncludesJsonLdItemList(): void
    {
        $this->persistBannedCard('Forest of Giant Plants', 'AOR', '74');

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
        self::assertTrue($found, 'Expected an ItemList JSON-LD block listing the banned card.');
    }

    public function testListIncludesCanonicalAndHreflang(): void
    {
        $crawler = $this->client->request('GET', '/en/banned-cards');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('link[rel="canonical"]')->count());
        self::assertGreaterThan(0, $crawler->filter('link[rel="alternate"][hreflang]')->count());
    }
}
