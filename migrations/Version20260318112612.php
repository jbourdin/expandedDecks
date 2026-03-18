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
 * Add mosaic_image_url column to deck_version for F6.6.
 */
final class Version20260318112612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mosaic_image_url column to deck_version (F6.6)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck_version ADD mosaic_image_url VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck_version DROP mosaic_image_url');
    }
}
