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

namespace App\Twig\Extension;

use App\Service\DeckList\CardNumberFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @see docs/features.md F6.7 — Export deck list as PTCGL text
 */
final class CardNumberExtension extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('card_number', [CardNumberFormatter::class, 'display']),
        ];
    }
}
