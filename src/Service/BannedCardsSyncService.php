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

namespace App\Service;

use App\Entity\BannedCard;
use App\Entity\BannedCardPrinting;
use App\Repository\BannedCardPrintingRepository;
use App\Repository\BannedCardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches the official Pokemon TCG banned card list and syncs it to the database.
 *
 * Each banned card entry on pokemon.com lists specific printings (set name + card number).
 * The service parses these, maps set names to PTCG set codes, and stores one row per
 * banned printing in the banned_card table.
 *
 * @see docs/features.md F6.5 — Banned card list management
 */
class BannedCardsSyncService
{
    private const string SOURCE_URL = 'https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list';

    /**
     * Maps official Pokemon set names (as they appear on pokemon.com) to PTCG set codes
     * used in deck list exports and by TCGdex.
     *
     * @var array<string, string>
     */
    private const array SET_NAME_TO_CODE = [
        // Black & White era
        'Black & White' => 'BLW',
        'Black & White—Noble Victories' => 'NVI',
        'Black & White—Next Destinies' => 'NXD',
        'Black & White—Dark Explorers' => 'DEX',
        'Black & White—Dragons Exalted' => 'DRX',
        'Black & White—Boundaries Crossed' => 'BCR',
        'Black & White—Plasma Storm' => 'PLS',
        'Black & White—Plasma Freeze' => 'PLF',
        'Black & White—Plasma Blast' => 'PLB',
        'Black & White—Legendary Treasures' => 'LTR',
        // XY era
        'XY' => 'XY',
        'XY—Flashfire' => 'FLF',
        'XY—Furious Fists' => 'FFI',
        'XY—Phantom Forces' => 'PHF',
        'XY—Primal Clash' => 'PRC',
        'XY—Roaring Skies' => 'ROS',
        'XY—Ancient Origins' => 'AOR',
        'XY—BREAKthrough' => 'BKT',
        'XY—BREAKpoint' => 'BKP',
        'XY—Fates Collide' => 'FCO',
        'XY—Steam Siege' => 'STS',
        'XY—Evolutions' => 'EVO',
        'Generations' => 'GEN',
        // Sun & Moon era
        'Sun & Moon' => 'SUM',
        'Sun & Moon—Guardians Rising' => 'GRI',
        'Sun & Moon—Burning Shadows' => 'BUS',
        'Sun & Moon—Crimson Invasion' => 'CIN',
        'Sun & Moon—Ultra Prism' => 'UPR',
        'Sun & Moon—Forbidden Light' => 'FLI',
        'Sun & Moon—Celestial Storm' => 'CES',
        'Sun & Moon—Lost Thunder' => 'LOT',
        'Sun & Moon—Team Up' => 'TEU',
        'Sun & Moon—Unbroken Bonds' => 'UNB',
        'Sun & Moon—Unified Minds' => 'UNM',
        'Sun & Moon—Cosmic Eclipse' => 'CEC',
        'Shining Legends' => 'SLG',
        'Hidden Fates' => 'HIF',
        // Sword & Shield era
        'Sword & Shield' => 'SSH',
        'Sword & Shield—Rebel Clash' => 'RCL',
        'Sword & Shield—Darkness Ablaze' => 'DAA',
        'Sword & Shield—Vivid Voltage' => 'VIV',
        'Sword & Shield—Battle Styles' => 'BST',
        'Sword & Shield—Chilling Reign' => 'CRE',
        'Sword & Shield—Evolving Skies' => 'EVS',
        'Sword & Shield—Fusion Strike' => 'FST',
        'Sword & Shield—Brilliant Stars' => 'BRS',
        'Sword & Shield—Astral Radiance' => 'ASR',
        'Sword & Shield—Lost Origin' => 'LOR',
        'Sword & Shield—Silver Tempest' => 'SIT',
        'Sword & Shield—Crown Zenith' => 'CRZ',
        'Sword & Shield—Shining Fates' => 'SHF',
        'Celebrations' => 'CEL',
        // Promos
        'Black Star Promo' => 'PR-SM',
        'Sword & Shield Promo' => 'PR-SW',
        'Sun & Moon Promo' => 'PR-SM',
        'XY Promo' => 'PR-XY',
        'BW Promo' => 'PR-BLW',
        // Scarlet & Violet era
        'Scarlet & Violet' => 'SVI',
        'Scarlet & Violet—Paldea Evolved' => 'PAL',
        'Scarlet & Violet—Obsidian Flames' => 'OBF',
        'Scarlet & Violet—151' => 'MEW',
        'Scarlet & Violet—Paradox Rift' => 'PAR',
        'Scarlet & Violet—Paldean Fates' => 'PAF',
        'Scarlet & Violet—Temporal Forces' => 'TEF',
        'Scarlet & Violet—Twilight Masquerade' => 'TWM',
        'Scarlet & Violet—Shrouded Fable' => 'SFA',
        'Scarlet & Violet—Stellar Crown' => 'SCR',
        'Scarlet & Violet—Surging Sparks' => 'SSP',
        'Scarlet & Violet—Prismatic Evolutions' => 'PRE',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly BannedCardRepository $bannedCardRepository,
        private readonly BannedCardPrintingRepository $bannedCardPrintingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly BannedCardEnricher $cardEnricher,
        private readonly BannedCardSeedData $seedData,
    ) {
    }

