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
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\local\calculator::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_audit_query')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_responsive_table')]
/**
 * Report filters, official totals and Moodle data-format tests.
 * @group mod_attendancejourneys
 * @covers \mod_attendancejourneys\local\calculator
 * @covers ::attendancejourneys_audit_query
 * @covers ::attendancejourneys_responsive_table
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_export_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
    }

    /**
     * One labelled region owns scrolling even when Moodle wraps tables by default.
     */
    public function test_responsive_table_keeps_one_keyboard_scroll_owner(): void {
        $table = new \html_table();
        $table->head = ['Session', 'Minutes'];
        $table->data = [['Theory', 100]];
        $original = clone $table;
        $html = attendancejourneys_responsive_table($table, 'Attendance details');
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $region = '//div[@role="region" and @tabindex="0" and @aria-label="Attendance details"]';
        $this->assertEquals(1, $xpath->query($region)->length);
        $this->assertEquals(1, $xpath->query($region . '/table')->length);
        $this->assertEquals(0, $xpath->query($region . '//div')->length);
        $this->assertStringContainsString('Theory', $html);
        $this->assertEquals($original, $table);
    }

    public function test_official_report_totals_exclude_pending_self_declarations(): void {
        $activity = (object) ['excusedmode' => 'excluded'];
        $sessions = [
            1 => (object) ['id' => 1, 'duration' => 60],
            2 => (object) ['id' => 2, 'duration' => 60],
            3 => (object) ['id' => 3, 'duration' => 120],
        ];
        $records = [
            1 => (object) ['userid' => 10, 'takenby' => 10, 'approved' => 0,
                'status' => 'present', 'minutesabsent' => 0],
            2 => (object) ['userid' => 10, 'takenby' => 20, 'approved' => 1,
                'status' => 'partial', 'minutesabsent' => 15],
            3 => (object) ['userid' => 10, 'takenby' => 10, 'approved' => 1,
                'status' => 'present', 'minutesabsent' => 0],
        ];

        $result = \mod_attendancejourneys\local\calculator::aggregate($activity, $sessions, $records);
        $this->assertSame(2, $result->recordedsessions);
        $this->assertSame(1, $result->pendingapprovals);
        $this->assertSame(165, $result->presentminutes);
        $this->assertSame(180, $result->possibleminutes);
        $this->assertEqualsWithDelta(91.6667, $result->percent, 0.0001);
    }

    public function test_audit_query_isolates_activity_and_combines_every_filter(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $firstactivity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $secondactivity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $participant = $generator->create_user();
        $actor = $generator->create_user();
        $group = $generator->create_group(['courseid' => $course->id]);
        $time = time() - HOURSECS;
        $sessionids = [];
        foreach ([$firstactivity, $secondactivity] as $activity) {
            $sessionids[] = $DB->insert_record('attendancejourneys_sessions', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => 0, 'name' => 'Audit session',
                'sessiondate' => $time, 'duration' => 60, 'groupid' => $activity->id === $firstactivity->id
                    ? $group->id : 0, 'description' => '', 'modality' => 'unspecified', 'location' => '',
                'meetingurl' => '', 'timecreated' => $time, 'timemodified' => $time,
            ]);
        }
        foreach ($sessionids as $index => $sessionid) {
            $recordid = $DB->insert_record('attendancejourneys_records', (object) [
                'sessionid' => $sessionid, 'userid' => $participant->id, 'status' => 'present',
                'minutesabsent' => 0, 'remarks' => '', 'takenby' => $actor->id, 'approved' => 1,
                'approvedby' => $actor->id, 'timeapproved' => $time, 'changesrequested' => 0,
                'reviewnote' => '', 'reviewedby' => 0, 'timereviewed' => 0, 'timemodified' => $time,
            ]);
            $DB->insert_record('attendancejourneys_reviews', (object) [
                'recordid' => $recordid, 'sessionid' => $sessionid, 'userid' => $participant->id,
                'action' => $index === 0 ? 'approved' : 'recorded', 'actorid' => $actor->id,
                'note' => 'Élève vérifié', 'timecreated' => $time,
            ]);
        }

        [$sql, $params] = attendancejourneys_audit_query((int) $firstactivity->id, [
            'userid' => $participant->id, 'sessionid' => $sessionids[0], 'groupid' => $group->id,
            'action' => 'approved', 'from' => $time - 1, 'to' => $time + 1,
        ]);
        $records = $DB->get_records_sql('SELECT arv.*' . $sql, $params);
        $this->assertCount(1, $records);
        $review = reset($records);
        $this->assertSame((int) $sessionids[0], (int) $review->sessionid);
        $this->assertSame('approved', $review->action);
        $this->assertSame('Élève vérifié', $review->note);

        [$sql, $params] = attendancejourneys_audit_query((int) $firstactivity->id, [
            'userid' => 0, 'sessionid' => 0, 'groupid' => -1, 'action' => '', 'from' => 0, 'to' => 0,
        ]);
        $this->assertCount(1, $DB->get_records_sql('SELECT arv.*' . $sql, $params));
    }

    public function test_required_moodle_export_formats_are_available(): void {
        foreach (['csv', 'excel', 'html', 'json', 'ods', 'pdf'] as $required) {
            $writer = \core\dataformat::get_format_instance($required);
            $this->assertInstanceOf(
                \core\dataformat\base::class,
                $writer,
                'Missing Moodle data format: ' . $required
            );
        }
    }

    public function test_every_required_format_writes_french_content(): void {
        $columns = ['participant' => 'Participant', 'note' => 'Remarque'];
        $rows = new \ArrayIterator([(object) [
            'participant' => 'Élodie Létourneau', 'note' => 'Présence vérifiée — 100 %',
        ]]);
        foreach (['csv', 'excel', 'html', 'json', 'ods', 'pdf'] as $format) {
            $path = \core\dataformat::write_data('attendancejourneys-export-test', $format, $columns, $rows);
            $this->assertFileExists($path, $format);
            $this->assertGreaterThan(0, filesize($path), $format);
        }
    }
}
