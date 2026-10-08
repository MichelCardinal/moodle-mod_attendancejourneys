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

use mod_attendancejourneys\local\journey_grades;
use mod_attendancejourneys\completion\custom_completion;

/**
 * Integration of separate final grades and all-required-journey completion.
 *
 * @covers \mod_attendancejourneys\completion\custom_completion
 * @covers \mod_attendancejourneys\local\journey_grades
 * @covers ::attendancejourneys_close_user_journey
 * @covers ::attendancejourneys_delete_instance
 * @covers ::attendancejourneys_delete_journey
 * @covers ::attendancejourneys_get_active_closure
 * @covers ::attendancejourneys_grade_item_update
 * @covers ::attendancejourneys_reopen_user_journey
 * @covers ::attendancejourneys_update_completion
 * @covers ::attendancejourneys_update_grades
 * @covers ::attendancejourneys_update_instance
 * @package    mod_attendancejourneys
 * @category   test
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\completion\custom_completion::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\journey_grades::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_close_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_delete_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_active_closure')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_grade_item_update')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_reopen_user_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_completion')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_grades')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_instance')]
final class journey_grades_test extends \advanced_testcase {
    /**
     * Closing one obligation publishes only its grade and cannot complete another obligation.
     */
    public function test_separate_publication_failure_correction_and_reopening(): void {
        global $DB;
        [$activity, $student, $teacher, $first, $second, $cm] = $this->fixture();
        $completion = new custom_completion(get_fast_modinfo($activity->course)->get_cm($cm->id), (int) $student->id);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertNull($this->raw($activity, $first, $student->id));
        $this->assertNull($this->raw($activity, $second, $student->id));
        foreach (custom_completion::get_defined_custom_rules() as $rule) {
            $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state($rule));
        }
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionpass'));

        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $first);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertEquals(70, $this->raw($activity, $first, $student->id));
        $this->assertNull($this->raw($activity, $second, $student->id));
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionpass'));

        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $second);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertEquals(70, $this->raw($activity, $second, $student->id));
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionpass'));
        $this->assertEquals(60, $this->item($activity, $first)->gradepass);
        $this->assertEquals(80, $this->item($activity, $second)->gradepass);

        attendancejourneys_reopen_user_journey($activity, $student->id, $teacher->id, $second);
        $sessionid = $DB->get_field('attendancejourneys_sessions', 'id', ['journeyid' => $second], MUST_EXIST);
        $DB->set_field('attendancejourneys_records', 'minutesabsent', 20, ['sessionid' => $sessionid]);
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $second);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertEquals(80, $this->raw($activity, $second, $student->id));
        $this->assertSame(COMPLETION_COMPLETE_PASS, $completion->get_state('completionpass'));

        attendancejourneys_reopen_user_journey($activity, $student->id, $teacher->id, $first);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertNull($this->raw($activity, $first, $student->id));
        $this->assertEquals(80, $this->raw($activity, $second, $student->id));
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionpass'));
        $this->assertSame(3, $DB->count_records('attendancejourneys_closures', ['attendancejourneysid' => $activity->id]));
    }

    /**
     * Activity settings cannot switch the grading contract or recycle grade item numbers.
     */
    public function test_activity_update_preserves_journey_grading_contract(): void {
        global $DB;
        [$activity, $student, $teacher, $first, $second] = $this->fixture();
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $first);
        attendancejourneys_update_grades($activity, $student->id);
        $item = $this->item($activity, $first);
        $counter = $DB->get_field('attendancejourneys', 'nextgradeitem', ['id' => $activity->id]);
        $data = clone $activity;
        $data->instance = $activity->id;
        $data->name = 'Renamed activity';
        $data->journeygrading = 0;
        $data->nextgradeitem = 0;
        $this->assertTrue(attendancejourneys_update_instance($data));
        $stored = $DB->get_record('attendancejourneys', ['id' => $activity->id], '*', MUST_EXIST);
        $this->assertEquals(1, $stored->journeygrading);
        $this->assertEquals($counter, $stored->nextgradeitem);
        $this->assertSame('Renamed activity', $stored->name);
        $this->assertEquals($item->id, $this->item($stored, $first)->id);
        $this->assertEquals(70, $this->raw($stored, $first, $student->id));
        $this->assertNull($this->raw($stored, $second, $student->id));
        $this->assertSame(2, $DB->count_records('grade_items', [
            'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id,
        ]));
    }

    /**
     * Historical activities require an explicit migration rather than a settings field change.
     */
    public function test_activity_update_preserves_legacy_grading_contract(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/attendancejourneys/lib.php');
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('attendancejourneys', [
            'course' => $course->id, 'journeygrading' => 0, 'gradeenabled' => 1,
        ]);
        $itemid = $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id, 'itemnumber' => 0,
        ], MUST_EXIST);
        $data = clone $activity;
        $data->instance = $activity->id;
        $data->name = 'Renamed historical activity';
        $data->journeygrading = 1;
        $data->nextgradeitem = 500;
        $this->assertTrue(attendancejourneys_update_instance($data));
        $stored = $DB->get_record('attendancejourneys', ['id' => $activity->id], '*', MUST_EXIST);
        $this->assertEquals(0, $stored->journeygrading);
        $this->assertEquals(0, $stored->nextgradeitem);
        $this->assertSame('Renamed historical activity', $stored->name);
        $item = $DB->get_record('grade_items', ['id' => $itemid], '*', MUST_EXIST);
        $this->assertSame($stored->name, $item->itemname);
        $this->assertSame(1, $DB->count_records('grade_items', [
            'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id,
        ]));
    }

    /**
     * A native grade condition cannot complete the activity after only its first journey.
     */
    public function test_native_grade_completion_keeps_global_journey_condition(): void {
        global $DB;
        [$activity, $student, $teacher, $first, $second, $cm] = $this->fixture();
        attendancejourneys_grade_item_update($activity);
        $item = $this->item($activity, $first);
        $DB->set_field('course_modules', 'completiongradeitemnumber', $item->itemnumber, ['id' => $cm->id]);
        $data = clone $activity;
        $data->instance = $activity->id;
        $data->completion = COMPLETION_TRACKING_AUTOMATIC;
        $data->completionrecorded = 0;
        $data->completionallsessions = 0;
        $data->completionclosed = 0;
        $data->completionpass = 0;
        attendancejourneys_update_instance($data);
        $this->assertEquals(1, $DB->get_field('attendancejourneys', 'completionpass', ['id' => $activity->id]));
        rebuild_course_cache($activity->course, true);
        $cm = get_fast_modinfo($activity->course)->get_cm($cm->id);
        $course = get_course($activity->course);
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $first);
        attendancejourneys_update_grades($activity, $student->id);
        attendancejourneys_update_completion($course, $cm, [$student->id]);
        $completion = new \completion_info($course);
        $this->assertEquals(70, $this->raw($activity, $first, $student->id));
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
        $this->assertNull($this->raw($activity, $second, $student->id));
    }

    /**
     * Invalid percentage settings are rejected before a saved activity is changed.
     */
    public function test_automatic_journey_completion_rejects_disabled_percentages(): void {
        global $DB;
        [$activity] = $this->fixture();
        $data = clone $activity;
        $data->instance = $activity->id;
        $data->percentageenabled = 0;
        $data->gradeenabled = 0;
        $data->completion = COMPLETION_TRACKING_AUTOMATIC;
        try {
            attendancejourneys_update_instance($data);
            $this->fail('Automatic success cannot be configured without percentage calculations.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('journeyrequirespercentage', $exception->errorcode);
        }
        $this->assertEquals(1, $DB->get_field('attendancejourneys', 'percentageenabled', ['id' => $activity->id]));
        $this->assertEquals(1, $DB->get_field('attendancejourneys', 'gradeenabled', ['id' => $activity->id]));
    }

    /**
     * Correcting a justified absence into an exemption requires reopening and preserves the first result.
     */
    public function test_exemption_changes_required_time_without_rewriting_closed_history(): void {
        global $DB;
        [$activity, $student, $teacher, $first] = $this->fixture();
        $sessionid = $DB->get_field('attendancejourneys_sessions', 'id', ['journeyid' => $first], MUST_EXIST);
        $DB->set_field('attendancejourneys_records', 'status', 'excused', ['sessionid' => $sessionid]);
        $extraid = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $first, 'name' => 'Attended session',
            'sessiondate' => time() - DAYSECS, 'duration' => 100,
        ]);
        $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $extraid, 'userid' => $student->id, 'status' => 'present', 'approved' => 1,
        ]);
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $first);
        attendancejourneys_update_grades($activity, $student->id);
        $firstclosure = attendancejourneys_get_active_closure((int) $activity->id, (int) $student->id, $first);
        $this->assertSame('failed', $firstclosure->result);
        $this->assertEquals(50, $this->raw($activity, $first, $student->id));
        attendancejourneys_reopen_user_journey($activity, $student->id, $teacher->id, $first);
        $DB->set_field('attendancejourneys_records', 'status', 'exempt', ['sessionid' => $sessionid]);
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $first);
        attendancejourneys_update_grades($activity, $student->id);
        $closure = attendancejourneys_get_active_closure((int) $activity->id, (int) $student->id, $first);
        $this->assertSame('passed', $closure->result);
        $this->assertEquals(100, $closure->presentminutes);
        $this->assertEquals(100, $closure->possibleminutes);
        $this->assertEquals(100, $this->raw($activity, $first, $student->id));
        $this->assertSame('failed', $DB->get_field('attendancejourneys_closures', 'result', ['id' => $firstclosure->id]));
        $this->assertEquals(200, $DB->get_field('attendancejourneys_closures', 'possibleminutes', ['id' => $firstclosure->id]));
    }

    /**
     * Optional journeys do not block, but an empty set of obligations is not an automatic pass.
     */
    public function test_optional_and_empty_obligations(): void {
        global $DB;
        [$activity, $student, $teacher, $first, $second] = $this->fixture();
        $DB->set_field('attendancejourneys_journeys', 'required', 0, ['id' => $second]);
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $first);
        $this->assertTrue(journey_grades::all_required_passed($activity, $student->id));
        $DB->set_field('attendancejourneys_journeys', 'required', 0, ['id' => $first]);
        $this->assertFalse(journey_grades::all_required_passed($activity, $student->id));
        $this->assertFalse(journey_grades::all_required_passed($activity, $teacher->id));
    }

    /**
     * Item identity survives a rename and a deleted item's number is never recycled.
     */
    public function test_stable_item_numbers_and_unused_item_deletion(): void {
        global $DB;
        [$activity, $student, $teacher, $first, $second] = $this->fixture();
        attendancejourneys_grade_item_update($activity);
        $firstitem = $this->item($activity, $first);
        $seconditem = $this->item($activity, $second);
        $DB->set_field('attendancejourneys_journeys', 'name', 'Renamed theory', ['id' => $first]);
        attendancejourneys_grade_item_update($activity);
        $this->assertEquals($firstitem->id, $this->item($activity, $first)->id);
        $this->assertSame($activity->name . ' — Renamed theory', $this->item($activity, $first)->itemname);
        $third = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Unused journey',
        ]);
        attendancejourneys_grade_item_update($activity);
        $thirditem = $this->item($activity, $third);
        attendancejourneys_delete_journey($activity->id, $third);
        $this->assertFalse($DB->record_exists('grade_items', ['id' => $thirditem->id]));
        $fourth = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'New journey',
        ]);
        attendancejourneys_grade_item_update($activity);
        $this->assertGreaterThan((int) $thirditem->itemnumber, (int) $this->item($activity, $fourth)->itemnumber);
        $this->assertEquals($seconditem->id, $this->item($activity, $second)->id);
    }

    /**
     * Moodle item locks and manually overridden grades remain authoritative in its gradebook.
     */
    public function test_locked_and_overridden_grades_are_preserved(): void {
        global $DB;
        [$activity, $student, $teacher, $first, $second] = $this->fixture();
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $first);
        attendancejourneys_update_grades($activity, $student->id);
        $item = $this->item($activity, $first);
        $this->assertSame([], journey_grades::protections($activity, $first, [$student->id]));
        $item->locked = time();
        $item->update();
        $this->assertSame(
            [$student->id => 'journeygradelocked'],
            journey_grades::protections($activity, $first, [$student->id])
        );
        $this->assertSame([], journey_grades::protections($activity, $second, [$student->id]));
        attendancejourneys_reopen_user_journey($activity, $student->id, $teacher->id, $first);
        $statuses = journey_grades::sync($activity, $student->id);
        $this->assertSame(GRADE_UPDATE_ITEM_LOCKED, $statuses[$first]);
        $this->assertEquals(70, $this->raw($activity, $first, $student->id));
        $this->assertFalse(journey_grades::all_required_passed($activity, $student->id));
        $withoutpercentage = clone $activity;
        $withoutpercentage->percentageenabled = 0;
        $this->assertSame([], journey_grades::protections($withoutpercentage, $first, [$student->id]));
        $item->locked = 0;
        $item->update();
        $item->update_final_grade($student->id, 95, 'manual');
        journey_grades::sync($activity, $student->id);
        $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $student->id], '*', MUST_EXIST);
        $this->assertEquals(95, $grade->finalgrade);
        $this->assertNotEmpty($grade->overridden);
        $this->assertSame(
            [$student->id => 'journeygradeoverridden'],
            journey_grades::protections($activity, $first, [$student->id])
        );
        $this->assertSame([], journey_grades::protections($activity, $first, []));
        $other = $this->getDataGenerator()->create_user();
        $this->assertSame([], journey_grades::protections($activity, $first, [$other->id]));
        $DB->set_field('grade_grades', 'locked', time(), ['id' => $grade->id]);
        $this->assertSame(
            [$student->id => 'journeygradelocked'],
            journey_grades::protections($activity, $first, [$student->id])
        );
        $this->expectException(\dml_missing_record_exception::class);
        journey_grades::protections($activity, -1, [$student->id]);
    }

    /**
     * Removing an activity removes every grade item, including subsequent journey items.
     */
    public function test_activity_deletion_removes_all_journey_items(): void {
        global $DB;
        [$activity] = $this->fixture();
        attendancejourneys_grade_item_update($activity);
        $conditions = ['itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id];
        $this->assertSame(2, $DB->count_records('grade_items', $conditions));
        $this->assertTrue(attendancejourneys_delete_instance($activity->id));
        $this->assertSame(0, $DB->count_records('grade_items', $conditions));
    }

    /**
     * Once results are final, changing an inherited activity threshold does not relabel their grades.
     */
    public function test_first_closure_freezes_inherited_threshold(): void {
        global $DB;
        [$activity, $student, $teacher, $first] = $this->fixture();
        $DB->set_field('attendancejourneys_journeys', 'passinggrade', null, ['id' => $first]);
        $activity->passinggrade = 60;
        $DB->set_field('attendancejourneys', 'passinggrade', 60, ['id' => $activity->id]);
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $first);
        $DB->set_field('attendancejourneys', 'passinggrade', 90, ['id' => $activity->id]);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertEquals(60, $DB->get_field('attendancejourneys_journeys', 'passinggrade', ['id' => $first]));
        $this->assertEquals(60, $this->item($activity, $first)->gradepass);
        $this->assertEquals(70, $this->raw($activity, $first, $student->id));
    }

    /**
     * A failed exact ratio must not round up to a passing Moodle grade.
     */
    public function test_gradebook_rounding_cannot_reverse_a_failed_decision(): void {
        global $DB;
        [$activity, $student, $teacher, $first] = $this->fixture();
        $DB->set_field('attendancejourneys_journeys', 'passinggrade', 80, ['id' => $first]);
        $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $first, 'userid' => $student->id,
            'percentage' => 80, 'threshold' => 80, 'result' => 'failed',
            'presentminutes' => 79999999, 'possibleminutes' => 100000000, 'timeclosed' => time(),
        ]);
        attendancejourneys_update_grades($activity, $student->id);
        $this->assertLessThan(80, $this->raw($activity, $first, $student->id));
        $this->assertFalse(journey_grades::all_required_passed($activity, $student->id));
    }

    /**
     * Moodle persists completion only after every obligation passes, and clears it after reopening.
     */
    public function test_moodle_persists_and_withdraws_global_completion(): void {
        global $DB;
        [$activity, $student, $teacher, $first, $second, $cm] = $this->fixture();
        $course = get_course($activity->course);
        $completion = new \completion_info($course);
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $first);
        attendancejourneys_update_completion($course, $cm, [$student->id]);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
        $sessionid = $DB->get_field('attendancejourneys_sessions', 'id', ['journeyid' => $second], MUST_EXIST);
        $DB->set_field('attendancejourneys_records', 'minutesabsent', 20, ['sessionid' => $sessionid]);
        attendancejourneys_close_user_journey($activity, $student->id, $teacher->id, $second);
        attendancejourneys_update_completion($course, $cm, [$student->id]);
        // Moodle consolidates successful custom rules into its normal completed state.
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
        attendancejourneys_reopen_user_journey($activity, $student->id, $teacher->id, $first);
        attendancejourneys_update_completion($course, $cm, [$student->id]);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
    }

    /**
     * Build two obligations with equal 70 percent attendance and different thresholds.
     *
     * @return array Activity, student, teacher, two journey identifiers and course module.
     */
    private function fixture(): array {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        require_once($CFG->dirroot . '/mod/attendancejourneys/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $activity = $generator->create_module('attendancejourneys', [
            'course' => $course->id, 'journeygrading' => 1, 'percentageenabled' => 1, 'gradeenabled' => 1,
            'passinggrade' => 80, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 1,
            'completionrecorded' => 1, 'completionallsessions' => 1, 'completionclosed' => 1,
        ]);
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $journeys = [];
        foreach ([60, 80] as $threshold) {
            $journeydata = (object) [
                'attendancejourneysid' => $activity->id, 'name' => 'Journey ' . $threshold, 'passinggrade' => $threshold,
                'audiencemode' => 2,
            ];
            if (!$journeys) {
                $journeydata->id = $DB->get_field(
                    'attendancejourneys_journeys',
                    'id',
                    ['attendancejourneysid' => $activity->id],
                    MUST_EXIST
                );
                $DB->update_record('attendancejourneys_journeys', $journeydata);
                $journeyid = $journeydata->id;
            } else {
                $journeyid = $DB->insert_record('attendancejourneys_journeys', $journeydata);
            }
            $journeys[] = $journeyid;
            $DB->insert_record('attendancejourneys_members', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'userid' => $student->id,
                'attemptnumber' => count($journeys), 'timeassigned' => time(),
            ]);
            $sessionid = $DB->insert_record('attendancejourneys_sessions', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'name' => 'Past session',
                'sessiondate' => time() - DAYSECS, 'duration' => 100,
            ]);
            $DB->insert_record('attendancejourneys_records', (object) [
                'sessionid' => $sessionid, 'userid' => $student->id, 'status' => 'partial', 'minutesabsent' => 30,
                'takenby' => $teacher->id, 'approved' => 1, 'approvedby' => $teacher->id, 'timeapproved' => time(),
            ]);
        }
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
        return [$activity, $student, $teacher, $journeys[0], $journeys[1], $cm];
    }

    /**
     * Fetch the item's stable gradebook record.
     *
     * @param \stdClass $activity Activity configuration.
     * @param int $journeyid Journey identifier.
     * @return \grade_item
     */
    private function item(\stdClass $activity, int $journeyid): \grade_item {
        global $DB;
        $number = $DB->get_field('attendancejourneys_journeys', 'gradeitemnumber', ['id' => $journeyid], MUST_EXIST);
        return \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'attendancejourneys',
            'iteminstance' => $activity->id, 'itemnumber' => $number]);
    }

    /**
     * Read a raw grade independently from the calculation code.
     *
     * @param \stdClass $activity Activity configuration.
     * @param int $journeyid Journey identifier.
     * @param int $userid Participant identifier.
     * @return float|null
     */
    private function raw(\stdClass $activity, int $journeyid, int $userid): ?float {
        global $DB;
        $item = $this->item($activity, $journeyid);
        $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $userid]);
        return $grade && $grade->rawgrade !== null ? (float) $grade->rawgrade : null;
    }
}
