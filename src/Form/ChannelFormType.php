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
use App\Form\DataTransformer\KeyValueTransformer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Intl\Languages;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly TranslatorInterface $translator,
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
            ])
            ->add('enableBannedCards', CheckboxType::class, [
                'label' => 'app.channel.enable_banned_cards',
                'required' => false,
            ])
            ->add('enableStaples', CheckboxType::class, [
                'label' => 'app.channel.enable_staples',
                'required' => false,
            ])
            ->add('locales', ChoiceType::class, [
                'label' => 'app.channel.locales',
                'choices' => $this->localeChoices($channel),
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('draftLocales', ChoiceType::class, [
                'label' => 'app.channel.draft_locales',
                'help' => 'app.channel.draft_locales_help',
                'choices' => $this->localeChoices($channel),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('addLocale', TextType::class, [
                'label' => 'app.channel.add_locale',
                'help' => 'app.channel.add_locale_help',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'list' => 'channel-locale-codes',
                    'maxlength' => 2,
                    'placeholder' => 'de',
                ],
            ])
            ->add('parameters', CollectionType::class, [
                'label' => 'app.channel.parameters',
                'entry_type' => KeyValuePairType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'by_reference' => false,
            ]);

        $transformer = new KeyValueTransformer();

        // Transform before ResizeFormListener (priority > 0) so CollectionType
        // sees the indexed list of pairs, not the raw associative array.
        $builder->get('parameters')->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($transformer): void {
            /** @var array<string, string> $data */
            $data = $event->getData() ?? [];
            $event->setData($transformer->transform($data));
        }, 1);

        $builder->get('parameters')->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) use ($transformer): void {
            /** @var list<mixed>|null $data */
            $data = $event->getData();
            $event->setData($transformer->reverseTransform($data));
        });

        // A locale added from the free input always starts as a draft (F9.18):
        // it has no content and no UI chrome yet, so publishing it is a later,
        // deliberate tick under "published locales".
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $code = $form->get('addLocale')->getData();
            if (!\is_string($code) || '' === trim($code)) {
                return;
            }

            $code = strtolower(trim($code));
            if (1 !== preg_match('/^[a-z]{2}$/', $code) || !Languages::exists($code)) {
                $form->get('addLocale')->addError(new FormError($this->translator->trans('app.channel.add_locale_invalid')));

                return;
            }

            /** @var Channel $channel */
            $channel = $event->getData();
            if (\in_array($code, $channel->getAllLocales(), true)) {
                $form->get('addLocale')->addError(new FormError($this->translator->trans('app.channel.add_locale_exists')));

                return;
            }

            $channel->setDraftLocales([...$channel->getDraftLocales(), $code]);
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var Channel $channel */
        $channel = $form->getData();

        $names = [];
        foreach (Languages::getNames() as $code => $name) {
            if (1 === preg_match('/^[a-z]{2}$/', $code)) {
                $names[$code] = $name;
            }
        }
        $view->vars['locale_datalist'] = $names;

        // UI chrome (navbar, buttons, form labels…) renders through XLIFF
        // catalogues; a locale without one falls back to English (F9.18).
        $missing = [];
        foreach ($channel->getAllLocales() as $locale) {
            if (!is_file(\sprintf('%s/translations/messages.%s.xlf', $this->projectDir, $locale))) {
                $missing[] = $locale;
            }
        }
        $view->vars['chrome_missing_locales'] = $missing;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Channel::class,
        ]);
    }

    /**
     * Checkbox choices for the published/draft locale fields: every locale
     * already configured on the channel plus the historical base pair, with
     * native language names. New languages enter through the free "add a
     * language" input (F9.18), never through this list.
     *
     * @return array<string, string> display name => locale code
     */
    private function localeChoices(Channel $channel): array
    {
        $codes = array_values(array_unique([...$channel->getAllLocales(), 'en', 'fr']));
        sort($codes);

        $choices = [];
        foreach ($codes as $code) {
            $name = Languages::exists($code) ? Languages::getName($code, $code) : $code;
            $choices[\sprintf('%s (%s)', ucfirst($name), $code)] = $code;
        }

        return $choices;
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
