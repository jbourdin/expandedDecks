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

namespace App\Tests\Validator;

use App\Service\FriendlyCaptchaVerifier;
use App\Validator\FriendlyCaptcha;
use App\Validator\FriendlyCaptchaValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * @see docs/features.md F12.4 — Bot protection with Friendly Captcha
 */
class FriendlyCaptchaValidatorTest extends TestCase
{
    public function testThrowsOnWrongConstraintType(): void
    {
        $verifier = $this->createStub(FriendlyCaptchaVerifier::class);
        $validator = new FriendlyCaptchaValidator($verifier);

        $this->expectException(UnexpectedTypeException::class);

        $validator->validate('token', $this->createStub(Constraint::class));
    }

    public function testNoViolationWhenVerifierAccepts(): void
    {
        $verifier = $this->createStub(FriendlyCaptchaVerifier::class);
        $verifier->method('verify')->willReturn(true);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        $validator = new FriendlyCaptchaValidator($verifier);
        $validator->validateInContext('valid-token', new FriendlyCaptcha(), $context);
    }

    public function testAddsViolationWhenVerifierRejects(): void
    {
        $verifier = $this->createStub(FriendlyCaptchaVerifier::class);
        $verifier->method('verify')->willReturn(false);

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects(self::once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::once())
            ->method('buildViolation')
            ->with('app.captcha.verification_failed')
            ->willReturn($violationBuilder);

        $validator = new FriendlyCaptchaValidator($verifier);
        $validator->validateInContext('bad-token', new FriendlyCaptcha(), $context);
    }

    public function testNonStringValueIsTreatedAsEmptyString(): void
    {
        $verifier = $this->createMock(FriendlyCaptchaVerifier::class);
        $verifier->expects(self::once())
            ->method('verify')
            ->with('')
            ->willReturn(true);

        $context = $this->createStub(ExecutionContextInterface::class);

        $validator = new FriendlyCaptchaValidator($verifier);
        $validator->validateInContext(null, new FriendlyCaptcha(), $context);
    }
}
