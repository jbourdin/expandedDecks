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

namespace App\Service\Translation;

use App\Entity\TranslationRevisionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves the most recent source-locale revision for a revision's subject —
 * the reference against which translation staleness is decided.
 *
 * @see docs/features.md F9.9 — Translation review workflow
 */
final readonly class LatestSourceRevisionProvider
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.default_locale%')]
        private string $sourceLocale,
    ) {
    }

    public function latestFor(TranslationRevisionInterface $revision): ?TranslationRevisionInterface
    {
        $found = $this->entityManager->getRepository($revision::class)->findOneBy(
            [$revision::subjectFieldName() => $revision->getSubject(), 'locale' => $this->sourceLocale],
            ['id' => 'DESC'],
        );

        return $found instanceof TranslationRevisionInterface ? $found : null;
    }
}
