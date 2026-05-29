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

use App\Entity\ArchetypeTranslation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F9.6 — Archetype localization
 *
 * @extends AbstractType<ArchetypeTranslation>
 */
class ArchetypeTranslationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'app.common.name',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'app.archetype.description_label',
                'required' => false,
                'attr' => [
                    'rows' => 10,
                    'placeholder' => 'app.archetype.description_placeholder',
                ],
            ])
            ->add('metaDescription', TextType::class, [
                'label' => 'app.archetype.meta_description_label',
                'required' => false,
                'attr' => ['maxlength' => 255],
            ])
            ->add('ogImage', TextType::class, [
                'label' => 'app.archetype.og_image_label',
                'help' => 'app.archetype.og_image_help',
                'required' => false,
                'empty_data' => null,
                'attr' => ['maxlength' => 255],
            ])
            ->add('ogDescription', TextareaType::class, [
                'label' => 'app.archetype.og_description_label',
                'help' => 'app.archetype.og_description_help',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArchetypeTranslation::class,
        ]);
    }
}
