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

final class Version20260321193725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add marketplace product IDs (Cardmarket, TCGPlayer) to CardPrinting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_printing ADD cardmarket_product_id INT DEFAULT NULL, ADD tcgplayer_product_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_printing DROP cardmarket_product_id, DROP tcgplayer_product_id');
    }
}
