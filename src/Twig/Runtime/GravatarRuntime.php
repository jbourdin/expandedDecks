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

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

/**
 * Builds Gravatar avatar URLs from an email address.
 *
 * @see docs/features.md F1.11 — Gravatar avatar
 */
class GravatarRuntime implements RuntimeExtensionInterface
{
    /**
     * Returns a Gravatar image URL for the given email.
     *
     * @param string $email user email address
     * @param int    $size  image size in pixels (1–2048)
     */
    public function url(string $email, int $size = 32): string
    {
        $hash = md5(strtolower(trim($email)));

        return \sprintf('https://www.gravatar.com/avatar/%s?s=%d&d=mp', $hash, $size);
    }
}
