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

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Validates a Friendly Captcha response token.
 *
 * @see docs/features.md F12.4 — Bot protection with Friendly Captcha
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class FriendlyCaptcha extends Constraint
{
    public string $message = 'app.captcha.verification_failed';
}
