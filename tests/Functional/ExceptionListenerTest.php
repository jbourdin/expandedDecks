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

class ExceptionListenerTest extends AbstractFunctionalTest
{
    public function testXhrRequestReturnsJson(): void
    {
        $this->client->xmlHttpRequest('GET', '/test-error/404');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Not Found', $data['error']);
        self::assertSame(404, $data['status']);
    }

    public function testXhrRequestInTestEnvIncludesDebugFields(): void
    {
        $this->client->xmlHttpRequest('GET', '/test-error/500');

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('message', $data);
        self::assertArrayHasKey('trace', $data);
    }

    public function testJsonAcceptHeaderReturnsJson(): void
    {
        $this->client->request('GET', '/test-error/403', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Forbidden', $data['error']);
        self::assertSame(403, $data['status']);
    }

    public function testNonHtmlAcceptReturnsEmptyBody(): void
    {
        $this->client->request('GET', '/test-error/404', [], [], [
            'HTTP_ACCEPT' => 'image/png',
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('', $this->client->getResponse()->getContent());
    }

    public function testHtmlRequestRendersDevTemplate(): void
    {
        $this->client->request('GET', '/test-error/404');

        self::assertResponseStatusCodeSame(404);

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('ditto.png', $content);
        self::assertStringContainsString('Stack trace', $content);
    }

    public function testHtmlRequestContainsSpriteForEachCode(): void
    {
        $this->client->request('GET', '/test-error/403');
        self::assertStringContainsString('snorlax.png', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/test-error/429');
        self::assertStringContainsString('maushold-family-of-four.png', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/test-error/500');
        self::assertStringContainsString('porygon.png', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/test-error/418');
        self::assertStringContainsString('psyduck.png', (string) $this->client->getResponse()->getContent());
    }

    public function testNonHttpExceptionReturns500(): void
    {
        // A generic non-HTTP exception triggered via a non-existent route
        // doesn't apply here; we test the status fallback via XHR to /test-error/500
        $this->client->xmlHttpRequest('GET', '/test-error/500');

        self::assertResponseStatusCodeSame(500);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(500, $data['status']);
        self::assertSame('Internal Server Error', $data['error']);
    }
}
