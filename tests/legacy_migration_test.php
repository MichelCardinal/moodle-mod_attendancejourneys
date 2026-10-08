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

use mod_attendancejourneys\local\legacy_migration;

/**
 * Conservative conversion and read-only preview tests.
 *
 * @package    mod_attendancejourneys
 * @category   test
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy_migration_test extends \advanced_testcase {
    /**
     * Build an unfinished historical activity with an independent session.
     *
     * @return array Activity, context, participant and session.
     */
    private function fixture(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once(__DIR__ . '/../locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $activity = $generator->create_module('attendancejourneys', [
            'course' => $course->id, 'journeygrading' => 0, 'gradeenabled' => 1,
            'excusedmode' => 'absent', 'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionrecorded' => 1,
        ]);
        $context = \context_module::instance($activity->cmid);
        $student = $generator->create_and_enrol($course, 'student');
        $sessionid = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Original session',
            'duration' => 100, 'sessiondate' => time() - DAYSECS,
        ]);
        $session = $DB->get_record('attendancejourneys_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $sessionid, 'userid' => $student->id, 'status' => 'present',
            'approved' => 1, 'takenby' => 2,
        ]);
        return [$activity, $context, $student, $session];
    }

    /**
     * Preview is stable and does not mutate records, items or completion.
     */
    public function test_read_only_preview_and_conversion_preserve_grade_item_and_records(): void {
        global $DB;
        [$activity, $context, $student, $session] = $this->fixture();
        $before = $DB->get_records('attendancejourneys_records');
        $item = $DB->get_record('grade_items', [
            'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id,
        ], '*', MUST_EXIST);
        $oldcompletion = get_fast_modinfo($activity->course)->get_cm($activity->cmid)->customdata;
        $this->assertEquals(1, $oldcompletion['customcompletionrules']['completionrecorded']);
        $preview = legacy_migration::preview($context);
        $this->assertSame([], $preview->blockers);
        $this->assertSame($preview->fingerprint, legacy_migration::preview($context)->fingerprint);
        $this->assertEquals($before, $DB->get_records('attendancejourneys_records'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_journeys'));
        $sink = $this->redirectEvents();
        $journeyid = legacy_migration::convert($context, $preview->fingerprint);
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \mod_attendancejourneys\event\grading_mode_changed
        ));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_attendancejourneys\event\grading_mode_changed::class, $events[0]);
        $this->assertEquals($activity->id, $events[0]->objectid);
        $this->assertEquals($journeyid, $events[0]->other['journeyid']);
        $this->assertSame(['db' => 'attendancejourneys', 'restore' => 'attendancejourneys'], $events[0]::get_objectid_mapping());
        $this->assertSame([
            'journeyid' => ['db' => 'attendancejourneys_journeys', 'restore' => 'attendancejourneys_journey'],
        ], $events[0]::get_other_mapping());
        $this->assertEquals($before, $DB->get_records('attendancejourneys_records'));
        $journey = $DB->get_record('attendancejourneys_journeys', ['id' => $journeyid], '*', MUST_EXIST);
        $this->assertEquals(1, $journey->audiencemode);
        $this->assertEquals(0, $journey->gradeitemnumber);
        $this->assertEquals($journeyid, $DB->get_field('attendancejourneys_sessions', 'journeyid', ['id' => $session->id]));
        $this->assertEquals($item->id, $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id,
        ]));
        $this->assertSame(0, $DB->count_records_select('grade_grades', 'rawgrade IS NOT NULL OR finalgrade IS NOT NULL'));
        $stored = $DB->get_record('attendancejourneys', ['id' => $activity->id], '*', MUST_EXIST);
        $this->assertEquals(1, $stored->journeygrading);
        $this->assertEquals(1, $stored->completionpass);
        $this->assertEquals(0, $stored->completionrecorded);
        $customdata = get_fast_modinfo($activity->course)->get_cm($activity->cmid)->customdata;
        $this->assertEquals(1, $customdata['journeygrading']);
        $this->assertEquals(1, $customdata['customcompletionrules']['completionpass']);
        attendancejourneys_close_user_journey($stored, $student->id, 2, $journeyid);
        attendancejourneys_update_grades($stored, $student->id);
        $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $student->id], '*', MUST_EXIST);
        $this->assertEquals(100, $grade->rawgrade);
    }

    /**
     * A single active journey retains its identifiers, fixed audience and threshold.
     */
    public function test_existing_journey_mapping_keeps_provisional_minutes_and_memberships(): void {
        global $DB;
        [$activity, $context, $student, $session] = $this->fixture();
        $journeyid = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Original journey', 'active' => 1,
            'passinggrade' => 60, 'audiencemode' => 0,
        ]);
        $DB->set_field('attendancejourneys_sessions', 'journeyid', $journeyid, ['id' => $session->id]);
        $DB->insert_record(
            'attendancejourneys_members',
            (object) ['journeyid' => $journeyid, 'userid' => $student->id, 'active' => 1]
        );
        $DB->set_field('attendancejourneys_records', 'status', 'partial', ['sessionid' => $session->id]);
        $DB->set_field('attendancejourneys_records', 'minutesabsent', 30, ['sessionid' => $session->id]);
        $members = $DB->get_records('attendancejourneys_members');
        $before = attendancejourneys_calculate_user_attendance($activity, $student->id, $journeyid);
        $preview = legacy_migration::preview($context);
        $this->assertSame([], $preview->blockers);
        $this->assertSame($journeyid, legacy_migration::convert($context, $preview->fingerprint));
        $stored = $DB->get_record('attendancejourneys', ['id' => $activity->id], '*', MUST_EXIST);
        $after = attendancejourneys_calculate_user_attendance($stored, $student->id, $journeyid);
        $this->assertEquals($before->presentminutes, $after->presentminutes);
        $this->assertEquals($before->possibleminutes, $after->possibleminutes);
        $this->assertEquals($before->percent, $after->percent);
        $this->assertEquals($members, $DB->get_records('attendancejourneys_members'));
        $this->assertEquals(2, $DB->get_field('attendancejourneys_journeys', 'audiencemode', ['id' => $journeyid]));
        $this->assertEquals(60, $DB->get_field('attendancejourneys_journeys', 'passinggrade', ['id' => $journeyid]));
    }

    /**
     * Changing a record invalidates the displayed state, even when all counts stay the same.
     */
    public function test_changed_records_prevent_stale_confirmation_without_writes(): void {
        global $DB;
        [$activity, $context, $student, $session] = $this->fixture();
        $preview = legacy_migration::preview($context);
        $DB->set_field('attendancejourneys_records', 'status', 'absent', ['sessionid' => $session->id]);
        $before = $DB->get_records('attendancejourneys_records');
        try {
            legacy_migration::convert($context, $preview->fingerprint);
            $this->fail('A stale confirmation must not convert the activity.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('migrationstale', $exception->errorcode);
        }
        $this->assertEquals(0, $DB->get_field('attendancejourneys', 'journeygrading', ['id' => $activity->id]));
        $this->assertEquals($before, $DB->get_records('attendancejourneys_records'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_journeys'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_closures'));
    }

    /**
     * Historical excused policies and published zero grades are not silently reinterpreted.
     */
    public function test_incompatible_excused_records_and_zero_grades_block_conversion(): void {
        global $DB;
        [$activity, $context, $student, $session] = $this->fixture();
        $DB->set_field('attendancejourneys_records', 'status', 'excused', ['sessionid' => $session->id]);
        foreach (['excluded', 'present'] as $mode) {
            $DB->set_field('attendancejourneys', 'excusedmode', $mode, ['id' => $activity->id]);
            $this->assertContains('migrationexcusedpolicy', legacy_migration::preview($context)->blockers);
        }
        $DB->set_field('attendancejourneys', 'excusedmode', 'absent', ['id' => $activity->id]);
        $this->assertSame([], legacy_migration::preview($context)->blockers);
        attendancejourneys_grade_item_update($activity, [$student->id => (object) ['userid' => $student->id, 'rawgrade' => 0]]);
        $preview = legacy_migration::preview($context);
        $this->assertContains('migrationpublishedgrades', $preview->blockers);
        $before = $DB->get_records('grade_grades');
        try {
            legacy_migration::convert($context, $preview->fingerprint);
            $this->fail('A published zero must not be treated as an empty grade.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('migrationblocked', $exception->errorcode);
        }
        $this->assertEquals($before, $DB->get_records('grade_grades'));
        $this->assertEquals(0, $DB->get_field('attendancejourneys', 'journeygrading', ['id' => $activity->id]));
    }

    /**
     * Both item locks without grades and manual overrides prevent conversion.
     */
    public function test_gradebook_protections_are_reported_without_removing_them(): void {
        global $DB;
        [$activity, $context, $student] = $this->fixture();
        $item = \grade_item::fetch(['itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id, 'itemnumber' => 0]);
        $item->locked = time();
        $item->update();
        $this->assertContains('migrationprotectedgrades', legacy_migration::preview($context)->blockers);
        $this->assertEquals($item->locked, $DB->get_field('grade_items', 'locked', ['id' => $item->id]));
        $item->locked = 0;
        $item->update();
        $item->update_final_grade($student->id, 95, 'manual');
        $preview = legacy_migration::preview($context);
        $this->assertSame(1, $preview->protected);
        $this->assertContains('migrationprotectedgrades', $preview->blockers);
        $this->assertContains('migrationpublishedgrades', $preview->blockers);
    }

    /**
     * Completed snapshots, manual completion and multiple journeys need a separate preservation plan.
     */
    public function test_historical_obligations_and_completion_decisions_are_reported(): void {
        global $DB;
        [$activity, $context, $student, $session] = $this->fixture();
        $journeyid = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'First', 'active' => 1,
        ]);
        $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Second', 'active' => 1,
        ]);
        $DB->set_field('attendancejourneys_sessions', 'journeyid', $journeyid, ['id' => $session->id]);
        $closureid = $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'userid' => $student->id, 'active' => 0,
        ]);
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $activity->cmid, 'userid' => $student->id, 'completionstate' => 0, 'overrideby' => 2,
            'timemodified' => time(),
        ]);
        $preview = legacy_migration::preview($context);
        $this->assertContains('migrationcomplexjourneys', $preview->blockers);
        $this->assertContains('migrationfinalresults', $preview->blockers);
        $this->assertContains('migrationexistingcompletion', $preview->blockers);
        $this->assertEquals(0, $DB->get_field('attendancejourneys_closures', 'active', ['id' => $closureid]));
    }

    /**
     * Students cannot inspect or convert the activity's grading inventory.
     */
    public function test_student_cannot_preview_migration(): void {
        [$activity, $context, $student] = $this->fixture();
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        legacy_migration::preview($context);
    }

    /**
     * Course editing rights do not give a restricted group manager access to the whole inventory.
     */
    public function test_restricted_manager_cannot_preview_the_whole_activity(): void {
        global $DB;
        [$activity, $context] = $this->fixture();
        $course = get_course($activity->course);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $activity->cmid]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $context->id);
        $this->setUser($teacher);
        $this->assertTrue(has_capability('moodle/course:manageactivities', $context));
        $this->expectException(\required_capability_exception::class);
        legacy_migration::preview($context);
    }

    /**
     * Conversion rechecks permissions even with a valid administrator fingerprint.
     */
    public function test_student_cannot_use_an_authorised_fingerprint(): void {
        global $DB;
        [$activity, $context, $student] = $this->fixture();
        $preview = legacy_migration::preview($context);
        $this->setUser($student);
        try {
            legacy_migration::convert($context, $preview->fingerprint);
            $this->fail('A fingerprint is not an authorisation to convert.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $this->assertEquals(0, $DB->get_field('attendancejourneys', 'journeygrading', ['id' => $activity->id]));
        $this->assertSame(0, $DB->count_records('attendancejourneys_journeys'));
    }

    /**
     * Mixed session scopes and invalid partial attendance require a repair before conversion.
     */
    public function test_mixed_sessions_and_invalid_partial_records_are_not_converted(): void {
        global $DB;
        [$activity, $context, $student, $session] = $this->fixture();
        $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Journey with independent session', 'active' => 1,
        ]);
        $DB->set_field('attendancejourneys_records', 'status', 'partial', ['sessionid' => $session->id]);
        $DB->set_field('attendancejourneys_records', 'minutesabsent', 100, ['sessionid' => $session->id]);
        $preview = legacy_migration::preview($context);
        $this->assertContains('migrationmixedsessions', $preview->blockers);
        $this->assertContains('migrationrecordpolicy', $preview->blockers);
        $this->assertEquals(0, $DB->get_field('attendancejourneys_sessions', 'journeyid', ['id' => $session->id]));
    }
}
