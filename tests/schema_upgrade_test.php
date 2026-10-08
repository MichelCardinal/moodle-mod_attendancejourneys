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

#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_attendancejourneys_upgrade')]
/**
 * Exercises the complete RC6 schema upgrade without converting historical obligations.
 *
 * @covers ::xmldb_attendancejourneys_upgrade
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schema_upgrade_test extends \advanced_testcase {
    /**
     * RC6 rows and protected Moodle outcomes survive all post-RC6 upgrade steps.
     */
    public function test_rc6_upgrade_preserves_historical_rows_and_protected_zero_grade(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $activity = $generator->create_module('attendancejourneys', [
            'course' => $course->id, 'journeygrading' => 0, 'gradeenabled' => 1,
            'excusedmode' => 'excluded', 'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionrecorded' => 1,
        ]);
        $this->assertSame(0, (int) $activity->journeygrading);
        $journeys = [];
        $sessions = [];
        foreach ([0, 1] as $index) {
            $journeys[] = $DB->insert_record('attendancejourneys_journeys', (object) [
                'attendancejourneysid' => $activity->id, 'name' => 'Historical journey ' . $index,
                'active' => $index, 'timecreated' => time() - DAYSECS,
            ]);
            $sessions[] = $DB->insert_record('attendancejourneys_sessions', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journeys[$index],
                'name' => 'Historical session ' . $index, 'duration' => 100,
                'sessiondate' => time() - DAYSECS,
            ]);
            $DB->insert_record('attendancejourneys_records', (object) [
                'sessionid' => $sessions[$index], 'userid' => $student->id,
                'status' => $index ? 'excused' : 'present', 'approved' => 1,
                'takenby' => 2, 'remarks' => 'Original historical attendance',
            ]);
            $DB->insert_record('attendancejourneys_members', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journeys[$index],
                'userid' => $student->id, 'attemptnumber' => $index + 1, 'active' => $index,
                'timeassigned' => time() - DAYSECS, 'timeended' => $index ? 0 : time(),
            ]);
        }
        // A frozen historical snapshot must not be replaced by the current attendance calculation.
        $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeys[0], 'userid' => $student->id,
            'active' => 1, 'percentage' => 90, 'threshold' => 80, 'result' => 'passed',
            'presentminutes' => 90, 'possibleminutes' => 100, 'closedby' => 2, 'timeclosed' => time(),
        ]);
        foreach (['approved', 'pending'] as $status) {
            $DB->insert_record('attendancejourneys_equivalences', (object) [
                'attendancejourneysid' => $activity->id, 'userid' => $student->id,
                'targetjourneyid' => $journeys[1], 'targetsessionid' => $sessions[1],
                'sourcesessionid' => $sessions[0], 'status' => $status,
                'decidedby' => $status === 'approved' ? 2 : 0,
                'timedecided' => $status === 'approved' ? time() - 100 : 0,
                'decisionnote' => $status === 'approved' ? 'Only known historical decision' : '',
            ]);
        }
        $item = $DB->get_record('grade_items', [
            'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id,
        ], '*', MUST_EXIST);
        $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $student->id]);
        $protected = (object) ['itemid' => $item->id, 'userid' => $student->id,
            'rawgrade' => 0, 'finalgrade' => 0, 'overridden' => 1, 'locked' => time()];
        if ($grade) {
            $protected->id = $grade->id;
            $DB->update_record('grade_grades', $protected);
        } else {
            $DB->insert_record('grade_grades', $protected);
        }
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $activity->cmid, 'userid' => $student->id,
            'completionstate' => COMPLETION_COMPLETE, 'timemodified' => time(),
        ]);
        $oldxml = new \xmldb_file(__DIR__ . '/fixtures/rc6-install.xml');
        $currentxml = new \xmldb_file(__DIR__ . '/../db/install.xml');
        $oldloaded = $oldxml->loadXMLStructure();
        $this->assertTrue($oldloaded, (string) $oldxml->getStructure()->getError());
        $this->assertTrue($currentxml->loadXMLStructure());
        $oldtables = $oldxml->getStructure()->getTables();
        $currenttables = $currentxml->getStructure()->getTables();
        $fields = [];
        foreach ($oldtables as $table) {
            $fields[$table->getName()] = [];
            foreach ($table->getFields() as $field) {
                $fields[$table->getName()][$field->getName()] = true;
            }
            $this->assertArrayHasKey('id', $fields[$table->getName()]);
        }
        foreach (['grade_items', 'grade_grades', 'course_modules_completion'] as $table) {
            $fields[$table] = array_fill_keys(array_keys($DB->get_columns($table)), true);
        }
        // Validate the exact schema delta before any destructive operation in the isolated test database.
        $removed = [];
        foreach ($currenttables as $table) {
            foreach ($table->getFields() as $field) {
                if (isset($fields[$table->getName()]) && !isset($fields[$table->getName()][$field->getName()])) {
                    $removed[] = $table->getName() . '.' . $field->getName();
                }
            }
        }
        sort($removed);
        $this->assertSame([
            'attendancejourneys.journeygrading', 'attendancejourneys.nextgradeitem',
            'attendancejourneys_journeys.audiencemode', 'attendancejourneys_journeys.gradeitemnumber',
            'attendancejourneys_journeys.passinggrade', 'attendancejourneys_journeys.required',
            'attendancejourneys_journeys.rootjourneyid',
            'attendancejourneys_sessions.cancelled',
        ], $removed);
        $before = $this->snapshot($fields);
        $dbman = $DB->get_manager();
        $installedversion = get_config('mod_attendancejourneys', 'version');
        try {
            foreach ($currenttables as $table) {
                if (!isset($fields[$table->getName()])) {
                    $dbman->drop_table($table);
                    continue;
                }
                foreach ($table->getFields() as $field) {
                    if (!isset($fields[$table->getName()][$field->getName()])) {
                        $dbman->drop_field($table, $field);
                    }
                }
            }
            set_config('version', 2026100705, 'mod_attendancejourneys');
            $this->assertTrue(xmldb_attendancejourneys_upgrade(2026100705));
            $this->assertSame($before, $this->snapshot($fields));
            $plugin = new \stdClass();
            require(__DIR__ . '/../version.php');
            $this->assertEquals($plugin->version, get_config('mod_attendancejourneys', 'version'));
            $this->assertSame(0, (int) $DB->get_field('attendancejourneys', 'journeygrading', ['id' => $activity->id]));
            foreach ($DB->get_records('attendancejourneys_journeys') as $journey) {
                $this->assertNull($journey->passinggrade);
                $this->assertNull($journey->gradeitemnumber);
                $this->assertSame(0, (int) $journey->audiencemode);
                $this->assertSame(1, (int) $journey->required);
                $this->assertSame(0, (int) $journey->rootjourneyid);
            }
            foreach ($DB->get_records('attendancejourneys_sessions') as $session) {
                $this->assertSame(0, (int) $session->cancelled);
            }
            $this->assertSame(0, $DB->count_records('attendancejourneys_obligations'));
            $this->assertSame(0, $DB->count_records('attendancejourneys_attempts'));
            $this->assertSame(0, $DB->count_records('attendancejourneys_obligationlog'));
            $this->assertSame(0, $DB->count_records('attendancejourneys_sessionlog'));
            $log = $DB->get_record('attendancejourneys_equivlog', [], '*', MUST_EXIST);
            $this->assertSame(1, $DB->count_records('attendancejourneys_equivlog'));
            $this->assertSame('baseline', $log->action);
            $this->assertSame('approved', $log->status);
            $this->assertSame('Only known historical decision', $log->note);
        } finally {
            // Restore the PHPUnit schema even when an upgrade or assertion fails.
            foreach ($currenttables as $table) {
                if (!$dbman->table_exists($table)) {
                    $dbman->create_table($table);
                    continue;
                }
                foreach ($table->getFields() as $field) {
                    if (!$dbman->field_exists($table, $field)) {
                        $dbman->add_field($table, $field);
                    }
                }
            }
            set_config('version', $installedversion, 'mod_attendancejourneys');
        }
    }

    /**
     * Capture original columns with stable key order while retaining exact persisted values.
     *
     * @param array $fields Allowed column names indexed by table.
     * @return array Persisted rows indexed by table and row identifier.
     */
    private function snapshot(array $fields): array {
        global $DB;
        $state = [];
        foreach ($fields as $table => $columns) {
            $state[$table] = [];
            foreach ($DB->get_records($table, [], 'id') as $record) {
                $values = array_intersect_key((array) $record, $columns);
                ksort($values);
                $state[$table][(int) $record->id] = $values;
            }
        }
        return $state;
    }
}
