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

import * as FormChangeChecker from 'core_form/changechecker';
import * as Notification from 'core/notification';
import {getString} from 'core/str';

/**
 * Interactive partial-attendance calculations.
 *
 * @module     mod_attendancejourneys/take
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Updates one participant row.
 *
 * @param {HTMLInputElement} selected Status radio button.
 * @param {Number} duration Session duration in minutes.
 * @param {Object} options Calculation policy and translated display strings.
 */
const updateRow = (selected, duration, options) => {
    const userId = selected.dataset.userid;
    const minutes = document.querySelector(`.attendancejourneys-minutes[data-userid="${userId}"]`);
    const calculation = document.querySelector(`.attendancejourneys-calculation[data-userid="${userId}"]`);
    const partial = selected.value === 'partial';
    minutes.hidden = !partial;
    minutes.disabled = !partial;
    minutes.closest('.attendancejourneys-minutes-field').hidden = !partial;

    if (selected.value === 'unrecorded') {
        calculation.textContent = '—';
        return;
    }

    if ((options.journeygrading && selected.value === 'exempt') ||
            (!options.journeygrading && selected.value === 'excused' && options.excusedmode === 'excluded')) {
        calculation.textContent = options.excludedlabel;
        return;
    }
    let present = 0;
    if (selected.value === 'present' ||
            (!options.journeygrading && selected.value === 'excused' && options.excusedmode === 'present')) {
        present = duration;
    } else if (partial) {
        present = Math.max(0, duration - (Number.parseInt(minutes.value, 10) || 0));
    }
    present = Math.min(duration, present);
    const percent = (duration > 0 ? ((present / duration) * 100) : 0).toFixed(1).replace('.', options.decimalpoint);
    calculation.textContent = options.calculationtemplate.replace('{present}', present)
        .replace('{total}', duration).replace('{percent}', percent);
};

/**
 * Normalizes text for a forgiving participant search.
 *
 * @param {String} value Text to normalize.
 * @returns {String}
 */
const normalize = (value) => value.toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

/**
 * Applies the participant search and status filter without disabling form values.
 */
const filterParticipants = () => {
    const search = document.querySelector('#attendancejourneys-participant-search');
    const status = document.querySelector('#attendancejourneys-participant-status-filter');
    const source = document.querySelector('#attendancejourneys-participant-source-filter');
    const rows = document.querySelectorAll('.attendancejourneys-attendance-row');
    const query = normalize(search?.value.trim() || '');
    const selectedStatus = status?.value || 'all';
    const selectedSource = source?.value || 'all';
    let visible = 0;

    rows.forEach((row) => {
        const nameMatches = query === '' || normalize(row.dataset.participantName || '').includes(query);
        const statusMatches = selectedStatus === 'all' || row.dataset.attendanceState === selectedStatus;
        const sourceMatches = selectedSource === 'all' || row.dataset.recordSource === selectedSource;
        row.hidden = !(nameMatches && statusMatches && sourceMatches);
        if (!row.hidden) {
            visible++;
        }
    });

    const count = document.querySelector('.attendancejourneys-participant-count');
    if (count) {
        count.textContent = count.dataset.label
            .replace('{visible}', visible)
            .replace('{total}', rows.length);
    }
    const empty = document.querySelector('.attendancejourneys-no-filtered-participants');
    if (empty) {
        empty.hidden = visible > 0;
    }
};

/**
 * Refreshes the progress of the attendance sheet.
 */
const updateProgress = () => {
    const container = document.querySelector('.attendancejourneys-entry-progress');
    if (!container) {
        return;
    }
    const rows = Array.from(document.querySelectorAll('.attendancejourneys-attendance-row'));
    const recorded = rows.filter((row) => row.dataset.attendanceState !== 'unrecorded').length;
    const total = rows.length;
    const remaining = total - recorded;
    const label = container.querySelector('.attendancejourneys-entry-progress-label');
    const state = container.querySelector('.attendancejourneys-entry-progress-state');
    const bar = container.querySelector('.progress-bar');
    label.textContent = container.dataset.label.replace('{recorded}', recorded).replace('{total}', total);
    state.textContent = remaining === 0 ? container.dataset.completeLabel :
        container.dataset.remainingLabel.replace('{remaining}', remaining);
    bar.style.width = `${total > 0 ? Math.round((recorded / total) * 100) : 0}%`;
    bar.setAttribute('aria-valuemax', total);
    bar.setAttribute('aria-valuenow', recorded);
    container.classList.toggle('is-complete', remaining === 0);
};

