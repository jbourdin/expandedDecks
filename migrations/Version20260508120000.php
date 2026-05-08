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

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rewrite existing homepage `latestPages` blocks from `categorySlug` (free-text
 * category name) to `categoryId` (stable FK).
 *
 * Issue #536 replaces the free-form category text input with a channel-scoped
 * dropdown that stores the category ID. Existing blocks in the
 * `homepage_layout.blocks` JSON column have legacy `categorySlug` entries —
 * this migration walks every layout, looks up each slug as a case-insensitive
 * English-name match against `menu_category_translation` within the same
 * channel, and rewrites the block to use `categoryId` instead.
 *
 * `HomepageRenderer::resolveLatestPages` keeps a `categorySlug` fallback for
 * any block this migration couldn't resolve (e.g. a slug that doesn't match
 * any current category name) so render-time behavior is unchanged for those.
 *
 * @see https://github.com/jbourdin/expandedDecks/issues/536
 */
final class Version20260508120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '#536 — rewrite homepage latestPages blocks: categorySlug → categoryId';
    }

    public function up(Schema $schema): void
    {
        /** @var list<array{id: int, blocks: string, channel_id: int|null}> $rows */
        $rows = $this->connection->fetchAllAssociative('SELECT id, blocks, channel_id FROM homepage_layout');

        foreach ($rows as $row) {
            $blocks = json_decode((string) $row['blocks'], true);
            if (!\is_array($blocks)) {
                continue;
            }

            $modified = false;
            foreach ($blocks as $index => $block) {
                if (!\is_array($block) || ($block['type'] ?? null) !== 'latestPages') {
                    continue;
                }
                if (isset($block['categoryId'])) {
                    // Already migrated.
                    continue;
                }
                $slug = $block['categorySlug'] ?? null;
                if (!\is_string($slug) || '' === $slug) {
                    continue;
                }

                $categoryId = $this->lookupCategoryId($slug, $row['channel_id']);
                if (null === $categoryId) {
                    // Unmatched legacy slug — leave categorySlug in place; the
                    // renderer's fallback path will keep using it.
                    continue;
                }

                unset($blocks[$index]['categorySlug']);
                $blocks[$index]['categoryId'] = $categoryId;
                $modified = true;
            }

            if ($modified) {
                $this->connection->update(
                    'homepage_layout',
                    ['blocks' => json_encode($blocks, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)],
                    ['id' => $row['id']],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op: rolling back would require recovering the original
        // slug strings from the category names, which produces something
        // semantically equivalent but not byte-equal to the editor's input.
    }

    private function lookupCategoryId(string $name, ?int $channelId): ?int
    {
        if (null === $channelId) {
            $sql = 'SELECT mc.id
                    FROM menu_category mc
                    INNER JOIN menu_category_translation mct ON mct.menu_category_id = mc.id
                    WHERE mc.channel_id IS NULL
                      AND mct.locale = :locale
                      AND LOWER(mct.name) = LOWER(:name)
                    LIMIT 1';
            $params = ['locale' => 'en', 'name' => $name];
        } else {
            $sql = 'SELECT mc.id
                    FROM menu_category mc
                    INNER JOIN menu_category_translation mct ON mct.menu_category_id = mc.id
                    WHERE mc.channel_id = :channel_id
                      AND mct.locale = :locale
                      AND LOWER(mct.name) = LOWER(:name)
                    LIMIT 1';
            $params = ['channel_id' => $channelId, 'locale' => 'en', 'name' => $name];
        }

        $id = $this->connection->fetchOne($sql, $params);

        return false !== $id ? (int) $id : null;
    }
}
