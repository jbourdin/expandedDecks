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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds `pokemon_type` to card_identity so mechanically-identical Pokemon cards with
 * different elemental types (e.g. Dialga GX printed as Metal and as Dragon) get
 * distinct identities. Splits any existing CardIdentity whose printings span multiple
 * types, by cloning the row for each non-keeper type group and repointing its printings.
 *
 * Printings whose TCGdex string identifier doesn't match a row in the local mirror are
 * ignored — the migration cannot infer their type. They remain attached to whichever
 * identity row survives the split (the keeper). Future enrichment runs that resolve
 * those printings via the API will assign them to the correct type-aware identity
 * through the resolver. Note: we join on `card_printing.tcgdex_id` (the string ID,
 * always populated) rather than the `tcgdex_card_id` FK (frequently NULL for older
 * printings) so the mirror is found whenever it exists.
 *
 * @see docs/features.md F6.10 — Card identity and printing model
 */
final class Version20260526230542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add card_identity.pokemon_type column, split mixed-type Pokemon identities (e.g. Dialga GX Metal vs Dragon), swap unique constraint.';
    }

    public function up(Schema $schema): void
    {
        // 1. Add nullable column so we can backfill before enforcing NOT NULL.
        //    Execute immediately (not addSql) so the subsequent UPDATE/INSERT calls
        //    via $this->connection see the new column — addSql queues statements
        //    until end-of-migration, which is too late for in-method DML below.
        $this->connection->executeStatement('ALTER TABLE card_identity ADD pokemon_type VARCHAR(100) DEFAULT NULL');

        // 2. Non-Pokemon identities use empty-string sentinel (same convention as ability/attack signatures).
        $this->connection->executeStatement("UPDATE card_identity SET pokemon_type = '' WHERE category <> 'pokemon'");

        // 2b. Drop the old unique index NOW — the split loop below INSERTs clones that share
        //     (name, category, hp, ability_signature, attack_signature) with the keeper and
        //     would violate the old constraint. The new constraint, which includes
        //     pokemon_type, is created at step 7 once all rows have their final value.
        $this->connection->executeStatement('DROP INDEX uniq_card_identity ON card_identity');

        // 3. Fetch all Pokemon printings with their tcgdex_card.types JSON.
        //    We join on cp.tcgdex_id (the string identifier, always populated) instead of
        //    the nullable cp.tcgdex_card_id FK — older printings often have the FK unset
        //    while the mirror row exists under the same string ID. INNER JOIN silently
        //    excludes printings whose mirror still isn't loaded; those stay attached to
        //    the keeper identity and get re-classified by the resolver on next enrichment.
        /** @var list<array{printing_id: int, identity_id: int, types_json: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT cp.id AS printing_id,
                   cp.card_identity_id AS identity_id,
                   tc.types AS types_json
            FROM card_printing cp
            JOIN card_identity ci ON ci.id = cp.card_identity_id
            JOIN tcgdex_card tc ON tc.id = cp.tcgdex_id
            WHERE ci.category = 'pokemon'
            ORDER BY cp.card_identity_id, cp.id
            SQL);

        // Group printings by identity, then by sorted type signature.
        // Structure: $identityGroups[identityId][signature] = list<printingId>.
        /** @var array<int, array<string, list<int>>> $identityGroups */
        $identityGroups = [];

        foreach ($rows as $row) {
            $identityId = (int) $row['identity_id'];
            $printingId = (int) $row['printing_id'];

            $decoded = json_decode((string) $row['types_json'], true);
            $types = \is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
            sort($types, \SORT_STRING);
            $signature = implode(',', $types);

            $identityGroups[$identityId][$signature][] = $printingId;
        }

        // 4. For each Pokemon identity, pick the keeper signature (largest group, stable order)
        //    and clone the identity for every other signature group.
        foreach ($identityGroups as $identityId => $groups) {
            // Stable keeper selection: walk groups in insertion order, pick the largest.
            $keeperSignature = null;
            $keeperCount = -1;

            foreach ($groups as $signature => $printingIds) {
                if (\count($printingIds) > $keeperCount) {
                    $keeperSignature = $signature;
                    $keeperCount = \count($printingIds);
                }
            }

            // The existing identity row inherits the keeper signature.
            $this->connection->update(
                'card_identity',
                ['pokemon_type' => $keeperSignature],
                ['id' => $identityId],
            );

            // Non-keeper groups need cloned identities.
            $otherSignatures = array_keys($groups);
            $otherSignatures = array_values(array_filter($otherSignatures, static fn (string $s): bool => $s !== $keeperSignature));

            if ([] === $otherSignatures) {
                continue;
            }

            /** @var array<string, mixed>|false $source */
            $source = $this->connection->fetchAssociative(
                'SELECT name, category, hp, ability_signature, ability_names, attack_signature, attack_names, trainer_type, rulebox_type FROM card_identity WHERE id = ?',
                [$identityId],
            );

            if (false === $source) {
                throw new \RuntimeException(\sprintf('card_identity row %d disappeared mid-migration.', $identityId));
            }

            foreach ($otherSignatures as $signature) {
                $this->connection->insert('card_identity', [
                    'name' => $source['name'],
                    'category' => $source['category'],
                    'hp' => $source['hp'],
                    'ability_signature' => $source['ability_signature'],
                    'ability_names' => $source['ability_names'],
                    'attack_signature' => $source['attack_signature'],
                    'attack_names' => $source['attack_names'],
                    'pokemon_type' => $signature,
                    'trainer_type' => $source['trainer_type'],
                    'rulebox_type' => $source['rulebox_type'],
                    'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
                $newIdentityId = (int) $this->connection->lastInsertId();

                $this->connection->executeStatement(
                    'UPDATE card_printing SET card_identity_id = ? WHERE id IN (?)',
                    [$newIdentityId, $groups[$signature]],
                    [ParameterType::INTEGER, ArrayParameterType::INTEGER],
                );
            }
        }

        // 5. Defensive backfill: any Pokemon identity whose printings all lacked a tcgdex_card
        //    mirror was skipped entirely above (the JOIN excluded them), so its pokemon_type
        //    is still NULL. Treat those as "unknown type" with the empty sentinel — future
        //    enrichment will reassign their printings to a properly-typed identity via the
        //    resolver, leaving these rows behind (cheap, harmless, and self-healing).
        //    Execute immediately so the NOT NULL ALTER below sees a fully-populated column.
        $this->connection->executeStatement("UPDATE card_identity SET pokemon_type = '' WHERE pokemon_type IS NULL");

        // 6. All rows now have a non-null pokemon_type. Enforce it at the schema level.
        $this->addSql("ALTER TABLE card_identity MODIFY pokemon_type VARCHAR(100) DEFAULT '' NOT NULL");

        // 7. Create the new unique index including pokemon_type (old one was dropped at step 2b).
        $this->addSql('CREATE UNIQUE INDEX uniq_card_identity ON card_identity (name, category, hp, ability_signature, attack_signature, pokemon_type)');
    }

    public function down(Schema $schema): void
    {
        // After the up() split, two identity rows (e.g. Dialga GX Metal + Dialga GX Dragon)
        // share name+category+hp+ability_signature+attack_signature. Dropping pokemon_type
        // would make them straight duplicates, so the restored unique index would fail to
        // build. We can't safely auto-merge them either — the clones may already have
        // printings, decks, or staple references pointing at them.
        $duplicates = (int) $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*) FROM (
                SELECT 1 FROM card_identity
                GROUP BY name, category, hp, ability_signature, attack_signature
                HAVING COUNT(*) > 1
            ) AS dupes
            SQL);

        if ($duplicates > 0) {
            throw new \RuntimeException(\sprintf('Cannot roll back: %d identity groups would become duplicates without pokemon_type. Manually merge them (re-point their printings and delete the clones) before re-running down().', $duplicates));
        }

        $this->addSql('DROP INDEX uniq_card_identity ON card_identity');
        $this->addSql('ALTER TABLE card_identity DROP pokemon_type');
        $this->addSql('CREATE UNIQUE INDEX uniq_card_identity ON card_identity (name, category, hp, ability_signature, attack_signature)');
    }
}
