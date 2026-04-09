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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A simple key-value pair subform for use in CollectionType.
 *
 * @extends AbstractType<array{key: string, value: string}>
 */
class KeyValuePairType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('key', TextType::class, [
                'label' => 'app.channel.param_key',
                'attr' => ['placeholder' => 'brand_name'],
            ])
            ->add('value', TextType::class, [
                'label' => 'app.channel.param_value',
                'attr' => ['placeholder' => 'Expanded Decks'],
            ]);
    }
}
