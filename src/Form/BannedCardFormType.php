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

use App\Entity\BannedCard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<BannedCard>
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cardName', TextType::class, [
                'label' => 'app.admin.banned_card.field.card_name',
                'help' => 'app.admin.banned_card.field.card_name_help',
            ])
            ->add('effectiveDate', DateType::class, [
                'label' => 'app.admin.banned_card.field.effective_date',
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('sourceUrl', UrlType::class, [
                'label' => 'app.admin.banned_card.field.source_url',
                'required' => false,
            ])
            ->add('explanation', TextareaType::class, [
                'label' => 'app.admin.banned_card.field.explanation',
                'help' => 'app.admin.banned_card.field.explanation_help',
                'required' => false,
                'attr' => ['rows' => 5],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BannedCard::class,
        ]);
    }
}
