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
 * An authorised teacher changed one participant’s journey obligation.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class individual_obligation_changed extends \core\event\base {
    /**
     * Initialise the updated object.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'attendancejourneys_obligations';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventindividualobligationchanged', 'attendancejourneys');
    }

    /**
     * Description for the Moodle event log.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' changed obligation '{$this->objectid}' " .
            "for participant '{$this->relateduserid}' in journey '{$this->other['journeyid']}'.";
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
     * Map the obligation identifier when restoring log references.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'attendancejourneys_obligations', 'restore' => 'attendancejourneys_obligation'];
    }

    /**
     * Mapping of the owning activity when restoring log references.
     *
     * @return array
     */
    public static function get_other_mapping(): array {
        return [
            'attendancejourneysid' => ['db' => 'attendancejourneys', 'restore' => 'attendancejourneys'],
            'journeyid' => ['db' => 'attendancejourneys_journeys', 'restore' => 'attendancejourneys_journey'],
        ];
    }
}
