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
 * Explicit audience modes must not depend on whether a membership row happens to exist.
 *
 * @covers \mod_attendancejourneys\local\journey_grades
 * @covers ::attendancejourneys_assign_journey_member
 * @covers ::attendancejourneys_close_user_journey
 * @covers ::attendancejourneys_get_addable_journey_participants
 * @covers ::attendancejourneys_get_journey_participants
 * @covers ::attendancejourneys_get_session_participants
 * @covers ::attendancejourneys_get_user_active_journeys
 * @covers ::attendancejourneys_get_user_report_journeys
 * @covers ::attendancejourneys_journey_audience_is_editable
 * @covers ::attendancejourneys_session_applies_to_user
 * @covers ::attendancejourneys_update_grades
 * @covers ::attendancejourneys_user_can_self_record_session
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\journey_grades::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_assign_journey_member')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_close_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_addable_journey_participants')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_journey_participants')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_session_participants')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_user_active_journeys')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_user_report_journeys')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_journey_audience_is_editable')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_session_applies_to_user')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_grades')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_user_can_self_record_session')]
final class journey_audience_test extends \advanced_testcase {
    /**
     * Normal creation is usable without a manual journey and publishes only after closure.
     */
    public function test_normal_creation_has_one_automatic_journey_and_no_early_grade(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        foreach (['light', 'professional'] as $mode) {
            $activity = $generator->create_module('attendancejourneys', [
                'course' => $course->id, 'journeygrading' => null, 'experiencemode' => $mode, 'gradeenabled' => 1,
                'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 0,
            ]);
            $this->assertEquals(1, $activity->journeygrading);
            $this->assertEquals(1, $activity->completionpass);
            $journeys = $DB->get_records('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id]);
            $this->assertCount(1, $journeys);
            $journey = reset($journeys);
            $this->assertEquals(1, $journey->audiencemode);
            $this->assertEquals(1, $journey->required);
            $this->assertNull($journey->passinggrade);
            $this->assertSame(0, $DB->count_records('attendancejourneys_members', ['journeyid' => $journey->id]));
            $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            $this->assertArrayHasKey($student->id, attendancejourneys_get_journey_participants($context, $journey));
            $sessionid = $DB->insert_record('attendancejourneys_sessions', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journey->id, 'name' => 'Completed session',
                'sessiondate' => time() - DAYSECS, 'duration' => 60,
            ]);
            $DB->insert_record('attendancejourneys_records', (object) [
                'sessionid' => $sessionid, 'userid' => $student->id, 'status' => 'present', 'approved' => 1,
            ]);
            attendancejourneys_update_grades($activity, (int) $student->id);
            $item = $DB->get_record('grade_items', [
                'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id,
            ], '*', MUST_EXIST);
            $this->assertNull($DB->get_field('grade_grades', 'rawgrade', ['itemid' => $item->id, 'userid' => $student->id]));
            $this->assertFalse(\mod_attendancejourneys\local\journey_grades::all_required_passed($activity, (int) $student->id));
            attendancejourneys_close_user_journey($activity, (int) $student->id, 2, (int) $journey->id);
            attendancejourneys_update_grades($activity, (int) $student->id);
            $this->assertEquals(100, $DB->get_field('grade_grades', 'rawgrade', ['itemid' => $item->id, 'userid' => $student->id]));
            $this->assertTrue(\mod_attendancejourneys\local\journey_grades::all_required_passed($activity, (int) $student->id));
        }
    }

