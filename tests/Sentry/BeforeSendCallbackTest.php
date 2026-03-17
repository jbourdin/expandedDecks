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

namespace App\Tests\Sentry;

use App\Sentry\BeforeSendCallback;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class BeforeSendCallbackTest extends TestCase
{
    private BeforeSendCallback $callback;

    protected function setUp(): void
    {
        $this->callback = new BeforeSendCallback();
    }

    public function testDrops404Exception(): void
    {
        $event = Event::createEvent();
        $hint = EventHint::fromArray(['exception' => new NotFoundHttpException()]);

        $result = ($this->callback)($event, $hint);

        self::assertNull($result);
    }

    public function testDropsSecurityAccessDeniedException(): void
    {
        $event = Event::createEvent();
        $hint = EventHint::fromArray(['exception' => new AccessDeniedException()]);

        $result = ($this->callback)($event, $hint);

        self::assertNull($result);
    }

    public function testDrops403Exception(): void
    {
        $event = Event::createEvent();
        $hint = EventHint::fromArray(['exception' => new AccessDeniedHttpException()]);

        $result = ($this->callback)($event, $hint);

        self::assertNull($result);
    }

    public function testDrops422Exception(): void
    {
        $event = Event::createEvent();
        $hint = EventHint::fromArray(['exception' => new UnprocessableEntityHttpException()]);

        $result = ($this->callback)($event, $hint);

        self::assertNull($result);
    }

    public function testKeeps500Exception(): void
    {
        $event = Event::createEvent();
        $hint = EventHint::fromArray(['exception' => new \RuntimeException('Server error')]);

        $result = ($this->callback)($event, $hint);

        self::assertSame($event, $result);
    }

    public function testKeepsEventWithNullHint(): void
    {
        $event = Event::createEvent();

        $result = ($this->callback)($event, null);

        self::assertSame($event, $result);
    }

    public function testKeepsEventWithHintWithoutException(): void
    {
        $event = Event::createEvent();
        $hint = new EventHint();

        $result = ($this->callback)($event, $hint);

        self::assertSame($event, $result);
    }
}
