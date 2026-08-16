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

namespace App\Entity;

use App\Enum\TranslationRevisionState;

/**
 * A point-in-time snapshot of a translation row's workflow fields.
 *
 * One revision table exists per content type, holding the history of all
 * locales including the source language. The live `*Translation` tables stay
 * authoritative for rendering; revisions are purely additive history.
 *
 * @see docs/features.md F9.7 — Translation foundation
 * @see docs/technicalities/translation_workflow.md
 */
interface TranslationRevisionInterface
{
    public function getId(): ?int;

    public function getLocale(): string;

    public function getState(): TranslationRevisionState;

    public function setState(TranslationRevisionState $state): static;

    public function getAuthor(): ?User;

    public function setAuthor(?User $author): static;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getReviewedBy(): ?User;

    public function setReviewedBy(?User $reviewedBy): static;

    public function getReviewedAt(): ?\DateTimeImmutable;

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static;

    public function getReviewComment(): ?string;

    public function setReviewComment(?string $reviewComment): static;

    /**
     * The source-locale revision this translation was based on (`null` on
     * source-locale rows — they ARE the source). The setter is not part of
     * the interface: each entity types it against its own class so the
     * self-referencing association stays concrete.
     */
    public function getSourceRevision(): ?self;

    /**
     * The translated content entity this revision belongs to.
     */
    public function getSubject(): Page|Archetype|MenuCategory|Deck;

    /**
     * Name of the Doctrine field referencing the subject, for generic
     * latest-source-revision queries.
     */
    public static function subjectFieldName(): string;
}
