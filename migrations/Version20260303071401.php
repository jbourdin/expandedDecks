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
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260303071401 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add public flag to deck table for catalog visibility';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deck ADD public TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE deck RENAME INDEX idx_4fac363748c921a8 TO IDX_4FAC3637732C6CC7');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deck DROP public');
        $this->addSql('ALTER TABLE deck RENAME INDEX idx_4fac3637732c6cc7 TO IDX_4FAC363748C921A8');
    }
}
