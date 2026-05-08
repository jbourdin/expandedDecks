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
        /** @var bool $isListingIntro */
        $isListingIntro = $options['is_listing_intro'];

        if (!$isListingIntro) {
            $builder->add('title', TextType::class, [
                'label' => 'app.cms.form.title',
            ]);
        }

        $builder->add('content', TextareaType::class, [
            'label' => 'app.cms.form.content',
            'required' => false,
            'attr' => [
                'rows' => 15,
                'placeholder' => 'app.cms.form.content_placeholder',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PageTranslation::class,
            'is_listing_intro' => false,
        ]);

        $resolver->setAllowedTypes('is_listing_intro', 'bool');
    }
}
