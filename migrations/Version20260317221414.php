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

final class Version20260317221414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop description and meta_description from archetype table — now in archetype_translation (F9.6)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype DROP description, DROP meta_description');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archetype ADD description LONGTEXT DEFAULT NULL, ADD meta_description VARCHAR(255) DEFAULT NULL');

        // Restore data from EN translations
        $this->addSql("UPDATE archetype a JOIN archetype_translation t ON t.archetype_id = a.id AND t.locale = 'en' SET a.description = t.description, a.meta_description = t.meta_description");
    }
}
