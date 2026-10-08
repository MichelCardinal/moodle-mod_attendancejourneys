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

#[\PHPUnit\Framework\Attributes\Group('mod_attendancejourneys')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_touch_session')]
/**
 * Database structure and optimistic-lock tests.
 *
 * @group mod_attendancejourneys
 * @covers ::attendancejourneys_delete_instance
 * @covers ::attendancejourneys_touch_session
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class database_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
    }

    public function test_performance_indexes_exist(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $expectations = [
            ['attendancejourneys_sessions', 'activitydate', ['attendancejourneysid', 'sessiondate']],
            ['attendancejourneys_sessions', 'activityjourney', ['attendancejourneysid', 'journeyid']],
            ['attendancejourneys_records', 'userid', ['userid']],
            ['attendancejourneys_closures', 'journeyuseractive', ['journeyid', 'userid', 'active']],
            ['attendancejourneys_waitlist', 'journeystatustime', ['journeyid', 'status', 'timecreated']],
            ['attendancejourneys_equivalences', 'targetuserstatus', ['targetsessionid', 'userid', 'status']],
            ['attendancejourneys_equivalences', 'sourceuserstatus', ['sourcesessionid', 'userid', 'status']],
        ];
        foreach ($expectations as [$table, $name, $fields]) {
            $this->assertTrue($dbman->index_exists(
                new \xmldb_table($table),
                new \xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, $fields)
            ));
        }
    }

    public function test_sheet_version_increases_within_same_second(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $activityid = $DB->insert_record('attendancejourneys', (object) [
            'course' => $course->id, 'name' => 'Version test', 'intro' => '', 'introformat' => FORMAT_HTML,
            'percentageenabled' => 1, 'passinggrade' => 80, 'gradeenabled' => 0,
            'excusedenabled' => 1, 'excusedmode' => 'excluded', 'completionrecorded' => 0,
            'completionallsessions' => 0, 'completionpass' => 0, 'completionclosed' => 0,
            'studentselfrecord' => 0, 'calendarenabled' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $sessionid = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activityid, 'journeyid' => 0, 'name' => 'Concurrent test',
            'sessiondate' => time(), 'duration' => 60, 'groupid' => 0, 'description' => '',
            'modality' => 'unspecified', 'location' => '', 'meetingurl' => '',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $first = attendancejourneys_touch_session($sessionid);
        $second = attendancejourneys_touch_session($sessionid);
        $this->assertGreaterThan($first, $second);
        $this->assertSame(
            $second,
            (int) $DB->get_field('attendancejourneys_sessions', 'timemodified', ['id' => $sessionid])
        );
    }

    public function test_delete_instance_removes_all_dependent_operational_data(): void {
        global $DB;
        require_once(__DIR__ . '/../lib.php');

        $course = $this->getDataGenerator()->create_course();
        $participant = $this->getDataGenerator()->create_user();
        $staff = $this->getDataGenerator()->create_user();
        $activityid = $DB->insert_record('attendancejourneys', (object) [
            'course' => $course->id, 'name' => 'Deletion test', 'intro' => '', 'introformat' => FORMAT_HTML,
            'percentageenabled' => 1, 'passinggrade' => 80, 'gradeenabled' => 0,
            'excusedenabled' => 1, 'excusedmode' => 'excluded', 'completionrecorded' => 0,
            'completionallsessions' => 0, 'completionpass' => 0, 'completionclosed' => 0,
            'studentselfrecord' => 0, 'calendarenabled' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $journeyid = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activityid, 'name' => 'Journey', 'description' => '', 'groupid' => 0,
            'startdate' => 0, 'enddate' => 0, 'defaultmodality' => 'unspecified', 'active' => 1,
            'completedby' => 0, 'timecompleted' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('attendancejourneys_rooms', (object) [
            'attendancejourneysid' => $activityid, 'name' => 'Room', 'code' => 'R1', 'capacity' => 20,
            'location' => 'First floor', 'notes' => '', 'active' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $sessionid = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activityid, 'journeyid' => $journeyid, 'name' => 'Session',
            'sessiondate' => time(), 'duration' => 60, 'groupid' => 0, 'description' => '',
            'modality' => 'unspecified', 'location' => '', 'meetingurl' => '',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $recordid = $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $sessionid, 'userid' => $participant->id, 'status' => 'present',
            'minutesabsent' => 0, 'remarks' => '', 'takenby' => $staff->id, 'approved' => 1,
            'approvedby' => $staff->id, 'timeapproved' => time(), 'changesrequested' => 0,
            'reviewnote' => '', 'reviewedby' => $staff->id, 'timereviewed' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('attendancejourneys_reviews', (object) [
            'recordid' => $recordid, 'sessionid' => $sessionid, 'userid' => $participant->id,
            'action' => 'approved', 'actorid' => $staff->id, 'note' => '', 'timecreated' => time(),
        ]);
        $DB->insert_record('attendancejourneys_members', (object) [
            'attendancejourneysid' => $activityid, 'journeyid' => $journeyid, 'userid' => $participant->id,
            'attemptnumber' => 1, 'active' => 1, 'timeassigned' => time(), 'timeended' => 0,
        ]);
        $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $activityid, 'journeyid' => $journeyid, 'userid' => $participant->id,
            'active' => 1, 'applicablesessions' => 1, 'recordedsessions' => 1, 'presentminutes' => 60,
            'possibleminutes' => 60, 'percentage' => 100, 'threshold' => 80, 'result' => 'passed',
            'closedby' => $staff->id, 'timeclosed' => time(), 'reopenedby' => 0, 'timereopened' => 0,
        ]);
        $DB->insert_record('attendancejourneys_waitlist', (object) [
            'attendancejourneysid' => $activityid, 'journeyid' => $journeyid, 'userid' => $participant->id,
            'status' => 'waiting', 'createdby' => $staff->id, 'timecreated' => time(),
            'updatedby' => 0, 'timeupdated' => 0,
        ]);

        $this->assertTrue(attendancejourneys_delete_instance($activityid));
        foreach (
            ['attendancejourneys', 'attendancejourneys_journeys', 'attendancejourneys_sessions',
                'attendancejourneys_records', 'attendancejourneys_reviews', 'attendancejourneys_members',
                'attendancejourneys_closures', 'attendancejourneys_waitlist', 'attendancejourneys_equivalences',
                'attendancejourneys_rooms', 'attendancejourneys_equivlog', 'attendancejourneys_sessionlog'] as $table
        ) {
            $this->assertSame(0, $DB->count_records($table), $table . ' should be empty after deletion');
        }
    }
}
