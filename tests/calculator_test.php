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

#[\PHPUnit\Framework\Attributes\Group('mod_attendancejourneys')]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\calculator::class)]
/**
 * Tests the central attendance calculator.
 * @group mod_attendancejourneys
 * @covers \mod_attendancejourneys\local\calculator
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calculator_test extends \basic_testcase {
    public function test_record_minutes_for_every_status_and_excused_mode(): void {
        $session = (object) ['duration' => 360];
        $activity = (object) ['excusedmode' => 'excluded'];
        $this->assertSame([360, 360], calculator::record_minutes(
            $activity,
            $session,
            (object) ['status' => 'present', 'minutesabsent' => 0]
        ));
        $this->assertSame([0, 360], calculator::record_minutes(
            $activity,
            $session,
            (object) ['status' => 'absent', 'minutesabsent' => 360]
        ));
        $this->assertSame([325, 360], calculator::record_minutes(
            $activity,
            $session,
            (object) ['status' => 'partial', 'minutesabsent' => 35]
        ));
        $this->assertSame([0, 0], calculator::record_minutes(
            $activity,
            $session,
            (object) ['status' => 'excused', 'minutesabsent' => 0]
        ));
        $activity->excusedmode = 'present';
        $this->assertSame([360, 360], calculator::record_minutes(
            $activity,
            $session,
            (object) ['status' => 'excused', 'minutesabsent' => 0]
        ));
        $activity->excusedmode = 'absent';
        $this->assertSame([0, 360], calculator::record_minutes(
            $activity,
            $session,
            (object) ['status' => 'excused', 'minutesabsent' => 0]
        ));
    }

    /**
     * A justified absence never grants time; an exemption only removes required time.
     */
    public function test_journey_absence_and_exemption_are_distinct(): void {
        $session = (object) ['duration' => 60];
        foreach (['excluded', 'present', 'absent'] as $oldpolicy) {
            $activity = (object) ['journeygrading' => 1, 'excusedmode' => $oldpolicy];
            $this->assertSame([0, 60], calculator::record_minutes($activity, $session, (object) ['status' => 'excused']));
            $this->assertSame([0, 0], calculator::record_minutes($activity, $session, (object) ['status' => 'exempt']));
        }
        $sessions = [];
        $records = [];
        foreach (['present', 'excused', 'exempt'] as $index => $status) {
            $sessions[$index] = (object) ['id' => $index, 'duration' => 60];
            $records[$index] = (object) ['status' => $status, 'minutesabsent' => 0];
        }
        $result = calculator::aggregate($activity, $sessions, $records);
        $this->assertSame(60, $result->presentminutes);
        $this->assertSame(120, $result->possibleminutes);
        $this->assertEquals(50, $result->percent);
        $this->assertSame(3, $result->recordedsessions);
        foreach ($records as $record) {
            $record->status = 'exempt';
        }
        $result = calculator::aggregate($activity, $sessions, $records);
        $this->assertSame(0, $result->presentminutes);
        $this->assertSame(0, $result->possibleminutes);
        $this->assertNull($result->percent);
    }

    public function test_future_session_is_not_due(): void {
        $now = 1_800_000_000;
        $this->assertFalse(calculator::session_is_due((object) [
            'sessiondate' => $now + HOURSECS, 'duration' => 60,
        ], $now));
        $this->assertTrue(calculator::session_is_due((object) [
            'sessiondate' => $now - HOURSECS, 'duration' => 60,
        ], $now));
    }

    public function test_aggregate_uses_one_consistent_percentage(): void {
        $activity = (object) ['excusedmode' => 'excluded'];
        $sessions = [
            1 => (object) ['id' => 1, 'duration' => 360],
            2 => (object) ['id' => 2, 'duration' => 60],
            3 => (object) ['id' => 3, 'duration' => 60],
        ];
        $records = [
            1 => (object) ['status' => 'partial', 'minutesabsent' => 35],
            2 => (object) ['status' => 'present', 'minutesabsent' => 0],
        ];
        $result = calculator::aggregate($activity, $sessions, $records);
        $this->assertSame(385, $result->presentminutes);
        $this->assertSame(420, $result->possibleminutes);
        $this->assertSame(2, $result->recordedsessions);
        $this->assertSame(3, $result->applicablesessions);
        $this->assertEqualsWithDelta(91.6667, $result->percent, 0.0001);
    }

    public function test_pending_self_declaration_is_excluded_until_approved(): void {
        $activity = (object) ['excusedmode' => 'excluded'];
        $sessions = [
            1 => (object) ['id' => 1, 'duration' => 60],
            2 => (object) ['id' => 2, 'duration' => 60],
        ];
        $records = [
            1 => (object) ['userid' => 10, 'takenby' => 10, 'approved' => 0,
                'status' => 'present', 'minutesabsent' => 0],
            2 => (object) ['userid' => 10, 'takenby' => 20, 'approved' => 1,
                'status' => 'partial', 'minutesabsent' => 15],
        ];

        $pending = calculator::aggregate($activity, $sessions, $records);
        $this->assertSame(1, $pending->pendingapprovals);
        $this->assertSame(1, $pending->recordedsessions);
        $this->assertSame(45, $pending->presentminutes);
        $this->assertSame(60, $pending->possibleminutes);

        $records[1]->approved = 1;
        $approved = calculator::aggregate($activity, $sessions, $records);
        $this->assertSame(0, $approved->pendingapprovals);
        $this->assertSame(2, $approved->recordedsessions);
        $this->assertSame(105, $approved->presentminutes);
        $this->assertSame(120, $approved->possibleminutes);
    }

    public function test_equivalence_uses_target_duration_and_caps_transferred_minutes(): void {
        $activity = (object) ['excusedmode' => 'excluded'];
        $equivalence = (object) ['id' => 9, 'userid' => 10, 'decidedby' => 20, 'timemodified' => 1000];
        $target = (object) ['id' => 1, 'duration' => 120];
        $source = (object) ['id' => 2, 'duration' => 90];
        $partial = calculator::equivalent_record(
            $activity,
            $target,
            $source,
            (object) ['status' => 'partial', 'minutesabsent' => 10],
            $equivalence
        );
        $this->assertSame('partial', $partial->status);
        $this->assertSame(40, $partial->minutesabsent);
        $longsource = (object) ['id' => 3, 'duration' => 180];
        $capped = calculator::equivalent_record(
            $activity,
            $target,
            $longsource,
            (object) ['status' => 'present', 'minutesabsent' => 0],
            $equivalence
        );
        $this->assertSame('present', $capped->status);
        $this->assertSame(0, $capped->minutesabsent);
    }
}
