/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

describe('test infrastructure', () => {
    it('creates a DOM element', () => {
        const div = document.createElement('div');
        div.textContent = 'Hello Expanded Decks';
        document.body.appendChild(div);

        expect(div).toBeInTheDocument();
        expect(div).toHaveTextContent('Hello Expanded Decks');
    });
});
