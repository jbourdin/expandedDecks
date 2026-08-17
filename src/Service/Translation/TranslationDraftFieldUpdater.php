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

namespace App\Service\Translation;

use App\Attribute\Translatable;
use App\Entity\ArchetypeTranslation;
use App\Entity\ArchetypeTranslationRevision;
use App\Entity\DeckTranslation;
use App\Entity\DeckTranslationRevision;
use App\Entity\MenuCategoryTranslation;
use App\Entity\MenuCategoryTranslationRevision;
use App\Entity\PageTranslation;
use App\Entity\PageTranslationRevision;
use App\Entity\TranslationRevisionInterface;

/**
 * Applies translator input onto a draft revision — only fields marked
 * `#[Translatable]` on the paired live-translation entity are writable,
 * so the autosave endpoint can never touch workflow metadata.
 *
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 */
final class TranslationDraftFieldUpdater
{
    /**
     * Revision class → live translation class carrying the `#[Translatable]`
     * attribute registry.
     *
     * @var array<class-string<TranslationRevisionInterface>, class-string>
     */
    private const array FIELD_REGISTRY = [
        PageTranslationRevision::class => PageTranslation::class,
        ArchetypeTranslationRevision::class => ArchetypeTranslation::class,
        MenuCategoryTranslationRevision::class => MenuCategoryTranslation::class,
        DeckTranslationRevision::class => DeckTranslation::class,
    ];

    /** @var array<class-string, list<string>> */
    private static array $translatableFieldsCache = [];

    /**
     * @return list<string> writable field names for this revision
     */
    public function writableFields(TranslationRevisionInterface $revision): array
    {
        return self::translatableFields(self::FIELD_REGISTRY[$revision::class]);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return list<string> the fields actually applied (unknown names are ignored)
     */
    public function apply(TranslationRevisionInterface $revision, array $fields): array
    {
        $writable = $this->writableFields($revision);
        $revisionReflection = new \ReflectionClass($revision);
        $applied = [];

        foreach ($fields as $field => $value) {
            if (!\in_array($field, $writable, true)) {
                continue;
            }
            if (null !== $value && !\is_string($value)) {
                continue;
            }

            $property = $revisionReflection->getProperty($field);
            $type = $property->getType();
            if (null === $value && $type instanceof \ReflectionNamedType && !$type->allowsNull()) {
                $value = '';
            }

            $property->setValue($revision, $value);
            $applied[] = $field;
        }

        return $applied;
    }

    /**
     * @param class-string $entityClass
     *
     * @return list<string>
     */
    private static function translatableFields(string $entityClass): array
    {
        if (isset(self::$translatableFieldsCache[$entityClass])) {
            return self::$translatableFieldsCache[$entityClass];
        }

        $fields = [];
        foreach ((new \ReflectionClass($entityClass))->getProperties() as $property) {
            if ([] !== $property->getAttributes(Translatable::class)) {
                $fields[] = $property->getName();
            }
        }

        return self::$translatableFieldsCache[$entityClass] = $fields;
    }
}
