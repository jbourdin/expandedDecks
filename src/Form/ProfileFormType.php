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

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F1.3 — User profile
 *
 * @extends AbstractType<User>
 */
class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'app.form.label.first_name',
            ])
            ->add('lastName', TextType::class, [
                'label' => 'app.form.label.last_name',
            ])
            ->add('screenName', TextType::class, [
                'label' => 'app.form.label.screen_name',
            ])
            ->add('playerId', TextType::class, [
                'label' => 'app.form.label.player_id',
                'required' => false,
            ])
            ->add('preferredLocale', ChoiceType::class, [
                'label' => 'app.form.label.preferred_locale',
                'choices' => [
                    'English' => 'en',
                    'Français' => 'fr',
                ],
            ])
            ->add('timezone', TimezoneType::class, [
                'label' => 'app.form.label.timezone',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
