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

namespace mod_attendancejourneys;

/**
 * Journey closure and reopening tests.
 *
 * @package    mod_attendancejourneys
 * @category   test
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class journey_lifecycle_test extends \advanced_testcase {
    public function test_individual_journey_result_is_frozen_then_reopened_without_losing_history(): void {
        global $DB;
        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', [
            'course' => $course->id, 'percentageenabled' => 1, 'passinggrade' => 80,
        ]);
        $participant = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $journeyid = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Lifecycle journey', 'description' => '',
            'groupid' => 0, 'startdate' => 0, 'enddate' => 0, 'defaultmodality' => 'unspecified',
            'active' => 1, 'completedby' => 0, 'timecompleted' => 0, 'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $sessionid = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'name' => 'Completed session',
            'sessiondate' => time() - HOURSECS, 'duration' => 60, 'groupid' => 0, 'description' => '',
            'modality' => 'unspecified', 'location' => '', 'meetingurl' => '',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('attendancejourneys_members', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'userid' => $participant->id,
            'attemptnumber' => 1, 'active' => 1, 'timeassigned' => time(), 'timeended' => 0,
        ]);
        $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $sessionid, 'userid' => $participant->id, 'status' => 'present',
            'minutesabsent' => 0, 'remarks' => '', 'takenby' => $teacher->id, 'approved' => 1,
            'approvedby' => $teacher->id, 'timeapproved' => time(), 'changesrequested' => 0,
            'reviewnote' => '', 'reviewedby' => $teacher->id, 'timereviewed' => time(),
            'timemodified' => time(),
        ]);

        $closure = attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $journeyid);
        $this->assertSame('passed', $closure->result);
        $this->assertEquals(100.0, (float) $closure->percentage);
        $this->assertSame(0, (int) $DB->get_field('attendancejourneys_members', 'active', [
            'journeyid' => $journeyid, 'userid' => $participant->id,
        ]));

        $this->assertTrue(attendancejourneys_reopen_user_journey($activity, $participant->id, $teacher->id));
        $savedclosure = $DB->get_record('attendancejourneys_closures', ['id' => $closure->id], '*', MUST_EXIST);
        $this->assertSame(0, (int) $savedclosure->active);
        $this->assertSame((int) $teacher->id, (int) $savedclosure->reopenedby);
        $this->assertGreaterThan(0, (int) $savedclosure->timereopened);
        $this->assertSame(1, (int) $DB->get_field('attendancejourneys_members', 'active', [
            'journeyid' => $journeyid, 'userid' => $participant->id,
        ]));
        $this->assertSame(1, $DB->count_records('attendancejourneys_closures'));
    }
}
