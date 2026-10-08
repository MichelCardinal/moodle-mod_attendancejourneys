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

use mod_attendancejourneys\local\individual_obligation;
use mod_attendancejourneys\local\obligation_policy;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\individual_obligation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\journey_grades::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\obligation_policy::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\privacy\provider::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_calculate_user_attendance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_close_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_closure_blocker')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_complete_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_journey_deletion_info')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_reopen_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_reset_user_data')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_completion')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_grades')]
/**
 * Individual decisions preserve attendance and require current authorised previews.
 *
 * @covers \mod_attendancejourneys\local\individual_obligation
 * @covers \mod_attendancejourneys\local\journey_grades
 * @covers \mod_attendancejourneys\local\obligation_policy
 * @covers \mod_attendancejourneys\privacy\provider
 * @covers ::attendancejourneys_calculate_user_attendance
 * @covers ::attendancejourneys_close_user_journey
 * @covers ::attendancejourneys_closure_blocker
 * @covers ::attendancejourneys_complete_journey
 * @covers ::attendancejourneys_delete_instance
 * @covers ::attendancejourneys_journey_deletion_info
 * @covers ::attendancejourneys_reopen_user_journey
 * @covers ::attendancejourneys_reset_user_data
 * @covers ::attendancejourneys_update_completion
 * @covers ::attendancejourneys_update_grades
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class individual_obligation_test extends \core_privacy\tests\provider_testcase {
    /**
     * An automatic journey with two completed sessions and a 40 percent provisional result.
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
            'course' => $course->id, 'journeygrading' => 1, 'percentageenabled' => 1, 'gradeenabled' => 1,
            'passinggrade' => 80, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 1,
        ]);
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $journey = $DB->get_record('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id], '*', MUST_EXIST);
        $sessions = [];
        foreach ([2, 1] as $index => $daysago) {
            $id = $DB->insert_record('attendancejourneys_sessions', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journey->id,
                'name' => 'Obligation session ' . $index, 'duration' => 100, 'sessiondate' => time() - $daysago * DAYSECS,
            ]);
            $sessions[] = $DB->get_record('attendancejourneys_sessions', ['id' => $id]);
            $DB->insert_record('attendancejourneys_records', (object) [
                'sessionid' => $id, 'userid' => $student->id, 'status' => $index ? 'partial' : 'absent',
                'minutesabsent' => $index ? 20 : 100, 'approved' => 1, 'takenby' => $teacher->id,
            ]);
        }
        $cm = get_coursemodule_from_id('attendancejourneys', $activity->cmid, 0, false, MUST_EXIST);
        $this->setUser($teacher);
        return [$activity, $cm, $journey, $student, $teacher, $sessions];
    }

    /**
     * Losing report access after preview prevents confirming unseen participant calculations.
     */
    public function test_confirmation_rechecks_report_permission(): void {
        global $DB;
        [, $cm, $journey, $student] = $this->fixture();
        $policy = obligation_policy::decision(true, 0, 0, 'Approved waiver');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $policy);
        $role = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('mod/attendancejourneys:viewreports', CAP_PROHIBIT, $role, \context_module::instance($cm->id)->id);
        try {
            individual_obligation::change($cm, $journey->id, $student->id, $policy, $preview->token);
            $this->fail('Report access must be checked again at confirmation.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligations'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
    }

    /**
     * Preview is read-only; a decision preserves the records and prior policy in history.
     */
    public function test_preview_and_reversal_preserve_attendance_and_do_not_publish_grades(): void {
        global $DB;
        [$activity, $cm, $journey, $student, $teacher, $sessions] = $this->fixture();
        $records = $DB->get_records('attendancejourneys_records', [], 'id');
        $policy = obligation_policy::decision(false, $sessions[1]->sessiondate, 0, 'Approved later start');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $policy);
        $this->assertEquals(40, $preview->before->percent);
        $this->assertEquals(80, $preview->after->percent);
        $this->assertSame([(int) $sessions[0]->id], array_keys($preview->excluded));
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligations'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
        $decision = individual_obligation::change($cm, $journey->id, $student->id, $policy, $preview->token);
        $this->assertSame(1, $decision->revision);
        $this->assertEquals($teacher->id, $decision->actorid);
        $waiver = obligation_policy::decision(true, 0, 0, 'Approved whole-journey waiver');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $waiver);
        $this->assertNull($preview->after->percent);
        $this->assertSame(0, $preview->after->applicablesessions);
        individual_obligation::change($cm, $journey->id, $student->id, $waiver, $preview->token);
        $restore = obligation_policy::decision(false, 0, 0, 'Restore the complete obligation');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $restore);
        $this->assertEquals(40, $preview->after->percent);
        individual_obligation::change($cm, $journey->id, $student->id, $restore, $preview->token);
        $this->assertSame(1, $DB->count_records('attendancejourneys_obligations'));
        $history = array_values($DB->get_records('attendancejourneys_obligationlog', [], 'revision'));
        $this->assertEquals([1, 2, 3], array_column($history, 'revision'));
        $this->assertEquals([0, 0, 1], array_column($history, 'previouswaived'));
        $this->assertEquals($sessions[1]->sessiondate, $history[1]->previousstarttime);
        $this->assertEquals($records, $DB->get_records('attendancejourneys_records', [], 'id'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures'));
        $this->assertSame(0, $DB->count_records_select('grade_grades', 'rawgrade IS NOT NULL OR finalgrade IS NOT NULL'));
    }

    /**
     * A changed attendance record invalidates a prior preview rather than accepting unseen minutes.
     */
    public function test_changed_attendance_rejects_stale_confirmation(): void {
        global $DB;
        [, $cm, $journey, $student, , $sessions] = $this->fixture();
        $policy = obligation_policy::decision(false, $sessions[1]->sessiondate, 0, 'Approved later start');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $policy);
        $DB->set_field('attendancejourneys_records', 'minutesabsent', 30, ['sessionid' => $sessions[1]->id]);
        try {
            individual_obligation::change($cm, $journey->id, $student->id, $policy, $preview->token);
            $this->fail('A changed record must invalidate the preview.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('obligationstale', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligations'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
    }

    /**
     * Learners cannot grant themselves a waiver even with a valid teacher preview token.
     */
    public function test_participant_cannot_apply_teacher_preview(): void {
        global $DB;
        [, $cm, $journey, $student] = $this->fixture();
        $policy = obligation_policy::decision(true, 0, 0, 'Approved waiver');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $policy);
        $this->setUser($student);
        try {
            individual_obligation::change($cm, $journey->id, $student->id, $policy, $preview->token);
            $this->fail('A participant must not waive their own obligation.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
    }

    /**
     * Closure after preview protects the frozen result and does not append a decision.
     */
    public function test_final_result_blocks_decision_until_explicit_reopening(): void {
        global $DB;
        [$activity, $cm, $journey, $student, $teacher] = $this->fixture();
        $policy = obligation_policy::decision(true, 0, 0, 'Approved waiver');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $policy);
        $closure = attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $journey->id);
        $before = $DB->get_record('attendancejourneys_closures', ['id' => $closure->id]);
        try {
            individual_obligation::change($cm, $journey->id, $student->id, $policy, $preview->token);
            $this->fail('A final result must be reopened before changing its obligation.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('obligationblockedfinal', $exception->errorcode);
        }
        $this->assertEquals($before, $DB->get_record('attendancejourneys_closures', ['id' => $closure->id]));
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
    }

    /**
     * A period exclusion still requires resolving credits using the affected source.
     */
    public function test_pending_credit_blocks_removal_of_its_source_session(): void {
        global $DB;
        [$activity, $cm, $journey, $student, , $sessions] = $this->fixture();
        $DB->insert_record('attendancejourneys_equivalences', (object) [
            'attendancejourneysid' => $activity->id, 'userid' => $student->id, 'targetjourneyid' => $journey->id,
            'sourcesessionid' => $sessions[0]->id, 'targetsessionid' => $sessions[1]->id, 'status' => 'pending',
        ]);
        $policy = obligation_policy::decision(false, $sessions[1]->sessiondate, 0, 'Approved later start');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('obligationblockedequivalence', 'attendancejourneys'));
        individual_obligation::preview($cm, $journey->id, $student->id, $policy);
    }
    /**
     * Privacy exports both policies and history, then anonymises actors without revoking a waiver.
     */
    public function test_privacy_export_and_actor_erasure_preserve_participant_policy(): void {
        global $DB;
        [, $cm, $journey, $student, $teacher] = $this->fixture();
        $policy = obligation_policy::decision(true, 0, 0, 'Private waiver explanation');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $policy);
        individual_obligation::change($cm, $journey->id, $student->id, $policy, $preview->token);
        // Isolate discovery: only the new policy/history tables still identify the participant and actor.
        $DB->delete_records('attendancejourneys_records');
        $context = \context_module::instance($cm->id);
        foreach ([$student, $teacher] as $user) {
            $this->assertContains((int) $context->id, array_map(
                'intval',
                \mod_attendancejourneys\privacy\provider::get_contexts_for_userid($user->id)->get_contextids()
            ));
        }
        $users = new \core_privacy\local\request\userlist($context, 'mod_attendancejourneys');
        \mod_attendancejourneys\privacy\provider::get_users_in_context($users);
        $this->assertContains((int) $student->id, array_map('intval', $users->get_userids()));
        $this->assertContains((int) $teacher->id, array_map('intval', $users->get_userids()));
        $approved = new \core_privacy\local\request\approved_contextlist($student, 'mod_attendancejourneys', [$context->id]);
        \mod_attendancejourneys\privacy\provider::export_user_data($approved);
        $export = \core_privacy\local\request\writer::with_context($context)->get_data([]);
        $this->assertCount(1, $export->individual_obligations);
        $this->assertCount(1, $export->individual_obligation_history);
        $this->assertSame('Private waiver explanation', $export->individual_obligation_history[0]->reason);
        $this->setAdminUser();
        $actor = new \core_privacy\local\request\approved_contextlist($teacher, 'mod_attendancejourneys', [$context->id]);
        \mod_attendancejourneys\privacy\provider::delete_data_for_user($actor);
        foreach (['attendancejourneys_obligations', 'attendancejourneys_obligationlog'] as $table) {
            $row = $DB->get_record($table, ['userid' => $student->id], '*', MUST_EXIST);
            $this->assertEquals(1, $row->waived);
            $this->assertEquals(0, $row->actorid);
            $this->assertNull($row->reason);
        }
        \mod_attendancejourneys\privacy\provider::delete_data_for_user($approved);
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligations'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
    }
    /**
     * Participant reset and activity deletion cannot leave personal decisions orphaned.
     */
    public function test_reset_and_activity_deletion_clean_up_stored_decisions(): void {
        global $DB;
        [$activity, $cm, $journey, $student, $teacher] = $this->fixture();
        $policy = obligation_policy::decision(true, 0, 0, 'Approved waiver');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $policy);
        individual_obligation::change($cm, $journey->id, $student->id, $policy, $preview->token);
        attendancejourneys_reset_user_data($activity, $cm, $student->id, $teacher->id);
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligations'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $policy);
        individual_obligation::change($cm, $journey->id, $student->id, $policy, $preview->token);
        $this->assertFalse(attendancejourneys_journey_deletion_info($activity->id, $journey->id)->candelete);
        $this->assertTrue(attendancejourneys_delete_instance($activity->id));
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligations'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
    }

    /**
     * A teacher loses access after a journey moves into a group they cannot manage.
     */
    public function test_confirmation_rechecks_separate_group_visibility(): void {
        global $DB;
        [$activity, $cm, $journey, $student] = $this->fixture();
        $policy = obligation_policy::decision(true, 0, 0, 'Approved waiver');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $policy);
        $group = $this->getDataGenerator()->create_group(['courseid' => $activity->course]);
        groups_add_member($group->id, $student->id);
        $DB->set_field('attendancejourneys_journeys', 'groupid', $group->id, ['id' => $journey->id]);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $cm->id]);
        $role = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $role, \context_module::instance($cm->id)->id);
        try {
            individual_obligation::change($cm, $journey->id, $student->id, $policy, $preview->token);
            $this->fail('A previous preview must not bypass current group permissions.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligations'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
    }
    /**
     * A justified period affects the official result but publishes nothing until explicit closure.
     */
    public function test_period_updates_official_calculation_and_only_explicit_closure_publishes(): void {
        global $DB;
        [$activity, $cm, $journey, $student, $teacher, $sessions] = $this->fixture();
        $records = $DB->get_records('attendancejourneys_records', [], 'id');
        $policy = obligation_policy::decision(false, $sessions[1]->sessiondate, 0, 'Approved later start');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $policy);
        individual_obligation::change($cm, $journey->id, $student->id, $policy, $preview->token);
        $calculation = attendancejourneys_calculate_user_attendance($activity, $student->id, $journey->id);
        $this->assertEquals(80, $calculation->percent);
        $this->assertSame(1, $calculation->applicablesessions);
        $this->assertSame(100, $calculation->possibleminutes);
        $this->assertNull($this->raw_grade($activity, $journey->id, $student->id));
        $completion = new \completion_info(get_course($activity->course));
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
        $closure = attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $journey->id);
        attendancejourneys_update_grades($activity, $student->id);
        attendancejourneys_update_completion(get_course($activity->course), $cm, [$student->id]);
        $this->assertEquals(80, $closure->percentage);
        $this->assertEquals(80, $this->raw_grade($activity, $journey->id, $student->id));
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
        attendancejourneys_reopen_user_journey($activity, $student->id, $teacher->id, $journey->id);
        $restore = obligation_policy::decision(false, 0, 0, 'Restore all sessions');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $restore);
        individual_obligation::change($cm, $journey->id, $student->id, $restore, $preview->token);
        $this->assertEquals(40, attendancejourneys_calculate_user_attendance($activity, $student->id, $journey->id)->percent);
        $this->assertNull($this->raw_grade($activity, $journey->id, $student->id));
        $this->assertEquals(80, $DB->get_field('attendancejourneys_closures', 'percentage', ['id' => $closure->id]));
        $this->assertEquals($records, $DB->get_records('attendancejourneys_records', [], 'id'));
    }

    /**
     * All-waived obligations remain incomplete and Moodle retains an authorised manual override.
     */
    public function test_all_waived_does_not_complete_but_preserves_manual_moodle_decision(): void {
        global $DB;
        [$activity, $cm, $journey, $student] = $this->fixture();
        $waiver = obligation_policy::decision(true, 0, 0, 'Approved waiver');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $waiver);
        individual_obligation::change($cm, $journey->id, $student->id, $waiver, $preview->token);
        $calculation = attendancejourneys_calculate_user_attendance($activity, $student->id, $journey->id);
        $this->assertTrue($calculation->waived);
        $this->assertNull($calculation->percent);
        $this->assertSame(['closureobligationwaived', null], attendancejourneys_closure_blocker($calculation));
        $this->assertFalse(\mod_attendancejourneys\local\journey_grades::all_required_passed($activity, $student->id));
        $completion = new \completion_info(get_course($activity->course));
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
        $this->assertNull($this->raw_grade($activity, $journey->id, $student->id));
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures'));
        $completion->update_state($cm, COMPLETION_COMPLETE, $student->id, true);
        $manual = $completion->get_data($cm, false, $student->id);
        $this->assertNotEmpty($manual->overrideby);
        $restore = obligation_policy::decision(false, 0, 0, 'Restore all sessions');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $restore);
        individual_obligation::change($cm, $journey->id, $student->id, $restore, $preview->token);
        $current = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $current->completionstate);
        $this->assertEquals($manual->overrideby, $current->overrideby);
    }

    /**
     * One waived obligation leaves the other independent result required; reinstatement withdraws completion.
     */
    public function test_waiver_of_one_journey_does_not_bypass_the_other(): void {
        global $DB;
        [$activity, $cm, $first, $student, $teacher] = $this->fixture();
        $secondid = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Theory obligation', 'passinggrade' => 60,
            'required' => 1, 'audiencemode' => 1,
        ]);
        $sessionid = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $secondid, 'name' => 'Theory session',
            'duration' => 100, 'sessiondate' => time() - DAYSECS,
        ]);
        $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $sessionid, 'userid' => $student->id, 'status' => 'partial', 'minutesabsent' => 30,
            'approved' => 1, 'takenby' => $teacher->id,
        ]);
        $waiver = obligation_policy::decision(true, 0, 0, 'Approved waiver of practical obligation');
        $preview = individual_obligation::preview($cm, $first->id, $student->id, $waiver);
        individual_obligation::change($cm, $first->id, $student->id, $waiver, $preview->token);
        $completion = new \completion_info(get_course($activity->course));
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $secondid);
        attendancejourneys_update_grades($activity, $student->id);
        attendancejourneys_update_completion(get_course($activity->course), $cm, [$student->id]);
        $this->assertEquals(70, $this->raw_grade($activity, $secondid, $student->id));
        $this->assertNull($this->raw_grade($activity, $first->id, $student->id));
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
        $restore = obligation_policy::decision(false, 0, 0, 'Restore practical obligation');
        $preview = individual_obligation::preview($cm, $first->id, $student->id, $restore);
        individual_obligation::change($cm, $first->id, $student->id, $restore, $preview->token);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
        $this->assertEquals(70, $this->raw_grade($activity, $secondid, $student->id));
    }

    /**
     * Collective administrative closure never creates a successful result for waived participants.
     */
    public function test_collective_closure_omits_waived_participant_results(): void {
        global $DB;
        [$activity, $cm, $journey, $student, $teacher] = $this->fixture();
        $waiver = obligation_policy::decision(true, 0, 0, 'Approved waiver');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $waiver);
        individual_obligation::change($cm, $journey->id, $student->id, $waiver, $preview->token);
        $closed = attendancejourneys_complete_journey($activity, $journey, \context_module::instance($cm->id), $teacher->id);
        $this->assertSame([], $closed);
        $this->assertEquals(0, $DB->get_field('attendancejourneys_journeys', 'active', ['id' => $journey->id]));
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures'));
        $this->assertNull($this->raw_grade($activity, $journey->id, $student->id));
        $this->assertFalse(\mod_attendancejourneys\local\journey_grades::all_required_passed($activity, $student->id));
    }

    /**
     * A future unrecorded session outside an explicit period does not block closure of retained sessions.
     */
    public function test_explicit_end_excludes_future_unrecorded_obligation(): void {
        global $DB;
        [$activity, $cm, $journey, $student, $teacher] = $this->fixture();
        $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journey->id, 'name' => 'Future session',
            'duration' => 100, 'sessiondate' => time() + DAYSECS,
        ]);
        $before = attendancejourneys_calculate_user_attendance($activity, $student->id, $journey->id);
        $this->assertSame(1, $before->futuresessions);
        $policy = obligation_policy::decision(false, 0, time(), 'Approved end of individual obligation');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $policy);
        $this->assertCount(1, $preview->excluded);
        individual_obligation::change($cm, $journey->id, $student->id, $policy, $preview->token);
        $after = attendancejourneys_calculate_user_attendance($activity, $student->id, $journey->id);
        $this->assertSame(0, $after->futuresessions);
        $this->assertSame(2, $after->applicablesessions);
        $closure = attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $journey->id);
        $this->assertEquals(40, $closure->percentage);
        $this->assertSame('failed', $closure->result);
    }

    /**
     * An explicitly approved credit can use real historical attendance on a waived source.
     */
    public function test_credit_from_waived_source_preserves_real_minutes(): void {
        global $DB;
        [$activity, $cm, $journey, $student, $teacher, $sessions] = $this->fixture();
        $waiver = obligation_policy::decision(true, 0, 0, 'Approved source waiver');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $waiver);
        individual_obligation::change($cm, $journey->id, $student->id, $waiver, $preview->token);
        $targetjourney = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Target obligation', 'audiencemode' => 1,
        ]);
        $targetsession = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $targetjourney, 'name' => 'Target session',
            'duration' => 100, 'sessiondate' => time() - DAYSECS,
        ]);
        $DB->insert_record('attendancejourneys_equivalences', (object) [
            'attendancejourneysid' => $activity->id, 'userid' => $student->id, 'targetjourneyid' => $targetjourney,
            'targetsessionid' => $targetsession, 'sourcesessionid' => $sessions[1]->id,
            'status' => 'approved', 'decidedby' => $teacher->id, 'timedecided' => time(),
        ]);
        $calculation = attendancejourneys_calculate_user_attendance($activity, $student->id, $targetjourney);
        $this->assertSame(1, $calculation->applicablesessions);
        $this->assertSame(1, $calculation->recordedsessions);
        $this->assertEquals(80, $calculation->percent);
        $this->assertEquals(80, $calculation->presentminutes);
        $this->assertEquals(2, $DB->count_records('attendancejourneys_records'));
    }

    /**
     * Waiving a source after approval preserves its credit, but waiving the target requires resolution.
     */
    public function test_source_waiver_preserves_approved_credit_and_target_protection(): void {
        global $DB;
        [$activity, $cm, $journey, $student, $teacher, $sessions] = $this->fixture();
        $targetjourney = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Transferred obligation', 'audiencemode' => 1, 'active' => 1,
        ]);
        $targetsession = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $targetjourney, 'name' => 'Short target',
            'duration' => 50, 'sessiondate' => time() - DAYSECS,
        ]);
        $creditid = $DB->insert_record('attendancejourneys_equivalences', (object) [
            'attendancejourneysid' => $activity->id, 'userid' => $student->id, 'targetjourneyid' => $targetjourney,
            'targetsessionid' => $targetsession, 'sourcesessionid' => $sessions[1]->id,
            'status' => 'approved', 'decidedby' => $teacher->id, 'timedecided' => time(),
        ]);
        $beforecredit = $DB->get_record('attendancejourneys_equivalences', ['id' => $creditid]);
        $beforerecords = $DB->get_records('attendancejourneys_records', [], 'id');
        $waiver = obligation_policy::decision(true, 0, 0, 'Approved transfer of obligation');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $waiver);
        individual_obligation::change($cm, $journey->id, $student->id, $waiver, $preview->token);
        $source = attendancejourneys_calculate_user_attendance($activity, $student->id, $journey->id);
        $target = attendancejourneys_calculate_user_attendance($activity, $student->id, $targetjourney);
        $this->assertTrue($source->waived);
        $this->assertSame(0, $source->applicablesessions);
        $this->assertEquals(50, $target->presentminutes);
        $this->assertEquals(100, $target->percent);
        $this->assertEquals($beforecredit, $DB->get_record('attendancejourneys_equivalences', ['id' => $creditid]));
        $this->assertEquals($beforerecords, $DB->get_records('attendancejourneys_records', [], 'id'));
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('obligationblockedequivalence', 'attendancejourneys'));
        individual_obligation::preview($cm, $targetjourney, $student->id, $waiver);
    }

    /**
     * Read the actual gradebook cell independently of the calculation.
     *
     * @param \stdClass $activity Activity configuration.
     * @param int $journeyid Journey identifier.
     * @param int $userid Participant identifier.
     * @return float|null Actual raw grade, or no published grade.
     */
    private function raw_grade(\stdClass $activity, int $journeyid, int $userid): ?float {
        global $DB;
        $number = $DB->get_field('attendancejourneys_journeys', 'gradeitemnumber', ['id' => $journeyid], MUST_EXIST);
        $itemid = $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id, 'itemnumber' => $number,
        ], MUST_EXIST);
        $grade = $DB->get_record('grade_grades', ['itemid' => $itemid, 'userid' => $userid]);
        return $grade && $grade->rawgrade !== null ? (float) $grade->rawgrade : null;
    }
    /**
     * A period restored to its old value cannot revive a confirmation from an earlier revision.
     */
    public function test_old_preview_stays_stale_after_a_policy_cycle(): void {
        global $DB;
        [, $cm, $journey, $student] = $this->fixture();
        $waiver = obligation_policy::decision(true, 0, 0, 'Approved waiver');
        $original = individual_obligation::preview($cm, $journey->id, $student->id, $waiver);
        individual_obligation::change($cm, $journey->id, $student->id, $waiver, $original->token);
        $restore = obligation_policy::decision(false, 0, 0, 'Restore complete obligation');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $restore);
        individual_obligation::change($cm, $journey->id, $student->id, $restore, $preview->token);
        try {
            individual_obligation::change($cm, $journey->id, $student->id, $waiver, $original->token);
            $this->fail('A policy cycle must not make an old preview current.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('obligationstale', $exception->errorcode);
        }
        $this->assertSame(2, $DB->count_records('attendancejourneys_obligationlog'));
    }

    /**
     * A changed policy in another journey invalidates a preview whose cross-journey state was read earlier.
     */
    public function test_preview_covers_other_journey_policies(): void {
        global $DB;
        [$activity, $cm, $journey, $student] = $this->fixture();
        $otherid = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Another obligation', 'audiencemode' => 1,
        ]);
        $waiver = obligation_policy::decision(true, 0, 0, 'Approved waiver');
        $original = individual_obligation::preview($cm, $journey->id, $student->id, $waiver);
        $otherpreview = individual_obligation::preview($cm, $otherid, $student->id, $waiver);
        individual_obligation::change($cm, $otherid, $student->id, $waiver, $otherpreview->token);
        // Refresh the activity snapshot so only the changed policy, not allocated grade-item counters, differs.
        $fresh = individual_obligation::preview($cm, $journey->id, $student->id, $waiver);
        $this->assertNotSame($original->token, $fresh->token);
        $DB->set_field('attendancejourneys_obligations', 'reason', 'Updated decision explanation', ['journeyid' => $otherid]);
        try {
            individual_obligation::change($cm, $journey->id, $student->id, $waiver, $fresh->token);
            $this->fail('Other journey policy state must participate in preview consistency.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('obligationstale', $exception->errorcode);
        }
        $this->assertSame(1, $DB->count_records('attendancejourneys_obligationlog'));
    }

    /**
     * A manual grade entered after preview cannot be erased through an obligation decision.
     */
    public function test_manual_grade_change_after_preview_is_protected(): void {
        global $DB;
        [$activity, $cm, $journey, $student] = $this->fixture();
        $waiver = obligation_policy::decision(true, 0, 0, 'Approved waiver');
        $preview = individual_obligation::preview($cm, $journey->id, $student->id, $waiver);
        $number = $DB->get_field('attendancejourneys_journeys', 'gradeitemnumber', ['id' => $journey->id], MUST_EXIST);
        $itemid = $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id, 'itemnumber' => $number,
        ], MUST_EXIST);
        $grade = $DB->get_record('grade_grades', ['itemid' => $itemid, 'userid' => $student->id]);
        $protected = (object) ['itemid' => $itemid, 'userid' => $student->id, 'rawgrade' => 0,
            'finalgrade' => 0, 'overridden' => time()];
        if ($grade) {
            $protected->id = $grade->id;
            $DB->update_record('grade_grades', $protected);
        } else {
            $protected->id = $DB->insert_record('grade_grades', $protected);
        }
        $before = $DB->get_record('grade_grades', ['id' => $protected->id]);
        try {
            individual_obligation::change($cm, $journey->id, $student->id, $waiver, $preview->token);
            $this->fail('An overridden zero grade must be protected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('journeygradeoverridden', $exception->errorcode);
        }
        $this->assertEquals($before, $DB->get_record('grade_grades', ['id' => $protected->id]));
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
    }
    /**
     * Equal minute totals do not hide a change in the actual sessions required by group membership.
     */
    public function test_group_session_switch_invalidates_preview_even_with_equal_totals(): void {
        global $DB;
        [$activity, $cm, $journey, $student, , $sessions] = $this->fixture();
        $DB->delete_records('attendancejourneys_records');
        $firstgroup = $this->getDataGenerator()->create_group(['courseid' => $activity->course]);
        $secondgroup = $this->getDataGenerator()->create_group(['courseid' => $activity->course]);
        $DB->set_field('attendancejourneys_sessions', 'groupid', $firstgroup->id, ['id' => $sessions[0]->id]);
        $DB->set_field('attendancejourneys_sessions', 'groupid', $secondgroup->id, ['id' => $sessions[1]->id]);
        groups_add_member($firstgroup->id, $student->id);
        $waiver = obligation_policy::decision(true, 0, 0, 'Approved waiver');
        $original = individual_obligation::preview($cm, $journey->id, $student->id, $waiver);
        $this->assertSame([(int) $sessions[0]->id], array_keys($original->excluded));
        groups_remove_member($firstgroup->id, $student->id);
        groups_add_member($secondgroup->id, $student->id);
        $fresh = individual_obligation::preview($cm, $journey->id, $student->id, $waiver);
        $this->assertEquals($original->before, $fresh->before);
        $this->assertSame([(int) $sessions[1]->id], array_keys($fresh->excluded));
        try {
            individual_obligation::change($cm, $journey->id, $student->id, $waiver, $original->token);
            $this->fail('A different required session must invalidate the preview even if totals match.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('obligationstale', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
    }
}
