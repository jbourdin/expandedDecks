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

use App\Entity\MenuCategory;
use App\Entity\Page;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F11.1 — Content pages
 *
 * @extends AbstractType<Page>
 */
class PageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string $locale */
        $locale = $options['locale'];

        $builder
            ->add('slug', TextType::class, [
                'label' => 'app.cms.form.slug',
                'attr' => ['placeholder' => 'app.cms.form.slug_placeholder'],
            ])
            ->add('menuCategory', EntityType::class, [
                'class' => MenuCategory::class,
                'label' => 'app.cms.form.menu_category',
                'required' => false,
                'placeholder' => 'app.cms.form.no_category',
                'choice_label' => static fn (MenuCategory $category): string => $category->getName($locale),
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('c')
                    ->orderBy('c.position', 'ASC'),
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'app.cms.form.published',
                'required' => false,
            ])
            ->add('noIndex', CheckboxType::class, [
                'label' => 'app.cms.form.no_index',
                'required' => false,
            ])
            ->add('canonicalUrl', TextType::class, [
                'label' => 'app.cms.form.canonical_url',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Page::class,
            'locale' => 'en',
        ]);

        $resolver->setAllowedTypes('locale', 'string');
    }
}
