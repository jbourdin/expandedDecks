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

namespace App\Service\Tcgdex;

/**
 * Normalizes and compares Pokemon TCG card names.
 *
 * Deck lists are pasted from PTCGL/PTCGO exports, so a name can differ from the
 * TCGdex spelling by accents, apostrophes, or spacing ("Pokemon Communication"
 * vs "Pokémon Communication", "Mew-EX" vs "Mew ex"). French lists also reach the
 * enricher before the canonical English name is applied. Comparison therefore
 * runs on a folded form rather than on raw string equality.
 *
 * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
 */
class CardNameMatcher
{
    /**
     * Below this score two names are treated as unrelated.
     *
     * Tuned so that a single-character drift on a short name still matches
     * ("Mew ex" vs "Mew-EX") while genuinely different cards printed at the same
     * number in two colliding sets do not.
     */
    public const float MINIMUM_SIMILARITY = 0.80;

    /**
     * Folds a card name to a comparable form: ASCII, lowercase, single-spaced.
     *
     * Every run of non-alphanumeric characters collapses to one space, which is
     * what makes "Trevenant & Dusknoir-GX" and "Trevenant & Dusknoir GX" the
     * same card. Word boundaries survive so that a missing word costs more
     * edits than a punctuation difference.
     *
     * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
     */
    public function normalize(string $name): string
    {
        $lowercased = strtolower($this->toAscii($name));

        // Apostrophes are intra-word, so they are dropped rather than turned
        // into a separator: "Professor's Research" and "Professors Research"
        // must fold to the same two words, not to "professor s research".
        $unquoted = str_replace("'", '', $lowercased);
        $spaced = preg_replace('/[^a-z0-9]+/', ' ', $unquoted) ?? $unquoted;

        return trim($spaced);
    }

    /**
     * Whether two names are the same card once folded.
     *
     * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
     */
    public function isExactMatch(string $first, string $second): bool
    {
        $normalizedFirst = $this->normalize($first);

        return '' !== $normalizedFirst && $normalizedFirst === $this->normalize($second);
    }

    /**
     * Similarity between two names on a 0.0–1.0 scale.
     *
     * Uses Levenshtein rather than similar_text because it is symmetric — the
     * caller compares one pasted name against several candidates and needs the
     * scores to be mutually comparable. Card names are capped at 100 characters
     * by the column definition, well inside levenshtein()'s 255-byte limit.
     *
     * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
     */
    public function score(string $first, string $second): float
    {
        $normalizedFirst = $this->normalize($first);
        $normalizedSecond = $this->normalize($second);

        if ('' === $normalizedFirst || '' === $normalizedSecond) {
            return 0.0;
        }

        if ($normalizedFirst === $normalizedSecond) {
            return 1.0;
        }

        $longestLength = max(\strlen($normalizedFirst), \strlen($normalizedSecond));
        $distance = levenshtein($normalizedFirst, $normalizedSecond);

        return max(0.0, 1.0 - $distance / $longestLength);
    }

    /**
     * Strips accents and other diacritics, leaving ASCII.
     *
     * Prefers the intl transliterator (installed in the production image) and
     * falls back to iconv, which is a hard composer requirement.
     */
    private function toAscii(string $value): string
    {
        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');

        if ($transliterator instanceof \Transliterator) {
            $transliterated = $transliterator->transliterate($value);

            if (\is_string($transliterated)) {
                return $transliterated;
            }
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return \is_string($converted) ? $converted : $value;
    }
}
