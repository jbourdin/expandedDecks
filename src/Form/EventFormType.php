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

use App\Entity\Event;
use App\Enum\TournamentStructure;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see docs/features.md F3.1 — Create a new event
 *
 * @extends AbstractType<Event>
 */
class EventFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Event name',
                'attr' => ['placeholder' => 'e.g. Weekly Expanded League'],
            ])
            ->add('eventId', TextType::class, [
                'label' => 'Tournament ID (optional)',
                'required' => false,
                'attr' => ['placeholder' => 'e.g. 0000118025'],
            ])
            ->add('format', TextType::class, [
                'label' => 'Format',
                'mapped' => false,
                'data' => 'Expanded',
                'disabled' => true,
            ])
            ->add('date', DateTimeType::class, [
                'label' => 'Start date & time',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'End date & time (optional)',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('timezone', TimezoneType::class, [
                'label' => 'Timezone',
            ])
            ->add('location', TextType::class, [
                'label' => 'Location (optional)',
                'required' => false,
                'attr' => ['placeholder' => 'e.g. Le Repaire du Dragon, Lyon'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optional)',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Event details, rules, etc.',
                ],
            ])
            ->add('registrationLink', UrlType::class, [
                'label' => 'Registration link',
                'attr' => ['placeholder' => 'https://...'],
            ])
            ->add('tournamentStructure', EnumType::class, [
                'class' => TournamentStructure::class,
                'required' => false,
                'placeholder' => '— Select —',
                'label' => 'Tournament structure (optional)',
                'choice_label' => static fn (TournamentStructure $ts): string => ucwords(str_replace('_', ' ', $ts->value)),
            ])
            ->add('minAttendees', IntegerType::class, [
                'label' => 'Min attendees (optional)',
                'required' => false,
                'attr' => ['min' => 1],
            ])
            ->add('maxAttendees', IntegerType::class, [
                'label' => 'Max attendees (optional)',
                'required' => false,
                'attr' => ['min' => 1],
            ])
            ->add('roundDuration', IntegerType::class, [
                'label' => 'Round duration in minutes (optional)',
                'required' => false,
                'attr' => ['min' => 1, 'placeholder' => 'e.g. 50'],
            ])
            ->add('topCutRoundDuration', IntegerType::class, [
                'label' => 'Top cut round duration in minutes (optional)',
                'required' => false,
                'attr' => ['min' => 1, 'placeholder' => 'e.g. 75'],
            ])
            ->add('entryFeeAmount', IntegerType::class, [
                'label' => 'Entry fee (cents, optional)',
                'required' => false,
                'attr' => ['min' => 0, 'placeholder' => 'e.g. 500 for 5.00'],
            ])
            ->add('entryFeeCurrency', CurrencyType::class, [
                'label' => 'Currency (optional)',
                'required' => false,
                'placeholder' => '— Select —',
            ])
            ->add('isDecklistMandatory', CheckboxType::class, [
                'label' => 'Decklist mandatory',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
