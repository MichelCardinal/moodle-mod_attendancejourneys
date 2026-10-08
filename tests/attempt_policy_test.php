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

use mod_attendancejourneys\local\attempt_policy;

/**
 * New attempts replace results within an obligation while preserving attendance history.
 *
 * @covers \mod_attendancejourneys\local\attempt_family
 * @covers \mod_attendancejourneys\local\attempt_policy
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\attempt_family::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\attempt_policy::class)]
final class attempt_policy_test extends \basic_testcase {
    /**
     * Explicitly linked second and third attempts, deliberately out of order.
     *
     * @return array Attempt links.
     */
    private function attempts(): array {
        return [
            (object) ['attendancejourneysid' => 10, 'rootjourneyid' => 20, 'userid' => 30,
                'journeyid' => 23, 'attemptnumber' => 3],
            (object) ['attendancejourneysid' => 10, 'rootjourneyid' => 20, 'userid' => 30,
                'journeyid' => 22, 'attemptnumber' => 2],
        ];
    }

    /**
     * Closure examples with a worse later attempt and an old attempt corrected later.
     *
     * @return array Final snapshots.
     */
    private function closures(): array {
        return [
            (object) ['id' => 1, 'attendancejourneysid' => 10, 'journeyid' => 20, 'userid' => 30,
                'active' => 1, 'percentage' => 100, 'result' => 'passed', 'timeclosed' => 100],
            (object) ['id' => 2, 'attendancejourneysid' => 10, 'journeyid' => 22, 'userid' => 30,
                'active' => 1, 'percentage' => 90, 'result' => 'passed', 'timeclosed' => 500],
            (object) ['id' => 3, 'attendancejourneysid' => 10, 'journeyid' => 23, 'userid' => 30,
                'active' => 1, 'percentage' => 60, 'result' => 'failed', 'timeclosed' => 300],
        ];
    }

    /**
     * Reports distinguish the selected final result from older successes and pending retakes.
     */
    public function test_result_status_does_not_publish_an_obsolete_success(): void {
        $closures = $this->closures();
        $family = (object) ['activityid' => 10, 'root' => (object) ['id' => 20],
            'links' => [30 => $this->attempts()], 'closures' => [30 => $closures]];
        $this->assertSame('journeypreviousresult', \mod_attendancejourneys\local\attempt_family::result_status(
            $family,
            30,
            20,
            $closures[0]
        ));
        $this->assertSame('journeyresultpublished', \mod_attendancejourneys\local\attempt_family::result_status(
            $family,
            30,
            23,
            $closures[2]
        ));
        $closures[2]->active = 0;
        $this->assertSame('provisional', \mod_attendancejourneys\local\attempt_family::result_status($family, 30, 23, null));
        $this->assertSame('journeypreviousresult', \mod_attendancejourneys\local\attempt_family::result_status(
            $family,
            30,
            20,
            $closures[0]
        ));
        $this->assertSame('attemptprevious', \mod_attendancejourneys\local\attempt_family::result_status($family, 30, 22, null));
        $this->assertSame('obligationwaived', \mod_attendancejourneys\local\attempt_family::result_status(
            $family,
            30,
            23,
            null,
            true
        ));
        $this->assertSame('provisional', \mod_attendancejourneys\local\attempt_family::result_status($family, 31, 20, null));
    }

    /**
     * Existing single journeys keep their own final result without invented links.
     */
    public function test_existing_obligation_keeps_its_original_result(): void {
        $state = attempt_policy::select(10, 20, 30, [], $this->closures());
        $this->assertSame(20, $state->journeyid);
        $this->assertSame(1, $state->attemptnumber);
        $this->assertSame(100, $state->closure->percentage);
    }

    /**
     * A lower latest result replaces the earlier success, without an average or best-score rule.
     */
    public function test_latest_attempt_replaces_better_results_without_changing_history(): void {
        $attempts = $this->attempts();
        $closures = $this->closures();
        $before = serialize([$attempts, $closures]);
        $state = attempt_policy::select(10, 20, 30, $attempts, $closures);
        $this->assertSame(23, $state->journeyid);
        $this->assertSame(3, $state->attemptnumber);
        $this->assertSame(60, $state->closure->percentage);
        $this->assertSame('failed', $state->closure->result);
        $state->closure->percentage = 0;
        $this->assertSame($before, serialize([$attempts, $closures]));
    }

    /**
     * A pending or reopened attempt cannot fall back to an obsolete successful result.
     */
    public function test_open_and_reopened_attempts_have_no_final_result(): void {
        $closures = $this->closures();
        $closures[2]->active = 0;
        $state = attempt_policy::select(10, 20, 30, $this->attempts(), $closures);
        $this->assertSame(23, $state->journeyid);
        $this->assertNull($state->closure);
        array_pop($closures);
        $state = attempt_policy::select(10, 20, 30, $this->attempts(), $closures);
        $this->assertSame(3, $state->attemptnumber);
        $this->assertNull($state->closure);
    }

    /**
     * A retake affects neither another participant nor an independent laboratory obligation.
     */
    public function test_selection_is_specific_to_the_participant_and_obligation(): void {
        $otheruser = attempt_policy::select(10, 20, 31, $this->attempts(), $this->closures());
        $otherobligation = attempt_policy::select(10, 40, 30, $this->attempts(), $this->closures());
        foreach ([$otheruser, $otherobligation] as $state) {
            $this->assertSame(1, $state->attemptnumber);
            $this->assertNull($state->closure);
        }
        $this->assertSame(20, $otheruser->journeyid);
        $this->assertSame(40, $otherobligation->journeyid);
    }

    /**
     * Ambiguous imported links cannot choose a grade silently.
     */
    public function test_duplicate_numbers_journeys_and_foreign_activity_are_rejected(): void {
        foreach (['number', 'journey', 'activity', 'root', 'first'] as $invalid) {
            $attempts = $this->attempts();
            if ($invalid === 'number') {
                $attempts[1]->attemptnumber = 3;
            } else if ($invalid === 'journey') {
                $attempts[1]->journeyid = 23;
            } else if ($invalid === 'activity') {
                $attempts[1]->attendancejourneysid = 11;
            } else if ($invalid === 'root') {
                $attempts[1]->journeyid = 20;
            } else {
                $attempts[1]->attemptnumber = 1;
            }
            try {
                attempt_policy::select(10, 20, 30, $attempts, $this->closures());
                $this->fail('An invalid attempt family must not choose a result.');
            } catch (\coding_exception $exception) {
                $this->assertStringContainsString('Attempt links', $exception->getMessage());
            }
        }
    }

    /**
     * Duplicate active snapshots or a foreign snapshot must not supply a final grade.
     */
    public function test_inconsistent_closures_are_rejected(): void {
        foreach (['duplicate', 'foreign'] as $invalid) {
            $closures = $this->closures();
            if ($invalid === 'duplicate') {
                $closures[] = clone $closures[2];
            } else {
                $closures[2]->attendancejourneysid = 11;
            }
            try {
                attempt_policy::select(10, 20, 30, $this->attempts(), $closures);
                $this->fail('An inconsistent snapshot must not supply a grade.');
            } catch (\coding_exception $exception) {
                $this->assertStringContainsString('selected attempt', $exception->getMessage());
            }
        }
    }
}
