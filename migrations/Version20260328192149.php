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

final class Version20260328192149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ending_phase_at column to event table (F4.6 — Overdue tracking)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD ending_phase_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP ending_phase_at');
    }
}
