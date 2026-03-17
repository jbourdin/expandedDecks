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

namespace App\Sentry;

use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Drops client-error (4xx) HTTP exceptions from Sentry reporting.
 *
 * 4xx errors are expected in normal operation (bad URLs, expired links, bots)
 * and should not pollute the Sentry issue stream.
 */
final class BeforeSendCallback
{
    public function __invoke(Event $event, ?EventHint $hint): ?Event
    {
        $exception = $hint?->exception;

        if ($exception instanceof HttpExceptionInterface
            && $exception->getStatusCode() >= 400
            && $exception->getStatusCode() < 500
        ) {
            return null;
        }

        return $event;
    }
}
