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

final class Version20260406104258 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tcgdex_asian_set_alias table and seed with Japanese/legacy set code mappings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tcgdex_asian_set_alias (alias_code VARCHAR(20) NOT NULL, tcgdex_set_id VARCHAR(20) NOT NULL, PRIMARY KEY (alias_code)) DEFAULT CHARACTER SET utf8mb4');

        // Seed: Japanese → international set mappings
        // Source: https://bulbapedia.bulbagarden.net/wiki/List_of_Japanese_Pokémon_Trading_Card_Game_expansions

        // --- XY era ---
        $this->addSql("INSERT INTO tcgdex_asian_set_alias (alias_code, tcgdex_set_id) VALUES
            ('XY1', 'xy1'),
            ('XY2', 'xy2'),
            ('XY3', 'xy3'),
            ('XY4', 'xy4'),
            ('XY5', 'xy5'),
            ('XY6', 'xy6'),
            ('XY7', 'xy7'),
            ('XY8', 'xy8'),
            ('XY9', 'xy9'),
            ('XY10', 'xy10'),
            ('XY11', 'xy11'),
            ('XY12', 'xy12')
        ");

        // --- Sun & Moon era ---
        $this->addSql("INSERT INTO tcgdex_asian_set_alias (alias_code, tcgdex_set_id) VALUES
            ('SM1', 'sm1'),
            ('SM1A', 'sm2'),
            ('SM2', 'sm2'),
            ('SM2L', 'sm2'),
            ('SM2K', 'sm2'),
            ('SM3', 'sm3'),
            ('SM3H', 'sm3'),
            ('SM3N', 'sm3'),
            ('SM3A', 'sm3.5'),
            ('SM4', 'sm4'),
            ('SM4A', 'sm5'),
            ('SM4S', 'sm4'),
            ('SM5', 'sm5'),
            ('SM5M', 'sm5'),
            ('SM5S', 'sm5'),
            ('SM6', 'sm6'),
            ('SM6A', 'sm7.5'),
            ('SM6B', 'sm6'),
            ('SM7', 'sm7'),
            ('SM7A', 'sm7'),
            ('SM7B', 'sm7'),
            ('SM8', 'sm8'),
            ('SM8A', 'sm8'),
            ('SM8B', 'sm115'),
            ('SM9', 'sm9'),
            ('SM9A', 'sm9'),
            ('SM9B', 'sm10'),
            ('SM10', 'sm10'),
            ('SM10A', 'sm11'),
            ('SM10B', 'sm115'),
            ('SM11', 'sm11'),
            ('SM11A', 'sm11'),
            ('SM11B', 'sm12'),
            ('SM12', 'sm12'),
            ('SM12A', 'sm12')
        ");

        // --- Sword & Shield era (S series) ---
        $this->addSql("INSERT INTO tcgdex_asian_set_alias (alias_code, tcgdex_set_id) VALUES
            ('S1W', 'swsh1'),
            ('S1H', 'swsh1'),
            ('S1A', 'swsh2'),
            ('S2', 'swsh2'),
            ('S2A', 'swsh2'),
            ('S3', 'swsh3'),
            ('S3A', 'swsh3'),
            ('S4', 'swsh4'),
            ('S4A', 'swsh4.5'),
            ('S5I', 'swsh5'),
            ('S5R', 'swsh5'),
            ('S5A', 'swsh5'),
            ('S6H', 'swsh6'),
            ('S6K', 'swsh6'),
            ('S6A', 'swsh6'),
            ('S7D', 'swsh7'),
            ('S7R', 'swsh7'),
            ('S8', 'swsh8'),
            ('S8A', 'cel25'),
            ('S8B', 'swsh8'),
            ('S9', 'swsh9'),
            ('S9A', 'swsh9'),
            ('S10', 'swsh10'),
            ('S10A', 'swsh10'),
            ('S10B', 'swsh10'),
            ('S10D', 'swsh10.5'),
            ('S10P', 'swsh10.5'),
            ('S11', 'swsh11'),
            ('S11A', 'swsh12'),
            ('S12', 'swsh12'),
            ('S12A', 'swsh12.5')
        ");

        // --- Scarlet & Violet era (SV series) ---
        $this->addSql("INSERT INTO tcgdex_asian_set_alias (alias_code, tcgdex_set_id) VALUES
            ('SV1S', 'sv01'),
            ('SV1V', 'sv01'),
            ('SV1A', 'sv02'),
            ('SV2D', 'sv02'),
            ('SV2P', 'sv02'),
            ('SV2A', 'sv03.5'),
            ('SV3', 'sv03'),
            ('SV3A', 'sv03'),
            ('SV3S', 'sv03'),
            ('SV4K', 'sv04'),
            ('SV4M', 'sv04'),
            ('SV4S', 'sv04'),
            ('SV4A', 'sv04.5'),
            ('SV5K', 'sv05'),
            ('SV5M', 'sv05'),
            ('SV5S', 'sv05'),
            ('SV5A', 'sv05'),
            ('SV6', 'sv06'),
            ('SV6A', 'sv06.5'),
            ('SV6S', 'sv06'),
            ('SV7', 'sv07'),
            ('SV7A', 'sv07'),
            ('SV7S', 'sv07'),
            ('SV8', 'sv08'),
            ('SV8A', 'sv08.5'),
            ('SV8S', 'sv08'),
            ('SV9', 'sv09'),
            ('SV9A', 'sv09'),
            ('SV9S', 'sv09'),
            ('SV10', 'sv10')
        ");

        // --- Black & White era ---
        $this->addSql("INSERT INTO tcgdex_asian_set_alias (alias_code, tcgdex_set_id) VALUES
            ('BW1', 'bw1'),
            ('BW2', 'bw2'),
            ('BW3', 'bw3'),
            ('BW4', 'bw4'),
            ('BW5', 'bw5'),
            ('BW6', 'bw6'),
            ('BW7', 'bw7'),
            ('BW8', 'bw8'),
            ('BW9', 'bw9'),
            ('BW10', 'bw10'),
            ('BW11', 'bw11')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tcgdex_asian_set_alias');
    }
}