    /**
     * Adding one person cannot remove the rest of an automatic audience.
     */
    public function test_enrolled_audience_survives_individual_assignment_and_late_enrolment(): void {
        global $DB;
        [$activity, $cm, $context, $students, $journey, $session] = $this->fixture(1);
        attendancejourneys_assign_journey_member($context, $journey, (int) $students[0]->id);
        foreach ($students as $student) {
            $this->assertArrayHasKey($journey->id, attendancejourneys_get_user_active_journeys(
                $context,
                (int) $activity->id,
                (int) $student->id
            ));
            $this->assertTrue(attendancejourneys_session_applies_to_user($session, (int) $student->id));
        }
        $late = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($late->id, $activity->course, 'student');
        $extraid = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Selected laboratory', 'audiencemode' => 2,
        ]);
        $extra = $DB->get_record('attendancejourneys_journeys', ['id' => $extraid], '*', MUST_EXIST);
        attendancejourneys_assign_journey_member($context, $extra, (int) $late->id);
        $this->assertCount(2, attendancejourneys_get_user_active_journeys($context, (int) $activity->id, (int) $late->id));
        $this->assertSame([], attendancejourneys_get_addable_journey_participants($context, $journey));
        $this->assertCount(3, attendancejourneys_get_journey_participants($context, $journey));
        $this->assertCount(3, attendancejourneys_get_session_participants($context, $session));
        $this->assertArrayHasKey($journey->id, attendancejourneys_get_user_report_journeys(
            $context,
            (int) $activity->id,
            (int) $late->id
        ));
        $this->assertTrue(attendancejourneys_user_can_self_record_session($context, $activity, $session, (int) $late->id));
        $this->assertFalse(\mod_attendancejourneys\local\journey_grades::all_required_passed($activity, (int) $late->id));
        $DB->set_field('attendancejourneys_journeys', 'active', 0, ['id' => $journey->id]);
        $this->assertArrayNotHasKey($journey->id, attendancejourneys_get_user_active_journeys(
            $context,
            (int) $activity->id,
            (int) $late->id
        ));
    }

    /**
     * An empty explicit audience cannot unexpectedly become the whole course.
     */
    public function test_empty_explicit_audience_remains_empty(): void {
        [$activity, $cm, $context, $students, $journey, $session] = $this->fixture(2);
        $this->assertSame([], attendancejourneys_get_journey_participants($context, $journey));
        $this->assertSame([], attendancejourneys_get_session_participants($context, $session));
        foreach ($students as $student) {
            $this->assertFalse(attendancejourneys_session_applies_to_user($session, (int) $student->id));
            $this->assertSame([], attendancejourneys_get_user_active_journeys($context, (int) $activity->id, (int) $student->id));
            $this->assertSame([], attendancejourneys_get_user_report_journeys($context, (int) $activity->id, (int) $student->id));
        }
        attendancejourneys_assign_journey_member($context, $journey, (int) $students[0]->id);
        $this->assertCount(1, attendancejourneys_get_session_participants($context, $session));
        $this->assertTrue(attendancejourneys_session_applies_to_user($session, (int) $students[0]->id));
        $this->assertFalse(attendancejourneys_session_applies_to_user($session, (int) $students[1]->id));
    }

    /**
     * Build an open journey with two enrolled learners and one past session.
     *
     * @param int $mode Audience mode.
     * @return array Activity, module, context, users, journey and session.
     */
    private function fixture(int $mode): array {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id, 'journeygrading' => 1]);
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $students = [$generator->create_user(), $generator->create_user()];
        foreach ($students as $student) {
            $generator->enrol_user($student->id, $course->id, 'student');
        }
        $journeyid = $DB->get_field('attendancejourneys_journeys', 'id', ['attendancejourneysid' => $activity->id], MUST_EXIST);
        $DB->update_record('attendancejourneys_journeys', (object) [
            'id' => $journeyid, 'name' => 'Audience test', 'audiencemode' => $mode,
        ]);
        $journey = $DB->get_record('attendancejourneys_journeys', ['id' => $journeyid], '*', MUST_EXIST);
        $sessionid = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'name' => 'Past session',
            'sessiondate' => time() - DAYSECS, 'duration' => 60, 'groupid' => 0,
        ]);
        $session = $DB->get_record('attendancejourneys_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        return [$activity, $cm, $context, $students, $journey, $session];
    }

    /**
     * Automatic audiences cannot be edited as an explicit list, even with stored membership rows.
     */
    public function test_automatic_audience_disables_individual_membership_actions(): void {
        global $DB;
        [$activity, $cm, $context, $students, $journey] = $this->fixture(1);
        $this->assertFalse(attendancejourneys_journey_audience_is_editable($journey));
        $DB->insert_record('attendancejourneys_members', (object) [
            'journeyid' => $journey->id, 'userid' => $students[0]->id, 'active' => 1,
        ]);
        $this->assertFalse(attendancejourneys_journey_audience_is_editable($journey));
        $this->assertSame([], attendancejourneys_get_addable_journey_participants($context, $journey));
        $this->assertCount(2, attendancejourneys_get_journey_participants($context, $journey));
        $explicit = $DB->get_record('attendancejourneys_journeys', ['id' => $journey->id], '*', MUST_EXIST);
        $explicit->audiencemode = 2;
        $DB->update_record('attendancejourneys_journeys', $explicit);
        $this->assertTrue(attendancejourneys_journey_audience_is_editable($explicit));
    }
}
