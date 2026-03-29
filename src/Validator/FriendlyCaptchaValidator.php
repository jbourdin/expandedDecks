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

use App\Service\FriendlyCaptchaVerifier;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Server-side verification of Friendly Captcha responses via the official SDK.
 *
 * @see docs/features.md F12.4 — Bot protection with Friendly Captcha
 */
class FriendlyCaptchaValidator extends ConstraintValidator
{
    public function __construct(
        private readonly FriendlyCaptchaVerifier $verifier,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof FriendlyCaptcha) {
            throw new UnexpectedTypeException($constraint, FriendlyCaptcha::class);
        }

        $response = \is_string($value) ? $value : '';

        if (!$this->verifier->verify($response)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
