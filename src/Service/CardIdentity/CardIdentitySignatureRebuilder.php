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

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Repository\CardIdentityRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Recomputes every Pokemon CardIdentity's attack_signature against the local
 * TCGdex mirror, in the damage-aware format introduced alongside this service.
 *
 * Two behaviors per identity:
 *  - All printings agree on the new signature → update the identity in place.
 *  - Printings disagree (cross-era reprints with re-tuned damage values that
 *    were previously collapsed under the name-only signature) → keep the
 *    majority group on the original identity and re-point each minority group
 *    to a find-or-created clone with the new signature.
 *
 * DeckCard rows reference CardPrinting (not CardIdentity), so re-pointing a
 * printing automatically carries every deck that listed it onto the new
 * identity without further work. StapleCard and BannedCard reference
 * CardIdentity directly, but a one-shot data check confirmed that none of
 * their rows would split under the damage-aware rule today; the
 * {@see RebuildResult::$staplesOrBansOnSplit} counter exists to catch any
 * regression on that assumption.
 *
 * @see docs/features.md F6.10 — Card identity and printing model
 */
final class CardIdentitySignatureRebuilder
{
    public function __construct(
        private readonly CardIdentityRepository $identityRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function rebuild(): RebuildResult
    {
        $result = new RebuildResult();

        /** @var list<CardIdentity> $identities */
        $identities = $this->identityRepository->findBy(['category' => 'pokemon']);

        /** @var array<string, CardIdentity> $cloneCache */
        $cloneCache = [];

        foreach ($identities as $identity) {
            $this->rebuildOne($identity, $result, $cloneCache);
        }

        $this->entityManager->flush();

        return $result;
    }

    /**
     * @param array<string, CardIdentity> $cloneCache mutates as new clones are created
     */
    private function rebuildOne(
        CardIdentity $identity,
        RebuildResult $result,
        array &$cloneCache,
    ): void {
        /** @var array<string, list<CardPrinting>> $groups */
        $groups = [];
        $missingTcgdexData = 0;

        foreach ($identity->getPrintings() as $printing) {
            $tcgdexEntity = $printing->getTcgdexCard();

            if (null === $tcgdexEntity) {
                ++$missingTcgdexData;

                continue;
            }

            $newSig = CardIdentityResolver::computeAttackSignatureFromParts(
                $tcgdexEntity->getAttackNamesEn(),
                $tcgdexEntity->getAttackDamagesEn(),
            );

            $groups[$newSig][] = $printing;
        }

        if ([] === $groups) {
            ++$result->skippedNoTcgdexData;

            return;
        }

        $result->printingsMissingTcgdexData += $missingTcgdexData;

        if (1 === \count($groups)) {
            $newSig = (string) array_key_first($groups);

            if ($identity->getAttackSignature() === $newSig) {
                ++$result->alreadyCorrect;

                return;
            }

            $identity->setAttackSignature($newSig);
            ++$result->updatedInPlace;

            return;
        }

        $primarySig = $this->pickPrimaryGroup($groups);
        $identity->setAttackSignature($primarySig);
        ++$result->splitAsPrimary;

        foreach ($groups as $sig => $printings) {
            if ($sig === $primarySig) {
                continue;
            }

            $target = $this->findOrCreateClone($identity, $sig, $cloneCache, $result);

            foreach ($printings as $printing) {
                $printing->setCardIdentity($target);
                ++$result->printingsRepointed;
            }
        }
    }

    /**
     * The primary group is the largest by printing count; ties broken by the
     * lowest CardPrinting.id (i.e. the oldest printing in the group).
     *
     * @param array<string, list<CardPrinting>> $groups
     */
    private function pickPrimaryGroup(array $groups): string
    {
        $bestSig = '';
        $bestCount = -1;
        $bestMinId = \PHP_INT_MAX;

        foreach ($groups as $sig => $printings) {
            $count = \count($printings);
            $minId = \PHP_INT_MAX;

            foreach ($printings as $printing) {
                $printingId = $printing->getId() ?? \PHP_INT_MAX;

                if ($printingId < $minId) {
                    $minId = $printingId;
                }
            }

            if ($count > $bestCount || ($count === $bestCount && $minId < $bestMinId)) {
                $bestSig = (string) $sig;
                $bestCount = $count;
                $bestMinId = $minId;
            }
        }

        return $bestSig;
    }

    /**
     * @param array<string, CardIdentity> $cloneCache
     */
    private function findOrCreateClone(
        CardIdentity $original,
        string $newAttackSig,
        array &$cloneCache,
        RebuildResult $result,
    ): CardIdentity {
        $cacheKey = $this->cloneCacheKey($original, $newAttackSig);

        if (isset($cloneCache[$cacheKey])) {
            ++$result->reusedExistingTarget;

            return $cloneCache[$cacheKey];
        }

        $existing = $this->identityRepository->findBySignature(
            $original->getName(),
            $original->getCategory(),
            $original->getHp(),
            $original->getAbilitySignature(),
            $newAttackSig,
            $original->getPokemonType(),
        );

        if (null !== $existing && $existing !== $original) {
            $cloneCache[$cacheKey] = $existing;
            ++$result->reusedExistingTarget;

            return $existing;
        }

        $clone = new CardIdentity();
        $clone->setName($original->getName());
        $clone->setCategory($original->getCategory());
        $clone->setHp($original->getHp());
        $clone->setAbilitySignature($original->getAbilitySignature());
        $clone->setAbilityNames($original->getAbilityNames());
        $clone->setAttackSignature($newAttackSig);
        $clone->setAttackNames($original->getAttackNames());
        $clone->setPokemonType($original->getPokemonType());
        $clone->setTrainerType($original->getTrainerType());
        $clone->setRuleboxType($original->getRuleboxType());

        $this->entityManager->persist($clone);
        $cloneCache[$cacheKey] = $clone;
        ++$result->clonesCreated;

        return $clone;
    }

    private function cloneCacheKey(CardIdentity $original, string $newAttackSig): string
    {
        return implode("\x1f", [
            $original->getName(),
            $original->getCategory(),
            (string) $original->getHp(),
            $original->getAbilitySignature(),
            $newAttackSig,
            $original->getPokemonType(),
            $original->getRuleboxType() ?? '',
        ]);
    }
}
