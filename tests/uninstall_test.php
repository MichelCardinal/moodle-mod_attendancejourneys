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

#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_attendancejourneys_uninstall')]
/**
 * Verifies that uninstalling activities removes Moodle-owned state as well.
 *
 * @covers ::xmldb_attendancejourneys_uninstall
 * @package    mod_attendancejourneys
 * @category   test
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class uninstall_test extends \advanced_testcase {
    public function test_uninstall_cleans_completion_and_preserves_other_activities(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/attendancejourneys/db/uninstall.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_and_enrol($course, 'student');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $contexts = [];
        foreach ([1, 2] as $number) {
            $activity = $generator->create_module('attendancejourneys', [
                'course' => $course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionrecorded' => 1, 'gradeenabled' => 1,
            ]);
            $DB->insert_record('course_modules_completion', (object) [
                'coursemoduleid' => $activity->cmid, 'userid' => $user->id,
                'completionstate' => COMPLETION_COMPLETE, 'timemodified' => time(),
            ]);
            $context = \context_module::instance($activity->cmid);
            $contexts[] = $context->id;
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id, 'component' => 'mod_attendancejourneys',
                'filearea' => 'intro', 'itemid' => 0, 'filepath' => '/', 'filename' => 'test.txt',
            ], 'Synthetic uninstall fixture');
        }
        $this->assertTrue(xmldb_attendancejourneys_uninstall());
        $this->assertSame(0, $DB->count_records('attendancejourneys'));
        $this->assertSame(0, $DB->count_records('course_modules_completion'));
        $this->assertSame(0, $DB->count_records('files', ['component' => 'mod_attendancejourneys']));
        $this->assertSame(0, $DB->count_records('grade_items', ['itemmodule' => 'attendancejourneys']));
        foreach ($contexts as $contextid) {
            $this->assertFalse($DB->record_exists('context', ['id' => $contextid]));
        }
        $this->assertTrue($DB->record_exists('course_modules', ['id' => $page->cmid]));
        $this->assertTrue($DB->record_exists('page', ['id' => $page->id]));
        $this->assertTrue($DB->record_exists('course', ['id' => $course->id]));
        $this->assertTrue(xmldb_attendancejourneys_uninstall());
    }
}
