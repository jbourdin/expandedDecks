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
 * Normalize schema to current Doctrine conventions (schema-drift cleanup).
 *
 * Renames legacy hand-named indexes/FKs to Doctrine's derived names, drops the
 * obsolete `(DC2Type:datetime_immutable)` column comments DBAL 4 no longer
 * emits, and aligns the framework-managed `messenger_messages`/`sessions`
 * tables with the current bundle expectations. All metadata-only (no table
 * rebuilds, no row changes), clearing long-standing drift so
 * doctrine:schema:validate is fully in sync on the production-evolved schema.
 *
 * Guarded: a freshly built schema (createSchema / migrate-from-zero) already
 * uses current conventions, so this migration skips itself there — it only
 * acts on the legacy production-evolved schema.
 *
 * @see docs/technicalities/schema_drift.md
 */
final class Version20260621081335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize legacy index/FK names, drop obsolete DC2Type comments, align framework tables (schema-drift cleanup)';
    }

    public function up(Schema $schema): void
    {
        $isLegacySchema = (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'event' AND index_name = 'idx_event_pending_transfer_to'",
        );
        $this->skipIf(!$isLegacySchema, 'Schema already uses current Doctrine conventions (fresh build); nothing to normalize.');

        // Legacy index / FK names -> Doctrine derived names.
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY `FK_event_pending_transfer_to`');
        $this->addSql('ALTER TABLE event CHANGE pending_transfer_requested_at pending_transfer_requested_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA768B7628F FOREIGN KEY (pending_transfer_to_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE event RENAME INDEX idx_event_pending_transfer_to TO IDX_3BAE0AA768B7628F');
        $this->addSql('ALTER TABLE event_event_tag RENAME INDEX idx_event_event_tag_event TO IDX_94D34C6D71F7E88B');
        $this->addSql('ALTER TABLE event_event_tag RENAME INDEX idx_event_event_tag_tag TO IDX_94D34C6D884B1443');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_user_calendar_token TO UNIQ_8D93D6493363A255');
        $this->addSql('ALTER TABLE banned_card_printing RENAME INDEX idx_banned_card_printing_banned_card TO IDX_E65998B1C14B8818');
        $this->addSql('ALTER TABLE banned_card_printing RENAME INDEX idx_banned_card_printing_card_printing TO IDX_E65998B19B43DC48');
        $this->addSql('DROP INDEX IDX_banned_card_deleted_at ON banned_card');
        $this->addSql('ALTER TABLE banned_card CHANGE effective_date effective_date DATETIME DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE banned_card RENAME INDEX fk_banned_card_representative_printing TO IDX_CB22283FB2864B3E');
        $this->addSql('ALTER TABLE event_tag CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE event_tag RENAME INDEX uniq_event_tag_name TO UNIQ_124672505E237E06');
        $this->addSql('ALTER TABLE event_tag RENAME INDEX uniq_event_tag_slug TO UNIQ_12467250989D9B62');
        $this->addSql('ALTER TABLE tcgdex_card CHANGE tcgdex_updated_at tcgdex_updated_at DATETIME DEFAULT NULL');

        // Framework-managed tables: align with current bundle schema.
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE sessions CHANGE sess_id sess_id VARBINARY(128) NOT NULL, CHANGE sess_data sess_data LONGBLOB NOT NULL');
        $this->addSql('ALTER TABLE sessions RENAME INDEX sessions_sess_lifetime_idx TO sess_lifetime_idx');
    }

    public function down(Schema $schema): void
    {
        // Intentionally not reversed: this migration only normalizes identifier
        // names and removes obsolete type comments to match current Doctrine
        // conventions. Reverting would re-introduce the exact drift it clears.
    }
}
