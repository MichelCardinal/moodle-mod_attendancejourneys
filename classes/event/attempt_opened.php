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
 * An authorised teacher opened a personal retake of an attendance obligation.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_opened extends \core\event\base {
    /**
     * Initialise the updated object.
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'attendancejourneys_attempts';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventattemptopened', 'attendancejourneys');
    }

    /**
     * Description for the Moodle event log.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' opened attempt '{$this->objectid}' " .
            "for participant '{$this->relateduserid}' in obligation '{$this->other['rootjourneyid']}'.";
    }

    /**
     * Activity page.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/attendancejourneys/individual.php', [
            'id' => $this->contextinstanceid, 'userid' => $this->relateduserid, 'journeyid' => $this->other['journeyid'],
        ]);
    }

    /**
     * Map the personal attempt identifier when restoring log references.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'attendancejourneys_attempts', 'restore' => 'attendancejourneys_attempt'];
    }

    /**
     * Mapping of the owning activity when restoring log references.
     *
     * @return array
     */
    public static function get_other_mapping(): array {
        return [
            'attendancejourneysid' => ['db' => 'attendancejourneys', 'restore' => 'attendancejourneys'],
            'rootjourneyid' => ['db' => 'attendancejourneys_journeys', 'restore' => 'attendancejourneys_journey'],
            'journeyid' => ['db' => 'attendancejourneys_journeys', 'restore' => 'attendancejourneys_journey'],
        ];
    }
}
