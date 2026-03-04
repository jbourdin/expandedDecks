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

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Base controller for all application controllers.
 *
 * Overrides addFlash() to automatically translate messages through the
 * Symfony Translator, so controllers can pass translation keys directly.
 */
abstract class AbstractAppController extends AbstractController
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, string|int> $parameters
     */
    protected function addFlash(string $type, mixed $message, array $parameters = []): void
    {
        if (\is_string($message)) {
            $message = $this->translator->trans($message, $parameters);
        }

        parent::addFlash($type, $message);
    }
}
