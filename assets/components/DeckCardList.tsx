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

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    ActionIcon,
    CloseButton,
    Group,
    Loader,
    Modal,
    SegmentedControl,
    Table,
    Text,
    Tooltip,
    UnstyledButton,
} from '@mantine/core';
import { useMediaQuery } from '@mantine/hooks';
import { IconCopy, IconCheck, IconShare, IconShoppingCart, IconChevronLeft, IconChevronRight } from '@tabler/icons-react';

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
 * Card image gallery modal — mobile swipeable viewer with prev/next navigation.
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
const CardImageModal: React.FC<{
    opened: boolean;
    cards: CardEntry[];
    currentIndex: number;
    onClose: () => void;
    onNavigate: (index: number) => void;
}> = ({ opened, cards, currentIndex, onClose, onNavigate }) => {
    const touchStartX = useRef(0);
    const touchStartY = useRef(0);

    const card = cards[currentIndex];

    const navigate = useCallback((direction: number) => {
        onNavigate((currentIndex + direction + cards.length) % cards.length);
    }, [currentIndex, cards.length, onNavigate]);

    useEffect(() => {
        if (!opened) return;

        const handleKeyDown = (event: KeyboardEvent): void => {
            if (event.key === 'ArrowLeft') navigate(-1);
            if (event.key === 'ArrowRight') navigate(1);
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [opened, navigate]);

    if (!card) return null;

    const title = `${card.quantity} \u00d7 ${card.name}`;

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            withCloseButton={false}
            centered
            size="sm"
            padding={0}
            styles={{
                body: { padding: 0 },
                content: { overflow: 'hidden' },
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '8px 12px' }}>
                <Text size="sm" fw={500} style={{ flex: 1 }}>{title}</Text>
                <Text size="xs" c="dimmed" style={{ marginRight: 8 }}>
                    {currentIndex + 1} / {cards.length}
                </Text>
                <CloseButton onClick={onClose} />
            </div>
            <div
                style={{ position: 'relative', textAlign: 'center', padding: '0 8px 8px' }}
                onTouchStart={(event) => {
                    touchStartX.current = event.touches[0].clientX;
                    touchStartY.current = event.touches[0].clientY;
                }}
                onTouchMove={(event) => {
                    const deltaX = Math.abs(event.touches[0].clientX - touchStartX.current);
                    const deltaY = Math.abs(event.touches[0].clientY - touchStartY.current);
                    if (deltaY > deltaX) {
                        event.preventDefault();
                    }
                }}
                onTouchEnd={(event) => {
                    const deltaX = event.changedTouches[0].clientX - touchStartX.current;
                    if (Math.abs(deltaX) > 50) {
                        navigate(deltaX < 0 ? 1 : -1);
                    }
                }}
            >
                <UnstyledButton
                    onClick={() => navigate(-1)}
                    style={{ position: 'absolute', left: 4, top: '50%', transform: 'translateY(-50%)', zIndex: 1 }}
                >
                    <IconChevronLeft size={28} />
                </UnstyledButton>
                <img
                    src={card.imageUrl}
                    alt={card.name}
                    style={{ maxWidth: '100%', borderRadius: 4 }}
                />
                <UnstyledButton
                    onClick={() => navigate(1)}
                    style={{ position: 'absolute', right: 4, top: '50%', transform: 'translateY(-50%)', zIndex: 1 }}
                >
                    <IconChevronRight size={28} />
                </UnstyledButton>
            </div>
        </Modal>
    );
};

interface CardEntry {
    imageUrl: string;
    name: string;
    quantity: number;
}

/**
 * Card hover preview — shows card image on mouse hover (desktop) or tap (mobile modal).
 */
const CardName: React.FC<{ card: CardData; onMobileTap?: () => void }> = ({ card, onMobileTap }) => {
    const [showPreview, setShowPreview] = useState(false);
    const spanRef = useRef<HTMLSpanElement>(null);
    const imgRef = useRef<HTMLImageElement>(null);
    const isMobile = useMediaQuery('(max-width: 767px)');

    const handleMouseEnter = useCallback(() => {
        if (!spanRef.current || !imgRef.current) return;

        const rect = spanRef.current.getBoundingClientRect();
        const imgHeight = Math.min(Math.max(280, window.innerHeight / 3), 672);
        const imgWidth = imgHeight / 1.4;

        let top: number;
        if (rect.top >= imgHeight + 8) {
            top = rect.top - imgHeight;
        } else {
            top = rect.bottom + 4;
        }

        top = Math.max(4, Math.min(top, window.innerHeight - imgHeight - 4));

        let left = rect.left;
        if (left + imgWidth > window.innerWidth - 4) {
            left = window.innerWidth - imgWidth - 4;
        }
        left = Math.max(4, left);

        // Set position imperatively before showing to avoid flicker
        imgRef.current.style.top = `${top}px`;
        imgRef.current.style.left = `${left}px`;

        setShowPreview(true);
    }, []);

    if (!card.imageUrl) {
        return <>{card.cardName}</>;
    }

    return (
        <span
            ref={spanRef}
            className="card-hover"
            data-quantity={card.quantity}
            onMouseEnter={handleMouseEnter}
            onMouseLeave={() => setShowPreview(false)}
            onClick={(event) => {
                if (isMobile && onMobileTap) {
                    event.preventDefault();
                    onMobileTap();
                }
            }}
        >
            {card.cardName}
            <img
                ref={imgRef}
                className="card-hover-img"
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
    onCardTap?: (card: CardData) => void;
}> = ({ label, cards, tableLabels, onCardTap }) => (
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
                                <CardName card={card} onMobileTap={onCardTap ? () => onCardTap(card) : undefined} />
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
    onCardTap?: (card: CardData) => void;
}> = ({ groupedCards, labels, onCardTap }) => {
    const pokemonCards = groupedCards.pokemon ?? [];
    const trainerCards = groupedCards.trainer ?? [];
    const energyCards = groupedCards.energy ?? [];
    const energyInLeft = pokemonCards.length <= trainerCards.length;

    const tableLabels = { qty: labels.tableQty, card: labels.tableCard, set: labels.tableSet };

    return (
        <div className="row">
            <div className="col-md-6 mb-3">
                {pokemonCards.length > 0 && (
                    <CardSection label={labels.sectionPokemon} cards={pokemonCards} tableLabels={tableLabels} onCardTap={onCardTap} />
                )}
                {energyInLeft && energyCards.length > 0 && (
                    <div className="mt-3">
                        <CardSection label={labels.sectionEnergy} cards={energyCards} tableLabels={tableLabels} onCardTap={onCardTap} />
                    </div>
                )}
            </div>
            <div className="col-md-6 mb-3">
                {trainerCards.length > 0 && (
                    <CardSection label={labels.sectionTrainer} cards={trainerCards} tableLabels={tableLabels} onCardTap={onCardTap} />
                )}
                {!energyInLeft && energyCards.length > 0 && (
                    <div className="mt-3">
                        <CardSection label={labels.sectionEnergy} cards={energyCards} tableLabels={tableLabels} onCardTap={onCardTap} />
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
    const [cardModalOpen, setCardModalOpen] = useState(false);
    const [cardModalIndex, setCardModalIndex] = useState(0);
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

    const flatCards: CardEntry[] = useMemo(() => {
        const entries: CardEntry[] = [];
        const order = ['pokemon', 'trainer', 'energy'];

        for (const section of order) {
            for (const card of activeCards[section] ?? []) {
                if (card.imageUrl) {
                    entries.push({ imageUrl: card.imageUrl, name: card.cardName, quantity: card.quantity });
                }
            }
        }

        return entries;
    }, [activeCards]);

    const handleCardTap = useCallback((card: CardData) => {
        const index = flatCards.findIndex((entry) => entry.imageUrl === card.imageUrl && entry.name === card.cardName);
        if (index >= 0) {
            setCardModalIndex(index);
            setCardModalOpen(true);
        }
    }, [flatCards]);

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
                <CardTable groupedCards={activeCards} labels={labels} onCardTap={handleCardTap} />
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

            {/* Card image gallery modal (mobile tap-to-view with swipe) */}
            <CardImageModal
                opened={cardModalOpen}
                cards={flatCards}
                currentIndex={cardModalIndex}
                onClose={() => setCardModalOpen(false)}
                onNavigate={setCardModalIndex}
            />

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
