/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Strip leading zeros from a card number for user-facing display
 * (PTCG Live's text format expects "DRI 51", not "DRI 051").
 *
 * @see docs/features.md F6.7 — Export deck list as PTCGL text
 */
export const displayCardNumber = (cardNumber: string): string => {
    if (cardNumber === '') {
        return '';
    }

    const stripped = cardNumber.replace(/^0+/, '');

    return stripped === '' ? '0' : stripped;
};
