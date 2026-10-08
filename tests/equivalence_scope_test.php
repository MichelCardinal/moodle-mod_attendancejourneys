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

/**
 * Approved equivalences cannot reuse a source across independently calculated journeys.
 *
 * @covers \mod_attendancejourneys\local\calculator
 * @package    mod_attendancejourneys
 * @category   test
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\calculator::class)]
final class equivalence_scope_test extends \advanced_testcase {
    /**
     * Create conflicting imported approvals on synthetic independent journeys.
     *
     * @return array Activity, student, journeys, sessions and approvals.
     */
    private function fixture(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once(__DIR__ . '/../locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id, 'journeygrading' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $main = $DB->get_record('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id], '*', MUST_EXIST);
        $journeys = [$main->id];
        foreach (['First target', 'Second target'] as $name) {
            $journeys[] = $DB->insert_record('attendancejourneys_journeys', (object) [
                'attendancejourneysid' => $activity->id, 'name' => $name, 'audiencemode' => 1, 'active' => 1,
            ]);
        }
        $sessions = [];
        foreach ([100, 200, 60] as $index => $duration) {
            $sessions[] = $DB->insert_record('attendancejourneys_sessions', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journeys[$index],
                'name' => 'Equivalence session ' . $index, 'duration' => $duration, 'sessiondate' => time() - DAYSECS,
            ]);
        }
        $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $sessions[0], 'userid' => $student->id, 'status' => 'partial',
            'minutesabsent' => 40, 'approved' => 1, 'takenby' => 2,
        ]);
        $approvals = [];
        foreach ([1, 2] as $index) {
            $approvals[] = $DB->insert_record('attendancejourneys_equivalences', (object) [
                'attendancejourneysid' => $activity->id, 'userid' => $student->id,
                'targetjourneyid' => $journeys[$index], 'targetsessionid' => $sessions[$index],
                'sourcesessionid' => $sessions[0], 'status' => 'approved', 'decidedby' => 2,
                'timedecided' => 1000, 'timemodified' => 1000,
            ]);
        }
        return [$activity, $student, $journeys, $sessions, $approvals];
    }

    /**
     * The first approval reserves its source globally, independently of the selected report scope.
     */
    public function test_duplicate_source_is_not_credited_in_another_journey(): void {
        global $DB;
        [$activity, $student, $journeys, $sessions, $approvals] = $this->fixture();
        $before = $DB->get_records('attendancejourneys_equivalences');
        $first = calculator::calculate($activity, $student->id, $journeys[1]);
        $second = calculator::calculate($activity, $student->id, $journeys[2]);
        $this->assertEquals(60, $first->presentminutes);
        $this->assertEquals(200, $first->possibleminutes);
        $this->assertEquals(0, $second->presentminutes);
        $this->assertEquals(0, $second->recordedsessions);
        $this->assertEquals($before, $DB->get_records('attendancejourneys_equivalences'));
        $DB->set_field('attendancejourneys_equivalences', 'status', 'revoked', ['id' => $approvals[0]]);
        $second = calculator::calculate($activity, $student->id, $journeys[2]);
        $this->assertEquals(60, $second->presentminutes);
        $this->assertEquals(100, $second->percent);
    }

    /**
     * Waived historical evidence keeps its minute cap and cannot credit two destinations.
     */
    public function test_waived_source_remains_capped_and_single_use(): void {
        global $DB;
        [$activity, $student, $journeys, , $approvals] = $this->fixture();
        $DB->insert_record('attendancejourneys_obligations', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeys[0], 'userid' => $student->id,
            'waived' => 1, 'reason' => 'Explicit transfer decision', 'revision' => 1,
        ]);
        $first = calculator::calculate($activity, $student->id, $journeys[1]);
        $second = calculator::calculate($activity, $student->id, $journeys[2]);
        $this->assertEquals(60, $first->presentminutes);
        $this->assertEquals(30, $first->percent);
        $this->assertSame(0, $second->recordedsessions);
        $DB->set_field('attendancejourneys_equivalences', 'status', 'revoked', ['id' => $approvals[0]]);
        $second = calculator::calculate($activity, $student->id, $journeys[2]);
        $this->assertEquals(60, $second->presentminutes);
        $this->assertEquals(100, $second->percent);
        $this->assertSame(1, $DB->count_records('attendancejourneys_records'));
    }

    /**
     * A later physical target record does not release a source without an explicit revocation.
     */
    public function test_real_target_record_does_not_silently_release_the_reserved_source(): void {
        global $DB;
        [$activity, $student, $journeys, $sessions] = $this->fixture();
        $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $sessions[1], 'userid' => $student->id, 'status' => 'present',
            'approved' => 1, 'takenby' => 2,
        ]);
        $first = calculator::calculate($activity, $student->id, $journeys[1]);
        $second = calculator::calculate($activity, $student->id, $journeys[2]);
        $this->assertEquals(200, $first->presentminutes);
        $this->assertEquals(0, $second->presentminutes);
    }

    /**
     * Inconsistent target references and cross-activity sources are never credited.
     */
    public function test_invalid_approvals_do_not_allocate_attendance_minutes(): void {
        global $DB;
        [$activity, $student, $journeys, $sessions, $approvals] = $this->fixture();
        $DB->set_field('attendancejourneys_equivalences', 'targetjourneyid', $journeys[2], ['id' => $approvals[0]]);
        $first = calculator::calculate($activity, $student->id, $journeys[1]);
        $second = calculator::calculate($activity, $student->id, $journeys[2]);
        $this->assertEquals(0, $first->presentminutes);
        $this->assertEquals(60, $second->presentminutes);
        $otheractivity = $this->getDataGenerator()->create_module('attendancejourneys', ['course' => $activity->course]);
        $DB->set_field('attendancejourneys_sessions', 'attendancejourneysid', $otheractivity->id, ['id' => $sessions[0]]);
        $this->assertEquals(0, calculator::calculate($activity, $student->id, $journeys[2])->presentminutes);
        $DB->set_field('attendancejourneys_sessions', 'attendancejourneysid', $activity->id, ['id' => $sessions[0]]);
        $DB->set_field('attendancejourneys_sessions', 'journeyid', $journeys[2], ['id' => $sessions[0]]);
        $targets = $DB->get_records('attendancejourneys_sessions', ['id' => $sessions[2]]);
        $this->assertSame([], calculator::apply_approved_equivalences($activity, $student->id, $targets, []));
    }

    /**
     * An approved future or unapproved source cannot finalise an earlier target.
     */
    public function test_future_unapproved_and_invalid_sources_do_not_supply_minutes(): void {
        global $DB;
        [$activity, $student, $journeys, $sessions] = $this->fixture();
        $DB->set_field('attendancejourneys_sessions', 'sessiondate', time() + DAYSECS, ['id' => $sessions[0]]);
        $this->assertEquals(0, calculator::calculate($activity, $student->id, $journeys[1])->recordedsessions);
        $DB->set_field('attendancejourneys_sessions', 'sessiondate', time() - DAYSECS, ['id' => $sessions[0]]);
        $DB->set_field('attendancejourneys_records', 'approved', 0, ['sessionid' => $sessions[0]]);
        $DB->set_field('attendancejourneys_records', 'takenby', $student->id, ['sessionid' => $sessions[0]]);
        $this->assertEquals(0, calculator::calculate($activity, $student->id, $journeys[1])->recordedsessions);
        $DB->set_field('attendancejourneys_records', 'approved', 1, ['sessionid' => $sessions[0]]);
        $DB->set_field('attendancejourneys_records', 'minutesabsent', -100, ['sessionid' => $sessions[0]]);
        $this->assertEquals(0, calculator::calculate($activity, $student->id, $journeys[1])->recordedsessions);
        $DB->set_field('attendancejourneys_records', 'status', 'excused', ['sessionid' => $sessions[0]]);
        $this->assertEquals(0, calculator::calculate($activity, $student->id, $journeys[1])->recordedsessions);
    }
}
