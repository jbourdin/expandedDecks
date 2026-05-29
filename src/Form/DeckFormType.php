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
use App\Enum\DeckFormat;
use App\Repository\TcgdexSetRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F2.1 — Register a new deck (owner)
 * @see docs/features.md F2.13 — Inline deck list import on creation
 * @see docs/features.md F2.23 — Standard format personal decks
 * @see docs/features.md F2.30 — Personal deck flag
 *
 * @extends AbstractType<Deck>
 */
class DeckFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'app.form.label.deck_name',
                'attr' => ['placeholder' => 'app.form.placeholder.deck_name'],
            ])
            ->add('format', EnumType::class, [
                'class' => DeckFormat::class,
                'label' => 'app.form.label.deck_format',
                'choice_label' => static fn (DeckFormat $format): string => match ($format) {
                    DeckFormat::Expanded => 'app.deck.format.expanded',
                    DeckFormat::Standard => 'app.deck.format.standard',
                },
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'app.form.label.notes',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'app.form.placeholder.notes',
                ],
            ])
            ->add('ogImage', TextType::class, [
                'label' => 'app.form.label.og_image',
                'help' => 'app.form.help.og_image',
                'required' => false,
                'empty_data' => null,
                'attr' => ['maxlength' => 255],
            ])
            ->add('ogDescription', TextareaType::class, [
                'label' => 'app.form.label.og_description',
                'help' => 'app.form.help.og_description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('archetype', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('languages', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('pokemonSlugs', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('latestSet', EntityType::class, [
                'class' => TcgdexSet::class,
                'label' => 'app.form.label.latest_set',
                'required' => false,
                'placeholder' => 'app.form.placeholder.latest_set',
                'query_builder' => static fn (TcgdexSetRepository $repository) => $repository->createExpandedSetsQueryBuilder(),
                'choice_label' => static fn (TcgdexSet $set): string => \sprintf(
                    '%s — %s',
                    $set->getPtcgCode() ?? $set->getId(),
                    $set->getLocalizedName() ?? $set->getId(),
                ),
            ])
            ->add('public', CheckboxType::class, [
                'label' => 'app.form.label.public',
                'required' => false,
                'disabled' => $options['public_disabled'],
            ])
            ->add('personal', CheckboxType::class, [
                'label' => 'app.form.label.personal',
                'help' => 'app.form.help.personal',
                'required' => false,
                'disabled' => $options['personal_disabled'],
            ]);

        if ($options['include_raw_list']) {
            $builder->add('rawList', TextareaType::class, [
                'label' => 'app.form.label.deck_list_optional',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'rows' => 15,
                    'placeholder' => "Pokémon: 16\n4 Arceus VSTAR BRS 123\n...\nTrainer: 32\n4 Professor's Research CEL 24\n...\nEnergy: 12\n4 Double Turbo Energy BRS 151\n...",
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Deck::class,
            'public_disabled' => false,
            'personal_disabled' => false,
            'include_raw_list' => false,
        ]);

        $resolver->setAllowedTypes('public_disabled', 'bool');
        $resolver->setAllowedTypes('personal_disabled', 'bool');
        $resolver->setAllowedTypes('include_raw_list', 'bool');
    }
}