    public function sync(): BannedCardsSyncResult
    {
        $warnings = [];

        $entries = $this->fetchBannedCardEntries($warnings);

        if (null === $entries) {
            return BannedCardsSyncResult::failure('No banned cards found — the page structure may have changed.');
        }

        $added = 0;
        $unchanged = 0;

        /** @var array<string, true> $syncedKeys tracks setCode|cardNumber pairs seen in this sync */
        $syncedKeys = [];

        /**
         * Fresh-parent map by CardIdentity id. The repo query in
         * reparentByIdentity() can't see entities created earlier in this same
         * loop until they're flushed — this in-memory cache plugs that gap so
         * we don't end up with two parents for the same identity (which would
         * trip the unique constraint).
         *
         * @var array<int, BannedCard>
         */
        $parentsByIdentityId = [];

        foreach ($entries as $entry) {
            $key = $entry['setCode'].'|'.$entry['cardNumber'];
            $syncedKeys[$key] = true;

            $printing = $this->bannedCardPrintingRepository->findOneBySetCodeAndCardNumber(
                $entry['setCode'],
                $entry['cardNumber'],
            );

            if ($printing instanceof BannedCardPrinting) {
                $parent = $printing->getBannedCard();

                if ($parent->isDeleted()) {
                    $parent->setDeletedAt(null);
                    ++$added;
                } else {
                    ++$unchanged;
                }

                $this->cardEnricher->enrichPrinting($printing);
                $this->trackByIdentity($parent, $parentsByIdentityId);
                continue;
            }

            // New (setCode, cardNumber) pair — provision a child + parent.
            // The source URL is intentionally left null so the seed migration
            // (or an admin) can populate the specific announcement URL.
            $printing = new BannedCardPrinting();
            $printing->setSetCode($entry['setCode']);
            $printing->setCardNumber($entry['cardNumber']);

            $parent = new BannedCard();
            $parent->setCardName($entry['cardName']);
            $parent->addPrinting($printing);

            $this->entityManager->persist($parent);
            $this->entityManager->persist($printing);

            // Enrich the printing first so its CardIdentity is known. Then attach to
            // the right parent (existing one for that identity, if any).
            $this->cardEnricher->enrichPrinting($printing);
            $this->reparentByIdentity($printing, $parentsByIdentityId);

            // Apply seed metadata to whichever parent now owns the printing —
            // identity reparenting may have replaced the placeholder.
            $this->seedData->applyTo($printing->getBannedCard());

            ++$added;
        }

        $now = new \DateTimeImmutable();
        $removed = 0;

        foreach ($this->bannedCardRepository->findActiveOrderedByEffectiveDate() as $bannedCard) {
            $printings = $bannedCard->getPrintings();
            if ($printings->isEmpty()) {
                continue;
            }

            $allMissing = true;
            foreach ($printings as $printing) {
                $printingKey = $printing->getSetCode().'|'.$printing->getCardNumber();
                if (isset($syncedKeys[$printingKey])) {
                    $allMissing = false;
                    break;
                }
            }

            if ($allMissing) {
                $bannedCard->setDeletedAt($now);
                ++$removed;
            }
        }

        $this->entityManager->flush();

        return new BannedCardsSyncResult(
            success: true,
            added: $added,
            removed: $removed,
            unchanged: $unchanged,
            warnings: $warnings,
        );
    }

