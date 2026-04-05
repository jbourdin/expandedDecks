/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Extracts card data from the tcgdex/cards-database repository and outputs NDJSON.
 *
 * Usage: npx tsx scripts/tcgdex-extract.ts <repo-path>
 *
 * Outputs three types of NDJSON lines (one per line), distinguished by the "type" field:
 * - type: "serie"  — serie metadata
 * - type: "set"    — set metadata with serie reference
 * - type: "card"   — full card data with set reference and multilingual fields
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */

import { readdir, stat } from 'fs/promises'
import { join, resolve } from 'path'
import { pathToFileURL } from 'url'

// Type aliases for multilingual objects from the cards-database
type Languages = Partial<Record<string, string>>

interface BannedCards {
  series: string[]
  excludedCards: string[]
}

const repoPath = resolve(process.argv[2] ?? '')

if (!repoPath || repoPath === resolve('')) {
  console.error('Usage: npx tsx scripts/tcgdex-extract.ts <repo-path>')
  process.exit(1)
}

const dataPath = join(repoPath, 'data')

/**
 * Parse expanded legality rules from meta/legals.ts.
 */
async function loadExpandedLegality(): Promise<BannedCards> {
  const legalsPath = join(repoPath, 'meta', 'legals.ts')
  const legalsModule = await import(pathToFileURL(legalsPath).href)
  const expanded = legalsModule.expanded

  return {
    series: expanded.includes?.series ?? [],
    excludedCards: expanded.excludes?.cards ?? [],
  }
}

function isExpandedLegal(
  serieId: string,
  cardTcgdexId: string,
  legality: BannedCards,
): boolean {
  if (!legality.series.includes(serieId)) {
    return false
  }

  return !legality.excludedCards.includes(cardTcgdexId)
}

/**
 * Extract a multilingual name/text object, keeping only non-empty strings.
 */
function extractLanguages(value: unknown): Languages | null {
  if (!value || typeof value !== 'object') return null

  const result: Languages = {}
  let hasValue = false

  for (const [key, val] of Object.entries(value as Record<string, unknown>)) {
    if (typeof val === 'string' && val !== '') {
      result[key] = val
      hasValue = true
    }
  }

  return hasValue ? result : null
}

/**
 * Extract ability objects with full multilingual data.
 */
function extractAbilities(abilities: unknown): Array<Record<string, unknown>> {
  if (!Array.isArray(abilities)) return []

  return abilities
    .filter((ability) => ability && typeof ability === 'object')
    .map((ability) => ({
      name: extractLanguages(ability.name),
      effect: extractLanguages(ability.effect),
      type: typeof ability.type === 'string' ? ability.type : null,
    }))
    .filter((ability) => ability.name !== null)
}

/**
 * Extract attack objects with full multilingual data.
 */
function extractAttacks(attacks: unknown): Array<Record<string, unknown>> {
  if (!Array.isArray(attacks)) return []

  return attacks
    .filter((attack) => attack && typeof attack === 'object')
    .map((attack) => ({
      name: extractLanguages(attack.name),
      effect: extractLanguages(attack.effect),
      cost: Array.isArray(attack.cost) ? attack.cost : [],
      damage: typeof attack.damage === 'number' || typeof attack.damage === 'string'
        ? attack.damage
        : null,
    }))
    .filter((attack) => attack.name !== null)
}

