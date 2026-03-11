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

use App\Entity\Archetype;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F2.6 — Archetype management
 *
 * @extends AbstractType<Archetype>
 */
class ArchetypeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'app.archetype.name_label',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'app.archetype.description_label',
                'required' => false,
                'attr' => ['rows' => 10, 'placeholder' => 'app.archetype.description_placeholder'],
            ])
            ->add('metaDescription', TextType::class, [
                'label' => 'app.archetype.meta_description_label',
                'required' => false,
            ])
            ->add('pokemonSlugs', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'app.archetype.is_published_label',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Archetype::class,
        ]);
    }
}
