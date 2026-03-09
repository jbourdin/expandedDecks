/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import './styles/app.scss';
import { Alert, Collapse, Dropdown, Tooltip } from 'bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new Tooltip(el));
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(el => new Collapse(el, { toggle: false }));
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => new Dropdown(el));
    document.querySelectorAll('[data-bs-dismiss="alert"]').forEach(el => new Alert(el));
});
