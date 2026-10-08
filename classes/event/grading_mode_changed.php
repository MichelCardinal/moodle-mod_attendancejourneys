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
 * An administrator explicitly converted an unfinished historical activity.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading_mode_changed extends \core\event\base {
    /**
     * Initialise the updated object.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'attendancejourneys';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventgradingmodechanged', 'attendancejourneys');
    }

    /**
     * Description for the Moodle event log.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' converted attendance activity '{$this->objectid}' " .
            "to separate journey grading, retaining grade item number 0 for journey '{$this->other['journeyid']}'.";
    }

    /**
     * Activity page.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/attendancejourneys/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Map the activity identifier when restoring log references.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'attendancejourneys', 'restore' => 'attendancejourneys'];
    }

    /**
     * Mapping of the retained journey when restoring log references.
     *
     * @return array
     */
    public static function get_other_mapping(): array {
        return ['journeyid' => ['db' => 'attendancejourneys_journeys', 'restore' => 'attendancejourneys_journey']];
    }
}
