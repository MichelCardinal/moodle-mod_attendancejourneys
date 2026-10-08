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
 * Accessible select-all behaviour for Attendance Journeys bulk forms.
 *
 * @module     mod_attendancejourneys/selection
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initializes one bulk-selection form.
 *
 * @param {String} selectAllId ID of the select-all checkbox.
 * @param {String} checkboxSelector Selector for row checkboxes.
 * @param {String[]} actionIds IDs of buttons enabled when rows are selected.
 */
export const init = (selectAllId, checkboxSelector, actionIds) => {
    const selectAll = document.getElementById(selectAllId);
    const boxes = Array.from(document.querySelectorAll(checkboxSelector));
    const actions = actionIds.map((id) => document.getElementById(id)).filter(Boolean);
    if (!selectAll) {
        return;
    }
    const refresh = () => {
        const checked = boxes.filter((box) => box.checked).length;
        selectAll.checked = boxes.length > 0 && checked === boxes.length;
        selectAll.indeterminate = checked > 0 && checked < boxes.length;
        actions.forEach((action) => {
            action.disabled = checked === 0;
        });
    };
    selectAll.addEventListener('change', () => {
        boxes.forEach((box) => {
            box.checked = selectAll.checked;
        });
        refresh();
    });
    boxes.forEach((box) => box.addEventListener('change', refresh));
    refresh();
};
