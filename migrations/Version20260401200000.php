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

final class Version20260401200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default homepage layout with news, hero, and rich text blocks';
    }

    public function up(Schema $schema): void
    {
        $blocks = json_encode([
            [
                'type' => 'latestPages',
                'columnWidth' => null,
                'cssClasses' => null,
                'startAt' => null,
                'endAt' => null,
                'categorySlug' => 'news',
                'limit' => 5,
            ],
            [
                'type' => 'hero',
                'columnWidth' => 6,
                'cssClasses' => null,
                'startAt' => null,
                'endAt' => null,
            ],
            [
                'type' => 'richText',
                'columnWidth' => 6,
                'cssClasses' => null,
                'startAt' => null,
                'endAt' => null,
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->addSql('INSERT INTO homepage_layout (blocks, is_published, created_at) VALUES (:blocks, 1, NOW())', [
            'blocks' => $blocks,
        ]);

        $translationEn = json_encode([
            '1' => [
                'title' => 'Share the Expanded Experience',
                'subtitle' => 'Borrow real decks, play at events, discover the format together.',
                'ctaButtons' => [
                    ['label' => 'Register', 'route' => 'app_register', 'style' => 'primary'],
                    ['label' => 'Login', 'route' => 'app_login', 'style' => 'outline'],
                ],
            ],
            '2' => [
                'content' => "## Your shared deck library\n\nExpanded Decks makes it easy to **manage a shared library of physical Pokemon TCG decks** for your local community. Register your decks, lend them for events, and keep track of who has what.\n\n## How it works\n\n1. **Register your decks** — import your deck list in PTCG text format, validated against TCGdex\n2. **Share for events** — other players can request to borrow a deck for upcoming events\n3. **Track everything** — see who borrowed what, when, and get notified at every step\n\nJump in and explore the library!",
            ],
        ], \JSON_THROW_ON_ERROR);

        $translationFr = json_encode([
            '1' => [
                'title' => "Partagez l'Expérience Expanded",
                'subtitle' => "Empruntez de vrais decks, jouez lors d'événements, découvrez le format ensemble.",
                'ctaButtons' => [
                    ['label' => "S'inscrire", 'route' => 'app_register', 'style' => 'primary'],
                    ['label' => 'Connexion', 'route' => 'app_login', 'style' => 'outline'],
                ],
            ],
            '2' => [
                'content' => "## Votre bibliothèque de decks partagée\n\nExpanded Decks facilite la gestion d'une bibliothèque partagée de decks physiques Pokemon TCG pour votre communauté locale. Enregistrez vos decks, prêtez-les pour des événements et suivez qui a quoi.\n\n## Comment ça marche\n\n1. **Enregistrez vos decks** — importez votre liste au format texte PTCG, validée via TCGdex\n2. **Partagez pour les événements** — d'autres joueurs peuvent demander à emprunter un deck\n3. **Suivez tout** — voyez qui a emprunté quoi, quand, et recevez des notifications à chaque étape\n\nPlongez et explorez la bibliothèque !",
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->addSql('INSERT INTO homepage_layout_translation (homepage_layout_id, locale, block_translations) VALUES ((SELECT id FROM homepage_layout WHERE is_published = 1 LIMIT 1), :locale, :translations)', [
            'locale' => 'en',
            'translations' => $translationEn,
        ]);

        $this->addSql('INSERT INTO homepage_layout_translation (homepage_layout_id, locale, block_translations) VALUES ((SELECT id FROM homepage_layout WHERE is_published = 1 LIMIT 1), :locale, :translations)', [
            'locale' => 'fr',
            'translations' => $translationFr,
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM homepage_layout_translation WHERE homepage_layout_id = (SELECT id FROM homepage_layout WHERE is_published = 1 LIMIT 1)');
        $this->addSql('DELETE FROM homepage_layout WHERE is_published = 1');
    }
}
