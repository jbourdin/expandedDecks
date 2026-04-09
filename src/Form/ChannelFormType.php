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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Channel>
 *
 * @see docs/features.md F18.6 — Admin: channel CRUD and assignment UI
 * @see docs/features.md F18.28 — Per-channel theme system
 */
class ChannelFormType extends AbstractType
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

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
            ->add('themeName', ChoiceType::class, [
                'label' => 'app.channel.theme_name',
                'choices' => $this->getAvailableThemes(),
                'required' => false,
                'placeholder' => 'app.channel.theme_default',
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
            ->add('enableArchetypes', CheckboxType::class, [
                'label' => 'app.channel.enable_archetypes',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Channel::class,
        ]);
    }

    /**
     * Scan templates/themes/ for available theme directories.
     *
     * @return array<string, string> theme label => theme name
     */
    private function getAvailableThemes(): array
    {
        $themesDir = $this->projectDir.'/templates/themes';

        if (!is_dir($themesDir)) {
            return [];
        }

        $finder = (new Finder())->directories()->in($themesDir)->depth(0)->sortByName();
        $themes = [];

        foreach ($finder as $directory) {
            $name = $directory->getFilename();
            $themes[$name] = $name;
        }

        return $themes;
    }
}
