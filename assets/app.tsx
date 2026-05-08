/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import './styles/app.scss';
import { Tooltip } from 'bootstrap';

type ThemeMode = 'light' | 'dark' | 'auto';

declare global {
    interface Window {
        __edTheme?: {
            get: () => ThemeMode;
            apply: (mode: ThemeMode) => void;
        };
    }
}

const initThemeSwitcher = (): void => {
    const container = document.querySelector<HTMLElement>('[data-theme-switcher]');
    if (!container || !window.__edTheme) {
        return;
    }
    const buttons = container.querySelectorAll<HTMLButtonElement>('button[data-theme-value]');
    const markActive = (mode: ThemeMode): void => {
        buttons.forEach((button) => {
            const value = button.dataset.themeValue;
            const isActive = value === mode;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };
    markActive(window.__edTheme.get());
    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const value = button.dataset.themeValue as ThemeMode | undefined;
            if (value !== 'light' && value !== 'dark' && value !== 'auto') {
                return;
            }
            window.__edTheme?.apply(value);
            markActive(value);
        });
    });
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => new Tooltip(element));
    initThemeSwitcher();
});
