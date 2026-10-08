// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Interactive multi-session student self-recording form.
 *
 * @module     mod_attendancejourneys/selfbulk
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const updateProgress = (form) => {
    const selected = form.querySelectorAll(
        '.attendancejourneys-selfbulk-status-input:checked:not([value="unrecorded"])'
    ).length;
    const progress = document.querySelector('.attendancejourneys-selfbulk-progress');
    const value = progress?.querySelector('.attendancejourneys-selfbulk-progress-value');
    if (progress && value) {
        value.textContent = progress.dataset.label.replace('{count}', selected);
    }
};

const updateRow = (row) => {
    const selected = row.querySelector('.attendancejourneys-selfbulk-status-input:checked');
    const partialPanel = row.querySelector('.attendancejourneys-selfbulk-partial');
    const minutes = partialPanel?.querySelector('input');
    const calculation = row.querySelector('.attendancejourneys-selfbulk-calculation');
    if (!partialPanel || !minutes || !calculation) {
        return;
    }
    const partial = selected?.value === 'partial';
    partialPanel.hidden = !partial;
    minutes.disabled = !partial;
    if (!selected || selected.value === 'unrecorded') {
        calculation.textContent = '—';
        return;
    }
    const duration = Number.parseInt(row.dataset.duration, 10) || 0;
    const absent = partial
        ? Math.max(0, Math.min(duration, Number.parseInt(minutes.value, 10) || 0))
        : 0;
    const present = Math.max(0, duration - absent);
    const percent = duration > 0 ? ((present / duration) * 100).toFixed(1) : '0.0';
    calculation.textContent = row.dataset.template
        .replace('{present}', present)
        .replace('{possible}', duration)
        .replace('{percent}', percent);
};

export const init = () => {
    const form = document.querySelector('#attendancejourneys-selfbulk-form');
    if (!form) {
        return;
    }
    form.querySelectorAll('.attendancejourneys-selfbulk-row:not(.is-locked)').forEach((row) => {
        row.querySelectorAll('.attendancejourneys-selfbulk-status-input').forEach((radio) => {
            radio.addEventListener('change', () => {
                updateRow(row);
                updateProgress(form);
            });
        });
        row.querySelector('.attendancejourneys-selfbulk-partial input')?.addEventListener('input', () => updateRow(row));
        updateRow(row);
    });
    updateProgress(form);
};
