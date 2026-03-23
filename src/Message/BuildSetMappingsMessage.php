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

namespace App\Message;

/**
 * Triggers an async rebuild of the TCGdex set mappings table.
 */
readonly class BuildSetMappingsMessage
{
}
