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
 * Field switching for the bulk session-details form.
 *
 * @module     mod_attendancejourneys/bulkdetails
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = () => {
    const operation = document.getElementById('attendancejourneys-details-operation');
    const textWrap = document.getElementById('attendancejourneys-details-text-wrap');
    const modalityWrap = document.getElementById('attendancejourneys-details-modality-wrap');
    const descriptionWrap = document.getElementById('attendancejourneys-details-description-wrap');
    if (!operation || !textWrap || !modalityWrap || !descriptionWrap) {
        return;
    }
    const refresh = () => {
        const modality = operation.value === 'modality';
        const description = operation.value === 'description';
        textWrap.hidden = modality || description;
        modalityWrap.hidden = !modality;
        descriptionWrap.hidden = !description;
    };
    operation.addEventListener('change', refresh);
    refresh();
};
