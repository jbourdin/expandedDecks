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
 * Add CardIdentity.ruleboxType — nullable string capturing the card's rulebox mechanic
 * (Ace Spec, V/VMAX/VSTAR, ex/EX, GX, BREAK, Mega, Radiant, Prism Star, etc.).
 *
 * Detection in PR-1 covers Ace Spec only, populated from `tcgdex_card.rarity = 'ACE SPEC Rare'`.
 * The other rulebox types are listed in {@see \App\Constants\RuleboxType} and will be detected
 * by follow-up PRs (most are name-pattern based).
 *
 * Backfill: any CardIdentity that has at least one printing flagged "ACE SPEC Rare" in
 * card_printing.rarity is marked. The 4 currently-known Ace Spec printings in the local
 * card_printing table cover 4 identities (1:1 in this dataset).
 *
 * @see https://github.com/jbourdin/expandedDecks/issues/532
 */
final class Version20260507204712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6.15 — CardIdentity.ruleboxType column + Ace Spec backfill';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_identity ADD rulebox_type VARCHAR(30) DEFAULT NULL');

        $this->addSql(
            <<<'SQL'
                UPDATE card_identity ci
                SET ci.rulebox_type = :ruleboxType
                WHERE EXISTS (
                    SELECT 1 FROM card_printing cp
                    WHERE cp.card_identity_id = ci.id
                      AND cp.rarity = :aceSpecRarity
                )
                SQL,
            [
                'ruleboxType' => 'ace_spec',
                'aceSpecRarity' => 'ACE SPEC Rare',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_identity DROP rulebox_type');
    }
}
