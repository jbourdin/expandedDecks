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
 * Heals persisted basic-energy image URLs so every basic-energy printing displays the
 * same homogeneous TCGdex sm1 art (cards 164–172) used by the new
 * CardEnricher::BASIC_ENERGY_IMAGES fallback.
 *
 * Two complementary passes:
 *
 *   1. Synthetic-fallback rows (tcgdex_id like 'energy-…') keep their old pre-PR
 *      pokemon.com/MEE_EN_*.png and pokemontcg.io/sm1/172_hires.png URLs forever
 *      because image_url is written once at enrichment time. Remap by exact URL.
 *
 *   2. Real TCGdex printings tagged with a PTCG Live energy-only set code
 *      (MEE/SVE/SME/XYE/BWE — the same list as CardEnricher::ENERGY_SET_CODES) may
 *      hold any of: legacy SVE_EN_*.png from pokemon.com, MEE_EN_*.png from
 *      pokemon.com, the 404-prone assets.tcgdex.net/en/me/mee/{N}/high.webp, or the
 *      pokemontcg.io sm1/172_hires fairy URL. They're all basic-energy fallbacks by
 *      definition (since TCGdex doesn't host artwork for these PTCG Live sets) so we
 *      JOIN to card_identity, identify them by their canonical name, and remap each
 *      to the corresponding sm1-164…sm1-172 TCGdex URL.
 *
 * The broader pass cannot be cleanly reversed (original URLs varied per row and
 * weren't recorded). down() only reverses the narrow synthetic-fallback URLs and
 * leaves the broader sweep in place — that's a deliberate one-way data heal.
 *
 * @see docs/features.md F6.9 — Improved energy card enrichment
 * @see docs/technicalities/basic_energy_images.md
 */
final class Version20260528230443 extends AbstractMigration
{
    /** @var array<string, array{0: string, 1: string}> english energy name → [pre-PR fallback url, new sm1 tcgdex url] */
    private const array URL_REMAP = [
        'Grass Energy' => [
            'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png',
            'https://assets.tcgdex.net/en/sm/sm1/164/high.webp',
        ],
        'Fire Energy' => [
            'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png',
            'https://assets.tcgdex.net/en/sm/sm1/165/high.webp',
        ],
        'Water Energy' => [
            'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png',
            'https://assets.tcgdex.net/en/sm/sm1/166/high.webp',
        ],
        'Lightning Energy' => [
            'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png',
            'https://assets.tcgdex.net/en/sm/sm1/167/high.webp',
        ],
        'Psychic Energy' => [
            'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png',
            'https://assets.tcgdex.net/en/sm/sm1/168/high.webp',
        ],
        'Fighting Energy' => [
            'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png',
            'https://assets.tcgdex.net/en/sm/sm1/169/high.webp',
        ],
        'Darkness Energy' => [
            'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png',
            'https://assets.tcgdex.net/en/sm/sm1/170/high.webp',
        ],
        'Metal Energy' => [
            'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png',
            'https://assets.tcgdex.net/en/sm/sm1/171/high.webp',
        ],
        'Fairy Energy' => [
            'https://images.pokemontcg.io/sm1/172_hires.png',
            'https://assets.tcgdex.net/en/sm/sm1/172/high.webp',
        ],
    ];

    public function getDescription(): string
    {
        return 'Heal persisted basic-energy image URLs to the new TCGdex sm1 (164–172) homogeneous fallback for synthetic and energy-only-set printings.';
    }

    public function up(Schema $schema): void
    {
        // 1. Synthetic-fallback rows: match by exact pre-PR URL.
        foreach (self::URL_REMAP as [$oldUrl, $newUrl]) {
            $this->addSql(
                'UPDATE card_printing SET image_url = :newUrl WHERE image_url = :oldUrl',
                ['oldUrl' => $oldUrl, 'newUrl' => $newUrl],
            );
        }

        // 2. Real TCGdex printings under PTCG-Live energy-only set codes:
        //    identify them by their canonical card_identity.name and remap to sm1 by color.
        $this->addSql(<<<'SQL'
            UPDATE card_printing cp
            JOIN card_identity ci ON ci.id = cp.card_identity_id
            SET cp.image_url = CASE ci.name
                WHEN 'Grass Energy'     THEN 'https://assets.tcgdex.net/en/sm/sm1/164/high.webp'
                WHEN 'Fire Energy'      THEN 'https://assets.tcgdex.net/en/sm/sm1/165/high.webp'
                WHEN 'Water Energy'     THEN 'https://assets.tcgdex.net/en/sm/sm1/166/high.webp'
                WHEN 'Lightning Energy' THEN 'https://assets.tcgdex.net/en/sm/sm1/167/high.webp'
                WHEN 'Psychic Energy'   THEN 'https://assets.tcgdex.net/en/sm/sm1/168/high.webp'
                WHEN 'Fighting Energy'  THEN 'https://assets.tcgdex.net/en/sm/sm1/169/high.webp'
                WHEN 'Darkness Energy'  THEN 'https://assets.tcgdex.net/en/sm/sm1/170/high.webp'
                WHEN 'Metal Energy'     THEN 'https://assets.tcgdex.net/en/sm/sm1/171/high.webp'
                WHEN 'Fairy Energy'     THEN 'https://assets.tcgdex.net/en/sm/sm1/172/high.webp'
            END
            WHERE cp.set_code IN ('MEE', 'SVE', 'SME', 'XYE', 'BWE')
              AND ci.name IN (
                'Grass Energy', 'Fire Energy', 'Water Energy', 'Lightning Energy',
                'Psychic Energy', 'Fighting Energy', 'Darkness Energy', 'Metal Energy',
                'Fairy Energy'
              )
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Only the synthetic-fallback rows can be reversed safely (their pre-PR URL is
        // known from URL_REMAP). The broader energy-only-set sweep is one-way: the
        // original image URLs were heterogeneous and weren't recorded, so down() leaves
        // those rows on the sm1 URL. Gate the narrow reverse on tcgdex_id LIKE 'energy-%'
        // so a legitimate sm1-164…sm1-172 organic enrichment isn't incorrectly flipped
        // back to the MEE/pokemontcg.io URL.
        foreach (self::URL_REMAP as [$oldUrl, $newUrl]) {
            $this->addSql(
                "UPDATE card_printing SET image_url = :oldUrl WHERE image_url = :newUrl AND tcgdex_id LIKE 'energy-%'",
                ['oldUrl' => $oldUrl, 'newUrl' => $newUrl],
            );
        }
    }
}
