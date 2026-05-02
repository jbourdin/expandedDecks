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
 * F6.14 step 2 — split banned_card into a parent (one row per CardIdentity)
 * and a child banned_card_printing (one row per upstream set+number pair).
 * All canonical metadata (effective date, source URL, explanation) moves to
 * the parent so admins manage one row per ban.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final class Version20260502130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.14 — split banned_card into parent (per CardIdentity) and banned_card_printing children';
    }

    public function up(Schema $schema): void
    {
        // 1. Rename the existing per-printing table. Drop FKs first so we can
        //    rename the indexes they depend on, then re-add the FKs at the end.
        $this->addSql('ALTER TABLE banned_card DROP FOREIGN KEY FK_banned_card_card_printing');
        $this->addSql('RENAME TABLE banned_card TO banned_card_printing');

        $this->addSql('ALTER TABLE banned_card_printing DROP INDEX uniq_banned_card');
        $this->addSql('ALTER TABLE banned_card_printing ADD UNIQUE INDEX uniq_banned_card_printing (set_code, card_number)');
        $this->addSql('ALTER TABLE banned_card_printing DROP INDEX IDX_banned_card_card_printing');
        $this->addSql('ALTER TABLE banned_card_printing ADD INDEX IDX_banned_card_printing_card_printing (card_printing_id)');
        $this->addSql('ALTER TABLE banned_card_printing ADD CONSTRAINT FK_banned_card_printing_card_printing FOREIGN KEY (card_printing_id) REFERENCES card_printing (id) ON DELETE SET NULL');

        // 2. Create the new parent banned_card table.
        $this->addSql(<<<'SQL'
            CREATE TABLE banned_card (
                id INT AUTO_INCREMENT NOT NULL,
                card_identity_id INT DEFAULT NULL,
                representative_printing_id INT DEFAULT NULL,
                card_name VARCHAR(100) NOT NULL,
                effective_date DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                source_url VARCHAR(255) DEFAULT NULL,
                explanation LONGTEXT DEFAULT NULL,
                deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                legacy_printing_id INT DEFAULT NULL,
                UNIQUE KEY uniq_banned_card_identity (card_identity_id),
                INDEX IDX_banned_card_deleted_at (deleted_at),
                INDEX IDX_banned_card_legacy_printing (legacy_printing_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql('ALTER TABLE banned_card ADD CONSTRAINT FK_banned_card_card_identity FOREIGN KEY (card_identity_id) REFERENCES card_identity (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE banned_card ADD CONSTRAINT FK_banned_card_representative_printing FOREIGN KEY (representative_printing_id) REFERENCES card_printing (id) ON DELETE SET NULL');

        // 3. Add the back-reference column on the child table; nullable until populated.
        $this->addSql('ALTER TABLE banned_card_printing ADD banned_card_id INT DEFAULT NULL');

        // 4. Populate parents: one row per (CardIdentity), or per (legacy printing id) for unlinked rows.
        //    `legacy_printing_id` is a transient bookkeeping column used to wire children back to
        //    parents whose CardIdentity is null. Aggregate metadata (earliest date, first non-null
        //    source/explanation, latest deletion) from the children.
        $this->addSql(<<<'SQL'
            INSERT INTO banned_card (
                card_identity_id,
                card_name,
                effective_date,
                source_url,
                explanation,
                deleted_at,
                created_at,
                legacy_printing_id
            )
            SELECT
                cp.card_identity_id,
                MIN(bcp.card_name)                                                AS card_name,
                MIN(bcp.effective_date)                                           AS effective_date,
                SUBSTRING_INDEX(GROUP_CONCAT(bcp.source_url ORDER BY bcp.id), ',', 1)  AS source_url,
                SUBSTRING_INDEX(GROUP_CONCAT(bcp.explanation ORDER BY bcp.id), ',', 1) AS explanation,
                MAX(bcp.deleted_at)                                               AS deleted_at,
                MIN(bcp.created_at)                                               AS created_at,
                CASE WHEN cp.card_identity_id IS NULL THEN MIN(bcp.id) ELSE NULL END AS legacy_printing_id
            FROM banned_card_printing bcp
            LEFT JOIN card_printing cp ON cp.id = bcp.card_printing_id
            GROUP BY cp.card_identity_id, IF(cp.card_identity_id IS NULL, bcp.id, 0)
            SQL);

        // 5. Wire children to parents.
        //    a) Linked rows: match on card_identity_id.
        $this->addSql(<<<'SQL'
            UPDATE banned_card_printing bcp
            INNER JOIN card_printing cp ON cp.id = bcp.card_printing_id
            INNER JOIN banned_card bc ON bc.card_identity_id = cp.card_identity_id
            SET bcp.banned_card_id = bc.id
            WHERE bcp.banned_card_id IS NULL
            SQL);

        //    b) Unlinked rows: match via the legacy_printing_id breadcrumb.
        $this->addSql(<<<'SQL'
            UPDATE banned_card_printing bcp
            INNER JOIN banned_card bc ON bc.legacy_printing_id = bcp.id
            SET bcp.banned_card_id = bc.id
            WHERE bcp.banned_card_id IS NULL
            SQL);

        // 6. Tighten constraints + add the FK now that every child row has a parent.
        $this->addSql('ALTER TABLE banned_card_printing MODIFY banned_card_id INT NOT NULL');
        $this->addSql('ALTER TABLE banned_card_printing ADD CONSTRAINT FK_banned_card_printing_banned_card FOREIGN KEY (banned_card_id) REFERENCES banned_card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE banned_card_printing ADD INDEX IDX_banned_card_printing_banned_card (banned_card_id)');

        // 7. Drop columns that have moved to the parent. Indexes on dropped columns
        //    are removed automatically by MySQL.
        $this->addSql('DROP INDEX IDX_banned_card_deleted_at ON banned_card_printing');
        $this->addSql('ALTER TABLE banned_card_printing DROP COLUMN effective_date');
        $this->addSql('ALTER TABLE banned_card_printing DROP COLUMN source_url');
        $this->addSql('ALTER TABLE banned_card_printing DROP COLUMN explanation');
        $this->addSql('ALTER TABLE banned_card_printing DROP COLUMN deleted_at');
        $this->addSql('ALTER TABLE banned_card_printing DROP COLUMN card_name');

        // 8. Drop the migration breadcrumb.
        $this->addSql('ALTER TABLE banned_card DROP INDEX IDX_banned_card_legacy_printing');
        $this->addSql('ALTER TABLE banned_card DROP COLUMN legacy_printing_id');
    }

    public function down(Schema $schema): void
    {
        // Rebuild the original single-table layout. Source/explanation/effective date
        // are fanned out to every child for backwards compatibility.
        $this->addSql('ALTER TABLE banned_card_printing ADD card_name VARCHAR(100) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE banned_card_printing ADD effective_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE banned_card_printing ADD source_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE banned_card_printing ADD explanation LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE banned_card_printing ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql(<<<'SQL'
            UPDATE banned_card_printing bcp
            INNER JOIN banned_card bc ON bc.id = bcp.banned_card_id
            SET
                bcp.card_name = bc.card_name,
                bcp.effective_date = bc.effective_date,
                bcp.source_url = bc.source_url,
                bcp.explanation = bc.explanation,
                bcp.deleted_at = bc.deleted_at
            SQL);

        $this->addSql('ALTER TABLE banned_card_printing DROP FOREIGN KEY FK_banned_card_printing_banned_card');
        $this->addSql('ALTER TABLE banned_card_printing DROP INDEX IDX_banned_card_printing_banned_card');
        $this->addSql('ALTER TABLE banned_card_printing DROP COLUMN banned_card_id');
        $this->addSql('CREATE INDEX IDX_banned_card_deleted_at ON banned_card_printing (deleted_at)');

        $this->addSql('ALTER TABLE banned_card_printing DROP INDEX uniq_banned_card_printing');
        $this->addSql('ALTER TABLE banned_card_printing ADD UNIQUE INDEX uniq_banned_card (set_code, card_number)');
        $this->addSql('ALTER TABLE banned_card_printing DROP INDEX IDX_banned_card_printing_card_printing');
        $this->addSql('ALTER TABLE banned_card_printing ADD INDEX IDX_banned_card_card_printing (card_printing_id)');

        $this->addSql('DROP TABLE banned_card');
        $this->addSql('RENAME TABLE banned_card_printing TO banned_card');
    }
}
