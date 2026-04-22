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
 * Add image/logo URL fields to TCGdex entities for API-based sync.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
final class Version20260422100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add logoUrl to tcgdex_serie, logoUrl + symbolUrl to tcgdex_set, imageBaseUrl to tcgdex_card';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tcgdex_serie ADD logo_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE tcgdex_set ADD logo_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE tcgdex_set ADD symbol_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE tcgdex_card ADD image_base_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tcgdex_serie DROP logo_url');
        $this->addSql('ALTER TABLE tcgdex_set DROP logo_url');
        $this->addSql('ALTER TABLE tcgdex_set DROP symbol_url');
        $this->addSql('ALTER TABLE tcgdex_card DROP image_base_url');
    }
}
