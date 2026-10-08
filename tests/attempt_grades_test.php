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

use mod_attendancejourneys\local\attempt_family;
use mod_attendancejourneys\local\journey_grades;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\attempt_family::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\journey_grades::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_close_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_passinggrade')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_reopen_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_completion')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_grades')]
/**
 * A retake replaces one logical grade and completion outcome without rewriting history.
 *
 * @covers \mod_attendancejourneys\local\attempt_family
 * @covers \mod_attendancejourneys\local\journey_grades
 * @covers ::attendancejourneys_close_user_journey
 * @covers ::attendancejourneys_get_passinggrade
 * @covers ::attendancejourneys_reopen_user_journey
 * @covers ::attendancejourneys_update_completion
 * @covers ::attendancejourneys_update_grades
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_grades_test extends \advanced_testcase {
    /**
     * Two learners have passed the root; the first has an explicitly opened retake.
     *
     * @return array Activity, root, retake, learners, actor, course, module and retake session.
     */
    private function fixture(): array {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $g = $this->getDataGenerator();
        $course = $g->create_course(['enablecompletion' => 1]);
        $activity = $g->create_module('attendancejourneys', ['course' => $course->id, 'journeygrading' => 1,
            'gradeenabled' => 1, 'passinggrade' => 80, 'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 1]);
        $student = $g->create_and_enrol($course, 'student');
        $other = $g->create_and_enrol($course, 'student');
        $actor = $g->create_and_enrol($course, 'editingteacher');
        $root = $DB->get_record('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id], '*', MUST_EXIST);
        $session = $DB->insert_record('attendancejourneys_sessions', (object) ['attendancejourneysid' => $activity->id,
            'journeyid' => $root->id, 'name' => 'Original session', 'duration' => 100,
            'sessiondate' => time() - 2 * DAYSECS]);
        foreach ([$student, $other] as $user) {
            $DB->insert_record('attendancejourneys_records', (object) ['sessionid' => $session, 'userid' => $user->id,
                'status' => 'present', 'approved' => 1, 'takenby' => $actor->id]);
            attendancejourneys_close_user_journey($activity, $user->id, $actor->id, $root->id);
        }
        attendancejourneys_update_grades($activity);
        $cm = get_coursemodule_from_id('attendancejourneys', $activity->cmid, 0, false, MUST_EXIST);
        attendancejourneys_update_completion($course, $cm, [$student->id, $other->id]);
        $retake = clone $root;
        unset($retake->id);
        $retake->name = 'Retake sessions';
        $retake->rootjourneyid = $root->id;
        $retake->audiencemode = 2;
        $retake->gradeitemnumber = null;
        $retake->passinggrade = 60;
        $retake->id = $DB->insert_record('attendancejourneys_journeys', $retake);
        $DB->insert_record('attendancejourneys_members', (object) ['attendancejourneysid' => $activity->id,
            'journeyid' => $retake->id, 'userid' => $student->id, 'active' => 1, 'attemptnumber' => 2]);
        $DB->insert_record('attendancejourneys_attempts', (object) ['attendancejourneysid' => $activity->id,
            'rootjourneyid' => $root->id, 'journeyid' => $retake->id, 'userid' => $student->id,
            'attemptnumber' => 2, 'actorid' => $actor->id, 'reason' => 'Approved retake', 'timecreated' => time()]);
        $session = $DB->insert_record('attendancejourneys_sessions', (object) ['attendancejourneysid' => $activity->id,
            'journeyid' => $retake->id, 'name' => 'Retake session', 'duration' => 100,
            'sessiondate' => time() - DAYSECS]);
        $DB->insert_record('attendancejourneys_records', (object) ['sessionid' => $session, 'userid' => $student->id,
            'status' => 'partial', 'minutesabsent' => 40, 'approved' => 1, 'takenby' => $actor->id]);
        return [$activity, $root, $retake, $student, $other, $actor, $course, $cm, $session];
    }

    /**
     * Open, failed, reopened and passed retakes share one stable grade cell and retain root snapshots.
     */
    public function test_replacement_and_completion_preserve_history_and_other_participant(): void {
        global $DB;
        [$activity, $root, $retake, $student, $other, $actor, $course, $cm, $session] = $this->fixture();
        $history = $DB->get_records('attendancejourneys_closures', ['journeyid' => $root->id], 'id');
        $records = $DB->get_records('attendancejourneys_records', [], 'id');
        $this->assertEquals(100, $this->raw($activity, $root->id, $student->id));
        attendancejourneys_update_grades($activity);
        attendancejourneys_update_completion($course, $cm, [$student->id, $other->id]);
        $this->assertNull($this->raw($activity, $root->id, $student->id));
        $this->assertEquals(100, $this->raw($activity, $root->id, $other->id));
        $this->assertEquals(COMPLETION_INCOMPLETE, $DB->get_field(
            'course_modules_completion',
            'completionstate',
            ['coursemoduleid' => $cm->id, 'userid' => $student->id]
        ));
        $this->assertEquals(COMPLETION_COMPLETE, $DB->get_field(
            'course_modules_completion',
            'completionstate',
            ['coursemoduleid' => $cm->id, 'userid' => $other->id]
        ));
        $closure = attendancejourneys_close_user_journey($activity, $student->id, $actor->id, $retake->id);
        $this->assertEquals(80, $closure->threshold);
        $this->assertSame('failed', $closure->result);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertEquals(60, $this->raw($activity, $root->id, $student->id));
        $this->assertFalse(journey_grades::all_required_passed($activity, $student->id));
        $this->assertTrue(journey_grades::all_required_passed($activity, $other->id));
        $this->assertSame(1, $DB->count_records('grade_items', ['itemmodule' => 'attendancejourneys',
            'iteminstance' => $activity->id]));
        $this->assertNull($DB->get_field('attendancejourneys_journeys', 'gradeitemnumber', ['id' => $retake->id]));
        $this->assertEquals($history, $DB->get_records('attendancejourneys_closures', ['journeyid' => $root->id], 'id'));
        $this->assertEquals($records, $DB->get_records('attendancejourneys_records', [], 'id'));
        attendancejourneys_reopen_user_journey($activity, $student->id, $actor->id, $retake->id);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertNull($this->raw($activity, $root->id, $student->id));
        $DB->set_field('attendancejourneys_records', 'minutesabsent', 20, ['sessionid' => $session]);
        attendancejourneys_close_user_journey($activity, $student->id, $actor->id, $retake->id);
        attendancejourneys_update_grades($activity, $student->id);
        attendancejourneys_update_completion($course, $cm, [$student->id]);
        $this->assertEquals(80, $this->raw($activity, $root->id, $student->id));
        $this->assertTrue(journey_grades::all_required_passed($activity, $student->id));
        $this->assertEquals(COMPLETION_COMPLETE, $DB->get_field(
            'course_modules_completion',
            'completionstate',
            ['coursemoduleid' => $cm->id, 'userid' => $student->id]
        ));
    }

    /**
     * An independent laboratory keeps its own threshold and grade while theory is retaken.
     */
    public function test_retaken_theory_and_independent_laboratory_remain_separate_obligations(): void {
        global $DB;
        [$activity, $root, $retake, $student, , $actor, , , $session] = $this->fixture();
        $laboratory = clone $root;
        unset($laboratory->id);
        $laboratory->rootjourneyid = 0;
        $laboratory->name = 'Independent laboratory';
        $laboratory->gradeitemnumber = null;
        $laboratory->passinggrade = 60;
        $laboratory->audiencemode = 2;
        $laboratory->id = $DB->insert_record('attendancejourneys_journeys', $laboratory);
        $DB->insert_record('attendancejourneys_members', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $laboratory->id, 'userid' => $student->id,
            'active' => 1, 'attemptnumber' => 3,
        ]);
        $labsession = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $laboratory->id, 'name' => 'Laboratory session',
            'duration' => 100, 'sessiondate' => time() - DAYSECS,
        ]);
        $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $labsession, 'userid' => $student->id, 'status' => 'partial',
            'minutesabsent' => 40, 'approved' => 1, 'takenby' => $actor->id,
        ]);
        $labclosure = attendancejourneys_close_user_journey($activity, $student->id, $actor->id, $laboratory->id);
        $theoryclosure = attendancejourneys_close_user_journey($activity, $student->id, $actor->id, $retake->id);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertSame('passed', $labclosure->result);
        $this->assertSame('failed', $theoryclosure->result);
        $this->assertEquals(60, $labclosure->threshold);
        $this->assertEquals(80, $theoryclosure->threshold);
        $this->assertEquals(60, $this->raw($activity, $root->id, $student->id));
        $this->assertEquals(60, $this->raw($activity, $laboratory->id, $student->id));
        $this->assertSame(2, $DB->count_records('grade_items', [
            'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id,
        ]));
        $this->assertFalse(journey_grades::all_required_passed($activity, $student->id));
        attendancejourneys_reopen_user_journey($activity, $student->id, $actor->id, $retake->id);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertNull($this->raw($activity, $root->id, $student->id));
        $this->assertEquals(60, $this->raw($activity, $laboratory->id, $student->id));
        $DB->set_field('attendancejourneys_records', 'minutesabsent', 20, ['sessionid' => $session]);
        attendancejourneys_close_user_journey($activity, $student->id, $actor->id, $retake->id);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertEquals(80, $this->raw($activity, $root->id, $student->id));
        $this->assertEquals(60, $this->raw($activity, $laboratory->id, $student->id));
        $this->assertTrue(journey_grades::all_required_passed($activity, $student->id));
    }

    /**
     * Correcting an older physical result later cannot replace a newer attempt's failed result.
     */
    public function test_late_correction_of_original_result_does_not_replace_current_attempt(): void {
        global $DB;
        [$activity, $root, $retake, $student, $other, $actor] = $this->fixture();
        attendancejourneys_close_user_journey($activity, $student->id, $actor->id, $retake->id);
        attendancejourneys_reopen_user_journey($activity, $student->id, $actor->id, $root->id);
        $source = $DB->get_field('attendancejourneys_sessions', 'id', ['journeyid' => $root->id], MUST_EXIST);
        $record = $DB->get_record('attendancejourneys_records', ['sessionid' => $source, 'userid' => $student->id]);
        $record->status = 'partial';
        $record->minutesabsent = 10;
        $DB->update_record('attendancejourneys_records', $record);
        $corrected = attendancejourneys_close_user_journey($activity, $student->id, $actor->id, $root->id);
        $this->assertEquals(90, $corrected->percentage);
        attendancejourneys_update_grades($activity);
        $this->assertEquals(60, $this->raw($activity, $root->id, $student->id));
        $this->assertEquals(100, $this->raw($activity, $root->id, $other->id));
        $this->assertFalse(journey_grades::all_required_passed($activity, $student->id));
        $this->assertSame(3, $DB->count_records('attendancejourneys_closures', ['journeyid' => $root->id]));
    }

    /**
     * Retake actions resolve the root cell's native locks and manual grade overrides.
     */
    public function test_root_protections_apply_to_the_retake(): void {
        global $DB;
        [$activity, $root, $retake, $student] = $this->fixture();
        $number = $DB->get_field('attendancejourneys_journeys', 'gradeitemnumber', ['id' => $root->id]);
        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'attendancejourneys',
            'iteminstance' => $activity->id, 'itemnumber' => $number]);
        $DB->set_field('grade_grades', 'overridden', 1, ['itemid' => $item->id, 'userid' => $student->id]);
        $protections = journey_grades::protections($activity, $retake->id, [$student->id]);
        $this->assertSame('journeygradeoverridden', $protections[$student->id]);
        $DB->set_field('grade_items', 'locked', time(), ['id' => $item->id]);
        $protections = journey_grades::protections($activity, $retake->id, [$student->id]);
        $this->assertSame('journeygradelocked', $protections[$student->id]);
        $this->assertEquals(80, attendancejourneys_get_passinggrade($activity, $retake->id));
    }

    /**
     * Invalid root chains cannot publish into a different logical obligation.
     */
    public function test_root_chains_are_rejected(): void {
        global $DB;
        [$activity, $root, $retake] = $this->fixture();
        $DB->set_field('attendancejourneys_journeys', 'rootjourneyid', $retake->id, ['id' => $root->id]);
        $this->expectException(\coding_exception::class);
        attempt_family::root($activity, $retake->id);
    }

    /**
     * Read the real gradebook cell.
     *
     * @param \stdClass $activity Activity configuration.
     * @param int $journeyid Logical obligation.
     * @param int $userid Participant identifier.
     * @return float|null Raw grade.
     */
    private function raw(\stdClass $activity, int $journeyid, int $userid): ?float {
        global $DB;
        $number = $DB->get_field('attendancejourneys_journeys', 'gradeitemnumber', ['id' => $journeyid]);
        $item = $DB->get_record('grade_items', ['itemmodule' => 'attendancejourneys',
            'iteminstance' => $activity->id, 'itemnumber' => $number], '*', MUST_EXIST);
        $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $userid]);
        return $grade && $grade->rawgrade !== null ? (float) $grade->rawgrade : null;
    }
}
