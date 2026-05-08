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

namespace App\Form;

use App\Constants\CardHotness;

/**
 * DTO bound to {@see StapleCardCreateFormType}. The editor types a single card code
 * (e.g. "LOR-093"), the controller normalises it to (setCode, cardNumber), and the
 * enricher takes it from there.
 *
 * @see docs/features.md F6.15 — Staple cards
 */
final class StapleCardCreateData
{
    public string $code = '';

    public int $hotness = CardHotness::STAPLE_THRESHOLD;

    public ?string $note = null;
}
