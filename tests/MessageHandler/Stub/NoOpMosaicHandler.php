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

namespace App\Tests\MessageHandler\Stub;

use App\Message\GenerateDeckMosaicMessage;
use App\Message\GenerateMinifiedMosaicMessage;

/**
 * No-op handler replacing both mosaic generators in functional tests.
 *
 * Mosaic generation uses GD image manipulation and is too expensive for
 * the test suite. The HTML mosaic is the primary display; server-generated
 * images are a secondary export feature covered by dedicated unit tests.
 */
class NoOpMosaicHandler
{
    public function handleDeckMosaic(GenerateDeckMosaicMessage $message): void
    {
    }

    public function handleMinifiedMosaic(GenerateMinifiedMosaicMessage $message): void
    {
    }
}
