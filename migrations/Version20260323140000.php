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

final class Version20260323140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tcgdex_set_mapping table for persistent set ID ↔ PTCG code mappings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE tcgdex_set_mapping (
                tcgdex_set_id VARCHAR(32) NOT NULL,
                ptcg_code VARCHAR(16) NOT NULL,
                PRIMARY KEY (tcgdex_set_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tcgdex_set_mapping');
    }
}
