<?php
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

declare(strict_types=1);

namespace mod_attendancejourneys\grades;

/**
 * Map dynamically allocated journey items to distinct Moodle activity form fields.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gradeitems implements
    \core_grades\local\gradeitem\fieldname_mapping,
    \core_grades\local\gradeitem\itemnumber_mapping {
    /**
     * Expose the standard activity field; journey-specific fields are mapped dynamically.
     *
     * @return array Standard item mapping shared by all activity instances.
     */
    public static function get_itemname_mapping_for_component(): array {
        return [0 => ''];
    }

    /**
     * Preserve the historical first item and avoid a fixed list of journey identifiers.
     *
     * @param string $component Component owning the grade item.
     * @param int $itemnumber Stable item number allocated by the plugin.
     * @param string $fieldname Base Moodle form field name.
     * @return string Field specific to this item.
     */
    public static function get_field_name_for_itemnumber(string $component, int $itemnumber, string $fieldname): string {
        return $itemnumber === 0 ? $fieldname : $fieldname . '_journey' . $itemnumber;
    }
}
