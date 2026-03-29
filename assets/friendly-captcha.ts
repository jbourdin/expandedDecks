/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Auto-attaches the Friendly Captcha v2 widget to any `.frc-captcha` element on the page.
 *
 * @see docs/features.md F12.4 — Bot protection with Friendly Captcha
 */
import { FriendlyCaptchaSDK } from '@friendlycaptcha/sdk';

const sdk = new FriendlyCaptchaSDK();
sdk.attach();
