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
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
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
            ->add('yearOfBirth', IntegerType::class, [
                'label' => 'app.form.label.year_of_birth',
                'required' => false,
                'help' => 'app.form.help.year_of_birth',
            ])
            ->add('discordUsername', TextType::class, [
                'label' => 'app.form.label.discord_username',
                'required' => false,
                'help' => 'app.form.help.discord_username',
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
            ])
            ->add('showCardmarketExport', CheckboxType::class, [
                'label' => 'app.form.label.show_cardmarket_export',
                'required' => false,
            ])
            ->add('organizerRole', CheckboxType::class, [
                'label' => 'app.form.label.organizer_role',
                'required' => false,
                'mapped' => false,
                'disabled' => $options['organizer_role_locked'],
                'data' => $options['is_organizer'],
            ])
            // Public author/contributor profile (F19.8). Editable by all users;
            // the values surface publicly only on content the user is credited for.
            ->add('isPublicAuthor', CheckboxType::class, [
                'label' => 'app.form.label.author_is_public',
                'required' => false,
                'help' => 'app.form.help.author_is_public',
            ])
            ->add('credential', TextType::class, [
                'label' => 'app.form.label.author_credential',
                'required' => false,
                'help' => 'app.form.help.author_credential',
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'app.form.label.author_bio',
                'required' => false,
                'help' => 'app.form.help.author_bio',
            ])
            ->add('sameAs', TextareaType::class, [
                'label' => 'app.form.label.author_same_as',
                'required' => false,
                'help' => 'app.form.help.author_same_as',
            ])
            ->add('avatarUrl', UrlType::class, [
                'label' => 'app.form.label.author_avatar_url',
                'required' => false,
                'help' => 'app.form.help.author_avatar_url',
            ])
            ->add('primaryUrl', UrlType::class, [
                'label' => 'app.form.label.author_primary_url',
                'required' => false,
                'help' => 'app.form.help.author_primary_url',
            ])
            ->add('publicSlug', TextType::class, [
                'label' => 'app.form.label.author_public_slug',
                'required' => false,
                'help' => 'app.form.help.author_public_slug',
            ]);

        // `sameAs` is stored as a list of URLs but edited as one-per-line text.
        $builder->get('sameAs')->addModelTransformer(new CallbackTransformer(
            static fn (mixed $urls): string => \is_array($urls) ? implode("\n", array_filter($urls, is_string(...))) : '',
            static function (mixed $text): ?array {
                if (!\is_string($text) || '' === trim($text)) {
                    return null;
                }

                return array_values(array_filter(
                    array_map(trim(...), explode("\n", $text)),
                    static fn (string $url): bool => '' !== $url,
                ));
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'organizer_role_locked' => false,
            'is_organizer' => false,
        ]);

        $resolver->setAllowedTypes('organizer_role_locked', 'bool');
        $resolver->setAllowedTypes('is_organizer', 'bool');
    }
}
