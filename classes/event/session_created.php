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
 * Logged when an attendance session is created.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_created extends \core\event\base {
    /**
     * Set the event operation, education level and affected table.
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'attendancejourneys_sessions';
    }
    /**
     * Return the translated event name.
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventsessioncreated', 'attendancejourneys');
    }
    /**
     * Describe the action and its actor for the Moodle event log.
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' created attendance session '{$this->objectid}' " .
            "in activity '{$this->other['attendancejourneysid']}'.";
    }
    /**
     * Return the page associated with this event.
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/attendancejourneys/sessions.php', ['id' => $this->contextinstanceid]);
    }
}
