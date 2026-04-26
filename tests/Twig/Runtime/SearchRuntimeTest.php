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

namespace App\Tests\Twig\Runtime;

use App\Service\Search\SearchResult;
use App\Twig\Runtime\SearchRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see docs/features.md F18.2 — Global search results page
 */
class SearchRuntimeTest extends TestCase
{
    public function testSearchResultUrlForArchetype(): void
    {
        $runtime = $this->createRuntime('/en/archetypes/regidrago');

        $result = new SearchResult('archetype', 'Regidrago', '', 'regidrago');
        $url = $runtime->searchResultUrl($result);

        self::assertSame('/en/archetypes/regidrago', $url);
    }

    public function testSearchResultUrlForVariant(): void
    {
        $runtime = $this->createRuntime('/en/archetypes/regidrago');

        $result = new SearchResult('variant', 'Turbo Regidrago', '', 'XYZ789', archetypeSlug: 'regidrago');
        $url = $runtime->searchResultUrl($result);

        self::assertSame('/en/archetypes/regidrago#XYZ789', $url);
    }

    public function testSearchResultUrlForPage(): void
    {
        $runtime = $this->createRuntime('/en/pages/welcome');

        $result = new SearchResult('page', 'Welcome', '', 'welcome');
        $url = $runtime->searchResultUrl($result);

        self::assertSame('/en/pages/welcome', $url);
    }

    public function testSearchResultUrlForEvent(): void
    {
        $runtime = $this->createRuntime('/event/7');

        $result = new SearchResult('event', 'Paris League', '', '7', '2026-05-01');
        $url = $runtime->searchResultUrl($result);

        self::assertSame('/event/7', $url);
    }

    public function testSearchResultUrlForDeck(): void
    {
        $runtime = $this->createRuntime('/deck/ABC123');

        $result = new SearchResult('deck', 'My Deck', '', 'ABC123', 'Julien');
        $url = $runtime->searchResultUrl($result);

        self::assertSame('/deck/ABC123', $url);
    }

    public function testSearchResultUrlForUnknownType(): void
    {
        $runtime = $this->createRuntime('/');

        $result = new SearchResult('unknown', 'Something', '', 'slug');
        $url = $runtime->searchResultUrl($result);

        self::assertSame('/', $url);
    }

    private function createRuntime(string $expectedUrl): SearchRuntime
    {
        $request = Request::create('/');
        $request->setLocale('en');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn($expectedUrl);

        return new SearchRuntime($urlGenerator, $requestStack);
    }
}
