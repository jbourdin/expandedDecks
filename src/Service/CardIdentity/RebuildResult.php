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

namespace App\Service\CardIdentity;

/**
 * Counters produced by {@see CardIdentitySignatureRebuilder::rebuild}.
 */
final class RebuildResult
{
    public int $alreadyCorrect = 0;
    public int $updatedInPlace = 0;
    public int $splitAsPrimary = 0;
    public int $clonesCreated = 0;
    public int $reusedExistingTarget = 0;
    public int $printingsRepointed = 0;
    public int $skippedNoTcgdexData = 0;
    public int $printingsMissingTcgdexData = 0;
}
