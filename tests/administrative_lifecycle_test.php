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
 * Destructive administration and deletion-safety tests.
 * @group mod_attendancejourneys
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('mod_attendancejourneys')]
final class administrative_lifecycle_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
    }

    /**
     * Create a journey fixture belonging to the given activity.
     *
     * @param int $activityid Attendance activity identifier.
     * @param string $name Display name.
     * @return \stdClass
     */
    private function create_journey(int $activityid, string $name = 'Administrative journey'): \stdClass {
        global $DB;
        $id = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activityid, 'name' => $name, 'description' => '', 'groupid' => 0,
            'startdate' => 0, 'enddate' => 0, 'defaultmodality' => 'unspecified', 'active' => 1,
            'completedby' => 0, 'timecompleted' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        return $DB->get_record('attendancejourneys_journeys', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Create a session fixture with the given audience.
     *
     * @param int $activityid Attendance activity identifier.
     * @param int $journeyid Journey identifier; zero denotes no specific journey.
     * @return \stdClass
     */
    private function create_session(int $activityid, int $journeyid = 0): \stdClass {
        global $DB;
        $id = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activityid, 'journeyid' => $journeyid, 'name' => 'Administrative session',
            'sessiondate' => time() - HOURSECS, 'duration' => 60, 'groupid' => 0, 'description' => '',
            'modality' => 'unspecified', 'location' => '', 'meetingurl' => '',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        return $DB->get_record('attendancejourneys_sessions', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Create an attendance record attributed to the supplied actor.
     *
     * @param int $sessionid Attendance session identifier.
     * @param int $userid User identifier.
     * @param int $actorid Identifier of the user performing the action.
     * @return int
     */
    private function create_record(int $sessionid, int $userid, int $actorid): int {
        global $DB;
        return (int) $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $sessionid, 'userid' => $userid, 'status' => 'present', 'minutesabsent' => 0,
            'remarks' => '', 'takenby' => $actorid, 'approved' => 1, 'approvedby' => $actorid,
            'timeapproved' => time(), 'changesrequested' => 0, 'reviewnote' => '', 'reviewedby' => 0,
            'timereviewed' => 0, 'timemodified' => time(),
        ]);
    }

    public function test_empty_journey_deletion_removes_only_its_structure(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', [
            'course' => $course->id,
            'calendarenabled' => 1,
        ]);
        $student = $generator->create_and_enrol($course, 'student');
        $context = \context_module::instance($activity->cmid);
        $journey = $this->create_journey((int) $activity->id);
        attendancejourneys_assign_journey_member($context, $journey, (int) $student->id);
        $firstsession = $this->create_session((int) $activity->id, (int) $journey->id);
        $secondsession = $this->create_session((int) $activity->id, (int) $journey->id);
        $independent = $this->create_session((int) $activity->id);
        attendancejourneys_update_calendar_event($activity, $firstsession);
        attendancejourneys_update_calendar_event($activity, $secondsession);
        attendancejourneys_update_calendar_event($activity, $independent);
        $this->assertSame(3, $DB->count_records('event', [
            'modulename' => 'attendancejourneys',
            'instance' => $activity->id,
        ]));

        $info = attendancejourneys_journey_deletion_info((int) $activity->id, (int) $journey->id);
        $this->assertTrue($info->candelete);
        $this->assertSame(2, $info->sessions);
        $this->assertSame(2, attendancejourneys_delete_journey((int) $activity->id, (int) $journey->id));
        $this->assertFalse($DB->record_exists('attendancejourneys_journeys', ['id' => $journey->id]));
        $this->assertFalse($DB->record_exists('attendancejourneys_sessions', ['id' => $firstsession->id]));
        $this->assertFalse($DB->record_exists('attendancejourneys_sessions', ['id' => $secondsession->id]));
        $this->assertFalse($DB->record_exists('attendancejourneys_members', ['journeyid' => $journey->id]));
        $this->assertTrue($DB->record_exists('attendancejourneys_sessions', ['id' => $independent->id]));
        $this->assertFalse($DB->record_exists('event', [
            'modulename' => 'attendancejourneys', 'instance' => $activity->id,
            'eventtype' => 'session-' . $firstsession->id,
        ]));
        $this->assertFalse($DB->record_exists('event', [
            'modulename' => 'attendancejourneys', 'instance' => $activity->id,
            'eventtype' => 'session-' . $secondsession->id,
        ]));
        $this->assertTrue($DB->record_exists('event', [
            'modulename' => 'attendancejourneys', 'instance' => $activity->id,
            'eventtype' => 'session-' . $independent->id,
        ]));
    }

    public function test_journey_with_attendance_cannot_be_deleted(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $journey = $this->create_journey((int) $activity->id);
        $session = $this->create_session((int) $activity->id, (int) $journey->id);
        $this->create_record((int) $session->id, (int) $student->id, (int) $teacher->id);

        $info = attendancejourneys_journey_deletion_info((int) $activity->id, (int) $journey->id);
        $this->assertFalse($info->candelete);
        $this->assertSame(1, $info->records);
        try {
            attendancejourneys_delete_journey((int) $activity->id, (int) $journey->id);
            $this->fail('A journey containing attendance must not be deleted.');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }
        $this->assertTrue($DB->record_exists('attendancejourneys_journeys', ['id' => $journey->id]));
        $this->assertTrue($DB->record_exists('attendancejourneys_sessions', ['id' => $session->id]));
    }

    public function test_cross_activity_journey_identifier_is_rejected_without_side_effects(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $firstactivity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $secondactivity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $journey = $this->create_journey((int) $secondactivity->id, 'Other activity');

        try {
            attendancejourneys_delete_journey((int) $firstactivity->id, (int) $journey->id);
            $this->fail('A journey identifier from another activity must be rejected.');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('does not belong', $exception->getMessage());
        }
        $this->assertTrue($DB->record_exists('attendancejourneys_journeys', [
            'id' => $journey->id, 'attendancejourneysid' => $secondactivity->id,
        ]));
    }

    public function test_participant_reset_removes_plugin_data_but_preserves_moodle_identity(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id, 'gradeenabled' => 0]);
        $student = $generator->create_and_enrol($course, 'student');
        $otherstudent = $generator->create_and_enrol($course, 'student');
        $admin = $generator->create_user();
        $context = \context_module::instance($activity->cmid);
        $journey = $this->create_journey((int) $activity->id);
        attendancejourneys_assign_journey_member($context, $journey, (int) $student->id);
        $session = $this->create_session((int) $activity->id, (int) $journey->id);
        $recordid = $this->create_record((int) $session->id, (int) $student->id, (int) $admin->id);
        $otherrecordid = $this->create_record((int) $session->id, (int) $otherstudent->id, (int) $admin->id);
        attendancejourneys_add_review_history(
            $recordid,
            (int) $session->id,
            (int) $student->id,
            'approved',
            (int) $admin->id
        );
        attendancejourneys_add_review_history(
            $otherrecordid,
            (int) $session->id,
            (int) $otherstudent->id,
            'approved',
            (int) $admin->id
        );
        $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journey->id, 'userid' => $student->id,
            'active' => 1, 'applicablesessions' => 1, 'recordedsessions' => 1, 'presentminutes' => 60,
            'possibleminutes' => 60, 'percentage' => 100, 'threshold' => 80, 'result' => 'passed',
            'closedby' => $admin->id, 'timeclosed' => time(), 'reopenedby' => 0, 'timereopened' => 0,
        ]);

        $result = attendancejourneys_reset_user_data(
            $activity,
            get_coursemodule_from_id('', $activity->cmid),
            (int) $student->id,
            (int) $admin->id
        );

        $this->assertSame(['records' => 1, 'closures' => 1], $result);
        $this->assertFalse($DB->record_exists('attendancejourneys_records', ['id' => $recordid]));
        $this->assertFalse($DB->record_exists('attendancejourneys_reviews', ['recordid' => $recordid]));
        $this->assertFalse($DB->record_exists('attendancejourneys_closures', [
            'attendancejourneysid' => $activity->id, 'userid' => $student->id,
        ]));
        $this->assertFalse($DB->record_exists('attendancejourneys_members', [
            'attendancejourneysid' => $activity->id, 'userid' => $student->id,
        ]));
        $this->assertTrue($DB->record_exists('user', ['id' => $student->id]));
        $this->assertTrue(is_enrolled($context, $student, '', true));
        $this->assertTrue($DB->record_exists('attendancejourneys_records', ['id' => $otherrecordid]));
        $this->assertTrue($DB->record_exists('attendancejourneys_reviews', ['recordid' => $otherrecordid]));
    }

    public function test_clearing_session_removes_records_and_reviews_but_keeps_session(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $firststudent = $generator->create_and_enrol($course, 'student');
        $secondstudent = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $session = $this->create_session((int) $activity->id);
        $oldversion = (int) $session->timemodified;
        $firstrecord = $this->create_record((int) $session->id, (int) $firststudent->id, (int) $teacher->id);
        $secondrecord = $this->create_record((int) $session->id, (int) $secondstudent->id, (int) $teacher->id);
        attendancejourneys_add_review_history(
            $firstrecord,
            (int) $session->id,
            (int) $firststudent->id,
            'approved',
            (int) $teacher->id
        );
        attendancejourneys_add_review_history(
            $secondrecord,
            (int) $session->id,
            (int) $secondstudent->id,
            'approved',
            (int) $teacher->id
        );

        $affected = attendancejourneys_clear_session_records($session);
        sort($affected);
        $expected = [(int) $firststudent->id, (int) $secondstudent->id];
        sort($expected);
        $this->assertSame($expected, $affected);
        $this->assertTrue($DB->record_exists('attendancejourneys_sessions', ['id' => $session->id]));
        $this->assertSame(0, $DB->count_records('attendancejourneys_records', ['sessionid' => $session->id]));
        $this->assertSame(0, $DB->count_records('attendancejourneys_reviews', ['sessionid' => $session->id]));
        $this->assertGreaterThan($oldversion, (int) $DB->get_field(
            'attendancejourneys_sessions',
            'timemodified',
            ['id' => $session->id]
        ));
        $this->assertSame([], attendancejourneys_clear_session_records($session));
    }

    public function test_active_final_result_protects_session_but_reopened_result_releases_it(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $session = $this->create_session((int) $activity->id);
        $recordid = $this->create_record((int) $session->id, (int) $student->id, (int) $teacher->id);
        $closureid = $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => 0, 'userid' => $student->id, 'active' => 1,
            'applicablesessions' => 1, 'recordedsessions' => 1, 'presentminutes' => 60,
            'possibleminutes' => 60, 'percentage' => 100, 'threshold' => 80, 'result' => 'passed',
            'closedby' => $teacher->id, 'timeclosed' => time(), 'reopenedby' => 0, 'timereopened' => 0,
        ]);

        $this->assertTrue(attendancejourneys_session_has_final_results($session));
        try {
            attendancejourneys_clear_session_records($session);
            $this->fail('An active final result must protect its attendance records.');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('final result', $exception->getMessage());
        }
        $this->assertTrue($DB->record_exists('attendancejourneys_records', ['id' => $recordid]));

        $DB->set_field('attendancejourneys_closures', 'active', 0, ['id' => $closureid]);
        $this->assertFalse(attendancejourneys_session_has_final_results($session));
        $this->assertSame([(int) $student->id], attendancejourneys_clear_session_records($session));
        $this->assertFalse($DB->record_exists('attendancejourneys_records', ['id' => $recordid]));
    }

    public function test_session_edit_guards_distinguish_content_structure_and_audience(): void {
        $existing = (object) [
            'sessiondate' => 1000, 'duration' => 60, 'groupid' => 5, 'journeyid' => 9,
            'name' => 'Original', 'description' => 'Original description',
        ];
        $contentedit = clone $existing;
        $contentedit->name = 'Renamed';
        $contentedit->description = 'Updated description';
        $this->assertFalse(attendancejourneys_session_structure_changed($existing, $contentedit));
        $this->assertFalse(attendancejourneys_session_audience_changed($existing, $contentedit));

        $durationedit = clone $existing;
        $durationedit->duration = 90;
        $this->assertTrue(attendancejourneys_session_structure_changed($existing, $durationedit));
        $this->assertFalse(attendancejourneys_session_audience_changed($existing, $durationedit));

        $groupedit = clone $existing;
        $groupedit->groupid = 6;
        $this->assertTrue(attendancejourneys_session_structure_changed($existing, $groupedit));
        $this->assertTrue(attendancejourneys_session_audience_changed($existing, $groupedit));

        $journeyedit = clone $existing;
        $journeyedit->journeyid = 10;
        $this->assertTrue(attendancejourneys_session_structure_changed($existing, $journeyedit));
        $this->assertTrue(attendancejourneys_session_audience_changed($existing, $journeyedit));
    }
}