async function main(): Promise<void> {
  const legality = await loadExpandedLegality()
  const serieEntries = await readdir(dataPath)

  let totalSeries = 0
  let totalSets = 0
  let totalCards = 0
  let errors = 0

  for (const serieEntry of serieEntries) {
    if (serieEntry.endsWith('.ts')) continue

    const serieDirPath = join(dataPath, serieEntry)
    const serieStat = await stat(serieDirPath)

    if (!serieStat.isDirectory()) continue

    // Import serie .ts file
    const serieTsPath = serieDirPath + '.ts'
    let serieModule: { default: Record<string, unknown> }

    try {
      serieModule = await import(pathToFileURL(serieTsPath).href)
    } catch {
      console.error(`[WARN] Could not import serie: ${serieTsPath}`)
      continue
    }

    const serieData = serieModule.default
    const serieId = String(serieData.id ?? '')

    if (!serieId) continue

    // Skip Pokémon TCG Pocket — different card game, not relevant for Expanded format
    if (serieId === 'tcgp') continue

    // Output serie
    process.stdout.write(JSON.stringify({
      type: 'serie',
      id: serieId,
      name: extractLanguages(serieData.name),
    }) + '\n')
    totalSeries++

    // Process sets in this serie
    const setEntries = await readdir(serieDirPath)

    for (const setEntry of setEntries) {
      if (setEntry.endsWith('.ts')) continue

      const setDirPath = join(serieDirPath, setEntry)
      const setStat = await stat(setDirPath)

      if (!setStat.isDirectory()) continue

      // Import set .ts file
      const setTsPath = setDirPath + '.ts'
      let setModule: { default: Record<string, unknown> }

      try {
        setModule = await import(pathToFileURL(setTsPath).href)
      } catch {
        console.error(`[WARN] Could not import set: ${setTsPath}`)
        continue
      }

      const setData = setModule.default
      const setId = String(setData.id ?? '')

      if (!setId) continue

      const abbreviations = setData.abbreviations as Record<string, string> | undefined
      const tcgOnline = typeof setData.tcgOnline === 'string' ? setData.tcgOnline : null
      const ptcgCode = tcgOnline ?? abbreviations?.official ?? null
      const cardCount = setData.cardCount as Record<string, number> | undefined
      const thirdParty = setData.thirdParty as Record<string, unknown> | undefined

      // Output set
      process.stdout.write(JSON.stringify({
        type: 'set',
        id: setId,
        serieId,
        name: extractLanguages(setData.name),
        ptcgCode,
        releaseDate: typeof setData.releaseDate === 'string' ? setData.releaseDate : null,
        officialCardCount: cardCount?.official ?? null,
        cardmarketId: typeof thirdParty?.cardmarket === 'number' ? thirdParty.cardmarket : null,
        tcgplayerId: typeof thirdParty?.tcgplayer === 'number' ? thirdParty.tcgplayer : null,
      }) + '\n')
      totalSets++

      // Process cards in this set
      const cardFiles = (await readdir(setDirPath)).filter((file) => file.endsWith('.ts'))

      for (const cardFile of cardFiles) {
        const cardPath = join(setDirPath, cardFile)
        let cardModule: { default: Record<string, unknown> }

        try {
          cardModule = await import(pathToFileURL(cardPath).href)
        } catch (error) {
          console.error(`[WARN] Could not import card: ${cardPath}: ${error}`)
          errors++
          continue
        }

        const card = cardModule.default
        const localId = cardFile.replace('.ts', '')
        const tcgdexId = `${setId}-${localId}`
        const cardName = extractLanguages(card.name)

        if (!cardName) continue

        const category = typeof card.category === 'string' ? card.category : ''
        const thirdPartyCard = card.thirdParty as Record<string, unknown> | undefined

        process.stdout.write(JSON.stringify({
          type: 'card',
          id: tcgdexId,
          setId,
          localId,
          name: cardName,
          category,
          hp: typeof card.hp === 'number' ? card.hp : null,
          trainerType: typeof card.trainerType === 'string' ? card.trainerType : null,
          energyType: typeof card.energyType === 'string' ? card.energyType : null,
          rarity: typeof card.rarity === 'string' ? card.rarity : null,
          isExpandedLegal: isExpandedLegal(serieId, tcgdexId, legality),
          abilities: extractAbilities(card.abilities),
          attacks: extractAttacks(card.attacks),
          effect: extractLanguages(card.effect),
          evolveFrom: extractLanguages(card.evolveFrom),
          stage: typeof card.stage === 'string' ? card.stage : null,
          types: Array.isArray(card.types) ? card.types.filter((type: unknown) => typeof type === 'string') : [],
          retreat: typeof card.retreat === 'number' ? card.retreat : null,
          regulationMark: typeof card.regulationMark === 'string' ? card.regulationMark : null,
          illustrator: typeof card.illustrator === 'string' ? card.illustrator : null,
          cardmarketProductId: typeof thirdPartyCard?.cardmarket === 'number' ? thirdPartyCard.cardmarket : null,
          tcgplayerProductId: typeof thirdPartyCard?.tcgplayer === 'number' ? thirdPartyCard.tcgplayer : null,
        }) + '\n')
        totalCards++
      }
    }
  }

  console.error(`[INFO] Extracted ${totalSeries} series, ${totalSets} sets, ${totalCards} cards (${errors} errors)`)
}

main().catch((error) => {
  console.error(`[FATAL] ${error}`)
  process.exit(1)
})
