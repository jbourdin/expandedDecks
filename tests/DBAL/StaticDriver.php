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

namespace App\Tests\DBAL;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Driver wrapper that caches the connection statically across kernel reboots.
 *
 * Ensures all kernel instances share the same underlying PDO connection,
 * so the outer test transaction is preserved between setUp/tearDown cycles.
 */
class StaticDriver extends AbstractDriverMiddleware
{
    private static ?TestConnection $connection = null;

    public function connect(array $params): Connection
    {
        if (null === self::$connection) {
            self::$connection = new TestConnection(parent::connect($params));
        }

        return self::$connection;
    }

    public static function getConnection(): ?TestConnection
    {
        return self::$connection;
    }

    public static function reset(): void
    {
        self::$connection = null;
    }
}
