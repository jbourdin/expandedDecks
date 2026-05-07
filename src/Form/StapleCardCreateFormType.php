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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Create form for new staples — the editor types a single card code and an optional note.
 *
 * @extends AbstractType<StapleCardCreateData>
 *
 * @see docs/features.md F6.15 — Staple cards
 */
class StapleCardCreateFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'app.admin.staple_card.field.code',
                'help' => 'app.admin.staple_card.field.code_help',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(
                        pattern: '/^[A-Za-z0-9]+[\s\-_]+[A-Za-z0-9]+$/',
                        message: 'app.admin.staple_card.field.code_invalid',
                    ),
                ],
            ])
            ->add('hotness', IntegerType::class, [
                'label' => 'app.admin.staple_card.field.hotness',
                'help' => 'app.admin.staple_card.field.hotness_help',
                'attr' => ['min' => 1, 'max' => 10],
                'constraints' => [
                    new Assert\Range(min: 1, max: 10),
                ],
            ])
            ->add('note', TextareaType::class, [
                'label' => 'app.admin.staple_card.field.note',
                'required' => false,
                'attr' => ['rows' => 5],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StapleCardCreateData::class,
        ]);
    }
}
