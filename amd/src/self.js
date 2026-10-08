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
 * Interactive student self-recording form.
 *
 * @module     mod_attendancejourneys/self
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initializes the form and calculated attendance preview.
 *
 * @param {Number} duration Session duration in minutes.
 */
export const init = (duration) => {
    const form = document.querySelector('#attendancejourneys-self-form');
    const partialPanel = form?.querySelector('.attendancejourneys-self-partial');
    const minutes = form?.querySelector('#attendancejourneys-self-minutes');
    const calculation = form?.querySelector('.attendancejourneys-self-calculation');
    const calculationValue = calculation?.querySelector('.attendancejourneys-self-calculation-value');

    if (!form || !partialPanel || !minutes || !calculation || !calculationValue) {
        return;
    }

    const refresh = () => {
        const status = form.querySelector('input[name="status"]:checked')?.value || 'present';
        const partial = status === 'partial';
        partialPanel.hidden = !partial;
        minutes.disabled = !partial;

        const absent = partial
            ? Math.max(0, Math.min(duration, Number.parseInt(minutes.value, 10) || 0))
            : 0;
        const present = Math.max(0, duration - absent);
        const percent = duration > 0 ? ((present / duration) * 100).toFixed(1) : '0.0';
        calculationValue.textContent = calculation.dataset.template
            .replace('{present}', present)
            .replace('{possible}', duration)
            .replace('{percent}', percent);
    };

    form.querySelectorAll('input[name="status"]').forEach((radio) => radio.addEventListener('change', refresh));
    minutes.addEventListener('input', refresh);
    refresh();
};
