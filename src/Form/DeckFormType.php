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

use App\Entity\Deck;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F2.1 — Register a new deck (owner)
 * @see docs/features.md F2.13 — Inline deck list import on creation
 *
 * @extends AbstractType<Deck>
 */
class DeckFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'app.form.label.deck_name',
                'attr' => ['placeholder' => 'app.form.placeholder.deck_name'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'app.form.label.notes',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'app.form.placeholder.notes',
                ],
            ])
            ->add('archetype', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('languages', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('public', CheckboxType::class, [
                'label' => 'app.form.label.public',
                'required' => false,
                'disabled' => $options['public_disabled'],
            ]);

        if ($options['include_raw_list']) {
            $builder->add('rawList', TextareaType::class, [
                'label' => 'app.form.label.deck_list_optional',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'rows' => 15,
                    'placeholder' => "Pokémon: 16\n4 Arceus VSTAR BRS 123\n...\nTrainer: 32\n4 Professor's Research CEL 24\n...\nEnergy: 12\n4 Double Turbo Energy BRS 151\n...",
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Deck::class,
            'public_disabled' => false,
            'include_raw_list' => false,
        ]);

        $resolver->setAllowedTypes('public_disabled', 'bool');
        $resolver->setAllowedTypes('include_raw_list', 'bool');
    }
}
