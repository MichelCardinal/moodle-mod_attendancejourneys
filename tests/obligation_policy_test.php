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
use mod_attendancejourneys\local\obligation_policy;

/**
 * Pedagogical boundary and waiver scenarios before persistence and UI integration.
 *
 * @covers \mod_attendancejourneys\local\calculator
 * @covers \mod_attendancejourneys\local\obligation_policy
 * @covers ::attendancejourneys_closure_blocker
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\calculator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\obligation_policy::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_closure_blocker')]
final class obligation_policy_test extends \basic_testcase {
    /**
     * With no decision, an earlier absence remains part of the required minutes.
     */
    public function test_default_requires_earlier_sessions_without_inferred_dates(): void {
        $sessions = [1 => (object) ['id' => 1, 'sessiondate' => 1000, 'duration' => 100],
            2 => (object) ['id' => 2, 'sessiondate' => 10000, 'duration' => 100]];
        $records = [1 => (object) ['status' => 'absent'],
            2 => (object) ['status' => 'partial', 'minutesabsent' => 20]];
        $required = obligation_policy::sessions(obligation_policy::unrestricted(), $sessions);
        $result = calculator::aggregate((object) ['journeygrading' => 1], $required, $records);
        $this->assertSame(200, $result->possibleminutes);
        $this->assertSame(80, $result->presentminutes);
        $this->assertEquals(40, $result->percent);
    }

    /**
     * An explicit later start removes the earlier obligation, without altering its attendance.
     */
    public function test_justified_period_changes_minutes_without_rewriting_records(): void {
        $sessions = [1 => (object) ['id' => 1, 'sessiondate' => 1000, 'duration' => 100],
            2 => (object) ['id' => 2, 'sessiondate' => 10000, 'duration' => 100]];
        $records = [1 => (object) ['status' => 'absent', 'remarks' => 'Earlier attendance'],
            2 => (object) ['status' => 'partial', 'minutesabsent' => 20]];
        $original = serialize([$sessions, $records]);
        $policy = obligation_policy::decision(false, 10000, 0, 'Approved later obligation');
        $required = obligation_policy::sessions($policy, $sessions);
        $result = calculator::aggregate((object) ['journeygrading' => 1], $required, $records);
        $this->assertSame([2], array_keys($required));
        $this->assertSame(100, $result->possibleminutes);
        $this->assertSame(80, $result->presentminutes);
        $this->assertEquals(80, $result->percent);
        $this->assertSame($original, serialize([$sessions, $records]));
    }

    /**
     * Boundary overlap requires the whole session; attendance is never prorated implicitly.
     */
    public function test_half_open_period_retains_whole_overlapping_sessions(): void {
        $policy = obligation_policy::decision(false, 10000, 20000, 'Approved individual period');
        $sessions = [1 => (object) ['id' => 1, 'sessiondate' => 4000, 'duration' => 100],
            2 => (object) ['id' => 2, 'sessiondate' => 5000, 'duration' => 100],
            3 => (object) ['id' => 3, 'sessiondate' => 19000, 'duration' => 100],
            4 => (object) ['id' => 4, 'sessiondate' => 20000, 'duration' => 100]];
        $required = obligation_policy::sessions($policy, $sessions);
        $this->assertSame([2, 3], array_keys($required));
        $result = calculator::aggregate((object) ['journeygrading' => 1], $required, [
            2 => (object) ['status' => 'present'], 3 => (object) ['status' => 'present'],
        ]);
        $this->assertSame(200, $result->possibleminutes);
        $this->assertSame(200, $result->presentminutes);
    }

    /**
     * A whole-journey waiver never manufactures a 100 percent attendance result.
     */
    public function test_waiver_removes_minutes_without_creating_a_successful_closure(): void {
        require_once(__DIR__ . '/../locallib.php');
        $policy = obligation_policy::decision(true, 0, 0, 'Approved whole-journey waiver');
        $sessions = [1 => (object) ['id' => 1, 'sessiondate' => 1000, 'duration' => 100]];
        $result = calculator::aggregate(
            (object) ['journeygrading' => 1],
            obligation_policy::sessions($policy, $sessions),
            [1 => (object) ['status' => 'present']]
        );
        $this->assertSame(0, $result->possibleminutes);
        $this->assertNull($result->percent);
        $this->assertSame(['closureemptysessions', null], attendancejourneys_closure_blocker($result));
    }

    /**
     * Invalid ranges and missing explanations cannot become previewable decisions.
     */
    public function test_invalid_decisions_are_rejected(): void {
        foreach ([-1, 100, 200] as $start) {
            try {
                obligation_policy::decision(false, $start, 100, 'Requested boundary');
                $this->fail('Invalid period accepted.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('obligationinvalidperiod', $exception->errorcode);
            }
        }
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('obligationreasonrequired', 'attendancejourneys'));
        obligation_policy::decision(true, 0, 0, '   ');
    }
}
