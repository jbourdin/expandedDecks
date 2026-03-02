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
final class Version20260302124410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create event_deck_registration table for per-deck-per-event staff delegation (F4.8)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_deck_registration (id INT AUTO_INCREMENT NOT NULL, delegate_to_staff TINYINT NOT NULL, registered_at DATETIME NOT NULL, event_id INT NOT NULL, deck_id INT NOT NULL, INDEX IDX_B8A2935E71F7E88B (event_id), INDEX IDX_B8A2935E111948DC (deck_id), UNIQUE INDEX uniq_event_deck_registration (event_id, deck_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE event_deck_registration ADD CONSTRAINT FK_B8A2935E71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event_deck_registration ADD CONSTRAINT FK_B8A2935E111948DC FOREIGN KEY (deck_id) REFERENCES deck (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_deck_registration DROP FOREIGN KEY FK_B8A2935E71F7E88B');
        $this->addSql('ALTER TABLE event_deck_registration DROP FOREIGN KEY FK_B8A2935E111948DC');
        $this->addSql('DROP TABLE event_deck_registration');
    }
}
