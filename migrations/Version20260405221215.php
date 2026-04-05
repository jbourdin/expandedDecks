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

final class Version20260405221215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refactor card model: CardPrinting proxy to tcgdex_card, CardIdentity trainerType, DeckCard simplified.';
    }

    public function up(Schema $schema): void
    {
        // CardIdentity: add trainerType
        $this->addSql('ALTER TABLE card_identity ADD trainer_type VARCHAR(30) DEFAULT NULL');

        // CardPrinting: add tcgdex_card FK and is_canonical flag
        $this->addSql('ALTER TABLE card_printing ADD tcgdex_card_id VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE card_printing ADD is_canonical TINYINT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE card_printing ADD CONSTRAINT FK_89BEA370A9599F9 FOREIGN KEY (tcgdex_card_id) REFERENCES tcgdex_card (id)');
        $this->addSql('CREATE INDEX IDX_89BEA370A9599F9 ON card_printing (tcgdex_card_id)');

        // DeckCard: add card_locale, drop redundant columns
        $this->addSql("ALTER TABLE deck_card ADD card_locale VARCHAR(5) NOT NULL DEFAULT 'en'");
        $this->addSql('ALTER TABLE deck_card DROP COLUMN tcgdex_id');
        $this->addSql('ALTER TABLE deck_card DROP COLUMN image_url');
        $this->addSql('ALTER TABLE deck_card DROP COLUMN trainer_subtype');

        // Data backfill: link existing card_printings to tcgdex_card by matching tcgdex_id
        $this->addSql('UPDATE card_printing cp JOIN tcgdex_card tc ON cp.tcgdex_id = tc.id SET cp.tcgdex_card_id = tc.id');

        // Data backfill: populate card_identity.trainer_type from tcgdex_card
        $this->addSql('UPDATE card_identity ci JOIN card_printing cp ON cp.card_identity_id = ci.id JOIN tcgdex_card tc ON cp.tcgdex_id = tc.id SET ci.trainer_type = tc.trainer_type WHERE ci.trainer_type IS NULL AND tc.trainer_type IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_identity DROP trainer_type');

        $this->addSql('ALTER TABLE card_printing DROP FOREIGN KEY FK_89BEA370A9599F9');
        $this->addSql('DROP INDEX IDX_89BEA370A9599F9 ON card_printing');
        $this->addSql('ALTER TABLE card_printing DROP tcgdex_card_id, DROP is_canonical');

        $this->addSql('ALTER TABLE deck_card ADD trainer_subtype VARCHAR(20) DEFAULT NULL, ADD tcgdex_id VARCHAR(30) DEFAULT NULL, ADD image_url VARCHAR(255) DEFAULT NULL, DROP card_locale');
    }
}
