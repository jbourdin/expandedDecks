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

namespace App\Tests\Service\Channel;

use App\Entity\Channel;
use App\Repository\ChannelRepository;
use App\Service\Channel\ChannelContext;
use App\Service\Channel\ChannelUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * @see docs/features.md F18.5 — Channel-aware route generation service
 */
final class ChannelUrlGeneratorTest extends TestCase
{
    private Channel $appChannel;
    private Channel $contentChannel;

    protected function setUp(): void
    {
        $this->appChannel = (new Channel())
            ->setCode('app')
            ->setDomain('expandeddecks.wip')
            ->setEnableDecks(true)
            ->setEnableRegister(true)
            ->setEnableEvents(true)
            ->setEnableBorrows(true)
            ->setEnableArchetypes(false);

        $this->contentChannel = (new Channel())
            ->setCode('content')
            ->setDomain('expandedtalks.wip')
            ->setEnableDecks(false)
            ->setEnableRegister(false)
            ->setEnableEvents(false)
            ->setEnableBorrows(false)
            ->setEnableArchetypes(true);
    }

    public function testForChannelReturnsPathWhenSameChannel(): void
    {
        $generator = $this->createGenerator($this->appChannel);

        $result = $generator->forChannel('app', 'app_deck_show', ['short_tag' => 'AB3K7N']);

        self::assertSame('/deck/AB3K7N', $result);
    }

    public function testForChannelReturnsAbsoluteUrlWhenDifferentChannel(): void
    {
        $generator = $this->createGenerator($this->appChannel);

        $result = $generator->forChannel('content', 'app_archetype_list', []);

        self::assertSame('https://expandedtalks.wip/archetypes', $result);
    }

    public function testForChannelFallsBackToPathWhenTargetNotFound(): void
    {
        $generator = $this->createGenerator($this->appChannel, findByCode: null);

        $result = $generator->forChannel('unknown', 'app_deck_list', []);

        self::assertSame('/decks', $result);
    }

    public function testForFeatureReturnsPathWhenCurrentChannelHasFeature(): void
    {
        $generator = $this->createGenerator($this->appChannel);

        $result = $generator->forFeature('decks', 'app_deck_show', ['short_tag' => 'AB3K7N']);

        self::assertSame('/deck/AB3K7N', $result);
    }

    public function testForFeatureCrossDomainWhenCurrentChannelLacksFeature(): void
    {
        $generator = $this->createGenerator($this->contentChannel, findAll: [$this->contentChannel, $this->appChannel]);

        $result = $generator->forFeature('decks', 'app_deck_show', ['short_tag' => 'AB3K7N']);

        self::assertSame('https://expandeddecks.wip/deck/AB3K7N', $result);
    }

    public function testForFeatureArchetypesCrossDomainFromAppChannel(): void
    {
        $generator = $this->createGenerator($this->appChannel, findAll: [$this->appChannel, $this->contentChannel]);

        $result = $generator->forFeature('archetypes', 'app_archetype_show', ['slug' => 'iron-thorns']);

        self::assertSame('https://expandedtalks.wip/archetype/iron-thorns', $result);
    }

    public function testForFeatureArchetypesStaysLocalOnContentChannel(): void
    {
        $generator = $this->createGenerator($this->contentChannel);

        $result = $generator->forFeature('archetypes', 'app_archetype_list', []);

        self::assertSame('/archetypes', $result);
    }

    public function testForFeatureRegisterCrossDomainFromContentChannel(): void
    {
        $generator = $this->createGenerator($this->contentChannel, findAll: [$this->contentChannel, $this->appChannel]);

        $result = $generator->forFeature('register', 'app_login', []);

        self::assertSame('https://expandeddecks.wip/login', $result);
    }

    public function testForFeatureFallsBackToPathWhenNoChannelHasFeature(): void
    {
        $channelWithNothing = (new Channel())->setCode('empty')->setDomain('empty.wip');
        $generator = $this->createGenerator($channelWithNothing, findAll: [$channelWithNothing]);

        $result = $generator->forFeature('decks', 'app_deck_list', []);

        self::assertSame('/decks', $result);
    }

    public function testForFeatureCachesChannelLookup(): void
    {
        $generator = $this->createGenerator($this->contentChannel, findAll: [$this->contentChannel, $this->appChannel]);

        // Call twice — second call uses cache
        $result1 = $generator->forFeature('decks', 'app_deck_list', []);
        $result2 = $generator->forFeature('decks', 'app_deck_list', []);

        self::assertSame($result1, $result2);
    }

    /**
     * @see docs/features.md F18.25 — Canonical URLs on all public pages
     */
    public function testCanonicalUrlAlwaysReturnsAbsoluteOnCorrectChannel(): void
    {
        // Even on the app channel, canonical for archetypes → content channel
        $generator = $this->createGenerator($this->appChannel, findAll: [$this->contentChannel, $this->appChannel]);

        $result = $generator->canonicalUrl('archetypes', 'app_archetype_show', ['slug' => 'lugia-vstar']);

        self::assertSame('https://expandedtalks.wip/archetype/lugia-vstar', $result);
    }

