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

namespace App\Tests\Functional;

class TestErrorControllerTest extends AbstractFunctionalTest
{
    public function testTestError404Returns404(): void
    {
        $this->client->request('GET', '/test-error/404');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTestError500Returns500(): void
    {
        $this->client->request('GET', '/test-error/500');

        self::assertResponseStatusCodeSame(500);
    }

    public function testTestError403Returns403(): void
    {
        $this->client->request('GET', '/test-error/403');

        self::assertResponseStatusCodeSame(403);
    }

    public function testTestError429Returns429(): void
    {
        $this->client->request('GET', '/test-error/429');

        self::assertResponseStatusCodeSame(429);
    }
}
