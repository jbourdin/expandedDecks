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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F2.2 — Import deck list (PTCG text format)
 *
 * @extends AbstractType<mixed>
 */
class DeckImportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rawList', TextareaType::class, [
                'label' => 'Deck list (PTCG format)',
                'attr' => [
                    'rows' => 20,
                    'placeholder' => "Pokémon: 16\n4 Arceus VSTAR BRS 123\n...\n\nTrainer: 36\n4 Battle VIP Pass FST 225\n...\n\nEnergy: 8\n4 Psychic Energy SVE 5\n...",
                ],
            ])
            ->add('archetype', TextType::class, [
                'label' => 'Archetype code (optional)',
                'required' => false,
                'attr' => ['placeholder' => 'e.g. giratina-vstar-comfey'],
            ])
            ->add('archetypeName', TextType::class, [
                'label' => 'Archetype display name (optional)',
                'required' => false,
                'attr' => ['placeholder' => 'e.g. Giratina VSTAR / Comfey'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
