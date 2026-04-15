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
use App\Entity\Deck;
use App\Enum\DeckStatus;
use App\Repository\DeckRepository;
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
        self::assertSelectorExists('.badge.bg-secondary');
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
        self::assertNotNull($archetype->getLocalizedDescription('en'));
        self::assertTrue($archetype->isPublished());
    }

    public function testUnpublishedArchetypeInFixtures(): void
    {
        $archetype = $this->getArchetype('Lugia Archeops');

        self::assertFalse($archetype->isPublished());
        self::assertNull($archetype->getLocalizedDescription('en'));
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
        self::assertContains('Combo', $tags);
        self::assertContains('Lock', $tags);
        self::assertContains('Toolbox', $tags);
    }

    /**
     * @see docs/features.md F2.15 — Archetype playstyle tags
     */
    public function testPlaystyleTagsDisplayedOnDetailPage(): void
    {
        $this->client->request('GET', '/archetypes/regidrago');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.badge.bg-secondary');
        self::assertSelectorTextContains('.badge.bg-secondary', 'Combo');
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
        $form['archetype_form[playstyleTags]'] = '["aggressive","control"]';
        $this->client->submit($form);

        self::assertResponseRedirects();

        $refreshed = $this->getArchetype('Lugia Archeops');
        self::assertContains('Aggressive', $refreshed->getPlaystyleTags());
        self::assertContains('Control', $refreshed->getPlaystyleTags());
    }

    // ---------------------------------------------------------------
    // Archetype variant management (F18.15)
    // ---------------------------------------------------------------

    /**
     * @see docs/features.md F18.15 — Admin archetype variant management
     */
    public function testEditPageDisplaysVariantsSection(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $this->client->request('GET', '/admin/archetypes/'.$archetype->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href*="variants/new"]');
    }

    /**
     * @see docs/features.md F18.15 — Admin archetype variant management
     */
    public function testEditPageListsExistingVariants(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetype->getId());

        self::assertResponseIsSuccessful();

        // Fixture creates 3 Regidrago variants
        $variantRows = $crawler->filter('table tbody tr');
        self::assertGreaterThanOrEqual(3, $variantRows->count());
    }

    /**
     * @see docs/features.md F18.15 — Admin archetype variant management
     */
    public function testNewVariantFormAccessible(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $this->client->request('GET', '/admin/archetypes/'.$archetype->getId().'/variants/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="archetype_variant_form"]');
    }

    /**
     * @see docs/features.md F18.15 — Admin archetype variant management
     */
    public function testCreateVariant(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetype->getId().'/variants/new');

        $form = $crawler->selectButton('Save')->form();
        $form['archetype_variant_form[name]'] = 'Test Variant';
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    /**
     * @see docs/features.md F18.15 — Admin archetype variant management
     */
    public function testEditVariantFormAccessible(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $variant = $this->getVariantByName('Regidrago', $archetype);

        $this->client->request('GET', '/admin/archetypes/'.$archetype->getId().'/variants/'.$variant->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="archetype_variant_form"]');
    }

    /**
     * @see docs/features.md F18.15 — Admin archetype variant management
     */
    public function testEditVariantUpdates(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $variant = $this->getVariantByName('Regidrago', $archetype);

        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetype->getId().'/variants/'.$variant->getId());

        $form = $crawler->selectButton('Save')->form();
        $form['archetype_variant_form[name]'] = 'Updated Regidrago';
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    /**
     * @see docs/features.md F18.15 — Admin archetype variant management
     */
    public function testDeleteVariant(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $variant = $this->getVariantByName('Third Regidrago', $archetype);

        // Load the edit page first to get a valid session for CSRF
        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetype->getId());

        // Find the delete form for this variant and submit it
        $deleteForm = $crawler->filter('form[action*="variants/'.$variant->getId().'/delete"]');
        self::assertGreaterThan(0, $deleteForm->count(), 'Delete form for variant should exist.');

        $form = $deleteForm->form();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    /**
     * @see docs/features.md F18.15 — Admin archetype variant management
     */
    public function testVariantRequiresAdmin(): void
    {
        $this->loginAs('borrower@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $this->client->request('GET', '/admin/archetypes/'.$archetype->getId().'/variants/new');

        self::assertResponseStatusCodeSame(403);
    }

    private function getArchetype(string $name): Archetype
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        /** @var Archetype $archetype */
        $archetype = $em->getRepository(Archetype::class)->findOneBy(['name' => $name]);

        return $archetype;
    }

    // ---------------------------------------------------------------
    // Reorder endpoints (F18.12, F18.19)
    // ---------------------------------------------------------------

    /**
     * @see docs/features.md F18.12 — Admin drag-and-drop archetype ordering
     */
    public function testReorderArchetypes(): void
    {
        $this->loginAs('admin@example.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $archetypes = $entityManager->getRepository(Archetype::class)->findBy(['deletedAt' => null], ['position' => 'ASC']);

        // Reverse the order
        $ids = array_map(static fn (Archetype $archetype): int => (int) $archetype->getId(), $archetypes);
        $reversed = array_reverse($ids);

        $this->client->request('POST', '/admin/archetypes/reorder', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode($reversed));

        self::assertResponseIsSuccessful();

        /** @var string $content */
        $content = $this->client->getResponse()->getContent();
        /** @var array{ok: bool} $data */
        $data = json_decode($content, true);
        self::assertTrue($data['ok']);

        // Verify first archetype now has position 0
        $entityManager->clear();
        $first = $entityManager->getRepository(Archetype::class)->find($reversed[0]);
        self::assertNotNull($first);
        self::assertSame(0, $first->getPosition());
    }

    /**
     * @see docs/features.md F18.19 — Archetype variant ordering
     */
    public function testReorderVariants(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');

        /** @var DeckRepository $deckRepository */
        $deckRepository = static::getContainer()->get(DeckRepository::class);
        $variants = $deckRepository->findVariantsByArchetype($archetype);
        self::assertGreaterThanOrEqual(2, \count($variants));

        // Reverse the order
        $ids = array_map(static fn (Deck $deck): int => (int) $deck->getId(), $variants);
        $reversed = array_reverse($ids);

        $this->client->request('POST', '/admin/archetypes/'.$archetype->getId().'/variants/reorder', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode($reversed));

        self::assertResponseIsSuccessful();

        /** @var string $content */
        $content = $this->client->getResponse()->getContent();
        /** @var array{ok: bool} $data */
        $data = json_decode($content, true);
        self::assertTrue($data['ok']);
    }

    /**
     * @see docs/features.md F18.19 — Archetype variant ordering
     */
    public function testReorderVariantsKeepsCanonicalAtPositionZero(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');

        /** @var DeckRepository $deckRepository */
        $deckRepository = static::getContainer()->get(DeckRepository::class);
        $variants = $deckRepository->findVariantsByArchetype($archetype);

        // Send reversed order
        $ids = array_map(static fn (Deck $deck): int => (int) $deck->getId(), $variants);
        $reversed = array_reverse($ids);

        $this->client->request('POST', '/admin/archetypes/'.$archetype->getId().'/variants/reorder', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode($reversed));

        self::assertResponseIsSuccessful();

        // Verify canonical variant has position 0
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->clear();

        $refreshedVariants = $deckRepository->findVariantsByArchetype($archetype);
        $canonical = null;
        foreach ($refreshedVariants as $variant) {
            if ($variant->isCanonical()) {
                $canonical = $variant;

                break;
            }
        }

        self::assertNotNull($canonical);
        self::assertSame(0, $canonical->getPosition());
    }

    /**
     * @see docs/features.md F18.12 — Admin drag-and-drop archetype ordering
     */
    public function testReorderRequiresAdmin(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('POST', '/admin/archetypes/reorder', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '[1,2,3]');

        self::assertResponseStatusCodeSame(403);
    }

    // ---------------------------------------------------------------
    // Expansion set boundary & outdated variant (F2.24)
    // ---------------------------------------------------------------

    /**
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function testOutdatedToggleSetsStatus(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $variant = $this->getVariantByName('Regidrago', $archetype);

        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetype->getId().'/variants/'.$variant->getId());

        $form = $crawler->selectButton('Save')->form();
        $form['archetype_variant_form[outdated]']->tick();
        $this->client->submit($form);

        self::assertResponseRedirects();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();

        $refreshed = $this->getVariantByName('Regidrago', $archetype);
        self::assertSame(DeckStatus::Outdated, $refreshed->getStatus());
    }

    /**
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function testUntickOutdatedRevertsToAvailable(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $variant = $this->getVariantByName('Regidrago', $archetype);

        // First set outdated
        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetype->getId().'/variants/'.$variant->getId());
        $form = $crawler->selectButton('Save')->form();
        $form['archetype_variant_form[outdated]']->tick();
        $this->client->submit($form);

        // Now untick it
        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetype->getId().'/variants/'.$variant->getId());
        $form = $crawler->selectButton('Save')->form();
        $form['archetype_variant_form[outdated]']->untick();
        $this->client->submit($form);

        self::assertResponseRedirects();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();

        $refreshed = $this->getVariantByName('Regidrago', $archetype);
        self::assertSame(DeckStatus::Available, $refreshed->getStatus());
    }

    /**
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function testDuplicateVariantCreatesNewDeck(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $variant = $this->getVariantByName('Regidrago', $archetype);

        // Load the edit page to get a valid CSRF session
        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetype->getId());

        $duplicateForm = $crawler->filter('form[action*="variants/'.$variant->getId().'/duplicate"]');
        self::assertGreaterThan(0, $duplicateForm->count(), 'Duplicate form for variant should exist.');

        $form = $duplicateForm->form();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');

        // Verify the copy exists
        /** @var DeckRepository $deckRepository */
        $deckRepository = static::getContainer()->get(DeckRepository::class);
        $variants = $deckRepository->findVariantsByArchetype($archetype);
        $copyNames = array_map(static fn (Deck $deck): string => $deck->getName(), $variants);
        self::assertContains('Copy of Regidrago', $copyNames);
    }

    /**
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function testDuplicateVariantWithInvalidCsrfFails(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $variant = $this->getVariantByName('Regidrago', $archetype);

        $this->client->request('POST', '/admin/archetypes/'.$archetype->getId().'/variants/'.$variant->getId().'/duplicate', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function testReenrichVariantRedirectsOnSuccess(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $variant = $this->getVariantByName('Regidrago', $archetype);

        // Load the edit page to establish session + get CSRF
        $crawler = $this->client->request('GET', '/admin/archetypes/'.$archetype->getId().'/variants/'.$variant->getId());

        $reenrichForm = $crawler->filter('form[action*="reenrich"]');
        self::assertGreaterThan(0, $reenrichForm->count(), 'Re-enrich form should exist for technical admin.');

        $form = $reenrichForm->form();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');
    }

    /**
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function testVariantFormShowsLatestSetField(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $this->client->request('GET', '/admin/archetypes/'.$archetype->getId().'/variants/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="archetype_variant_form[latestSet]"]');
    }

    /**
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function testVariantFormShowsOutdatedCheckbox(): void
    {
        $this->loginAs('admin@example.com');

        $archetype = $this->getArchetype('Regidrago');
        $this->client->request('GET', '/admin/archetypes/'.$archetype->getId().'/variants/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="archetype_variant_form[outdated]"]');
    }

    private function getVariantByName(string $name, Archetype $archetype): Deck
    {
        /** @var DeckRepository $deckRepository */
        $deckRepository = static::getContainer()->get(DeckRepository::class);

        $variants = $deckRepository->findVariantsByArchetype($archetype);

        foreach ($variants as $variant) {
            if ($variant->getName() === $name) {
                return $variant;
            }
        }

        self::fail(\sprintf('Variant "%s" not found for archetype "%s".', $name, $archetype->getName()));
    }
}
