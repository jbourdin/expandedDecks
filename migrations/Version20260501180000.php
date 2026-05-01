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
 * Add pending-transfer columns to event for the two-step organizer handover.
 *
 * @see docs/features.md F3.23 — Organizer handover
 */
final class Version20260501180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event.pending_transfer_to_id + pending_transfer_requested_at for organizer handover';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD pending_transfer_to_id INT DEFAULT NULL, ADD pending_transfer_requested_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_event_pending_transfer_to FOREIGN KEY (pending_transfer_to_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_event_pending_transfer_to ON event (pending_transfer_to_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_event_pending_transfer_to');
        $this->addSql('DROP INDEX IDX_event_pending_transfer_to ON event');
        $this->addSql('ALTER TABLE event DROP pending_transfer_to_id, DROP pending_transfer_requested_at');
    }
}