    /**
     * @see docs/features.md F18.25 — Canonical URLs on all public pages
     */
    public function testCanonicalUrlReturnsAbsoluteEvenOnSameChannel(): void
    {
        // On content channel, canonical for archetypes is still absolute
        $generator = $this->createGenerator($this->contentChannel, findAll: [$this->contentChannel, $this->appChannel]);

        $result = $generator->canonicalUrl('archetypes', 'app_archetype_show', ['slug' => 'lugia-vstar']);

        self::assertSame('https://expandedtalks.wip/archetype/lugia-vstar', $result);
    }

    /**
     * @see docs/features.md F18.25 — Canonical URLs on all public pages
     */
    public function testCanonicalUrlForDecksPointsToAppChannel(): void
    {
        $generator = $this->createGenerator($this->contentChannel, findAll: [$this->contentChannel, $this->appChannel]);

        $result = $generator->canonicalUrl('decks', 'app_deck_show', ['short_tag' => 'AB3K7N']);

        self::assertSame('https://expandeddecks.wip/deck/AB3K7N', $result);
    }

    /**
     * @see docs/features.md F18.25 — Canonical URLs on all public pages
     */
    public function testSelfCanonicalUrlUsesCurrentChannel(): void
    {
        $generator = $this->createGenerator($this->appChannel);

        $result = $generator->selfCanonicalUrl('app_deck_list');

        self::assertSame('https://expandeddecks.wip/decks', $result);
    }

    /**
     * @see docs/features.md F18.28 — Open Graph and Twitter Card meta tags
     */
    public function testAbsolutizeUrlPrependsChannelDomainToRelativePath(): void
    {
        $generator = $this->createGenerator($this->contentChannel);

        $result = $generator->absolutizeUrl('/api/editor/image/banner.png');

        self::assertSame('https://expandedtalks.wip/api/editor/image/banner.png', $result);
    }

    /**
     * @see docs/features.md F18.28 — Open Graph and Twitter Card meta tags
     */
    public function testAbsolutizeUrlReturnsAbsoluteHttpsUrlUnchanged(): void
    {
        $generator = $this->createGenerator($this->appChannel);

        $result = $generator->absolutizeUrl('https://cdn.example.com/img.png');

        self::assertSame('https://cdn.example.com/img.png', $result);
    }

    /**
     * @see docs/features.md F18.28 — Open Graph and Twitter Card meta tags
     */
    public function testAbsolutizeUrlReturnsAbsoluteHttpUrlUnchanged(): void
    {
        $generator = $this->createGenerator($this->appChannel);

        $result = $generator->absolutizeUrl('http://legacy.example.com/img.png');

        self::assertSame('http://legacy.example.com/img.png', $result);
    }

    /**
     * @see docs/features.md F18.28 — Open Graph and Twitter Card meta tags
     */
    public function testAbsolutizeUrlReturnsEmptyStringUnchanged(): void
    {
        $generator = $this->createGenerator($this->appChannel);

        self::assertSame('', $generator->absolutizeUrl(''));
    }

    /**
     * @see docs/features.md F18.28 — Open Graph and Twitter Card meta tags
     */
    public function testAbsolutizeUrlUsesCurrentChannelDomain(): void
    {
        $generator = $this->createGenerator($this->appChannel);

        $result = $generator->absolutizeUrl('/uploads/og.png');

        self::assertSame('https://expandeddecks.wip/uploads/og.png', $result);
    }

    /**
     * @param list<Channel>|null $findAll
     */
    private function createGenerator(
        Channel $currentChannel,
        ?Channel $findByCode = null,
        ?array $findAll = null,
    ): ChannelUrlGenerator {
        $context = $this->createChannelContext($currentChannel);

        $repository = $this->createStub(ChannelRepository::class);

        if (null !== $findByCode) {
            $repository->method('findByCode')->willReturn($findByCode);
        } else {
            $repository->method('findByCode')->willReturnCallback(
                fn (string $code): ?Channel => match ($code) {
                    'app' => $this->appChannel,
                    'content' => $this->contentChannel,
                    default => null,
                },
            );
        }

        if (null !== $findAll) {
            $repository->method('findAll')->willReturn($findAll);
        }

        return new ChannelUrlGenerator($context, $repository, $this->createUrlGeneratorStub());
    }

    private function createChannelContext(Channel $channel): ChannelContext
    {
        $request = new Request();
        $request->attributes->set('_channel', $channel);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new ChannelContext($requestStack);
    }

    private function createUrlGeneratorStub(): UrlGeneratorInterface
    {
        $requestContext = new RequestContext(scheme: 'https');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')->willReturn($requestContext);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $parameters): string {
                return match ($route) {
                    'app_deck_show' => '/deck/'.($parameters['short_tag'] ?? ''),
                    'app_deck_list' => '/decks',
                    'app_archetype_list' => '/archetypes',
                    'app_archetype_show' => '/archetype/'.($parameters['slug'] ?? ''),
                    'app_event_list' => '/events',
                    'app_login' => '/login',
                    default => '/'.$route,
                };
            },
        );

        return $urlGenerator;
    }
}
