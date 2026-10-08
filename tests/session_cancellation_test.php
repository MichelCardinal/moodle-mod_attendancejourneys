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

use mod_attendancejourneys\local\calculator;
use mod_attendancejourneys\local\session_cancellation;
use mod_attendancejourneys\privacy\provider;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\event\session_cancellation_changed::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\calculator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\session_cancellation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\privacy\provider::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_close_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_reopen_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_session_deletion_blocker')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_calendar_event')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_user_can_self_record_session')]
/**
 * Cancellation preserves attendance while changing obligations only in open journeys.
 *
 * @covers \mod_attendancejourneys\event\session_cancellation_changed
 * @covers \mod_attendancejourneys\local\calculator
 * @covers \mod_attendancejourneys\local\session_cancellation
 * @covers \mod_attendancejourneys\privacy\provider
 * @covers ::attendancejourneys_close_user_journey
 * @covers ::attendancejourneys_reopen_user_journey
 * @covers ::attendancejourneys_session_deletion_blocker
 * @covers ::attendancejourneys_update_calendar_event
 * @covers ::attendancejourneys_user_can_self_record_session
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_cancellation_test extends \core_privacy\tests\provider_testcase {
    /**
     * Create a graded journey with recorded 100-minute presence and 60-minute absence.
     *
     * @return array Activity, module, journey, participant, teacher and sessions.
     */
    private function fixture(): array {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $activity = $generator->create_module('attendancejourneys', [
            'course' => $course->id, 'journeygrading' => 1, 'percentageenabled' => 1, 'passinggrade' => 80,
            'calendarenabled' => 1, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 1,
        ]);
        $participant = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $journey = $DB->get_record('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id], '*', MUST_EXIST);
        $sessions = [];
        foreach ([100, 60] as $index => $duration) {
            $id = $DB->insert_record('attendancejourneys_sessions', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journey->id,
                'name' => 'Cancellation session ' . $index, 'duration' => $duration, 'sessiondate' => time() - DAYSECS,
            ]);
            $session = $DB->get_record('attendancejourneys_sessions', ['id' => $id]);
            attendancejourneys_update_calendar_event($activity, $session);
            $DB->insert_record('attendancejourneys_records', (object) [
                'sessionid' => $id, 'userid' => $participant->id, 'status' => $index ? 'absent' : 'present',
                'approved' => 1, 'takenby' => 2,
            ]);
            $sessions[] = $session;
        }
        $cm = get_coursemodule_from_id('attendancejourneys', $activity->cmid, 0, false, MUST_EXIST);
        $this->setUser($teacher);
        return [$activity, $cm, $journey, $participant, $teacher, $sessions];
    }

    /**
     * Cancellation removes minutes/calendar but retains records and final snapshots; reinstatement reverses it.
     */
    public function test_cancel_reinstate_and_frozen_result_guards(): void {
        global $DB;
        [$activity, $cm, $journey, $participant, $teacher, $sessions] = $this->fixture();
        $before = $DB->get_records('attendancejourneys_records', [], 'id');
        $this->assertEquals(62.5, calculator::calculate($activity, $participant->id, $journey->id)->percent);
        $sink = $this->redirectEvents();
        session_cancellation::change($cm, $sessions[1]->id, true, 0, 'Room unavailable');
        $calculation = calculator::calculate($activity, $participant->id, $journey->id);
        $this->assertEquals(100, $calculation->percent);
        $this->assertSame(1, $calculation->applicablesessions);
        $this->assertEquals($before, $DB->get_records('attendancejourneys_records', [], 'id'));
        $this->assertSame(1, $DB->count_records('event', ['modulename' => 'attendancejourneys', 'instance' => $activity->id]));
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures'));
        $this->assertSame(0, $DB->count_records_select('grade_grades', 'rawgrade IS NOT NULL OR finalgrade IS NOT NULL'));
        $this->assertSame(0, $DB->count_records_select('course_modules_completion', 'completionstate <> 0'));
        $events = array_filter($sink->get_events(), static fn($event) =>
            $event instanceof \mod_attendancejourneys\event\session_cancellation_changed);
        $this->assertCount(1, $events);
        $closure = attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $journey->id);
        $frozen = $DB->get_record('attendancejourneys_closures', ['id' => $closure->id]);
        try {
            session_cancellation::change($cm, $sessions[1]->id, false, 1, 'Rescheduled');
            $this->fail('An active final result must prevent reinstatement.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('sessioncancellationblockedfinal', $exception->errorcode);
        }
        $this->assertSame(1, $DB->count_records('attendancejourneys_sessionlog'));
        $this->assertTrue(attendancejourneys_reopen_user_journey($activity, $participant->id, $teacher->id, $journey->id));
        session_cancellation::change($cm, $sessions[1]->id, false, 1, 'Rescheduled');
        $this->assertEquals(62.5, calculator::calculate($activity, $participant->id, $journey->id)->percent);
        $this->assertEquals($before, $DB->get_records('attendancejourneys_records', [], 'id'));
        $this->assertEquals(
            $frozen->percentage,
            $DB->get_field('attendancejourneys_closures', 'percentage', ['id' => $closure->id])
        );
        $this->assertSame(2, $DB->count_records('event', ['modulename' => 'attendancejourneys', 'instance' => $activity->id]));
        $logs = array_values($DB->get_records('attendancejourneys_sessionlog', [], 'id'));
        $this->assertSame(['cancel', 'reinstate'], array_column($logs, 'action'));
        $this->assertSame(['Room unavailable', 'Rescheduled'], array_column($logs, 'reason'));
        $this->assertSame('sessiondeleteblockedcancellation', attendancejourneys_session_deletion_blocker(
            $DB->get_record('attendancejourneys_sessions', ['id' => $sessions[1]->id])
        ));
    }

    /**
     * A future cancelled session stops blocking closure; reinstatement restores its obligation.
     */
    public function test_future_cancelled_session_can_close_and_reinstatement_blocks_again(): void {
        global $DB;
        [$activity, $cm, $journey, $participant, $teacher, $sessions] = $this->fixture();
        $DB->set_field('attendancejourneys_sessions', 'sessiondate', time() + DAYSECS, ['id' => $sessions[1]->id]);
        $records = $DB->get_records('attendancejourneys_records', [], 'id');
        try {
            attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $journey->id);
            $this->fail('A future required session did not block closure.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('closurefuturesessions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures'));
        session_cancellation::change($cm, $sessions[1]->id, true, 0, 'Future session cancelled');
        $calculation = calculator::calculate($activity, $participant->id, $journey->id);
        $this->assertSame(0, $calculation->futuresessions);
        $this->assertSame(1, $calculation->applicablesessions);
        $this->assertEquals(100, $calculation->percent);
        $closure = attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $journey->id);
        $this->assertEquals(100, $closure->percentage);
        $this->assertEquals($records, $DB->get_records('attendancejourneys_records', [], 'id'));
        $this->assertTrue(attendancejourneys_reopen_user_journey($activity, $participant->id, $teacher->id, $journey->id));
        session_cancellation::change($cm, $sessions[1]->id, false, 1, 'Future obligation reinstated');
        $this->assertSame(1, calculator::calculate($activity, $participant->id, $journey->id)->futuresessions);
        try {
            attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $journey->id);
            $this->fail('A reinstated future session did not block closure.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('closurefuturesessions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures', ['active' => 1]));
        $this->assertSame(0, $DB->count_records_select('grade_grades', 'rawgrade IS NOT NULL OR finalgrade IS NOT NULL'));
        $this->assertEquals($records, $DB->get_records('attendancejourneys_records', [], 'id'));
    }

    /**
     * Unresolved or approved equivalences must be handled before either end is cancelled.
     */
    public function test_equivalence_guards_and_record_preservation(): void {
        global $DB;
        [$activity, $cm, $journey, $participant, , $sessions] = $this->fixture();
        $id = $DB->insert_record('attendancejourneys_equivalences', (object) [
            'attendancejourneysid' => $activity->id, 'userid' => $participant->id, 'targetjourneyid' => $journey->id,
            'sourcesessionid' => $sessions[0]->id, 'targetsessionid' => $sessions[1]->id, 'status' => 'pending',
        ]);
        foreach (['pending', 'approved'] as $status) {
            $DB->set_field('attendancejourneys_equivalences', 'status', $status, ['id' => $id]);
            foreach ($sessions as $session) {
                try {
                    session_cancellation::change($cm, $session->id, true, 0, 'Unavailable');
                    $this->fail('Resolve the equivalence first.');
                } catch (\moodle_exception $exception) {
                    $this->assertSame('sessioncancellationblockedequivalence', $exception->errorcode);
                }
            }
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_sessionlog'));
        $DB->set_field('attendancejourneys_equivalences', 'status', 'revoked', ['id' => $id]);
        session_cancellation::change($cm, $sessions[1]->id, true, 0, 'Unavailable');
        $this->assertTrue($DB->record_exists('attendancejourneys_equivalences', ['id' => $id]));
        $this->assertSame(2, $DB->count_records('attendancejourneys_records'));
    }

    /**
     * Blank reasons, stale confirmations and participant submissions have no effect.
     */
    public function test_invalid_changes_do_not_write(): void {
        global $DB;
        [, $cm, , $participant, , $sessions] = $this->fixture();
        foreach (
            [[true, 0, ' ', 'sessioncancellationreasonrequired'],
            [false, 0, 'Wrong action', 'sessioncancellationstale'],
            [true, 1, 'Stale form', 'sessioncancellationstale']] as [$cancelled, $expected, $reason, $code]
        ) {
            try {
                session_cancellation::change($cm, $sessions[1]->id, $cancelled, $expected, $reason);
                $this->fail('Invalid change must fail.');
            } catch (\moodle_exception $exception) {
                $this->assertSame($code, $exception->errorcode);
            }
        }
        $this->setUser($participant);
        try {
            session_cancellation::change($cm, $sessions[1]->id, true, 0, 'Student submission');
            $this->fail('Participant must not cancel sessions.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_sessionlog'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_sessions', ['cancelled' => 1]));
    }

    /**
     * Entirely cancelled obligations never generate an artificial pass or 100 percent.
     */
    public function test_all_cancelled_sessions_remain_without_a_final_result(): void {
        global $DB;
        [$activity, $cm, $journey, $participant, $teacher, $sessions] = $this->fixture();
        foreach ($sessions as $session) {
            session_cancellation::change($cm, $session->id, true, 0, 'No session held');
        }
        $calculation = calculator::calculate($activity, $participant->id, $journey->id);
        $this->assertNull($calculation->percent);
        $this->assertSame(0, $calculation->applicablesessions);
        $this->assertSame(0, $calculation->futuresessions);
        $this->assertSame(0, calculator::aggregate(
            $activity,
            $DB->get_records('attendancejourneys_sessions'),
            $DB->get_records('attendancejourneys_records'),
            $participant->id
        )->applicablesessions);
        $this->assertFalse(attendancejourneys_user_can_self_record_session(
            \context_module::instance($cm->id),
            $activity,
            $DB->get_record('attendancejourneys_sessions', ['id' => $sessions[0]->id]),
            $participant->id
        ));
        try {
            attendancejourneys_close_user_journey($activity, $participant->id, $teacher->id, $journey->id);
            $this->fail('An entirely cancelled journey is not automatically passed.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('closureemptysessions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures'));
    }

    /**
     * Privacy discovers historical actors and erases their explanations without reinstating sessions.
     */
    public function test_privacy_preserves_state_and_anonymises_actor(): void {
        global $DB;
        [$activity, $cm, , , $teacher, $sessions] = $this->fixture();
        session_cancellation::change($cm, $sessions[1]->id, true, 0, 'Private operational explanation');
        $context = \context_module::instance($cm->id);
        $this->assertContains((int) $context->id, array_map(
            'intval',
            provider::get_contexts_for_userid($teacher->id)->get_contextids()
        ));
        $approved = new \core_privacy\local\request\approved_contextlist($teacher, 'mod_attendancejourneys', [$context->id]);
        provider::export_user_data($approved);
        $export = \core_privacy\local\request\writer::with_context($context)->get_data([]);
        $actions = array_values(array_filter($export->authored_actions, static fn($item) =>
            $item->table === 'attendancejourneys_sessionlog'));
        $this->assertCount(1, $actions);
        $this->assertSame('Private operational explanation', $actions[0]->details->reason);
        $this->setAdminUser();
        provider::delete_data_for_user($approved);
        $log = $DB->get_record('attendancejourneys_sessionlog', ['sessionid' => $sessions[1]->id], '*', MUST_EXIST);
        $this->assertEquals(0, $log->actorid);
        $this->assertNull($log->reason);
        $this->assertEquals(1, $DB->get_field('attendancejourneys_sessions', 'cancelled', ['id' => $sessions[1]->id]));
        $this->assertSame('cancel', $log->action);
    }
    /**
     * A cancel/reinstate cycle cannot make an old confirmation valid again, even within one second.
     */
    public function test_old_confirmation_is_rejected_after_a_state_cycle(): void {
        global $DB;
        [, $cm, , , , $sessions] = $this->fixture();
        $originalversion = (int) $sessions[1]->timemodified;
        $cancelled = session_cancellation::change($cm, $sessions[1]->id, true, 0, 'Unavailable', $originalversion);
        $reinstated = session_cancellation::change(
            $cm,
            $sessions[1]->id,
            false,
            1,
            'Available again',
            (int) $cancelled->timemodified
        );
        $this->assertGreaterThan($cancelled->timemodified, $reinstated->timemodified);
        try {
            session_cancellation::change($cm, $sessions[1]->id, true, 0, 'Old explanation', $originalversion);
            $this->fail('An old confirmation must not become valid after reinstatement.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('sessioncancellationstale', $exception->errorcode);
        }
        $this->assertSame(2, $DB->count_records('attendancejourneys_sessionlog'));
        $this->assertEquals(0, $DB->get_field('attendancejourneys_sessions', 'cancelled', ['id' => $sessions[1]->id]));
    }
}
