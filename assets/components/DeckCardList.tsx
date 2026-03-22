/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 * @see docs/features.md F6.7 — Export deck list as PTCGL text
 * @see docs/features.md F6.8 — Minified deck list export
 * @see docs/features.md F6.6b — Minified mosaic
 * @see docs/features.md F6.11 — Export deck list for Cardmarket wishlist
 */

import React, { useCallback, useMemo, useRef, useState } from 'react';
import {
    ActionIcon,
    Group,
    Loader,
    Modal,
    SegmentedControl,
    Table,
    Text,
    Tooltip,
} from '@mantine/core';
import { useMediaQuery } from '@mantine/hooks';
import { IconCopy, IconCheck, IconShare, IconShoppingCart } from '@tabler/icons-react';

interface CardData {
    cardName: string;
    quantity: number;
    setCode: string;
    cardNumber: string;
    cardType: string;
    trainerSubtype: string | null;
    imageUrl: string | null;
}

interface Labels {
    variantOriginal: string;
    variantMinified: string;
    viewTable: string;
    viewMosaic: string;
    copyButton: string;
    copied: string;
    shareMosaic: string;
    mosaicAlt: string;
    sectionPokemon: string;
    sectionTrainer: string;
    sectionEnergy: string;
    tableQty: string;
    tableCard: string;
    tableSet: string;
    nameMatchWarningTitle: string;
    nameMatchWarningBody: string;
    copyCardmarket: string;
    copyCardmarketTooltip: string;
    mosaicGenerating: string;
    minifiedGenerating: string;
}

export interface DeckCardListProps {
    originalCards: Record<string, CardData[]>;
    minifiedCards: Record<string, CardData[]>;
    mosaicUrl: string | null;
    minifiedMosaicUrl: string | null;
    rawList: string;
    minifiedList: string | null;
    cardmarketWishlist: string | null;
    deckName: string;
    labels: Labels;
}

type Variant = 'original' | 'minified';
type ViewMode = 'table' | 'mosaic';

/**
 * Card hover preview — shows card image on mouse hover (desktop) or tap (mobile modal).
 */
const CardName: React.FC<{ card: CardData }> = ({ card }) => {
    const [showPreview, setShowPreview] = useState(false);

    if (!card.imageUrl) {
        return <>{card.cardName}</>;
    }

    return (
        <span
            className="card-hover"
            data-quantity={card.quantity}
            onMouseEnter={() => setShowPreview(true)}
            onMouseLeave={() => setShowPreview(false)}
        >
            {card.cardName}
            <img
                className={`card-hover-img${showPreview ? '' : ''}`}
                src={card.imageUrl}
                alt={card.cardName}
                style={{ display: showPreview ? 'block' : undefined }}
            />
        </span>
    );
};

/**
 * Card table section — renders a group of cards (Pokemon, Trainer, or Energy).
 */
const CardSection: React.FC<{
    label: string;
    cards: CardData[];
    tableLabels: { qty: string; card: string; set: string };
}> = ({ label, cards, tableLabels }) => (
    <div className="card shadow-sm">
        <div className="card-header card-header-themed">
            <h5 className="mb-0">{label}</h5>
        </div>
        <div className="card-body p-0">
            <Table striped className="table-themed table-sm mb-0">
                <Table.Thead>
                    <Table.Tr>
                        <Table.Th style={{ width: 32 }}>{tableLabels.qty}</Table.Th>
                        <Table.Th>{tableLabels.card}</Table.Th>
                        <Table.Th style={{ width: 56 }}>{tableLabels.set}</Table.Th>
                        <Table.Th style={{ width: 40 }}>#</Table.Th>
                    </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                    {cards.map((card, index) => (
                        <Table.Tr key={`${card.cardName}-${card.setCode}-${card.cardNumber}-${index}`}>
                            <Table.Td>{card.quantity}</Table.Td>
                            <Table.Td>
                                <CardName card={card} />
                            </Table.Td>
                            <Table.Td><code>{card.setCode}</code></Table.Td>
                            <Table.Td>{card.cardNumber}</Table.Td>
                        </Table.Tr>
                    ))}
                </Table.Tbody>
            </Table>
        </div>
    </div>
);

/**
 * Two-column card table layout.
 */
