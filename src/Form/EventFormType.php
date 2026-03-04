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
        /** @var string $viewTimezone */
        $viewTimezone = $options['event_timezone'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'app.form.label.event_name',
                'attr' => ['placeholder' => 'app.form.placeholder.event_name'],
            ])
            ->add('eventId', TextType::class, [
                'label' => 'app.form.label.tournament_id',
                'required' => false,
                'attr' => ['placeholder' => 'app.form.placeholder.tournament_id'],
            ])
            ->add('format', TextType::class, [
                'label' => 'app.form.label.format',
                'mapped' => false,
                'data' => 'Expanded',
                'disabled' => true,
            ])
            ->add('date', DateTimeType::class, [
                'label' => 'app.form.label.start_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'model_timezone' => 'UTC',
                'view_timezone' => $viewTimezone,
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'app.form.label.end_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'model_timezone' => 'UTC',
                'view_timezone' => $viewTimezone,
            ])
            ->add('timezone', TimezoneType::class, [
                'label' => 'app.form.label.timezone',
            ])
            ->add('location', TextType::class, [
                'label' => 'app.form.label.location',
                'required' => false,
                'attr' => ['placeholder' => 'app.form.placeholder.location'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'app.form.label.description',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'app.form.placeholder.description',
                ],
            ])
            ->add('registrationLink', UrlType::class, [
                'label' => 'app.form.label.registration_link',
                'attr' => ['placeholder' => 'https://...'],
            ])
            ->add('tournamentStructure', EnumType::class, [
                'class' => TournamentStructure::class,
                'required' => false,
                'placeholder' => 'app.form.placeholder.select',
                'label' => 'app.form.label.tournament_structure',
                'choice_label' => static fn (TournamentStructure $ts): string => ucwords(str_replace('_', ' ', $ts->value)),
            ])
            ->add('minAttendees', IntegerType::class, [
                'label' => 'app.form.label.min_attendees',
                'required' => false,
                'attr' => ['min' => 1],
            ])
            ->add('maxAttendees', IntegerType::class, [
                'label' => 'app.form.label.max_attendees',
                'required' => false,
                'attr' => ['min' => 1],
            ])
            ->add('roundDuration', IntegerType::class, [
                'label' => 'app.form.label.round_duration',
                'required' => false,
                'attr' => ['min' => 1, 'placeholder' => 'app.form.placeholder.round_duration'],
            ])
            ->add('topCutRoundDuration', IntegerType::class, [
                'label' => 'app.form.label.top_cut_duration',
                'required' => false,
                'attr' => ['min' => 1, 'placeholder' => 'app.form.placeholder.top_cut_duration'],
            ])
            ->add('entryFeeAmount', IntegerType::class, [
                'label' => 'app.form.label.entry_fee',
                'required' => false,
                'attr' => ['min' => 0, 'placeholder' => 'app.form.placeholder.entry_fee'],
            ])
            ->add('entryFeeCurrency', CurrencyType::class, [
                'label' => 'app.form.label.currency',
                'required' => false,
                'placeholder' => 'app.form.placeholder.select',
            ])
            ->add('isDecklistMandatory', CheckboxType::class, [
                'label' => 'app.form.label.decklist_mandatory',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
            'event_timezone' => 'UTC',
        ]);

        $resolver->setAllowedTypes('event_timezone', 'string');
    }
}
