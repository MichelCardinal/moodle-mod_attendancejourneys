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
 * Test data generator for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @category   test
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2026 Michel Cardinal
 */
class mod_attendancejourneys_generator extends testing_module_generator {
    /**
     * Create an Attendance Journeys activity for automated tests.
     *
     * @param stdClass|array|null $record Activity field overrides for the generated instance.
     * @param array|null $options Options passed to the module generator.
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) ($record ?? []);
        // Most existing fixtures characterise historical activities; null exercises normal creation.
        if (!property_exists($record, 'journeygrading')) {
            $record->journeygrading = 0;
        }
        $record->name = $record->name ?? 'Attendance Journeys test';
        $record->intro = $record->intro ?? '';
        $record->introformat = $record->introformat ?? FORMAT_HTML;
        $record->experiencemode = $record->experiencemode ?? 'professional';
        $record->percentageenabled = $record->percentageenabled ?? 1;
        $record->passinggrade = $record->passinggrade ?? 80;
        $record->gradeenabled = $record->gradeenabled ?? 0;
        $record->excusedenabled = $record->excusedenabled ?? 1;
        $record->excusedmode = $record->excusedmode ?? 'excluded';
        $record->completionrecorded = $record->completionrecorded ?? 0;
        $record->completionallsessions = $record->completionallsessions ?? 0;
        $record->completionpass = $record->completionpass ?? 0;
        $record->completionclosed = $record->completionclosed ?? 0;
        $record->studentselfrecord = $record->studentselfrecord ?? 0;
        $record->calendarenabled = $record->calendarenabled ?? 0;
        return parent::create_instance($record, $options);
    }
}
