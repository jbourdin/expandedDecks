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

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\LocaleListener;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\LocaleSwitcher;

class LocaleListenerTest extends TestCase
{
    public function testAuthenticatedUserLocaleIsApplied(): void
    {
        $user = new User();
        $user->setPreferredLocale('fr');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $localeSwitcher->expects(self::once())->method('setLocale')->with('fr');

        $request = $this->createRequestWithSession();
        $event = $this->createRequestEvent($request);

        $listener = new LocaleListener($security, $localeSwitcher);
        $listener($event);

        self::assertSame('fr', $request->getLocale());
        self::assertSame('fr', $request->getSession()->get('_locale'));
    }

    public function testSessionLocaleUsedForAnonymousUser(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $localeSwitcher->expects(self::once())->method('setLocale')->with('fr');

        $request = $this->createRequestWithSession();
        $request->getSession()->set('_locale', 'fr');
        $event = $this->createRequestEvent($request);

        $listener = new LocaleListener($security, $localeSwitcher);
        $listener($event);

        self::assertSame('fr', $request->getLocale());
    }

    public function testAcceptLanguageDetectionForAnonymousUser(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $localeSwitcher->expects(self::once())->method('setLocale')->with('fr');

        $request = $this->createRequestWithSession();
        $request->headers->set('Accept-Language', 'fr-FR,fr;q=0.9,en;q=0.8');
        $event = $this->createRequestEvent($request);

        $listener = new LocaleListener($security, $localeSwitcher);
        $listener($event);

        self::assertSame('fr', $request->getLocale());
        self::assertSame('fr', $request->getSession()->get('_locale'));
    }

    public function testDefaultLocaleWhenNoSignal(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $localeSwitcher->expects(self::once())->method('setLocale')->with('en');

        $request = $this->createRequestWithSession();
        $event = $this->createRequestEvent($request);

        $listener = new LocaleListener($security, $localeSwitcher);
        $listener($event);

        self::assertSame('en', $request->getLocale());
    }

    public function testUnsupportedSessionLocaleIsIgnored(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $localeSwitcher->expects(self::once())->method('setLocale')->with('en');

        $request = $this->createRequestWithSession();
        $request->getSession()->set('_locale', 'de');
        $event = $this->createRequestEvent($request);

        $listener = new LocaleListener($security, $localeSwitcher);
        $listener($event);

        self::assertSame('en', $request->getLocale());
    }

    public function testAcceptLanguageWithUnsupportedLocalesFallsToDefault(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $localeSwitcher->expects(self::once())->method('setLocale')->with('en');

        $request = $this->createRequestWithSession();
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.9,ja;q=0.8');
        $event = $this->createRequestEvent($request);

        $listener = new LocaleListener($security, $localeSwitcher);
        $listener($event);

        self::assertSame('en', $request->getLocale());
    }

    private function createRequestWithSession(): Request
    {
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        return $request;
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