    /**
     * After enrichment populates a printing's CardIdentity, move the printing to
     * the canonical parent for that identity (promoting the placeholder when
     * none exists yet). The placeholder parent is dropped if it ends up empty.
     *
     * @param array<int, BannedCard> $parentsByIdentityId in-memory cache of parents created this run
     */
    private function reparentByIdentity(BannedCardPrinting $printing, array &$parentsByIdentityId): void
    {
        $cardPrinting = $printing->getCardPrinting();
        if (null === $cardPrinting) {
            return;
        }

        $identity = $cardPrinting->getCardIdentity();
        $identityId = $identity->getId();
        $currentParent = $printing->getBannedCard();

        $canonical = (null !== $identityId ? ($parentsByIdentityId[$identityId] ?? null) : null)
            ?? $this->bannedCardRepository->findOneByCardIdentity($identity);

        if (null === $canonical) {
            // Promote the placeholder: tag it with the resolved CardIdentity.
            $currentParent->setCardIdentity($identity);
            $currentParent->setCardName($identity->getName());
            $this->trackByIdentity($currentParent, $parentsByIdentityId);

            return;
        }

        if ($canonical === $currentParent) {
            $this->trackByIdentity($canonical, $parentsByIdentityId);

            return;
        }

        // Move the printing under the canonical parent.
        $currentParent->removePrinting($printing);
        $canonical->addPrinting($printing);

        if ($currentParent->getPrintings()->isEmpty() && null === $currentParent->getCardIdentity()) {
            $this->entityManager->remove($currentParent);
        }

        $this->trackByIdentity($canonical, $parentsByIdentityId);
    }

    /**
     * @param array<int, BannedCard> $parentsByIdentityId
     */
    private function trackByIdentity(BannedCard $parent, array &$parentsByIdentityId): void
    {
        $identity = $parent->getCardIdentity();
        if (null === $identity) {
            return;
        }
        $id = $identity->getId();
        if (null !== $id) {
            $parentsByIdentityId[$id] = $parent;
        }
    }