const CardTable: React.FC<{
    groupedCards: Record<string, CardData[]>;
    labels: Labels;
}> = ({ groupedCards, labels }) => {
    const pokemonCards = groupedCards.pokemon ?? [];
    const trainerCards = groupedCards.trainer ?? [];
    const energyCards = groupedCards.energy ?? [];
    const energyInLeft = pokemonCards.length <= trainerCards.length;

    const tableLabels = { qty: labels.tableQty, card: labels.tableCard, set: labels.tableSet };

    return (
        <div className="row">
            <div className="col-md-6 mb-3">
                {pokemonCards.length > 0 && (
                    <CardSection label={labels.sectionPokemon} cards={pokemonCards} tableLabels={tableLabels} />
                )}
                {energyInLeft && energyCards.length > 0 && (
                    <div className="mt-3">
                        <CardSection label={labels.sectionEnergy} cards={energyCards} tableLabels={tableLabels} />
                    </div>
                )}
            </div>
            <div className="col-md-6 mb-3">
                {trainerCards.length > 0 && (
                    <CardSection label={labels.sectionTrainer} cards={trainerCards} tableLabels={tableLabels} />
                )}
                {!energyInLeft && energyCards.length > 0 && (
                    <div className="mt-3">
                        <CardSection label={labels.sectionEnergy} cards={energyCards} tableLabels={tableLabels} />
                    </div>
                )}
            </div>
        </div>
    );
};

/**
 * Placeholder shown when async-generated data is not yet available.
 *
 * @see docs/features.md F2.3 — Show pending state when mosaic/minified data is not yet available
 */
const GeneratingPlaceholder: React.FC<{ message: string }> = ({ message }) => (
    <div className="card shadow-sm">
        <div className="card-body text-center py-5">
            <Loader size="sm" className="mb-2" />
            <Text size="sm" c="dimmed">{message}</Text>
        </div>
    </div>
);

