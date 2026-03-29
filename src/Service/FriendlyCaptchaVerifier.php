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

namespace App\Service;

use FriendlyCaptcha\SDK\Client;
use FriendlyCaptcha\SDK\ClientConfig;
use Psr\Log\LoggerInterface;

/**
 * Verifies Friendly Captcha response tokens via the official SDK.
 *
 * Returns true (accept) when the API key is not configured, so that
 * tests and unconfigured dev environments are not blocked.
 *
 * @see docs/features.md F12.4 — Bot protection with Friendly Captcha
 */
class FriendlyCaptchaVerifier
{
    private ?Client $client;

    public function __construct(
        string $friendlyCaptchaSitekey,
        string $friendlyCaptchaApiKey,
        string $friendlyCaptchaEndpoint,
        private readonly LoggerInterface $logger,
    ) {
        if ('' === $friendlyCaptchaApiKey) {
            $this->client = null;

            return;
        }

        $config = new ClientConfig();
        $config
            ->setAPIKey($friendlyCaptchaApiKey)
            ->setSitekey($friendlyCaptchaSitekey)
            ->setApiEndpoint($friendlyCaptchaEndpoint);

        $this->client = new Client($config);
    }

    /**
     * Returns true if the captcha response should be accepted.
     */
    public function verify(string $response): bool
    {
        if (!$this->client instanceof Client) {
            $this->logger->warning('Friendly Captcha is not configured — skipping verification.');

            return true;
        }

        $result = $this->client->verifyCaptchaResponse($response);

        if (!$result->wasAbleToVerify()) {
            $this->logger->error('Friendly Captcha verification request failed.', [
                'errorCode' => $result->getErrorCode(),
            ]);
        }

        return $result->shouldAccept();
    }
}
