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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Channel>
 *
 * @see docs/features.md F18.6 — Admin: channel CRUD and assignment UI
 */
class ChannelFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Channel $channel */
        $channel = $options['data'];
        $isEdit = null !== $channel->getId();

        $builder
            ->add('code', TextType::class, [
                'label' => 'app.channel.code',
                'disabled' => $isEdit,
            ])
            ->add('domain', TextType::class, [
                'label' => 'app.channel.domain',
            ])
            ->add('enableDecks', CheckboxType::class, [
                'label' => 'app.channel.enable_decks',
                'required' => false,
            ])
            ->add('enableRegister', CheckboxType::class, [
                'label' => 'app.channel.enable_register',
                'required' => false,
            ])
            ->add('enableEvents', CheckboxType::class, [
                'label' => 'app.channel.enable_events',
                'required' => false,
            ])
            ->add('enableBorrows', CheckboxType::class, [
                'label' => 'app.channel.enable_borrows',
                'required' => false,
            ])
            ->add('isArchetypeSource', CheckboxType::class, [
                'label' => 'app.channel.is_archetype_source',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Channel::class,
        ]);
    }
}
