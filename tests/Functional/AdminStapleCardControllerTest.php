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

/**
 * @see docs/features.md F6.15 — Staple cards
 */
class AdminStapleCardControllerTest extends AbstractFunctionalTest
{
    public function testReorderRejectsMissingCsrfToken(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('POST', '/admin/staple-cards/reorder/pokemon', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '[]');

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('Invalid CSRF token', (string) $this->client->getResponse()->getContent());
    }
}
