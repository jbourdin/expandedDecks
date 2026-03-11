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

use App\Entity\Archetype;
use App\Enum\PlaystyleTag;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.6 — Archetype management
 * @see docs/features.md F2.15 — Archetype playstyle tags
 * @see docs/features.md F2.18 — Admin archetype create/edit form
 */
class AdminArchetypeControllerTest extends AbstractFunctionalTest
{
    public function testListRequiresAdmin(): void
    {
        $this->loginAs('borrower@example.com');
        $this->client->request('GET', '/admin/archetypes');

        self::assertResponseStatusCodeSame(403);
    }

    public function testListAccessibleByAdmin(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/archetypes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Archetype management');
        self::assertSelectorTextContains('table', 'Iron Thorns ex');
    }

    /**
     * @see docs/features.md F2.12 — Archetype sprite pictograms
     */
    public function testListDisplaysSpritesForArchetypes(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/archetypes');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.archetype-sprites .archetype-sprite');
        self::assertSelectorExists('img[src$="iron-thorns.png"]');
    }

    /**
     * @see docs/features.md F2.18 — Admin archetype create/edit form
     */
    public function testListHasCreateButton(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/archetypes');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/archetypes/new"]');
    }

    /**
     * @see docs/features.md F2.15 — Archetype playstyle tags
     */
    public function testListDisplaysPlaystyleTags(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/archetypes');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.badge.bg-danger');
    }

    public function testEditPageAccessibleByAdmin(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Iron Thorns ex');
        $this->client->request('GET', '/admin/archetypes/'.$archetype->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Iron Thorns ex');
    }

    public function testEditUpdatesArchetype(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Lugia Archeops');
        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetype->getId());

        $form = $crawler->selectButton('Save')->form();
        $form['archetype_form[description]'] = 'Updated description for testing.';
        $form['archetype_form[isPublished]']->tick();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    public function testEditRequiresAdmin(): void
    {
        $this->loginAs('borrower@example.com');

        $archetype = $this->getArchetype('Iron Thorns ex');
        $this->client->request('GET', '/admin/archetypes/'.$archetype->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testRedirectsForAnonymous(): void
    {
        $this->client->request('GET', '/admin/archetypes');

        self::assertResponseRedirects('/login');
    }

    public function testNewFieldsInFixtures(): void
    {
        $archetype = $this->getArchetype('Iron Thorns ex');

        self::assertSame(['iron-thorns'], $archetype->getPokemonSlugs());
        self::assertNotNull($archetype->getDescription());
        self::assertTrue($archetype->isPublished());
    }

    public function testUnpublishedArchetypeInFixtures(): void
    {
        $archetype = $this->getArchetype('Lugia Archeops');

        self::assertFalse($archetype->isPublished());
        self::assertNull($archetype->getDescription());
    }

    /**
     * @see docs/features.md F2.18 — Admin archetype create/edit form
     */
    public function testNewPageAccessibleByAdmin(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/admin/archetypes/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'New archetype');
    }

    /**
     * @see docs/features.md F2.18 — Admin archetype create/edit form
     */
    public function testNewPageRequiresAdmin(): void
    {
        $this->loginAs('borrower@example.com');
        $this->client->request('GET', '/admin/archetypes/new');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @see docs/features.md F2.18 — Admin archetype create/edit form
     */
    public function testCreateArchetype(): void
    {
        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', '/admin/archetypes/new');

        $form = $crawler->selectButton('Create')->form();
        $form['archetype_form[name]'] = 'Gardevoir ex';
        $form['archetype_form[isPublished]']->tick();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');

        $archetype = $this->getArchetype('Gardevoir ex');
        self::assertSame('gardevoir-ex', $archetype->getSlug());
        self::assertTrue($archetype->isPublished());
    }

    /**
     * @see docs/features.md F2.15 — Archetype playstyle tags
     */
    public function testPlaystyleTagsInFixtures(): void
    {
        $archetype = $this->getArchetype('Regidrago');

        $tags = $archetype->getPlaystyleTags();
        self::assertContains(PlaystyleTag::Combo, $tags);
        self::assertContains(PlaystyleTag::Lock, $tags);
        self::assertContains(PlaystyleTag::Toolbox, $tags);
    }

    /**
     * @see docs/features.md F2.15 — Archetype playstyle tags
     */
    public function testPlaystyleTagsDisplayedOnDetailPage(): void
    {
        $this->client->request('GET', '/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.badge.bg-warning');
        self::assertSelectorExists('.badge.bg-dark');
        self::assertSelectorExists('.badge.bg-success');
    }

    /**
     * @see docs/features.md F2.15 — Archetype playstyle tags
     */
    public function testEditPlaystyleTags(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Lugia Archeops');
        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetype->getId());

        $form = $crawler->selectButton('Save')->form();
        $form['archetype_form[playstyleTags][0]']->tick();
        $this->client->submit($form);

        self::assertResponseRedirects();

        $refreshed = $this->getArchetype('Lugia Archeops');
        self::assertContains(PlaystyleTag::Aggressive, $refreshed->getPlaystyleTags());
    }

    private function getArchetype(string $name): Archetype
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        /** @var Archetype $archetype */
        $archetype = $em->getRepository(Archetype::class)->findOneBy(['name' => $name]);

        return $archetype;
    }
}
