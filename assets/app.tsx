/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

const App: React.FC = () => (
    <h1>Expanded Decks</h1>
);

const container = document.getElementById('app');
if (container) {
    createRoot(container).render(<App />);
}