    /**
     * Parses the Expanded section of the banned card page.
     *
     * @param list<string> $warnings collected warnings (passed by reference)
     *
     * @return list<array{cardName: string, setCode: string, cardNumber: string}>|null null if the Expanded section cannot be found
     */
    private function fetchBannedCardEntries(array &$warnings): ?array
    {
        $response = $this->httpClient->request('GET', self::SOURCE_URL);
        $html = $response->getContent();

        $expandedPosition = stripos($html, '>Expanded<');
        if (false === $expandedPosition) {
            $expandedPosition = stripos($html, '>Expanded Format<');
        }

        if (false === $expandedPosition) {
            return null;
        }

        $afterExpanded = substr($html, $expandedPosition);
        if (!preg_match('/<ul\b[^>]*>(.*?)<\/ul>/si', $afterExpanded, $ulMatch)) {
            $warnings[] = 'Could not find a <ul> list in the Expanded section.';

            return [];
        }

        $ulHtml = $ulMatch[1];

        $entries = [];
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/si', $ulHtml, $liMatches)) {
            foreach ($liMatches[1] as $liContent) {
                $parsed = $this->parseBannedEntry($liContent, $warnings);
                foreach ($parsed as $entry) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * Parses a single <li> element containing a banned card with its printings.
     *
     * Format: "CardName (SetName, 67/101; SetName, 110/108)"
     * or:     "CardName (SetName, 98/122, 98a/122, and 98b/122)"
     *
     * @param list<string> $warnings
     *
     * @return list<array{cardName: string, setCode: string, cardNumber: string}>
     */
    private function parseBannedEntry(string $liHtml, array &$warnings): array
    {
        $cardName = $this->extractCardName($liHtml);

        if (null === $cardName) {
            return [];
        }

        $text = html_entity_decode(strip_tags($liHtml), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        if (!preg_match('/\((.+)\)\s*$/', $text, $parenMatch)) {
            $warnings[] = \sprintf('No set info found for banned card "%s".', $cardName);

            return [];
        }

        $printingsText = $parenMatch[1];

        $setGroups = preg_split('/\s*;\s*/', $printingsText);

        if (false === $setGroups) {
            return [];
        }

        $entries = [];

        foreach ($setGroups as $setGroup) {
            $parsed = $this->parseSetGroup(trim($setGroup), $cardName, $warnings);
            foreach ($parsed as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Parses a set group like "Black & White—Noble Victories, 67/101"
     * or "XY—BREAKpoint, 98/122, 98a/122, and 98b/122".
     *
     * @param list<string> $warnings
     *
     * @return list<array{cardName: string, setCode: string, cardNumber: string}>
     */
    private function parseSetGroup(string $group, string $cardName, array &$warnings): array
    {
        $commaPosition = strpos($group, ',');

        if (false === $commaPosition) {
            $warnings[] = \sprintf('Could not parse set group "%s" for card "%s".', $group, $cardName);

            return [];
        }

        $setName = trim(substr($group, 0, $commaPosition));
        $numbersText = substr($group, $commaPosition + 1);

        $setCode = $this->resolveSetCode($setName);

        if (null === $setCode) {
            $warnings[] = \sprintf('Unknown set "%s" for card "%s". Add it to SET_NAME_TO_CODE.', $setName, $cardName);

            return [];
        }

        $numberParts = preg_split('/\s*(?:,|and)\s+/', $numbersText);

        if (false === $numberParts) {
            return [];
        }

        $entries = [];

        foreach ($numberParts as $part) {
            $part = trim($part);

            if ('' === $part) {
                continue;
            }

            $cardNumber = $part;
            if (str_contains($part, '/')) {
                $slashParts = explode('/', $part);
                $cardNumber = $slashParts[0];
            }

            $entries[] = [
                'cardName' => $cardName,
                'setCode' => $setCode,
                'cardNumber' => $cardNumber,
            ];
        }

        return $entries;
    }

    /**
     * Extracts the card name from a <li> inner HTML.
     *
     * Uses strip_tags + text parsing instead of regex on raw HTML, because
     * pokemon.com embeds unescaped <em> tags inside <a> attribute values
     * (e.g. alt="Shaymin-<em>EX</em>"), which breaks regex-based tag matching.
     */
    private function extractCardName(string $liHtml): ?string
    {
        $text = html_entity_decode(strip_tags($liHtml), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $parenPosition = strpos($text, '(');

        if (false !== $parenPosition) {
            $text = substr($text, 0, $parenPosition);
        }

        $text = trim($text);

        return '' !== $text ? $text : null;
    }

    /**
     * Resolves a full set name to a PTCG set code.
     * Tries exact match first, then normalized match (dash variants).
     */
    private function resolveSetCode(string $setName): ?string
    {
        if (isset(self::SET_NAME_TO_CODE[$setName])) {
            return self::SET_NAME_TO_CODE[$setName];
        }

        $normalized = str_replace(['—', '–', '−'], '—', $setName);

        if (isset(self::SET_NAME_TO_CODE[$normalized])) {
            return self::SET_NAME_TO_CODE[$normalized];
        }

        return null;
    }
}