/**
 * Initializes the attendance form.
 *
 * @param {Number} duration Session duration in minutes.
 * @param {Object} options Calculation policy and translated display strings.
 */
export const init = (duration, options) => {
    const form = document.querySelector('#attendancejourneys-take-form');
    document.querySelectorAll('.attendancejourneys-status:checked').forEach((selected) => {
        updateRow(selected, duration, options);
    });
    document.querySelectorAll('.attendancejourneys-status').forEach((radio) => {
        radio.addEventListener('change', () => {
            if (radio.checked) {
                updateRow(radio, duration, options);
                radio.closest('.attendancejourneys-attendance-row').dataset.attendanceState = radio.value;
                filterParticipants();
                updateProgress();
            }
        });
    });
    document.querySelectorAll('.attendancejourneys-minutes').forEach((input) => {
        input.addEventListener('input', () => {
            const selected = document.querySelector(
                `.attendancejourneys-status[data-userid="${input.dataset.userid}"]:checked`
            );
            updateRow(selected, duration, options);
        });
    });
    document.querySelectorAll('.attendancejourneys-bulk-status').forEach((button) => {
        button.addEventListener('click', () => {
            const status = button.dataset.status;
            const visibleRows = Array.from(document.querySelectorAll('.attendancejourneys-attendance-row'))
                .filter((row) => !row.hidden);
            const applyChanges = () => {
                let commonMinutes = 0;
                if (status === 'partial') {
                    const partialInput = document.querySelector('#attendancejourneys-bulk-partial-minutes');
                    commonMinutes = Math.max(1, Math.min(duration - 1,
                        Number.parseInt(partialInput?.value, 10) || 1));
                    if (partialInput) {
                        partialInput.value = commonMinutes;
                    }
                }
                let changed = false;
                visibleRows.forEach((row) => {
                    const radio = row.querySelector(`.attendancejourneys-status[value="${status}"]`);
                    if (!radio) {
                        return;
                    }
                    const minutes = row.querySelector('.attendancejourneys-minutes');
                    changed = changed || row.dataset.attendanceState !== status ||
                        (status === 'partial' && Number.parseInt(minutes.value, 10) !== commonMinutes);
                    radio.checked = true;
                    if (status === 'partial') {
                        minutes.value = commonMinutes;
                    }
                    updateRow(radio, duration, options);
                    row.dataset.attendanceState = status;
                });
                if (changed && form) {
                    FormChangeChecker.markFormAsDirty(form);
                }
                filterParticipants();
                updateProgress();
            };
            if (status === 'unrecorded' && button.dataset.confirm &&
                    visibleRows.some((row) => row.dataset.attendanceState !== 'unrecorded')) {
                // The modal body accepts HTML; preserve the confirmation as plain text.
                const message = document.createElement('div');
                message.textContent = button.dataset.confirm;
                Notification.saveCancel(
                    getString('confirm'),
                    message.innerHTML,
                    getString('continue'),
                    applyChanges,
                    null,
                    {triggerElement: button}
                ).catch(Notification.exception);
            } else {
                applyChanges();
            }
        });
    });
    document.querySelector('#attendancejourneys-participant-search')?.addEventListener('input', filterParticipants);
    document.querySelector('#attendancejourneys-participant-status-filter')?.addEventListener('change', filterParticipants);
    document.querySelector('#attendancejourneys-participant-source-filter')?.addEventListener('change', filterParticipants);
    filterParticipants();
    updateProgress();
};
