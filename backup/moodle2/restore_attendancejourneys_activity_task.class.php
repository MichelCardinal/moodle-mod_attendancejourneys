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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/attendancejourneys/backup/moodle2/restore_attendancejourneys_stepslib.php');

/**
 * Attendance Journeys restore task.
 *
 * @package    mod_attendancejourneys
 * @category   backup
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_attendancejourneys_activity_task extends restore_activity_task {
    /**
     * Use the standard activity backup and restore settings.
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Add the Attendance Journeys XML structure step to this task.
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_attendancejourneys_activity_structure_step(
            'attendancejourneys_structure',
            'attendancejourneys.xml'
        ));
    }

    /**
     * Identify activity introduction content containing restorable links.
     * @return array
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('attendancejourneys', ['intro'], 'attendancejourneys')];
    }

    /**
     * Define how encoded activity and course links are restored.
     * @return array
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('ATTENDANCEPLUSVIEWBYID', '/mod/attendancejourneys/view.php?id=$1', 'course_module'),
            new restore_decode_rule('ATTENDANCEPLUSINDEX', '/mod/attendancejourneys/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Return the legacy activity log rules; none are required.
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Return the legacy course log rules; none are required.
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
