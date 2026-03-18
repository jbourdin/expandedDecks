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
 * Add CardIdentity, CardPrinting entities; DeckCard.cardPrinting FK; DeckVersion minified fields.
 */
final class Version20260318182526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add card_identity, card_printing tables, deck_card.card_printing_id FK, and deck_version minified fields (F6.10, F6.8, F6.6b)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE card_identity (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, category VARCHAR(20) NOT NULL, hp INT NOT NULL, attack_signature VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_card_identity (name, category, hp, attack_signature), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE card_printing (id INT AUTO_INCREMENT NOT NULL, tcgdex_id VARCHAR(30) NOT NULL, set_code VARCHAR(20) NOT NULL, card_number VARCHAR(20) NOT NULL, rarity VARCHAR(50) DEFAULT NULL, rarity_tier INT NOT NULL, image_url VARCHAR(255) DEFAULT NULL, set_release_date DATETIME DEFAULT NULL, price_in_cents INT DEFAULT NULL, is_expanded_legal TINYINT NOT NULL, card_identity_id INT NOT NULL, INDEX IDX_89BEA37018EA8B32 (card_identity_id), UNIQUE INDEX uniq_tcgdex_id (tcgdex_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE card_printing ADD CONSTRAINT FK_89BEA37018EA8B32 FOREIGN KEY (card_identity_id) REFERENCES card_identity (id)');
        $this->addSql('ALTER TABLE deck_card ADD card_printing_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE deck_card ADD CONSTRAINT FK_2AF3DCED9B43DC48 FOREIGN KEY (card_printing_id) REFERENCES card_printing (id)');
        $this->addSql('CREATE INDEX IDX_2AF3DCED9B43DC48 ON deck_card (card_printing_id)');
        $this->addSql('ALTER TABLE deck_version ADD minified_list LONGTEXT DEFAULT NULL, ADD minified_mosaic_image_url VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_printing DROP FOREIGN KEY FK_89BEA37018EA8B32');
        $this->addSql('DROP TABLE card_identity');
        $this->addSql('DROP TABLE card_printing');
        $this->addSql('ALTER TABLE deck_card DROP FOREIGN KEY FK_2AF3DCED9B43DC48');
        $this->addSql('DROP INDEX IDX_2AF3DCED9B43DC48 ON deck_card');
        $this->addSql('ALTER TABLE deck_card DROP card_printing_id');
        $this->addSql('ALTER TABLE deck_version DROP minified_list, DROP minified_mosaic_image_url');
    }
}
