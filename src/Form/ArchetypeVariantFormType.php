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

use App\Entity\Deck;
use App\Entity\TcgdexSet;
use App\Repository\TcgdexSetRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form for creating/editing archetype variant decks.
 * Only used in the archetype admin page — never on standard deck forms.
 *
 * @see docs/features.md F18.15 — Admin archetype variant management
 *
 * @extends AbstractType<Deck>
 */
class ArchetypeVariantFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'app.archetype.variant.name',
            ])
            ->add('canonical', CheckboxType::class, [
                'label' => 'app.archetype.variant.canonical',
                'required' => false,
            ])
            ->add('pokemonSlugs', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('rawList', TextareaType::class, [
                'label' => 'app.archetype.variant.raw_list',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'rows' => 10,
                    'placeholder' => 'app.archetype.variant.raw_list_placeholder',
                ],
            ])
            ->add('latestSet', EntityType::class, [
                'class' => TcgdexSet::class,
                'label' => 'app.archetype.variant.latest_set',
                'required' => false,
                'placeholder' => 'app.archetype.variant.latest_set_placeholder',
                'query_builder' => static fn (TcgdexSetRepository $repository) => $repository->createExpandedSetsQueryBuilder(),
                'choice_label' => static fn (TcgdexSet $set): string => \sprintf(
                    '%s — %s',
                    $set->getPtcgCode() ?? $set->getId(),
                    $set->getLocalizedName() ?? $set->getId(),
                ),
            ])
            ->add('outdated', CheckboxType::class, [
                'label' => 'app.archetype.variant.outdated',
                'required' => false,
                'mapped' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'app.archetype.variant.description',
                'required' => false,
                'attr' => ['rows' => 15],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Deck::class,
        ]);
    }
}
