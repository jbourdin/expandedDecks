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
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F11.2 — Menu categories
 * @see docs/features.md F18.8 — Add channel association to MenuCategory
 *
 * @extends AbstractType<MenuCategory>
 */
class MenuCategoryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('channel', EntityType::class, [
                'class' => Channel::class,
                'choice_label' => 'domain',
                'label' => 'app.cms.form.channel',
                'placeholder' => '',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MenuCategory::class,
        ]);
    }
}
