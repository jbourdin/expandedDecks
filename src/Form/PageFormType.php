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

use App\Entity\Channel;
use App\Entity\MenuCategory;
use App\Entity\Page;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F11.1 — Content pages
 * @see docs/features.md F18.8 — Add channel association to MenuCategory
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
                'label' => 'app.common.slug',
                'attr' => ['placeholder' => 'app.cms.form.slug_placeholder'],
            ])
            ->add('channel', EntityType::class, [
                'class' => Channel::class,
                'choice_label' => 'domain',
                'label' => 'app.cms.form.channel',
                'placeholder' => '',
                'required' => false,
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
                'label' => 'app.common.published',
                'required' => false,
            ])
            ->add('noIndex', CheckboxType::class, [
                'label' => 'app.cms.form.no_index',
                'required' => false,
            ])
            ->add('ogImage', TextType::class, [
                'label' => 'app.cms.form.og_image',
                'required' => false,
                'empty_data' => null,
            ]);

        // Filter categories by the selected channel
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($locale): void {
            /** @var Page $page */
            $page = $event->getData();
            $channel = $page->getChannel();

            if (null === $channel) {
                return;
            }

            $event->getForm()->add('menuCategory', EntityType::class, [
                'class' => MenuCategory::class,
                'label' => 'app.cms.form.menu_category',
                'required' => false,
                'placeholder' => 'app.cms.form.no_category',
                'choice_label' => static fn (MenuCategory $category): string => $category->getName($locale),
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('c')
                    ->where('c.channel = :channel')
                    ->setParameter('channel', $channel)
                    ->orderBy('c.position', 'ASC'),
            ]);
        });
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
