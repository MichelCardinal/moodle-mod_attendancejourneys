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
 * Readable, lossless PDF presentation of tabular report data.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pdf_export {
    /**
     * Download a report using Moodle's bundled PDF library.
     *
     * @param string $filename Download filename without extension.
     * @param array $columns Original column labels.
     * @param iterable $rows Report records.
     */
    public static function download(string $filename, array $columns, iterable $rows): void {
        if (ob_get_length()) {
            throw new \coding_exception('Output can not be buffered before downloading a PDF');
        }
        \core_php_time_limit::raise();
        \core\session\manager::write_close();
        \core_form\util::form_download_complete();
        self::document($filename, $columns, $rows)->Output($filename . '.pdf', 'D');
    }

    /**
     * Build labelled record tables with a repeating record heading on continued pages.
     *
     * @param string $title Report title.
     * @param array $columns Original column labels.
     * @param iterable $rows Report records.
     * @return \pdf
     */
    public static function document(string $title, array $columns, iterable $rows): \pdf {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        $pdf = new \pdf();
        $pdf->setPrintHeader(false);
        $pdf->SetTitle($title);
        $pdf->SetFont('freesans', '', 10);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->AddPage();
        // Plain-text MultiCell also wraps long uninterrupted names within the page margins.
        $pdf->SetFont('freesans', 'B', 14);
        $pdf->MultiCell(0, 0, $title, 0, 'L');
        $pdf->Ln(2);
        $pdf->SetFont('freesans', '', 10);
        $entry = 0;
        foreach ($rows as $row) {
            $entry++;
            $heading = \attendancejourneys_get_string('pdfentry', 'attendancejourneys') . ' ' . $entry;
            $html = '<table border="1" cellpadding="5"><thead><tr>' .
                '<th colspan="2" bgcolor="#eeeeee"><strong>' . s($heading) . '</strong></th></tr>' .
                '<tr><th width="35%"><strong>' . s(\attendancejourneys_get_string('pdffield', 'attendancejourneys')) .
                '</strong></th><th width="65%"><strong>' .
                s(\attendancejourneys_get_string('pdfvalue', 'attendancejourneys')) . '</strong></th></tr></thead><tbody>';
            foreach (self::rows($columns, [$row]) as $field) {
                $html .= '<tr><td width="35%">' . $field['field'] . '</td><td width="65%">' .
                    $field['value'] . '</td></tr>';
            }
            $pdf->writeHTML($html . '</tbody></table><br>');
        }
        return $pdf;
    }

    /**
     * Expand each record into labelled fields without discarding blank or zero values.
     *
     * Only declared columns are included, in their original order. Escape text because
     * Moodle's PDF writer accepts HTML, whereas report remarks are plain text.
     * The entry number keeps records distinguishable when their names are identical.
     *
     * @param array $columns Original field headings keyed by field name.
     * @param iterable $rows Original report records.
     * @return \Generator
     */
    public static function rows(array $columns, iterable $rows): \Generator {
        $entry = 0;
        foreach ($rows as $row) {
            $entry++;
            $record = (array) $row;
            foreach ($columns as $key => $label) {
                yield [
                    'entry' => (string) $entry,
                    'field' => s((string) $label),
                    'value' => nl2br(s((string) ($record[$key] ?? ''))),
                ];
            }
        }
    }
}
