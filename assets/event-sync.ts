/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Event sync: fetches event data from the Pokemon event page via backend
 * proxy and prefills the event form fields.
 *
 * @see docs/features.md F3.18 — Sync from Pokemon event page
 */

interface SyncResponse {
    success: boolean;
    data: Record<string, string | number | null>;
    error?: string;
    code?: string;
}

/**
 * Maps API response keys to Symfony form input IDs.
 */
const FIELD_MAP: Record<string, string> = {
    name: 'event_form_name',
    startDate: 'event_form_date',
    location: 'event_form_location',
    entryFeeAmount: 'event_form_entryFeeAmount',
    entryFeeCurrency: 'event_form_entryFeeCurrency',
    tournamentStructure: 'event_form_tournamentStructure',
    registrationLink: 'event_form_registrationLink',
};

/** Fields that are always overwritten silently (no confirm dialog). */
const SILENT_OVERWRITE_FIELDS = new Set(['startDate']);

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('event-sync-btn') as HTMLButtonElement | null;
    if (!btn) return;

    btn.addEventListener('click', () => handleSync(btn));
});

async function handleSync(btn: HTMLButtonElement): Promise<void> {
    const tournamentIdInput = document.getElementById('event_form_eventId') as HTMLInputElement | null;
    const tournamentId = tournamentIdInput?.value?.trim() ?? '';

    if (!tournamentId) {
        showAlert(btn.dataset.errorEmpty ?? 'Please enter a Tournament ID first.', 'danger');
        return;
    }

    const syncUrl = btn.dataset.syncUrl;
    if (!syncUrl) return;

    setLoading(btn, true);

    try {
        const response = await fetch(syncUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tournamentId }),
        });

        const result: SyncResponse = await response.json();

        if (!response.ok || !result.success) {
            showAlert(result.error ?? btn.dataset.errorNetwork ?? 'An error occurred.', 'danger');
            return;
        }

        prefillForm(result.data, btn);
    } catch {
        showAlert(btn.dataset.errorNetwork ?? 'Network error. Could not reach the server.', 'danger');
    } finally {
        setLoading(btn, false);
    }
}

function prefillForm(data: Record<string, string | number | null>, btn: HTMLButtonElement): void {
    const filledFields: string[] = [];

    for (const [key, formId] of Object.entries(FIELD_MAP)) {
        const value = data[key];
        if (value === null || value === undefined) continue;

        const input = document.getElementById(formId) as HTMLInputElement | HTMLSelectElement | null;
        if (!input) continue;

        let stringValue = String(value);

        // For date fields, convert YYYY-MM-DD to YYYY-MM-DDT10:00 for datetime-local
        if (key === 'startDate' && input.type === 'datetime-local') {
            stringValue = stringValue + 'T10:00';
        }

        // For entry fee, convert cents to display value
        if (key === 'entryFeeAmount' && typeof value === 'number') {
            stringValue = (value / 100).toFixed(2);
        }

        const currentValue = input.value.trim();

        if (currentValue && currentValue !== stringValue && !SILENT_OVERWRITE_FIELDS.has(key)) {
            const confirmMsg = btn.dataset.confirmOverwrite ?? 'This field already has a value. Overwrite?';
            const label = input.labels?.[0]?.textContent?.trim() ?? key;
            if (!confirm(`${label}: ${confirmMsg}`)) {
                continue;
            }
        }

        if (currentValue === stringValue) continue;

        input.value = stringValue;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        filledFields.push(input.labels?.[0]?.textContent?.trim() ?? key);
    }

    if (filledFields.length > 0) {
        const successMsg = btn.dataset.successMsg ?? 'Synced! Prefilled fields:';
        showAlert(`${successMsg} ${filledFields.join(', ')}`, 'success');
    } else {
        showAlert(btn.dataset.noChangesMsg ?? 'All fields already filled. No changes made.', 'info');
    }
}

function setLoading(btn: HTMLButtonElement, loading: boolean): void {
    btn.disabled = loading;
    const icon = btn.querySelector('i');

    if (loading) {
        btn.dataset.originalHtml = btn.innerHTML;
        if (icon) {
            icon.className = 'spinner-border spinner-border-sm';
            icon.setAttribute('role', 'status');
        }
    } else {
        if (btn.dataset.originalHtml) {
            btn.innerHTML = btn.dataset.originalHtml;
            delete btn.dataset.originalHtml;
        }
    }
}

function showAlert(message: string, type: 'success' | 'info' | 'danger'): void {
    const row = document.getElementById('event-sync-row');
    if (!row) return;

    // Remove any existing alert
    const existing = row.querySelector('.alert.event-sync-alert');
    existing?.remove();

    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show event-sync-alert mt-2`;
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    row.appendChild(alert);

    // Auto-dismiss after 8 seconds
    setTimeout(() => alert.remove(), 8000);
}
