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

final class Version20260323170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add minified_card_views column to deck_version for pre-computed card view JSON';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck_version ADD minified_card_views LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck_version DROP minified_card_views');
    }
}
