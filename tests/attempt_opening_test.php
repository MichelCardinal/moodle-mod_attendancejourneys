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

use mod_attendancejourneys\local\attempt_opening;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\event\attempt_opened::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\attempt_opening::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_close_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_completion')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_grades')]
/**
 * Personal retakes require an authorised, current preview and preserve previous attendance.
 *
 * @covers \mod_attendancejourneys\event\attempt_opened
 * @covers \mod_attendancejourneys\local\attempt_opening
 * @covers ::attendancejourneys_close_user_journey
 * @covers ::attendancejourneys_update_completion
 * @covers ::attendancejourneys_update_grades
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_opening_test extends \advanced_testcase {
    /**
     * A passed obligation ready for an explicit retake.
     *
     * @return array Activity, module, root, participant and teacher.
     */
    private function fixture(): array {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $g = $this->getDataGenerator();
        $course = $g->create_course(['enablecompletion' => 1]);
        $activity = $g->create_module('attendancejourneys', ['course' => $course->id, 'journeygrading' => 1,
            'gradeenabled' => 1, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 1]);
        $student = $g->create_and_enrol($course, 'student');
        $teacher = $g->create_and_enrol($course, 'editingteacher');
        $root = $DB->get_record('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id], '*', MUST_EXIST);
        $session = $DB->insert_record('attendancejourneys_sessions', (object) ['attendancejourneysid' => $activity->id,
            'journeyid' => $root->id, 'name' => 'Original attendance', 'duration' => 100,
            'sessiondate' => time() - DAYSECS]);
        $DB->insert_record('attendancejourneys_records', (object) ['sessionid' => $session, 'userid' => $student->id,
            'status' => 'present', 'approved' => 1, 'takenby' => $teacher->id]);
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $root->id);
        attendancejourneys_update_grades($activity, $student->id);
        $cm = get_coursemodule_from_id('attendancejourneys', $activity->cmid, 0, false, MUST_EXIST);
        attendancejourneys_update_completion($course, $cm, [$student->id]);
        $this->setUser($teacher);
        return [$activity, $cm, $root, $student, $teacher];
    }

    /**
     * Preview is read-only and confirmation creates no invented sessions or attendance.
     */
    public function test_preview_confirmation_and_replay_preserve_history(): void {
        global $DB;
        [$activity, $cm, $root, $student, $teacher] = $this->fixture();
        $records = $DB->get_records('attendancejourneys_records', [], 'id');
        $closures = $DB->get_records('attendancejourneys_closures', [], 'id');
        $sink = $this->redirectEvents();
        $preview = attempt_opening::preview($cm, $root->id, $student->id, 'Retake', 'Approved retake');
        $this->assertSame(0, $DB->count_records('attendancejourneys_attempts'));
        $this->assertSame(1, $DB->count_records('attendancejourneys_journeys'));
        $this->assertSame(2, $preview->attemptnumber);
        $opening = attempt_opening::confirm($cm, $root->id, $student->id, 'Retake', 'Approved retake', $preview->token);
        $events = array_values(array_filter($sink->get_events(), static fn($event) =>
            $event instanceof \mod_attendancejourneys\event\attempt_opened));
        $this->assertCount(1, $events);
        $this->assertEquals($opening->id, $events[0]->objectid);
        $this->assertEquals($student->id, $events[0]->relateduserid);
        $this->assertEquals($teacher->id, $events[0]->userid);
        $this->assertEquals($teacher->id, $opening->actorid);
        $this->assertEquals($root->id, $opening->rootjourneyid);
        $this->assertSame(0, $DB->count_records('attendancejourneys_sessions', ['journeyid' => $opening->journeyid]));
        $this->assertEquals($records, $DB->get_records('attendancejourneys_records', [], 'id'));
        $this->assertEquals($closures, $DB->get_records('attendancejourneys_closures', [], 'id'));
        $this->assertSame(1, $DB->count_records('grade_items', ['itemmodule' => 'attendancejourneys',
            'iteminstance' => $activity->id]));
        $itemid = $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id,
        ], MUST_EXIST);
        $this->assertNull($DB->get_field('grade_grades', 'rawgrade', ['itemid' => $itemid, 'userid' => $student->id]));
        $this->assertEquals(COMPLETION_INCOMPLETE, $DB->get_field(
            'course_modules_completion',
            'completionstate',
            ['coursemoduleid' => $cm->id, 'userid' => $student->id]
        ));
        try {
            attempt_opening::confirm($cm, $root->id, $student->id, 'Retake', 'Approved retake', $preview->token);
            $this->fail('Replaying the confirmation must not create another attempt.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('attemptpreviousopen', $exception->errorcode);
        }
        $this->assertSame(1, $DB->count_records('attendancejourneys_attempts'));
    }

    /**
     * A changed proposal cannot reuse a preview for a different pedagogical decision.
     */
    public function test_changed_reason_invalidates_confirmation(): void {
        global $DB;
        [, $cm, $root, $student] = $this->fixture();
        $preview = attempt_opening::preview($cm, $root->id, $student->id, 'Retake', 'Approved retake');
        try {
            attempt_opening::confirm($cm, $root->id, $student->id, 'Retake', 'Different decision', $preview->token);
            $this->fail('Changed proposal must be previewed again.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('attemptstale', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_attempts'));
    }

    /**
     * A manual grade override applied after preview prevents opening and preserves the grade.
     */
    public function test_grade_protection_is_rechecked_at_confirmation(): void {
        global $DB;
        [$activity, $cm, $root, $student] = $this->fixture();
        $preview = attempt_opening::preview($cm, $root->id, $student->id, 'Retake', 'Approved retake');
        $itemid = $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id,
        ], MUST_EXIST);
        $DB->set_field('grade_grades', 'overridden', 1, ['itemid' => $itemid, 'userid' => $student->id]);
        $before = $DB->get_record('grade_grades', ['itemid' => $itemid, 'userid' => $student->id]);
        try {
            attempt_opening::confirm($cm, $root->id, $student->id, 'Retake', 'Approved retake', $preview->token);
            $this->fail('A protected grade must not be withdrawn by opening a retake.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('journeygradeoverridden', $exception->errorcode);
        }
        $this->assertEquals($before, $DB->get_record('grade_grades', ['itemid' => $itemid, 'userid' => $student->id]));
        $this->assertSame(0, $DB->count_records('attendancejourneys_attempts'));
        $this->assertSame(1, $DB->count_records('attendancejourneys_journeys'));
    }

    /**
     * A student cannot confirm a teacher preview.
     */
    public function test_student_cannot_confirm_teacher_preview(): void {
        global $DB;
        [, $cm, $root, $student] = $this->fixture();
        $preview = attempt_opening::preview($cm, $root->id, $student->id, 'Retake', 'Approved retake');
        $this->setUser($student);
        try {
            attempt_opening::confirm($cm, $root->id, $student->id, 'Retake', 'Approved retake', $preview->token);
            $this->fail('Teacher permissions must be checked at confirmation.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_attempts'));
    }
}
