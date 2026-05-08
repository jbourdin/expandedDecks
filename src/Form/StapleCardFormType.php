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

use App\Entity\StapleCard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Edit form for an existing staple — the card identity is locked once chosen,
 * editors can only adjust hotness and the note.
 *
 * @extends AbstractType<StapleCard>
 *
 * @see docs/features.md F6.15 — Staple cards
 */
class StapleCardFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
            'data_class' => StapleCard::class,
        ]);
    }
}
