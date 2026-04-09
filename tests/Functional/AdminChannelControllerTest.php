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
 * @see docs/features.md F18.6 — Admin: channel CRUD and assignment UI
 */
class AdminChannelControllerTest extends AbstractFunctionalTest
{
    public function testListRequiresAdmin(): void
    {
        $this->loginAs('borrower@example.com');
        $this->client->request('GET', '/admin/channels');

        self::assertResponseStatusCodeSame(403);
    }

    public function testListAccessibleByAdmin(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/channels');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Channel management');
    }

    public function testListDisplaysFixtureChannels(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/channels');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'app');
        self::assertSelectorTextContains('table', 'content');
    }

    public function testNewPageRendersForm(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/channels/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Create channel');
        self::assertSelectorExists('input[name="channel_form[code]"]');
        self::assertSelectorExists('input[name="channel_form[domain]"]');
    }

    public function testCreateChannelAndRedirects(): void
    {
        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', '/admin/channels/new');

        $form = $crawler->selectButton('Create')->form();
        $form['channel_form[code]'] = 'test';
        $form['channel_form[domain]'] = 'test.example.com';
        $form['channel_form[enableDecks]']->tick();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testEditChannelPageRendersForm(): void
    {
        $this->loginAs('admin@example.com');

        $channel = $this->getChannel('app');
        $this->client->request('GET', '/admin/channels/'.$channel->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Edit channel: app');
    }

    public function testEditChannelCodeIsReadOnly(): void
    {
        $this->loginAs('admin@example.com');

        $channel = $this->getChannel('app');
        $crawler = $this->client->request('GET', '/admin/channels/'.$channel->getId());

        $codeInput = $crawler->filter('input[name="channel_form[code]"]');
        self::assertSame('disabled', $codeInput->attr('disabled'));
    }

    public function testUpdateChannelSavesAndRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $channel = $this->getChannel('app');
        $crawler = $this->client->request('GET', '/admin/channels/'.$channel->getId());

        $form = $crawler->selectButton('Save')->form();
        $form['channel_form[domain]'] = 'updated.expanded-decks.wip';
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    private function getChannel(string $code): Channel
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $channel = $entityManager->getRepository(Channel::class)->findOneBy(['code' => $code]);
        \assert($channel instanceof Channel);

        return $channel;
    }
}
