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

use App\Entity\Channel;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Renders the coming-soon landing page for channels that have no features
 * enabled and no published content.
 */
class EmptyChannelHomeTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function persistEmptyChannel(string $domain): Channel
    {
        $em = $this->getEntityManager();
        $channel = (new Channel())
            ->setCode('empty-'.bin2hex(random_bytes(3)))
            ->setDomain($domain)
            ->setEnableDecks(false)
            ->setEnableRegister(false)
            ->setEnableEvents(false)
            ->setEnableBorrows(false)
            ->setEnableArchetypes(false)
            ->setEnableBannedCards(false)
            ->setLocales(['en', 'fr'])
            ->setParameters(['brand_name' => 'Empty Brand']);
        $em->persist($channel);
        $em->flush();

        return $channel;
    }

    public function testHomeShowsComingSoonOnEmptyChannel(): void
    {
        $this->persistEmptyChannel('empty.wip');

        $crawler = $this->client->request('GET', 'https://empty.wip/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Coming soon');
        self::assertSelectorExists('img[alt="Psyduck"]');
    }

    public function testHomeFallsBackToWelcomePageOnNonEmptyChannel(): void
    {
        // The default `app` channel has decks/events/etc. enabled — the empty-
        // channel path must NOT trigger.
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Coming soon');
    }

    public function testEmptyChannelRendersEvenInFrench(): void
    {
        $this->persistEmptyChannel('empty-fr.wip');

        $crawler = $this->client->request('GET', 'https://empty-fr.wip/fr/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bientôt disponible');
    }
}
