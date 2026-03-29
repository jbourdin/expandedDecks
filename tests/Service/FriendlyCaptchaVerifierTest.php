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

namespace App\Tests\Service;

use App\Service\FriendlyCaptchaVerifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see docs/features.md F12.4 — Bot protection with Friendly Captcha
 */
class FriendlyCaptchaVerifierTest extends TestCase
{
    public function testVerifyReturnsTrueWhenApiKeyIsEmpty(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Friendly Captcha is not configured — skipping verification.');

        $verifier = new FriendlyCaptchaVerifier('sitekey', '', 'eu', $logger);

        self::assertTrue($verifier->verify('any-response'));
    }

    public function testVerifyReturnsTrueWhenApiKeyIsEmptyRegardlessOfResponse(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $verifier = new FriendlyCaptchaVerifier('', '', 'global', $logger);

        self::assertTrue($verifier->verify(''));
    }
}
