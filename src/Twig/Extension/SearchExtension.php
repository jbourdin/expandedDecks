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

use App\Twig\Runtime\SearchRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @see docs/features.md F18.2 — Global search results page
 */
class SearchExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('_search_result_url', [SearchRuntime::class, 'searchResultUrl']),
        ];
    }
}
