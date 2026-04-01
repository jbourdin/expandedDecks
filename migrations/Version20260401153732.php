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
final class Version20260401153732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_footer flag to menu_category table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_category ADD is_footer TINYINT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_category DROP is_footer');
    }
}
