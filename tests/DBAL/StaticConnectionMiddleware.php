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

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * DBAL middleware that caches the underlying driver connection statically.
 *
 * When Symfony reboots the kernel (e.g. on each createClient()), the new DBAL
 * Connection reconnects through this middleware and gets the same underlying PDO,
 * preserving any open test transaction.
 *
 * Registered only in the test environment via config/services_test.yaml.
 */
class StaticConnectionMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new StaticDriver($driver);
    }
}
