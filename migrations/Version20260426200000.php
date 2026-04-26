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
 * Convert deck.format from free-text string to DeckFormat enum-backed column.
 *
 * Normalizes existing 'Expanded' values to lowercase 'expanded' to match
 * the enum backing values.
 *
 * @see docs/features.md F2.23 — Standard format personal decks
 */
final class Version20260426200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert deck.format to enum-backed column (expanded/standard)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE deck SET format = 'expanded' WHERE format = 'Expanded'");
        $this->addSql("UPDATE deck SET format = 'expanded' WHERE format NOT IN ('expanded', 'standard')");
        $this->addSql("ALTER TABLE deck CHANGE format format VARCHAR(20) NOT NULL DEFAULT 'expanded'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE deck SET format = 'Expanded' WHERE format = 'expanded'");
        $this->addSql("UPDATE deck SET format = 'Standard' WHERE format = 'standard'");
        $this->addSql("ALTER TABLE deck CHANGE format format VARCHAR(30) NOT NULL DEFAULT 'Expanded'");
    }
}
