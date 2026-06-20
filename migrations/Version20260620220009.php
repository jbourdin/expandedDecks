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
 * F19.8 — Backfill author attribution for pre-existing content.
 *
 * Attributes existing, unattributed editorial content by the channel it
 * belongs to:
 *  - expandeddecks.app pages -> the "Sylf" user (the app/tool developer);
 *  - all other content (archetypes, archetype variants, content-channel
 *    pages) -> the "Luby" user (the content writer).
 *
 * Each target is resolved by screen name via a subquery; when the user does
 * not exist the subquery yields NULL and the row is left unattributed
 * (a no-op, since only NULL authors are touched). Idempotent and data-only.
 */
final class Version20260620220009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F19.8: backfill author attribution for existing content (content->Luby, expandeddecks.app->Sylf)';
    }

    public function up(Schema $schema): void
    {
        // Archetypes live on the content channel -> Luby.
        $this->addSql("UPDATE archetype SET author_id = (SELECT id FROM `user` WHERE screen_name = 'Luby' LIMIT 1) WHERE author_id IS NULL");

        // Archetype variants (owner-less editorial decklists) -> Luby.
        $this->addSql("UPDATE deck SET author_id = (SELECT id FROM `user` WHERE screen_name = 'Luby' LIMIT 1) WHERE author_id IS NULL AND owner_id IS NULL AND archetype_id IS NOT NULL");

        // Pages on the app channel -> Sylf.
        $this->addSql("UPDATE page p JOIN channel c ON p.channel_id = c.id SET p.author_id = (SELECT id FROM `user` WHERE screen_name = 'Sylf' LIMIT 1) WHERE p.author_id IS NULL AND c.domain = 'expandeddecks.app'");

        // Pages on any other channel -> Luby.
        $this->addSql("UPDATE page p JOIN channel c ON p.channel_id = c.id SET p.author_id = (SELECT id FROM `user` WHERE screen_name = 'Luby' LIMIT 1) WHERE p.author_id IS NULL AND c.domain <> 'expandeddecks.app'");
    }

    public function down(Schema $schema): void
    {
        // Data backfill: not reversed (clearing author_id would also discard
        // attributions set legitimately after this migration ran).
    }
}
