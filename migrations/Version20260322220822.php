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

final class Version20260322220822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ability/attack signature and name columns to card_identity, and update unique constraint.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_card_identity ON card_identity');
        $this->addSql(<<<'SQL'
            ALTER TABLE card_identity
                ADD ability_signature VARCHAR(255) NOT NULL DEFAULT '',
                ADD ability_names VARCHAR(255) NOT NULL DEFAULT '',
                ADD attack_names VARCHAR(255) NOT NULL DEFAULT '',
                MODIFY attack_signature VARCHAR(255) NOT NULL
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_card_identity ON card_identity (name, category, hp, ability_signature, attack_signature)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_card_identity ON card_identity');
        $this->addSql(<<<'SQL'
            ALTER TABLE card_identity
                DROP ability_signature,
                DROP ability_names,
                DROP attack_names,
                MODIFY attack_signature VARCHAR(500) NOT NULL
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_card_identity ON card_identity (name, category, hp, attack_signature)');
    }
}
