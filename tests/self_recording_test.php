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
 * Student declaration, approval and audit workflow tests.
 * @group mod_attendancejourneys
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('mod_attendancejourneys')]
final class self_recording_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
    }

    public function test_student_edit_lock_has_one_consistent_rule(): void {
        $studentid = 42;
        $this->assertTrue(attendancejourneys_self_record_is_editable(null, $studentid));
        $this->assertTrue(attendancejourneys_self_record_is_editable((object) [
            'takenby' => $studentid, 'approved' => 0, 'changesrequested' => 0,
        ], $studentid));
        $this->assertTrue(attendancejourneys_self_record_is_editable((object) [
            'takenby' => $studentid, 'approved' => 0, 'changesrequested' => 1,
        ], $studentid));
        $this->assertFalse(attendancejourneys_self_record_is_editable((object) [
            'takenby' => $studentid, 'approved' => 1, 'changesrequested' => 0,
        ], $studentid));
        $this->assertFalse(attendancejourneys_self_record_is_editable((object) [
            'takenby' => 99, 'approved' => 0, 'changesrequested' => 0,
        ], $studentid));
    }

    public function test_only_approved_self_declarations_are_official(): void {
        $pending = (object) ['userid' => 42, 'takenby' => 42, 'approved' => 0];
        $approved = (object) ['userid' => 42, 'takenby' => 42, 'approved' => 1];
        $staffrecorded = (object) ['userid' => 42, 'takenby' => 99, 'approved' => 0];
        $legacy = (object) ['userid' => 42, 'takenby' => 42];

        $this->assertFalse(calculator::record_is_official($pending));
        $this->assertTrue(calculator::record_is_official($approved));
        $this->assertTrue(calculator::record_is_official($staffrecorded));
        $this->assertTrue(calculator::record_is_official($legacy));
    }

    public function test_review_history_is_immutable_and_rejects_unknown_actions(): void {
        global $DB;
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $recordid = 701;
        $sessionid = 501;

        $firstid = attendancejourneys_add_review_history(
            $recordid,
            $sessionid,
            (int) $student->id,
            'recorded',
            (int) $student->id,
            'status=present; minutesabsent=0'
        );
        $secondid = attendancejourneys_add_review_history(
            $recordid,
            $sessionid,
            (int) $student->id,
            'approved',
            (int) $teacher->id,
            '  Verified  '
        );

        $this->assertNotSame($firstid, $secondid);
        $this->assertSame(2, $DB->count_records('attendancejourneys_reviews', ['recordid' => $recordid]));
        $first = $DB->get_record('attendancejourneys_reviews', ['id' => $firstid], '*', MUST_EXIST);
        $second = $DB->get_record('attendancejourneys_reviews', ['id' => $secondid], '*', MUST_EXIST);
        $this->assertSame('recorded', $first->action);
        $this->assertSame((int) $student->id, (int) $first->actorid);
        $this->assertSame('approved', $second->action);
        $this->assertSame((int) $teacher->id, (int) $second->actorid);
        $this->assertSame('Verified', $second->note);

        $this->expectException(\coding_exception::class);
        attendancejourneys_add_review_history(
            $recordid,
            $sessionid,
            (int) $student->id,
            'silentlychanged',
            (int) $teacher->id
        );
    }

    public function test_audit_snapshot_is_stable_and_language_neutral(): void {
        $this->assertSame(
            'status=partial; minutesabsent=5; remarks=Late bus',
            attendancejourneys_record_audit_snapshot((object) [
                'status' => 'partial', 'minutesabsent' => 5, 'remarks' => '  Late bus  ',
            ])
        );
        $this->assertSame(
            'status=present; minutesabsent=0',
            attendancejourneys_record_audit_snapshot((object) [
                'status' => 'present', 'minutesabsent' => 0, 'remarks' => '',
            ])
        );
    }

    public function test_audit_presentation_preserves_notes_and_stored_snapshots(): void {
        force_current_language('en');
        foreach (['present', 'absent', 'partial', 'excused', 'unrecorded'] as $status) {
            $note = 'status=' . $status . '; minutesabsent=15';
            $review = (object) ['action' => 'recorded', 'note' => $note];
            $expected = 'Status: ' . get_string('status' . $status, 'attendancejourneys') . '; minutes absent: 15';
            $this->assertSame($expected, attendancejourneys_format_review_note($review));
            $this->assertSame($note, $review->note);
        }
        $remarks = "Bus; remarks=unchanged\n<script>alert('test')</script>";
        $review = (object) ['action' => 'updated', 'note' => 'status=partial; minutesabsent=15; remarks=' . $remarks];
        $this->assertSame(
            "Status: Partial; minutes absent: 15\nRemarks: " . $remarks,
            attendancejourneys_format_review_note($review)
        );
        $this->assertStringNotContainsString('<script>', s(attendancejourneys_format_review_note($review)));

        // A staff-authored correction request is never interpreted as a snapshot.
        $review->action = 'changesrequested';
        $this->assertSame($review->note, attendancejourneys_format_review_note($review));
        foreach (['', 'Future snapshot format', 'status=unknown; minutesabsent=0', 'status=present; minutesabsent=-1'] as $note) {
            $review = (object) ['action' => 'recorded', 'note' => $note];
            $this->assertSame($note, attendancejourneys_format_review_note($review));
        }
    }
}
