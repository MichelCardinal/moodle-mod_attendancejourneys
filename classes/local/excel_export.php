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

declare(strict_types=1);

namespace mod_attendancejourneys\local;

/**
 * Typed report cells through Moodle's native Excel API.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class excel_export {
    /**
     * Download numeric report fields and explicit plain-text user content.
     *
     * @param string $filename Download filename without extension.
     * @param array $columns Column labels keyed by report field.
     * @param iterable $rows Report records in display order.
     */
    public static function download(string $filename, array $columns, iterable $rows): void {
        global $CFG;
        if (ob_get_length()) {
            throw new \coding_exception('Output can not be buffered before downloading an Excel report');
        }
        require_once($CFG->libdir . '/excellib.class.php');
        \core_php_time_limit::raise();
        \core\session\manager::write_close();
        $workbook = new \MoodleExcelWorkbook($filename);
        $sheet = $workbook->add_worksheet();
        $headerformat = $workbook->add_format(['bold' => 1, 'text_wrap' => true, 'valign' => 'top']);
        $textformat = $workbook->add_format(['text_wrap' => true, 'valign' => 'top']);
        $sheet->set_row(0, 36);
        foreach (array_values($columns) as $col => $label) {
            $sheet->write_string(0, $col, (string) $label, $headerformat);
            $sheet->set_column($col, $col, 32);
        }
        $rownum = 1;
        foreach ($rows as $row) {
            $record = (array) $row;
            foreach (array_keys($columns) as $col => $key) {
                $value = $record[$key] ?? '';
                if (is_int($value) || is_float($value)) {
                    $sheet->write_number($rownum, $col, $value);
                } else {
                    // Explicit text retains identifiers and cannot become an Excel formula.
                    $sheet->write_string($rownum, $col, (string) $value, $textformat);
                }
            }
            $rownum++;
        }
        $workbook->close();
    }
}
