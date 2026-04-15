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
 * Add latest_set_id foreign key on deck table.
 *
 * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
 */
final class Version20260415203227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add latest_set_id (ManyToOne → tcgdex_set) on deck table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck ADD latest_set_id VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE deck ADD CONSTRAINT FK_4FAC3637C46FBE9A FOREIGN KEY (latest_set_id) REFERENCES tcgdex_set (id)');
        $this->addSql('CREATE INDEX IDX_4FAC3637C46FBE9A ON deck (latest_set_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck DROP FOREIGN KEY FK_4FAC3637C46FBE9A');
        $this->addSql('DROP INDEX IDX_4FAC3637C46FBE9A ON deck');
        $this->addSql('ALTER TABLE deck DROP latest_set_id');
    }
}
