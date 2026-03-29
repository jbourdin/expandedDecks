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

namespace App\Form\Type;

use App\Validator\FriendlyCaptcha;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type that renders the Friendly Captcha v2 widget and validates the response server-side.
 *
 * @see docs/features.md F12.4 — Bot protection with Friendly Captcha
 *
 * @extends AbstractType<string>
 */
class FriendlyCaptchaType extends AbstractType
{
    public function __construct(
        private readonly string $friendlyCaptchaSitekey,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'constraints' => [new FriendlyCaptcha()],
            'error_bubbling' => false,
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['sitekey'] = $this->friendlyCaptchaSitekey;
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'friendly_captcha';
    }
}
