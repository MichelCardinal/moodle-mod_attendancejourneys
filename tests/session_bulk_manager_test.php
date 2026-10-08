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

/**
 * Bulk session scheduling tests.
 * @group mod_attendancejourneys
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('mod_attendancejourneys')]
final class session_bulk_manager_test extends \advanced_testcase {
    public function test_day_and_minute_shifts_preserve_duration_and_local_time(): void {
        $timezone = new \DateTimeZone('America/Toronto');
        $start = new \DateTime('2026-10-31 09:00:00', $timezone);
        $session = (object) ['id' => 7, 'sessiondate' => $start->getTimestamp(), 'duration' => 90,
            'journeyid' => 0, 'groupid' => 0];

        $days = \mod_attendancejourneys\local\session_bulk_manager::preview_shift([$session], 'days', 2, $timezone);
        $local = new \DateTime('@' . $days[7]->sessiondate);
        $local->setTimezone($timezone);
        $this->assertSame('2026-11-02 09:00', $local->format('Y-m-d H:i'));
        $this->assertSame(90, (int) $days[7]->duration);

        $minutes = \mod_attendancejourneys\local\session_bulk_manager::preview_shift(
            [$session],
            'minutes',
            -30,
            $timezone
        );
        $this->assertSame($session->sessiondate - (30 * MINSECS), (int) $minutes[7]->sessiondate);
    }

    public function test_conflicts_are_limited_to_the_same_audience(): void {
        $base = (object) ['id' => 1, 'sessiondate' => 1000, 'duration' => 60, 'journeyid' => 0, 'groupid' => 0];
        $same = (object) ['id' => 2, 'sessiondate' => 2000, 'duration' => 60, 'journeyid' => 0, 'groupid' => 0];
        $group = (object) ['id' => 3, 'sessiondate' => 2000, 'duration' => 60, 'journeyid' => 0, 'groupid' => 9];
        $shifted = \mod_attendancejourneys\local\session_bulk_manager::preview_shift(
            [$base],
            'minutes',
            10,
            new \DateTimeZone('UTC')
        );

        $conflicts = \mod_attendancejourneys\local\session_bulk_manager::find_conflicts($shifted, [$same, $group]);
        $this->assertCount(1, $conflicts);
        $this->assertSame(2, (int) $conflicts[0]['other']->id);
    }

    public function test_bulk_changes_preserve_unrelated_session_fields(): void {
        $session = (object) ['id' => 4, 'sessiondate' => 1000, 'duration' => 60, 'journeyid' => 0,
            'groupid' => 0, 'modality' => 'unspecified', 'location' => '', 'meetingurl' => ''];
        $duration = \mod_attendancejourneys\local\session_bulk_manager::preview_change([$session], 'duration', '90');
        $this->assertSame(90, (int) $duration[4]->duration);
        $this->assertSame(1000, (int) $duration[4]->sessiondate);
        $online = \mod_attendancejourneys\local\session_bulk_manager::preview_change([$session], 'modality', 'online');
        $this->assertSame('online', $online[4]->modality);
        $link = \mod_attendancejourneys\local\session_bulk_manager::preview_change(
            [$session],
            'meetingurl',
            'https://example.invalid/class'
        );
        $this->assertSame('https://example.invalid/class', $link[4]->meetingurl);
    }

    public function test_audience_preview_updates_group_and_journey_only(): void {
        $session = (object) ['id' => 8, 'sessiondate' => 1000, 'duration' => 60,
            'journeyid' => 0, 'groupid' => 0, 'name' => 'Audience test'];
        $group = \mod_attendancejourneys\local\session_bulk_manager::preview_audience([$session], 12, 0);
        $this->assertSame(12, (int) $group[8]->groupid);
        $this->assertSame(0, (int) $group[8]->journeyid);
        $this->assertSame('Audience test', $group[8]->name);

        $journey = \mod_attendancejourneys\local\session_bulk_manager::preview_audience([$session], 12, 4);
        $this->assertSame(12, (int) $journey[8]->groupid);
        $this->assertSame(4, (int) $journey[8]->journeyid);
    }

    public function test_numbering_is_chronological_and_description_can_be_cleared(): void {
        $later = (object) ['id' => 2, 'sessiondate' => 2000, 'duration' => 60, 'journeyid' => 0,
            'groupid' => 0, 'name' => 'Old', 'description' => 'Old description'];
        $earlier = clone $later;
        $earlier->id = 1;
        $earlier->sessiondate = 1000;
        $numbered = \mod_attendancejourneys\local\session_bulk_manager::preview_change(
            [$later, $earlier],
            'number',
            'Module'
        );
        $this->assertSame('Module 1', $numbered[1]->name);
        $this->assertSame('Module 2', $numbered[2]->name);
        $cleared = \mod_attendancejourneys\local\session_bulk_manager::preview_change(
            [$earlier],
            'description',
            ''
        );
        $this->assertSame('', $cleared[1]->description);
    }

    public function test_copy_preview_keeps_source_identity_separate(): void {
        $source = (object) ['id' => 12, 'sessiondate' => 1000, 'duration' => 60,
            'journeyid' => 0, 'groupid' => 4, 'name' => 'Day one'];
        $copies = \mod_attendancejourneys\local\session_bulk_manager::preview_copies(
            [$source],
            'minutes',
            90,
            new \DateTimeZone('UTC')
        );

        $this->assertArrayHasKey(-12, $copies);
        $this->assertSame(12, $copies[-12]->sourceid);
        $this->assertSame(6400, $copies[-12]->sessiondate);
        $this->assertSame(4, $copies[-12]->groupid);
        $this->assertSame(1000, $source->sessiondate);

        $reassigned = \mod_attendancejourneys\local\session_bulk_manager::preview_audience($copies, 8, 3);
        $this->assertSame(12, $reassigned[-12]->sourceid);
        $this->assertSame(8, $reassigned[-12]->groupid);
        $this->assertSame(3, $reassigned[-12]->journeyid);
    }
}
