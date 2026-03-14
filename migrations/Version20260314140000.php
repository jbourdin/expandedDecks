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
 * Create sessions table for database-backed session storage (F14.2).
 */
final class Version20260314140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sessions table for PdoSessionHandler (F14.2)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sessions (
                sess_id VARCHAR(128) NOT NULL,
                sess_data BLOB NOT NULL,
                sess_lifetime INT UNSIGNED NOT NULL,
                sess_time INT UNSIGNED NOT NULL,
                INDEX sessions_sess_lifetime_idx (sess_lifetime),
                PRIMARY KEY(sess_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sessions');
    }
}
