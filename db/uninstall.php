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

/**
 * Native activity cleanup before Attendance Journeys tables are uninstalled.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Removes activity instances through Moodle's full course-module deletion API.
 *
 * @return bool True when all activity instances have been removed.
 */
function xmldb_attendancejourneys_uninstall(): bool {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/course/lib.php');
    $moduleid = $DB->get_field('modules', 'id', ['name' => 'attendancejourneys']);
    if (!$moduleid) {
        return true;
    }
    $cmids = $DB->get_fieldset_select('course_modules', 'id', 'module = :module', ['module' => $moduleid]);
    foreach ($cmids as $cmid) {
        if (method_exists(\core_courseformat\local\cmactions::class, 'delete')) {
            $coursecontext = context_module::instance($cmid)->get_course_context();
            \core_courseformat\formatactions::cm($coursecontext->instanceid)->delete((int) $cmid);
        } else {
            course_delete_module((int) $cmid);
        }
    }
    return true;
}
