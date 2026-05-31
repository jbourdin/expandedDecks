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

use App\Entity\TcgdexSet;
use App\Repository\TcgdexSetRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Standalone form for the admin "Force update" action: pick a single Expanded-era set
 * to re-fetch every card across every configured locale.
 *
 * The set list mirrors the archetype variant selector so editors see the same labels.
 *
 * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
 *
 * @extends AbstractType<array<string, mixed>>
 */
class TcgdexForceUpdateFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('set', EntityType::class, [
                'class' => TcgdexSet::class,
                'label' => 'app.admin.technical.tcgdex_sync.force_update_set_label',
                'placeholder' => 'app.admin.technical.tcgdex_sync.force_update_set_placeholder',
                'query_builder' => static fn (TcgdexSetRepository $repository) => $repository->createExpandedSetsQueryBuilder(),
                'choice_label' => static fn (TcgdexSet $set): string => \sprintf(
                    '%s — %s',
                    $set->getPtcgCode() ?? $set->getId(),
                    $set->getLocalizedName() ?? $set->getId(),
                ),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'technical-tcgdex-force-update',
        ]);
    }
}
