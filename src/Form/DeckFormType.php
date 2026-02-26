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
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F2.1 — Register a new deck (owner)
 *
 * @extends AbstractType<Deck>
 */
class DeckFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Deck name',
                'attr' => ['placeholder' => 'e.g. Giratina VSTAR / Comfey'],
            ])
            ->add('format', TextType::class, [
                'label' => 'Format',
                'data' => 'Expanded',
                'attr' => ['placeholder' => 'Expanded'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes (optional)',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Any notes about this deck...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Deck::class,
        ]);
    }
}
