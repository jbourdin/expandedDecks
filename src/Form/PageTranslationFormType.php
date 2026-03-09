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

use App\Entity\PageTranslation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F11.1 — Content pages
 *
 * @extends AbstractType<PageTranslation>
 */
class PageTranslationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'app.cms.form.title',
            ])
            ->add('slug', TextType::class, [
                'label' => 'app.cms.form.localized_slug',
                'required' => false,
                'attr' => ['placeholder' => 'app.cms.form.localized_slug_placeholder'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'app.cms.form.content',
                'attr' => [
                    'rows' => 15,
                    'placeholder' => 'app.cms.form.content_placeholder',
                ],
            ])
            ->add('metaTitle', TextType::class, [
                'label' => 'app.cms.form.meta_title',
                'required' => false,
                'attr' => ['maxlength' => 70],
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'app.cms.form.meta_description',
                'required' => false,
                'attr' => ['rows' => 2, 'maxlength' => 160],
            ])
            ->add('ogImage', TextType::class, [
                'label' => 'app.cms.form.og_image',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PageTranslation::class,
        ]);
    }
}
