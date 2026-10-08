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

use mod_attendancejourneys\completion\custom_completion;

/**
 * Gradebook and custom completion integration tests.
 *
 * @covers \mod_attendancejourneys\completion\custom_completion
 * @covers ::attendancejourneys_assign_journey_member
 * @covers ::attendancejourneys_close_user_journey
 * @covers ::attendancejourneys_complete_journey
 * @covers ::attendancejourneys_get_active_closure
 * @covers ::attendancejourneys_get_passinggrade
 * @covers ::attendancejourneys_grade_item_update
 * @covers ::attendancejourneys_reopen_user_journey
 * @covers ::attendancejourneys_update_grades
 * @package    mod_attendancejourneys
 * @category   test
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\completion\custom_completion::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_assign_journey_member')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_close_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_complete_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_active_closure')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_passinggrade')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_grade_item_update')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_reopen_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_grades')]
final class grade_completion_test extends \advanced_testcase {
    public function test_grade_is_published_only_after_closure_and_cleared_after_reopening(): void {
        global $DB;
        [$activity, $participant, $teacher, $journeyid] = $this->create_fixture();

        attendancejourneys_update_grades($activity, (int) $participant->id);
        $this->assertNull($this->get_raw_grade($activity, $participant));

        attendancejourneys_close_user_journey($activity, (int) $participant->id, (int) $teacher->id, $journeyid);
        attendancejourneys_update_grades($activity, (int) $participant->id);
        $this->assertEquals(100.0, $this->get_raw_grade($activity, $participant));

        $this->assertTrue(attendancejourneys_reopen_user_journey(
            $activity,
            (int) $participant->id,
            (int) $teacher->id
        ));
        attendancejourneys_update_grades($activity, (int) $participant->id);
        $this->assertNull($this->get_raw_grade($activity, $participant));

        $item = $DB->get_record('grade_items', [
            'courseid' => $activity->course, 'itemmodule' => 'attendancejourneys',
            'iteminstance' => $activity->id, 'itemnumber' => 0,
        ], '*', MUST_EXIST);
        $this->assertEquals(80.0, (float) $item->gradepass);
    }

    public function test_completion_requires_an_official_closure_for_closed_and_pass_rules(): void {
        [$activity, $participant, $teacher, $journeyid, $cm] = $this->create_fixture();
        $completion = new custom_completion(
            get_fast_modinfo($activity->course)->get_cm($cm->id),
            (int) $participant->id
        );

        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completionrecorded'));
        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completionallsessions'));
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionclosed'));
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionpass'));

        attendancejourneys_close_user_journey($activity, (int) $participant->id, (int) $teacher->id, $journeyid);
        $completion = new custom_completion(
            get_fast_modinfo($activity->course)->get_cm($cm->id),
            (int) $participant->id
        );
        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completionclosed'));
        $this->assertSame(COMPLETION_COMPLETE_PASS, $completion->get_state('completionpass'));
    }

    /**
     * Required unrecorded sessions prevent final publication.
     */
    public function test_missing_session_prevents_closure_without_side_effects(): void {
        global $DB;
        [$activity, $participant, $teacher, $journeyid] = $this->create_fixture();
        $session = $DB->get_record('attendancejourneys_sessions', ['journeyid' => $journeyid], '*', MUST_EXIST);
        unset($session->id);
        $session->name = 'Unrecorded required session';
        $DB->insert_record('attendancejourneys_sessions', $session);
        try {
            attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $journeyid);
            $this->fail('Incomplete attendance was closed.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('closuremissingsessions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures'));
        $this->assertSame(1, (int) $DB->get_field('attendancejourneys_members', 'active', ['journeyid' => $journeyid]));
        attendancejourneys_update_grades($activity, (int) $participant->id);
        $this->assertNull($this->get_raw_grade($activity, $participant));
    }

    /**
     * Even pre-recorded attendance cannot finalise an unfinished session.
     */
    public function test_future_recorded_session_prevents_closure(): void {
        global $DB;
        [$activity, $participant, $teacher, $journeyid] = $this->create_fixture();
        $DB->set_field('attendancejourneys_sessions', 'sessiondate', time() + DAYSECS, ['journeyid' => $journeyid]);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('closurefuturesessions', 'attendancejourneys', 1));
        attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $journeyid);
    }

    /**
     * A started but unfinished session cannot publish a pre-recorded result.
     */
    public function test_running_recorded_session_prevents_closure_without_side_effects(): void {
        global $DB;
        [$activity, $participant, $teacher, $journeyid] = $this->create_fixture();
        $DB->set_field('attendancejourneys_sessions', 'sessiondate', time() - MINSECS, ['journeyid' => $journeyid]);
        try {
            attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $journeyid);
            $this->fail('An unfinished session published a final result.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('closurefuturesessions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures'));
        $this->assertSame(1, (int) $DB->get_field('attendancejourneys_members', 'active', ['journeyid' => $journeyid]));
        attendancejourneys_update_grades($activity, (int) $participant->id);
        $this->assertNull($this->get_raw_grade($activity, $participant));
    }

    /**
     * Collective closure preflights everyone before publishing any result.
     */
    public function test_collective_closure_rejects_missing_record_without_partial_results(): void {
        global $DB;
        [$activity, $participant, $teacher, $journeyid, $cm] = $this->create_fixture();
        $second = $this->getDataGenerator()->create_and_enrol((object) ['id' => $activity->course], 'student');
        attendancejourneys_assign_journey_member(
            \context_module::instance($cm->id),
            $DB->get_record('attendancejourneys_journeys', ['id' => $journeyid]),
            (int) $second->id
        );
        try {
            attendancejourneys_complete_journey(
                $activity,
                $DB->get_record('attendancejourneys_journeys', ['id' => $journeyid]),
                \context_module::instance($cm->id),
                (int) $teacher->id
            );
            $this->fail('A partially recorded journey was completed.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('closuremissingsessions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures'));
        $this->assertSame(1, (int) $DB->get_field('attendancejourneys_journeys', 'active', ['id' => $journeyid]));
    }

    /**
     * An empty journey must not produce a final result or a grade.
     */
    public function test_empty_journey_cannot_be_closed(): void {
        global $DB;
        [$activity, $participant, $teacher, $journeyid] = $this->create_fixture();
        $sessionid = $DB->get_field('attendancejourneys_sessions', 'id', ['journeyid' => $journeyid]);
        $DB->delete_records('attendancejourneys_records', ['sessionid' => $sessionid]);
        $DB->delete_records('attendancejourneys_sessions', ['id' => $sessionid]);
        try {
            attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $journeyid);
            $this->fail('An empty journey was closed.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('closureemptysessions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures'));
        $this->assertNull($this->get_raw_grade($activity, $participant));
    }

    /**
     * Display rounding cannot turn a result below the threshold into a pass.
     */
    public function test_final_result_uses_unrounded_minutes_ratio(): void {
        global $DB;
        [$activity, $participant, $teacher, $journeyid] = $this->create_fixture();
        $session = $DB->get_record('attendancejourneys_sessions', ['journeyid' => $journeyid], '*', MUST_EXIST);
        $session->duration = 2500;
        $session->sessiondate = time() - (3 * DAYSECS);
        $DB->update_record('attendancejourneys_sessions', $session);
        $record = $DB->get_record('attendancejourneys_records', ['sessionid' => $session->id], '*', MUST_EXIST);
        $record->status = 'partial';
        $record->minutesabsent = 501;
        $DB->update_record('attendancejourneys_records', $record);
        $closure = attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $journeyid);
        $this->assertEqualsWithDelta(79.96, (float) $closure->percentage, 0.000001);
        $this->assertSame('failed', $closure->result);
        $this->assertEquals(1999, $closure->presentminutes);
        $this->assertEquals(2500, $closure->possibleminutes);
    }

    /**
     * Identical attendance can pass a 60 percent journey and fail an 80 percent journey.
     */
    public function test_journey_thresholds_are_independent_and_frozen(): void {
        global $DB;
        [$activity, $participant, $teacher, $firstid] = $this->create_fixture();
        $first = $DB->get_record('attendancejourneys_journeys', ['id' => $firstid], '*', MUST_EXIST);
        $first->passinggrade = 60;
        $DB->update_record('attendancejourneys_journeys', $first);
        unset($first->id);
        $first->name = 'Second obligation';
        $first->passinggrade = 80;
        $secondid = $DB->insert_record('attendancejourneys_journeys', $first);
        $session = $DB->get_record('attendancejourneys_sessions', ['journeyid' => $firstid], '*', MUST_EXIST);
        $session->duration = 100;
        $session->sessiondate = time() - DAYSECS;
        $DB->update_record('attendancejourneys_sessions', $session);
        $record = $DB->get_record('attendancejourneys_records', ['sessionid' => $session->id], '*', MUST_EXIST);
        $record->status = 'partial';
        $record->minutesabsent = 30;
        $DB->update_record('attendancejourneys_records', $record);
        unset($session->id, $record->id);
        $session->journeyid = $secondid;
        $record->sessionid = $DB->insert_record('attendancejourneys_sessions', $session);
        $DB->insert_record('attendancejourneys_records', $record);
        $membership = $DB->get_record('attendancejourneys_members', ['journeyid' => $firstid], '*', MUST_EXIST);
        unset($membership->id);
        $membership->journeyid = $secondid;
        $DB->insert_record('attendancejourneys_members', $membership);

        $firstclosure = attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $firstid);
        $secondclosure = attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $secondid);
        $this->assertEquals(70, $firstclosure->percentage);
        $this->assertEquals(70, $secondclosure->percentage);
        $this->assertEquals(60, $firstclosure->threshold);
        $this->assertEquals(80, $secondclosure->threshold);
        $this->assertSame('passed', $firstclosure->result);
        $this->assertSame('failed', $secondclosure->result);

        $DB->set_field('attendancejourneys_journeys', 'passinggrade', 100, ['id' => $firstid]);
        $frozen = attendancejourneys_get_active_closure($activity->id, $participant->id, $firstid);
        $this->assertEquals(60, $frozen->threshold);
        $this->assertSame('passed', $frozen->result);
    }

    /**
     * Legacy null thresholds inherit the activity; an explicit zero is not treated as null.
     */
    public function test_threshold_inheritance_preserves_zero(): void {
        global $DB;
        [$activity, , , $journeyid] = $this->create_fixture();
        $this->assertSame(80.0, attendancejourneys_get_passinggrade($activity, $journeyid));
        $activity->passinggrade = 75;
        $this->assertSame(75.0, attendancejourneys_get_passinggrade($activity, $journeyid));
        $DB->set_field('attendancejourneys_journeys', 'passinggrade', 0, ['id' => $journeyid]);
        $this->assertSame(0.0, attendancejourneys_get_passinggrade($activity, $journeyid));
    }

    /**
     * A journey from a different activity cannot supply a threshold.
     */
    public function test_threshold_rejects_another_activity(): void {
        [$activity, , , $journeyid] = $this->create_fixture();
        $other = $this->getDataGenerator()->create_module('attendancejourneys', ['course' => $activity->course]);
        $this->expectException(\dml_missing_record_exception::class);
        attendancejourneys_get_passinggrade($other, $journeyid);
    }

    /**
     * Create the activity and related records needed by this test.
     * @return array
     */
    private function create_fixture(): array {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/attendancejourneys/lib.php');
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $activity = $generator->create_module('attendancejourneys', [
            'course' => $course->id, 'percentageenabled' => 1, 'gradeenabled' => 1, 'passinggrade' => 80,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionrecorded' => 1,
            'completionallsessions' => 1, 'completionclosed' => 1, 'completionpass' => 1,
        ]);
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
        $participant = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $journeyid = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Grade journey', 'description' => '', 'groupid' => 0,
            'startdate' => 0, 'enddate' => 0, 'defaultmodality' => 'unspecified', 'active' => 1,
            'completedby' => 0, 'timecompleted' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $sessionid = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'name' => 'Past session',
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
            'reviewnote' => '', 'reviewedby' => $teacher->id, 'timereviewed' => time(), 'timemodified' => time(),
        ]);
        attendancejourneys_grade_item_update($activity);
        return [$activity, $participant, $teacher, $journeyid, $cm];
    }

    /**
     * Read the participant grade stored in the Moodle gradebook.
     *
     * @param \stdClass $activity Attendance activity and its configuration.
     * @param \stdClass $participant Participant whose grade is requested.
     * @return float|null
     */
    private function get_raw_grade(\stdClass $activity, \stdClass $participant): ?float {
        global $DB;
        $item = $DB->get_record('grade_items', [
            'courseid' => $activity->course, 'itemmodule' => 'attendancejourneys',
            'iteminstance' => $activity->id, 'itemnumber' => 0,
        ], '*', MUST_EXIST);
        $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $participant->id]);
        return $grade && $grade->rawgrade !== null ? (float) $grade->rawgrade : null;
    }
}
