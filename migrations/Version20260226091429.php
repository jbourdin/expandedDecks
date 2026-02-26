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

final class Version20260226091429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial schema: user, deck, deck_version, deck_card, league, event, event_staff, borrow, event_deck_entry, notification';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE borrow (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(30) NOT NULL, is_delegated_to_staff TINYINT NOT NULL, requested_at DATETIME NOT NULL, approved_at DATETIME DEFAULT NULL, handed_off_at DATETIME DEFAULT NULL, returned_at DATETIME DEFAULT NULL, returned_to_owner_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, deck_id INT NOT NULL, deck_version_id INT NOT NULL, borrower_id INT NOT NULL, event_id INT NOT NULL, approved_by_id INT DEFAULT NULL, handed_off_by_id INT DEFAULT NULL, returned_to_id INT DEFAULT NULL, cancelled_by_id INT DEFAULT NULL, INDEX IDX_55DBA8B0111948DC (deck_id), INDEX IDX_55DBA8B0BE66DA24 (deck_version_id), INDEX IDX_55DBA8B011CE312B (borrower_id), INDEX IDX_55DBA8B071F7E88B (event_id), INDEX IDX_55DBA8B02D234F6A (approved_by_id), INDEX IDX_55DBA8B020E67D3D (handed_off_by_id), INDEX IDX_55DBA8B0EB7FEDB8 (returned_to_id), INDEX IDX_55DBA8B0187B2D12 (cancelled_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE deck (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, format VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, owner_id INT NOT NULL, current_version_id INT DEFAULT NULL, INDEX IDX_4FAC36377E3C61F9 (owner_id), INDEX IDX_4FAC36379407EE77 (current_version_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE deck_card (id INT AUTO_INCREMENT NOT NULL, card_name VARCHAR(100) NOT NULL, set_code VARCHAR(20) NOT NULL, card_number VARCHAR(20) NOT NULL, quantity INT NOT NULL, card_type VARCHAR(20) NOT NULL, trainer_subtype VARCHAR(20) DEFAULT NULL, tcgdex_id VARCHAR(30) DEFAULT NULL, deck_version_id INT NOT NULL, INDEX IDX_2AF3DCEDBE66DA24 (deck_version_id), UNIQUE INDEX uniq_deck_card (deck_version_id, set_code, card_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE deck_version (id INT AUTO_INCREMENT NOT NULL, version_number INT NOT NULL, archetype VARCHAR(80) DEFAULT NULL, archetype_name VARCHAR(100) DEFAULT NULL, languages JSON NOT NULL, estimated_value_amount INT DEFAULT NULL, estimated_value_currency VARCHAR(3) DEFAULT NULL, raw_list LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, deck_id INT NOT NULL, INDEX IDX_3B92E6BF111948DC (deck_id), UNIQUE INDEX uniq_deck_version (deck_id, version_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, event_id VARCHAR(50) DEFAULT NULL, format VARCHAR(30) NOT NULL, date DATETIME NOT NULL, end_date DATETIME DEFAULT NULL, timezone VARCHAR(50) NOT NULL, location VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, registration_link VARCHAR(255) NOT NULL, tournament_structure VARCHAR(30) DEFAULT NULL, min_attendees INT DEFAULT NULL, max_attendees INT DEFAULT NULL, round_duration INT DEFAULT NULL, top_cut_round_duration INT DEFAULT NULL, entry_fee_amount INT DEFAULT NULL, entry_fee_currency VARCHAR(3) DEFAULT NULL, is_decklist_mandatory TINYINT NOT NULL, created_at DATETIME NOT NULL, cancelled_at DATETIME DEFAULT NULL, organizer_id INT NOT NULL, league_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_3BAE0AA771F7E88B (event_id), INDEX IDX_3BAE0AA7876C4DDA (organizer_id), INDEX IDX_3BAE0AA758AFC4DE (league_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event_participant (event_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_7C16B89171F7E88B (event_id), INDEX IDX_7C16B891A76ED395 (user_id), PRIMARY KEY (event_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event_deck_entry (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, event_id INT NOT NULL, player_id INT NOT NULL, deck_version_id INT NOT NULL, INDEX IDX_B292BEB071F7E88B (event_id), INDEX IDX_B292BEB099E6F5DF (player_id), INDEX IDX_B292BEB0BE66DA24 (deck_version_id), UNIQUE INDEX uniq_event_deck_entry (event_id, player_id, deck_version_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event_staff (id INT AUTO_INCREMENT NOT NULL, assigned_at DATETIME NOT NULL, event_id INT NOT NULL, user_id INT NOT NULL, assigned_by_id INT NOT NULL, INDEX IDX_37542BE71F7E88B (event_id), INDEX IDX_37542BEA76ED395 (user_id), INDEX IDX_37542BE6E6F1246 (assigned_by_id), UNIQUE INDEX uniq_event_staff (event_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE league (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, website VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, contact_details JSON DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, context JSON DEFAULT NULL, is_read TINYINT NOT NULL, read_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, recipient_id INT NOT NULL, INDEX IDX_BF5476CAE92F8F78 (recipient_id), INDEX idx_notification_recipient_read (recipient_id, is_read, created_at), INDEX idx_notification_recipient_date (recipient_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, screen_name VARCHAR(50) NOT NULL, player_id VARCHAR(30) DEFAULT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, is_verified TINYINT NOT NULL, verification_token VARCHAR(64) DEFAULT NULL, token_expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, reset_token VARCHAR(64) DEFAULT NULL, reset_token_expires_at DATETIME DEFAULT NULL, preferred_locale VARCHAR(5) NOT NULL, timezone VARCHAR(50) NOT NULL, deleted_at DATETIME DEFAULT NULL, is_anonymized TINYINT NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D6495E9D8B89 (screen_name), UNIQUE INDEX UNIQ_8D93D64999E6F5DF (player_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE borrow ADD CONSTRAINT FK_55DBA8B0111948DC FOREIGN KEY (deck_id) REFERENCES deck (id)');
        $this->addSql('ALTER TABLE borrow ADD CONSTRAINT FK_55DBA8B0BE66DA24 FOREIGN KEY (deck_version_id) REFERENCES deck_version (id)');
        $this->addSql('ALTER TABLE borrow ADD CONSTRAINT FK_55DBA8B011CE312B FOREIGN KEY (borrower_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE borrow ADD CONSTRAINT FK_55DBA8B071F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE borrow ADD CONSTRAINT FK_55DBA8B02D234F6A FOREIGN KEY (approved_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE borrow ADD CONSTRAINT FK_55DBA8B020E67D3D FOREIGN KEY (handed_off_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE borrow ADD CONSTRAINT FK_55DBA8B0EB7FEDB8 FOREIGN KEY (returned_to_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE borrow ADD CONSTRAINT FK_55DBA8B0187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE deck ADD CONSTRAINT FK_4FAC36377E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE deck ADD CONSTRAINT FK_4FAC36379407EE77 FOREIGN KEY (current_version_id) REFERENCES deck_version (id)');
        $this->addSql('ALTER TABLE deck_card ADD CONSTRAINT FK_2AF3DCEDBE66DA24 FOREIGN KEY (deck_version_id) REFERENCES deck_version (id)');
        $this->addSql('ALTER TABLE deck_version ADD CONSTRAINT FK_3B92E6BF111948DC FOREIGN KEY (deck_id) REFERENCES deck (id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7876C4DDA FOREIGN KEY (organizer_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA758AFC4DE FOREIGN KEY (league_id) REFERENCES league (id)');
        $this->addSql('ALTER TABLE event_participant ADD CONSTRAINT FK_7C16B89171F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_participant ADD CONSTRAINT FK_7C16B891A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_deck_entry ADD CONSTRAINT FK_B292BEB071F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event_deck_entry ADD CONSTRAINT FK_B292BEB099E6F5DF FOREIGN KEY (player_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE event_deck_entry ADD CONSTRAINT FK_B292BEB0BE66DA24 FOREIGN KEY (deck_version_id) REFERENCES deck_version (id)');
        $this->addSql('ALTER TABLE event_staff ADD CONSTRAINT FK_37542BE71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event_staff ADD CONSTRAINT FK_37542BEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE event_staff ADD CONSTRAINT FK_37542BE6E6F1246 FOREIGN KEY (assigned_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE borrow DROP FOREIGN KEY FK_55DBA8B0111948DC');
        $this->addSql('ALTER TABLE borrow DROP FOREIGN KEY FK_55DBA8B0BE66DA24');
        $this->addSql('ALTER TABLE borrow DROP FOREIGN KEY FK_55DBA8B011CE312B');
        $this->addSql('ALTER TABLE borrow DROP FOREIGN KEY FK_55DBA8B071F7E88B');
        $this->addSql('ALTER TABLE borrow DROP FOREIGN KEY FK_55DBA8B02D234F6A');
        $this->addSql('ALTER TABLE borrow DROP FOREIGN KEY FK_55DBA8B020E67D3D');
        $this->addSql('ALTER TABLE borrow DROP FOREIGN KEY FK_55DBA8B0EB7FEDB8');
        $this->addSql('ALTER TABLE borrow DROP FOREIGN KEY FK_55DBA8B0187B2D12');
        $this->addSql('ALTER TABLE deck DROP FOREIGN KEY FK_4FAC36377E3C61F9');
        $this->addSql('ALTER TABLE deck DROP FOREIGN KEY FK_4FAC36379407EE77');
        $this->addSql('ALTER TABLE deck_card DROP FOREIGN KEY FK_2AF3DCEDBE66DA24');
        $this->addSql('ALTER TABLE deck_version DROP FOREIGN KEY FK_3B92E6BF111948DC');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7876C4DDA');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA758AFC4DE');
        $this->addSql('ALTER TABLE event_participant DROP FOREIGN KEY FK_7C16B89171F7E88B');
        $this->addSql('ALTER TABLE event_participant DROP FOREIGN KEY FK_7C16B891A76ED395');
        $this->addSql('ALTER TABLE event_deck_entry DROP FOREIGN KEY FK_B292BEB071F7E88B');
        $this->addSql('ALTER TABLE event_deck_entry DROP FOREIGN KEY FK_B292BEB099E6F5DF');
        $this->addSql('ALTER TABLE event_deck_entry DROP FOREIGN KEY FK_B292BEB0BE66DA24');
        $this->addSql('ALTER TABLE event_staff DROP FOREIGN KEY FK_37542BE71F7E88B');
        $this->addSql('ALTER TABLE event_staff DROP FOREIGN KEY FK_37542BEA76ED395');
        $this->addSql('ALTER TABLE event_staff DROP FOREIGN KEY FK_37542BE6E6F1246');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAE92F8F78');
        $this->addSql('DROP TABLE borrow');
        $this->addSql('DROP TABLE deck');
        $this->addSql('DROP TABLE deck_card');
        $this->addSql('DROP TABLE deck_version');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_participant');
        $this->addSql('DROP TABLE event_deck_entry');
        $this->addSql('DROP TABLE event_staff');
        $this->addSql('DROP TABLE league');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
