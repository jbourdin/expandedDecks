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

namespace App\Tests\Twig;

use App\Entity\Channel;
use App\Service\Channel\ChannelContext;
use App\Service\Channel\ChannelUrlGenerator;
use App\Twig\Runtime\ChannelRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @see docs/features.md F18.3 — Twig channel context and global template variables
 */
final class ChannelRuntimeTest extends TestCase
{
    public function testGetCurrentChannelDelegatesToContext(): void
    {
        $channel = (new Channel())->setCode('app');
        $runtime = $this->createRuntime($channel);

        self::assertSame($channel, $runtime->getCurrentChannel());
    }

    public function testIsChannelReturnsTrueForMatchingCode(): void
    {
        $runtime = $this->createRuntime((new Channel())->setCode('content'));

        self::assertTrue($runtime->isChannel('content'));
        self::assertFalse($runtime->isChannel('app'));
    }

    public function testChannelUrlDelegatesToGenerator(): void
    {
        $generator = $this->createStub(ChannelUrlGenerator::class);
        $generator->method('forChannel')->willReturn('https://expandedtalks.wip/archetypes');

        $runtime = new ChannelRuntime(
            $this->createChannelContext((new Channel())->setCode('app')),
            $generator,
        );

        self::assertSame('https://expandedtalks.wip/archetypes', $runtime->channelUrl('content', 'app_archetype_list'));
    }

    public function testFeatureUrlDelegatesToGenerator(): void
    {
        $generator = $this->createStub(ChannelUrlGenerator::class);
        $generator->method('forFeature')->willReturn('/deck/AB3K7N');

        $runtime = new ChannelRuntime(
            $this->createChannelContext((new Channel())->setCode('app')),
            $generator,
        );

        self::assertSame('/deck/AB3K7N', $runtime->featureUrl('decks', 'app_deck_show', ['short_tag' => 'AB3K7N']));
    }

    private function createRuntime(Channel $channel): ChannelRuntime
    {
        return new ChannelRuntime(
            $this->createChannelContext($channel),
            $this->createStub(ChannelUrlGenerator::class),
        );
    }

    private function createChannelContext(Channel $channel): ChannelContext
    {
        $request = new Request();
        $request->attributes->set('_channel', $channel);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new ChannelContext($requestStack);
    }
}
