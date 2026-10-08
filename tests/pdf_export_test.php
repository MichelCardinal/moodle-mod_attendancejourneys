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

use mod_attendancejourneys\local\pdf_export;

/**
 * Checks that the readable PDF layout preserves report data and escapes plain text.
 *
 * @group mod_attendancejourneys
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('mod_attendancejourneys')]
final class pdf_export_test extends \basic_testcase {
    /**
     * Test repeated identities, field ordering, zeroes, blanks and hidden fields.
     */
    public function test_preserves_declared_fields_and_record_boundaries(): void {
        $columns = ['name' => 'Session', 'minutes' => 'Minutes', 'note' => 'Note'];
        $rows = [(object) ['name' => 'Same', 'minutes' => 0, 'note' => '', 'secret' => 'hidden'],
            (object) ['name' => 'Same', 'minutes' => 120, 'note' => null]];
        $actual = iterator_to_array(pdf_export::rows($columns, $rows));
        $this->assertCount(6, $actual);
        $this->assertSame(['1', '1', '1', '2', '2', '2'], array_column($actual, 'entry'));
        $this->assertSame(['Session', 'Minutes', 'Note', 'Session', 'Minutes', 'Note'], array_column($actual, 'field'));
        $this->assertSame(['Same', '0', '', 'Same', '120', ''], array_column($actual, 'value'));
    }

    /**
     * Test that remarks cannot become HTML, images or external requests in the PDF.
     */
    public function test_escapes_plain_text_and_preserves_line_breaks(): void {
        $text = "<img src=\"https://example.invalid/image\"> & <script>x</script>\nSecond line";
        $actual = iterator_to_array(pdf_export::rows(['note' => '<Note>'], [['note' => $text]]));
        $this->assertSame('&lt;Note&gt;', $actual[0]['field']);
        $this->assertSame(nl2br(s($text)), $actual[0]['value']);
        $this->assertStringNotContainsString('<img', $actual[0]['value']);
        $this->assertStringNotContainsString('<script', $actual[0]['value']);
    }

    /**
     * Empty reports must remain empty rather than creating a fictitious record.
     */
    public function test_empty_report(): void {
        $this->assertSame([], iterator_to_array(pdf_export::rows(['note' => 'Note'], [])));
    }
    /**
     * Exercise Moodle's PDF library with a report spanning multiple pages.
     */
    public function test_document_renders_long_plain_text(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $rows = [['name' => 'Session <one>', 'note' => str_repeat("Long remark & detail.\n", 100)]];
        $pdf = pdf_export::document('Test report', ['name' => 'Session', 'note' => 'Remark'], $rows);
        $this->assertGreaterThan(1, $pdf->getNumPages());
        $this->assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
    }

    /**
     * Very long unbroken report titles wrap before the first record rather than overflowing it.
     */
    public function test_long_unbroken_title_wraps_before_records(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $short = pdf_export::document('Report', [], []);
        $long = pdf_export::document(str_repeat('UnbrokenReportName', 30), [], []);
        $this->assertGreaterThan($short->GetY(), $long->GetY());
        $this->assertLessThan($long->getPageHeight() - $long->getBreakMargin(), $long->GetY());
        $this->assertStringStartsWith('%PDF-', $long->Output('', 'S'));
    }
}
