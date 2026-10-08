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

namespace mod_attendancejourneys\event;

/**
 * Event triggered when an authorised user permanently resets a participant's activity data.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_data_reset extends \core\event\base {
    /**
     * Defines event properties.
     */
    protected function init(): void {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'attendancejourneys';
    }

    /**
     * Return the translated event name.
     *
     * @return string Localised event name.
     */
    public static function get_name(): string {
        return get_string('eventuserdatareset', 'attendancejourneys');
    }

    /**
     * Describe the action and its actor for the Moodle event log.
     *
     * @return string Human-readable event description.
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' reset Attendance Journeys data for the user with id " .
            "'{$this->relateduserid}' in the activity with id '{$this->objectid}'.";
    }

    /**
     * Return the page associated with this event.
     *
     * @return \moodle_url Activity URL.
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/attendancejourneys/view.php', ['id' => $this->contextinstanceid]);
    }
}
