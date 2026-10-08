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

#[\PHPUnit\Framework\Attributes\Group('mod_attendancejourneys')]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\series_builder::class)]
/**
 * Series preview generation and bulk-change tests.
 * @group mod_attendancejourneys
 * @covers \mod_attendancejourneys\local\series_builder
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class series_builder_test extends \advanced_testcase {
    public function test_generates_numbered_occurrences_across_daylight_saving_time(): void {
        $timezone = new \DateTimeZone('America/Toronto');
        $first = new \DateTime('2026-10-25 09:00:00', $timezone);
        $occurrences = \mod_attendancejourneys\local\series_builder::generate(
            'Jour',
            $first->getTimestamp(),
            2 * HOURSECS,
            3,
            7,
            'numbered',
            $timezone
        );

        $this->assertSame(['Jour 1', 'Jour 2', 'Jour 3'], array_column($occurrences, 'name'));
        foreach ($occurrences as $occurrence) {
            $local = new \DateTime('@' . $occurrence['sessiondate']);
            $local->setTimezone($timezone);
            $this->assertSame('09:00', $local->format('H:i'));
            $this->assertSame(2 * HOURSECS, $occurrence['enddate'] - $occurrence['sessiondate']);
        }
    }

    public function test_applies_numbering_rename_and_duration_only_to_selection(): void {
        $occurrences = [
            ['name' => 'Séance', 'sessiondate' => 1000, 'enddate' => 4600, 'included' => 1],
            ['name' => 'Séance', 'sessiondate' => 5000, 'enddate' => 8600, 'included' => 1],
            ['name' => 'Séance', 'sessiondate' => 9000, 'enddate' => 12600, 'included' => 1],
        ];
        $numbered = \mod_attendancejourneys\local\series_builder::apply_bulk(
            $occurrences,
            [1, 0, 1],
            'number',
            'Jour'
        );
        $this->assertSame(['Jour 1', 'Séance', 'Jour 2'], array_column($numbered, 'name'));

        $renamed = \mod_attendancejourneys\local\series_builder::apply_bulk(
            $numbered,
            [0, 1, 0],
            'rename',
            'Examen'
        );
        $this->assertSame('Examen', $renamed[1]['name']);

        $duration = \mod_attendancejourneys\local\series_builder::apply_bulk(
            $renamed,
            [1, 0, 1],
            'duration',
            '90'
        );
        $this->assertSame(90 * MINSECS, $duration[0]['enddate'] - $duration[0]['sessiondate']);
        $this->assertSame(3600, $duration[1]['enddate'] - $duration[1]['sessiondate']);
        $this->assertSame(90 * MINSECS, $duration[2]['enddate'] - $duration[2]['sessiondate']);

        $hybrid = \mod_attendancejourneys\local\series_builder::apply_bulk(
            $duration,
            [1, 1, 0],
            'modality',
            'hybrid'
        );
        $this->assertSame('hybrid', $hybrid[0]['modality']);
        $this->assertSame('hybrid', $hybrid[1]['modality']);
        $this->assertArrayNotHasKey('modality', $hybrid[2]);
    }

    public function test_finds_internal_and_existing_session_overlaps(): void {
        $occurrences = [
            ['included' => 1, 'sessiondate' => 1000, 'enddate' => 4600],
            ['included' => 1, 'sessiondate' => 4000, 'enddate' => 7000],
            ['included' => 0, 'sessiondate' => 5000, 'enddate' => 8000],
            ['included' => 1, 'sessiondate' => 10000, 'enddate' => 12000],
        ];
        $existing = [(object) ['sessiondate' => 11500, 'duration' => 60]];
        $this->assertSame(
            [0, 1, 3],
            \mod_attendancejourneys\local\series_builder::find_overlaps($occurrences, $existing)
        );
        $details = \mod_attendancejourneys\local\series_builder::describe_overlaps($occurrences, $existing);
        $this->assertCount(2, $details);
        $this->assertSame('proposed', $details[0]['source']);
        $this->assertSame(600, $details[0]['overlapseconds']);
        $this->assertSame('existing', $details[1]['source']);
        $this->assertSame(500, $details[1]['overlapseconds']);

        $touching = [
            ['included' => 1, 'sessiondate' => 1000, 'enddate' => 2000],
            ['included' => 1, 'sessiondate' => 2000, 'enddate' => 3000],
        ];
        $this->assertSame([], \mod_attendancejourneys\local\series_builder::find_overlaps($touching));
    }

    public function test_bulk_date_shift_preserves_local_time_and_duration(): void {
        $timezone = new \DateTimeZone('America/Toronto');
        $start = new \DateTime('2026-10-25 09:00:00', $timezone);
        $occurrences = [[
            'name' => 'Jour 1', 'included' => 1,
            'sessiondate' => $start->getTimestamp(), 'enddate' => $start->getTimestamp() + (2 * HOURSECS),
        ]];
        $shifted = \mod_attendancejourneys\local\series_builder::apply_bulk(
            $occurrences,
            [1],
            'shiftdays',
            '7',
            $timezone
        );
        $local = new \DateTime('@' . $shifted[0]['sessiondate']);
        $local->setTimezone($timezone);
        $this->assertSame('2026-11-01 09:00', $local->format('Y-m-d H:i'));
        $this->assertSame(2 * HOURSECS, $shifted[0]['enddate'] - $shifted[0]['sessiondate']);

        $minutes = \mod_attendancejourneys\local\series_builder::apply_bulk(
            $shifted,
            [1],
            'shiftminutes',
            '-30',
            $timezone
        );
        $this->assertSame(-30 * MINSECS, $minutes[0]['sessiondate'] - $shifted[0]['sessiondate']);
        $this->assertSame(2 * HOURSECS, $minutes[0]['enddate'] - $minutes[0]['sessiondate']);
    }

    public function test_sorts_stably_and_summarises_only_included_occurrences(): void {
        $occurrences = [
            ['name' => 'Deuxième A', 'included' => 1, 'sessiondate' => 5000, 'enddate' => 8600,
                'modality' => 'online'],
            ['name' => 'Première', 'included' => 1, 'sessiondate' => 1000, 'enddate' => 4600,
                'modality' => 'inperson'],
            ['name' => 'Deuxième B', 'included' => 1, 'sessiondate' => 5000, 'enddate' => 6800,
                'modality' => 'online'],
            ['name' => 'Exclue', 'included' => 0, 'sessiondate' => 100, 'enddate' => 10000,
                'modality' => 'hybrid'],
        ];
        $sorted = \mod_attendancejourneys\local\series_builder::sort_chronologically($occurrences);
        $this->assertSame(['Exclue', 'Première', 'Deuxième A', 'Deuxième B'], array_column($sorted, 'name'));

        $summary = \mod_attendancejourneys\local\series_builder::summarise($sorted);
        $this->assertSame(3, $summary['included']);
        $this->assertSame(1, $summary['excluded']);
        $this->assertSame(150, $summary['totalminutes']);
        $this->assertSame(1000, $summary['firststart']);
        $this->assertSame(8600, $summary['lastend']);
        $this->assertSame(['inperson' => 1, 'online' => 2], $summary['modalities']);
    }
}
