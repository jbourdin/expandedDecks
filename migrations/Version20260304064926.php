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
 * @see docs/features.md F4.14 — Staff custody handover tracking
 */
final class Version20260304064926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add staff custody handover fields to event_deck_registration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_deck_registration ADD staff_received_at DATETIME DEFAULT NULL, ADD staff_returned_at DATETIME DEFAULT NULL, ADD staff_received_by_id INT DEFAULT NULL, ADD staff_returned_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event_deck_registration ADD CONSTRAINT FK_B8A2935E2CA7D800 FOREIGN KEY (staff_received_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE event_deck_registration ADD CONSTRAINT FK_B8A2935E328782CE FOREIGN KEY (staff_returned_by_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_B8A2935E2CA7D800 ON event_deck_registration (staff_received_by_id)');
        $this->addSql('CREATE INDEX IDX_B8A2935E328782CE ON event_deck_registration (staff_returned_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_deck_registration DROP FOREIGN KEY FK_B8A2935E2CA7D800');
        $this->addSql('ALTER TABLE event_deck_registration DROP FOREIGN KEY FK_B8A2935E328782CE');
        $this->addSql('DROP INDEX IDX_B8A2935E2CA7D800 ON event_deck_registration');
        $this->addSql('DROP INDEX IDX_B8A2935E328782CE ON event_deck_registration');
        $this->addSql('ALTER TABLE event_deck_registration DROP staff_received_at, DROP staff_returned_at, DROP staff_received_by_id, DROP staff_returned_by_id');
    }
}
