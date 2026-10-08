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
 * Session audience, enrolment and journey-attempt tests.
 * @group mod_attendancejourneys
 * @covers ::attendancejourneys_assign_journey_member
 * @covers ::attendancejourneys_get_session_participants
 * @covers ::attendancejourneys_get_user_active_journey
 * @covers ::attendancejourneys_is_light_mode
 * @covers ::attendancejourneys_session_applies_to_user
 * @covers ::attendancejourneys_user_can_self_record_session
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('mod_attendancejourneys')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_assign_journey_member')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_session_participants')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_user_active_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_is_light_mode')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_session_applies_to_user')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_user_can_self_record_session')]
final class session_targeting_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
    }

    /**
     * Creates a minimal session record for a generated activity.
     *
     * @param int $activityid Attendance activity identifier.
     * @param int $groupid Moodle group identifier; zero denotes no group restriction.
     * @param int $journeyid Journey identifier; zero denotes no specific journey.
     */
    private function create_session(int $activityid, int $groupid = 0, int $journeyid = 0): \stdClass {
        global $DB;
        $id = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activityid, 'journeyid' => $journeyid, 'name' => 'Targeted session',
            'sessiondate' => time() - HOURSECS, 'duration' => 60, 'groupid' => $groupid,
            'description' => '', 'modality' => 'unspecified', 'location' => '', 'meetingurl' => '',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        return $DB->get_record('attendancejourneys_sessions', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Creates an active journey record.
     *
     * @param int $activityid Attendance activity identifier.
     * @param string $name Display name.
     */
    private function create_journey(int $activityid, string $name): \stdClass {
        global $DB;
        $id = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activityid, 'name' => $name, 'description' => '', 'groupid' => 0,
            'startdate' => 0, 'enddate' => 0, 'defaultmodality' => 'unspecified', 'active' => 1,
            'completedby' => 0, 'timecompleted' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        return $DB->get_record('attendancejourneys_journeys', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_light_mode_is_explicit_and_professional_is_the_safe_default(): void {
        $this->assertTrue(attendancejourneys_is_light_mode((object) ['experiencemode' => 'light']));
        $this->assertFalse(attendancejourneys_is_light_mode((object) ['experiencemode' => 'professional']));
        $this->assertFalse(attendancejourneys_is_light_mode((object) []));
    }

    public function test_common_and_group_sessions_target_only_expected_participants(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $studenta = $generator->create_and_enrol($course, 'student');
        $studentb = $generator->create_and_enrol($course, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $studenta->id]);

        $common = $this->create_session((int) $activity->id);
        $targeted = $this->create_session((int) $activity->id, (int) $group->id);

        $this->assertTrue(attendancejourneys_session_applies_to_user($common, (int) $studenta->id));
        $this->assertTrue(attendancejourneys_session_applies_to_user($common, (int) $studentb->id));
        $this->assertTrue(attendancejourneys_session_applies_to_user($targeted, (int) $studenta->id));
        $this->assertFalse(attendancejourneys_session_applies_to_user($targeted, (int) $studentb->id));
    }

    public function test_frozen_journey_audience_excludes_non_members(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $member = $generator->create_and_enrol($course, 'student');
        $nonmember = $generator->create_and_enrol($course, 'student');
        $context = \context_module::instance($activity->cmid);
        $journey = $this->create_journey((int) $activity->id, 'Frozen audience');
        attendancejourneys_assign_journey_member($context, $journey, (int) $member->id);
        $session = $this->create_session((int) $activity->id, 0, (int) $journey->id);

        $this->assertTrue(attendancejourneys_session_applies_to_user($session, (int) $member->id));
        $this->assertFalse(attendancejourneys_session_applies_to_user($session, (int) $nonmember->id));
        $participants = attendancejourneys_get_session_participants($context, $session, 'u.id');
        $this->assertArrayHasKey($member->id, $participants);
        $this->assertArrayNotHasKey($nonmember->id, $participants);
    }

    public function test_individually_added_journey_member_is_listed_outside_original_group(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $originalmember = $generator->create_and_enrol($course, 'student');
        $addedmember = $generator->create_and_enrol($course, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $originalmember->id]);
        $context = \context_module::instance($activity->cmid);
        $journey = $this->create_journey((int) $activity->id, 'Group journey');
        $journey->groupid = $group->id;
        attendancejourneys_assign_journey_member($context, $journey, (int) $originalmember->id);
        attendancejourneys_assign_journey_member($context, $journey, (int) $addedmember->id);
        $session = $this->create_session((int) $activity->id, (int) $group->id, (int) $journey->id);

        $participants = attendancejourneys_get_session_participants($context, $session, 'u.id');
        $this->assertArrayHasKey($originalmember->id, $participants);
        $this->assertArrayHasKey($addedmember->id, $participants);
    }

    public function test_suspended_enrolment_disappears_without_deleting_history(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $context = \context_module::instance($activity->cmid);
        $session = $this->create_session((int) $activity->id);
        $recordid = $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $session->id, 'userid' => $student->id, 'status' => 'present',
            'minutesabsent' => 0, 'remarks' => '', 'takenby' => $student->id, 'approved' => 1,
            'approvedby' => 0, 'timeapproved' => time(), 'changesrequested' => 0, 'reviewnote' => '',
            'reviewedby' => 0, 'timereviewed' => 0, 'timemodified' => time(),
        ]);
        $this->assertArrayHasKey(
            $student->id,
            attendancejourneys_get_session_participants($context, $session, 'u.id')
        );

        $enrolment = $DB->get_record('user_enrolments', ['userid' => $student->id], '*', MUST_EXIST);
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['id' => $enrolment->id]);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertArrayNotHasKey(
            $student->id,
            attendancejourneys_get_session_participants($context, $session, 'u.id')
        );
        $this->assertTrue($DB->record_exists('attendancejourneys_records', ['id' => $recordid]));
        $this->assertNull(attendancejourneys_get_user_active_journey(
            $context,
            (int) $activity->id,
            (int) $student->id
        ));
    }

    public function test_new_journey_assignment_gets_a_new_attempt_and_controls_self_recording(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', [
            'course' => $course->id, 'studentselfrecord' => 1,
        ]);
        $student = $generator->create_and_enrol($course, 'student');
        $context = \context_module::instance($activity->cmid);
        $firstjourney = $this->create_journey((int) $activity->id, 'First attempt');
        $firstmemberid = attendancejourneys_assign_journey_member($context, $firstjourney, (int) $student->id);
        $firstsession = $this->create_session((int) $activity->id, 0, (int) $firstjourney->id);

        $DB->set_field('attendancejourneys_members', 'active', 0, ['id' => $firstmemberid]);
        $DB->set_field('attendancejourneys_journeys', 'active', 0, ['id' => $firstjourney->id]);
        $secondjourney = $this->create_journey((int) $activity->id, 'Second attempt');
        $secondmemberid = attendancejourneys_assign_journey_member($context, $secondjourney, (int) $student->id);
        $secondsession = $this->create_session((int) $activity->id, 0, (int) $secondjourney->id);

        $this->assertSame(1, (int) $DB->get_field(
            'attendancejourneys_members',
            'attemptnumber',
            ['id' => $firstmemberid]
        ));
        $this->assertSame(2, (int) $DB->get_field(
            'attendancejourneys_members',
            'attemptnumber',
            ['id' => $secondmemberid]
        ));
        $active = attendancejourneys_get_user_active_journey($context, (int) $activity->id, (int) $student->id);
        $this->assertSame((int) $secondjourney->id, (int) $active->id);
        $this->assertFalse(attendancejourneys_user_can_self_record_session(
            $context,
            $activity,
            $firstsession,
            (int) $student->id
        ));
        $this->assertTrue(attendancejourneys_user_can_self_record_session(
            $context,
            $activity,
            $secondsession,
            (int) $student->id
        ));
    }
}
