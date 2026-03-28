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

class CdnErrorControllerTest extends AbstractFunctionalTest
{
    public function testCdnError404Returns200WithDittoSprite(): void
    {
        $this->client->request('GET', '/cdn-error/404');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('img[alt="Ditto"]');
    }

    public function testCdnError403Returns200WithSnorlaxSprite(): void
    {
        $this->client->request('GET', '/cdn-error/403');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('img[alt="Snorlax"]');
    }

    public function testCdnError429Returns200WithMausholdSprite(): void
    {
        $this->client->request('GET', '/cdn-error/429');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('img[alt="Maushold"]');
    }

    public function testCdnError500Returns200WithPorygonSprite(): void
    {
        $this->client->request('GET', '/cdn-error/500');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('img[alt="Porygon"]');
    }

    public function testCdnErrorGenericReturns200WithPsyduckSprite(): void
    {
        $this->client->request('GET', '/cdn-error/418');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('img[alt="Psyduck"]');
    }

    public function testCdnErrorContainsBackHomeLink(): void
    {
        $this->client->request('GET', '/cdn-error/404');

        self::assertSelectorExists('a[href="/"]');
    }
}
