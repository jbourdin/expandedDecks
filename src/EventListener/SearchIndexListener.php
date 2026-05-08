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

namespace App\EventListener;

use App\Constants\ListingIntroPage;
use App\Entity\Archetype;
use App\Entity\ArchetypeTranslation;
use App\Entity\BannedCard;
use App\Entity\BannedCardPrinting;
use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\StapleCard;
use App\Entity\StapleCardPrinting;
use App\Repository\PageRepository;
use App\Service\Search\SearchIndexer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * Keeps MeiliSearch indexes in sync with Doctrine entity changes.
 *
 * Listens to postPersist, postUpdate, and postRemove events on the four
 * searchable content types. Translation entity changes are propagated
 * through the parent entity.
 *
 * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class SearchIndexListener
{
    public function __construct(
        private readonly SearchIndexer $searchIndexer,
        private readonly PageRepository $pageRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->handleEntity($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->handleEntity($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Archetype) {
            $this->searchIndexer->removeArchetype($entity);
        } elseif ($entity instanceof Page) {
            $this->searchIndexer->removePage($entity);
        } elseif ($entity instanceof Event) {
            $this->searchIndexer->removeEvent($entity);
        } elseif ($entity instanceof Deck) {
            if (null === $entity->getOwner()) {
                $this->searchIndexer->removeVariant($entity);
            } else {
                $this->searchIndexer->removeDeck($entity);
            }
        } elseif ($entity instanceof BannedCard || $entity instanceof BannedCardPrinting) {
            $this->refreshListingIntroPage(ListingIntroPage::BANNED_CARDS_SLUG);
        } elseif ($entity instanceof StapleCard || $entity instanceof StapleCardPrinting) {
            $this->refreshListingIntroPage(ListingIntroPage::STAPLE_CARDS_SLUG);
        }
    }

    private function handleEntity(object $entity): void
    {
        try {
            if ($entity instanceof Archetype) {
                $this->searchIndexer->indexArchetype($entity);
            } elseif ($entity instanceof Page) {
                $this->searchIndexer->indexPage($entity);
            } elseif ($entity instanceof Event) {
                $this->searchIndexer->indexEvent($entity);
            } elseif ($entity instanceof Deck) {
                if (null === $entity->getOwner()) {
                    $this->searchIndexer->indexVariant($entity);
                } else {
                    $this->searchIndexer->indexDeck($entity);
                }
            } elseif ($entity instanceof ArchetypeTranslation) {
                $this->searchIndexer->indexArchetype($entity->getArchetype());
            } elseif ($entity instanceof PageTranslation) {
                $this->searchIndexer->indexPage($entity->getPage());
            } elseif ($entity instanceof BannedCard || $entity instanceof BannedCardPrinting) {
                $this->refreshListingIntroPage(ListingIntroPage::BANNED_CARDS_SLUG);
            } elseif ($entity instanceof StapleCard || $entity instanceof StapleCardPrinting) {
                $this->refreshListingIntroPage(ListingIntroPage::STAPLE_CARDS_SLUG);
            }
        } catch (\Throwable $exception) {
            // Search index sync should never break the main application flow
            $this->logger->warning('Search index sync failed: {error}', [
                'error' => $exception->getMessage(),
                'entity' => $entity::class,
            ]);
        }
    }

    /**
     * Refresh the augmented Meilisearch document for a listing intro page.
     *
     * Banned/staple card lifecycle changes don't index a card directly — they
     * update the parent listing page's indexed content (which carries every
     * card name on that page). One re-index per affected channel-scoped page.
     */
    private function refreshListingIntroPage(string $slug): void
    {
        try {
            foreach ($this->pageRepository->findAllBySlug($slug) as $page) {
                $this->searchIndexer->indexPage($page);
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to refresh listing intro page {slug}: {error}', [
                'slug' => $slug,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