export const DeckCardList: React.FC<DeckCardListProps> = ({
    originalCards,
    minifiedCards,
    mosaicUrl,
    minifiedMosaicUrl,
    rawList,
    minifiedList,
    cardmarketWishlist,
    labels,
}) => {
    const hasMinifiedData = Object.keys(minifiedCards).length > 0 || minifiedList !== null;
    const hasMosaic = mosaicUrl !== null;
    const hasMinifiedMosaic = minifiedMosaicUrl !== null;
    const isMobile = useMediaQuery('(max-width: 767px)');

    const [variant, setVariant] = useState<Variant>(hasMinifiedData ? 'minified' : 'original');
    const [viewMode, setViewMode] = useState<ViewMode>(hasMosaic ? 'mosaic' : 'table');
    const [copied, setCopied] = useState(false);
    const [cardmarketCopied, setCardmarketCopied] = useState(false);
    const [mosaicModalOpen, setMosaicModalOpen] = useState(false);
    const copyTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const cardmarketCopyTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    const isMinifiedPending = variant === 'minified' && !hasMinifiedData;

    const activeCards = variant === 'minified' && Object.keys(minifiedCards).length > 0
        ? minifiedCards
        : originalCards;

    const activeMosaicUrl = useMemo(() => {
        if (variant === 'minified' && hasMinifiedMosaic) {
            return minifiedMosaicUrl;
        }

        return mosaicUrl;
    }, [variant, mosaicUrl, minifiedMosaicUrl, hasMinifiedMosaic]);

    const isMosaicPending = viewMode === 'mosaic' && activeMosaicUrl === null;

    const activeList = variant === 'minified' && minifiedList !== null
        ? minifiedList
        : rawList;

    const handleCopy = useCallback(() => {
        navigator.clipboard.writeText(activeList.trim()).then(() => {
            setCopied(true);

            if (copyTimeout.current) {
                clearTimeout(copyTimeout.current);
            }

            copyTimeout.current = setTimeout(() => setCopied(false), 2000);
        });
    }, [activeList]);

    const handleCopyCardmarket = useCallback(() => {
        if (!cardmarketWishlist) {
            return;
        }

        navigator.clipboard.writeText(cardmarketWishlist.trim()).then(() => {
            setCardmarketCopied(true);

            if (cardmarketCopyTimeout.current) {
                clearTimeout(cardmarketCopyTimeout.current);
            }

            cardmarketCopyTimeout.current = setTimeout(() => setCardmarketCopied(false), 2000);
        });
    }, [cardmarketWishlist]);

    const handleShareMosaic = useCallback(async () => {
        if (!activeMosaicUrl) {
            return;
        }

        // Try Web Share API with the image file
        if (navigator.share) {
            try {
                const response = await fetch(activeMosaicUrl);
                const blob = await response.blob();
                const file = new File([blob], 'deck-mosaic.png', { type: 'image/png' });

                await navigator.share({
                    files: [file],
                });

                return;
            } catch {
                // Share cancelled or not supported with files — fall back to URL share
            }

            try {
                await navigator.share({
                    title: labels.mosaicAlt,
                    url: activeMosaicUrl,
                });

                return;
            } catch {
                // Share cancelled — fall back to clipboard
            }
        }

        // Fallback: copy mosaic URL to clipboard
        await navigator.clipboard.writeText(window.location.origin + activeMosaicUrl);
        setCopied(true);

        if (copyTimeout.current) {
            clearTimeout(copyTimeout.current);
        }

        copyTimeout.current = setTimeout(() => setCopied(false), 2000);
    }, [activeMosaicUrl, labels.mosaicAlt]);

    const handleMosaicClick = useCallback(() => {
        if (isMobile) {
            setMosaicModalOpen(true);
        } else {
            setViewMode('mosaic');
        }
    }, [isMobile]);

    return (
        <>
            {/* Top bar: Variant toggle (left) + Copy (center) + View toggle (right) */}
            <Group justify="space-between" align="center" mb="xs">
                <div>
                    <SegmentedControl
                        size="xs"
                        value={variant}
                        onChange={(value) => setVariant(value as Variant)}
                        data={[
                            { label: labels.variantOriginal, value: 'original' },
                            { label: labels.variantMinified, value: 'minified' },
                        ]}
                    />
                </div>

                <Group gap="xs">
                    <Tooltip label={labels.copyButton}>
                        <ActionIcon
                            variant="subtle"
                            color={copied ? 'green' : 'gray'}
                            onClick={handleCopy}
                            size="lg"
                        >
                            {copied ? <IconCheck size={18} /> : <IconCopy size={18} />}
                        </ActionIcon>
                    </Tooltip>
                    {activeMosaicUrl && (
                        <Tooltip label={labels.shareMosaic}>
                            <ActionIcon
                                variant="subtle"
                                color="gray"
                                onClick={handleShareMosaic}
                                size="lg"
                            >
                                <IconShare size={18} />
                            </ActionIcon>
                        </Tooltip>
                    )}
                    {cardmarketWishlist && (
                        <Tooltip label={cardmarketCopied ? labels.copied : labels.copyCardmarketTooltip}>
                            <ActionIcon
                                variant="subtle"
                                color={cardmarketCopied ? 'green' : 'gray'}
                                onClick={handleCopyCardmarket}
                                size="lg"
                            >
                                {cardmarketCopied ? <IconCheck size={18} /> : <IconShoppingCart size={18} />}
                            </ActionIcon>
                        </Tooltip>
                    )}
                    {(copied || cardmarketCopied) && (
                        <Text size="sm" c="green">
                            {labels.copied}
                        </Text>
                    )}
                </Group>

                <SegmentedControl
                    size="xs"
                    value={isMobile ? 'table' : viewMode}
                    onChange={(value) => {
                        if (value === 'mosaic') {
                            handleMosaicClick();
                        } else {
                            setViewMode('table');
                        }
                    }}
                    data={[
                        { label: labels.viewTable, value: 'table' },
                        { label: labels.viewMosaic, value: 'mosaic' },
                    ]}
                />
            </Group>

            {/* Minified variant pending */}
            {isMinifiedPending && (
                <GeneratingPlaceholder message={labels.minifiedGenerating} />
            )}

            {/* Table view */}
            {!isMinifiedPending && (viewMode === 'table' || isMobile) && (
                <CardTable groupedCards={activeCards} labels={labels} />
            )}

            {/* Mosaic view (desktop inline) */}
            {viewMode === 'mosaic' && !isMobile && activeMosaicUrl && (
                <div className="text-center">
                    <img
                        src={activeMosaicUrl}
                        alt={labels.mosaicAlt}
                        className="img-fluid rounded shadow-sm"
                    />
                </div>
            )}

            {/* Mosaic view pending (desktop inline) */}
            {isMosaicPending && !isMobile && !isMinifiedPending && (
                <GeneratingPlaceholder message={labels.mosaicGenerating} />
            )}

            {/* Mosaic fullscreen modal (mobile) */}
            <Modal
                opened={mosaicModalOpen}
                onClose={() => setMosaicModalOpen(false)}
                fullScreen
                title={labels.mosaicAlt}
                styles={{
                    body: {
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: 8,
                        overflow: 'auto',
                    },
                    header: { backgroundColor: '#213568', color: 'white' },
                    content: { backgroundColor: '#213568' },
                }}
            >
                {activeMosaicUrl ? (
                    <img
                        src={activeMosaicUrl}
                        alt={labels.mosaicAlt}
                        className="img-fluid"
                        style={{ maxHeight: '90vh' }}
                    />
                ) : (
                    <div className="text-center py-5">
                        <Loader size="sm" className="mb-2" color="white" />
                        <Text size="sm" c="white">{labels.mosaicGenerating}</Text>
                    </div>
                )}
            </Modal>
        </>
    );
};
