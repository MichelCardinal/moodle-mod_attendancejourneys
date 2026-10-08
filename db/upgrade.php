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

/**
 * Database upgrades for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades Attendance Journeys.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 */
function xmldb_attendancejourneys_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081215) {
        $table = new xmldb_table('attendancejourneys');
        $fields = [
            new xmldb_field('completionrecorded', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'excusedmode'),
            new xmldb_field(
                'completionallsessions',
                XMLDB_TYPE_INTEGER,
                '1',
                null,
                XMLDB_NOTNULL,
                null,
                '0',
                'completionrecorded'
            ),
            new xmldb_field(
                'completionpass',
                XMLDB_TYPE_INTEGER,
                '1',
                null,
                XMLDB_NOTNULL,
                null,
                '0',
                'completionallsessions'
            ),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026081215, 'attendancejourneys');
    }

    if ($oldversion < 2026081216) {
        $table = new xmldb_table('attendancejourneys');
        $field = new xmldb_field(
            'studentselfrecord',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'completionpass'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026081216, 'attendancejourneys');
    }

    if ($oldversion < 2026081218) {
        $table = new xmldb_table('attendancejourneys_sessions');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'duration');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026081218, 'attendancejourneys');
    }

    if ($oldversion < 2026081219) {
        $table = new xmldb_table('attendancejourneys_sessions');
        $index = new xmldb_index('group', XMLDB_INDEX_NOTUNIQUE, ['groupid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_mod_savepoint(true, 2026081219, 'attendancejourneys');
    }

    if ($oldversion < 2026081229) {
        $table = new xmldb_table('attendancejourneys');
        $field = new xmldb_field(
            'gradeenabled',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'passinggrade'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026081229, 'attendancejourneys');
    }

    if ($oldversion < 2026081230) {
        $activitytable = new xmldb_table('attendancejourneys');
        $completionfield = new xmldb_field(
            'completionclosed',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'completionpass'
        );
        if (!$dbman->field_exists($activitytable, $completionfield)) {
            $dbman->add_field($activitytable, $completionfield);
        }

        $table = new xmldb_table('attendancejourneys_closures');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('applicablesessions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('recordedsessions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('presentminutes', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('possibleminutes', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('percentage', XMLDB_TYPE_NUMBER, '7', '3', null, null, null);
            $table->add_field('threshold', XMLDB_TYPE_NUMBER, '5', '2', XMLDB_NOTNULL, null, '0');
            $table->add_field('result', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none');
            $table->add_field('closedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeclosed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('reopenedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timereopened', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('activity', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
            $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('activityuseractive', XMLDB_INDEX_NOTUNIQUE, ['attendancejourneysid', 'userid', 'active']);
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026081230, 'attendancejourneys');
    }

    if ($oldversion < 2026081232) {
        $table = new xmldb_table('attendancejourneys_journeys');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('description', XMLDB_TYPE_TEXT);
            $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('startdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('enddate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('activity', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
            $table->add_key('group', XMLDB_KEY_FOREIGN, ['groupid'], 'groups', ['id']);
            $table->add_index('activityactive', XMLDB_INDEX_NOTUNIQUE, ['attendancejourneysid', 'active']);
            $dbman->create_table($table);
        }
        $sessiontable = new xmldb_table('attendancejourneys_sessions');
        $field = new xmldb_field(
            'journeyid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'attendancejourneysid'
        );
        if (!$dbman->field_exists($sessiontable, $field)) {
            $dbman->add_field($sessiontable, $field);
        }
        upgrade_mod_savepoint(true, 2026081232, 'attendancejourneys');
    }

    if ($oldversion < 2026081233) {
        upgrade_mod_savepoint(true, 2026081233, 'attendancejourneys');
    }

    if ($oldversion < 2026081234) {
        $journeytable = new xmldb_table('attendancejourneys_journeys');
        $completedby = new xmldb_field('completedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'active');
        if (!$dbman->field_exists($journeytable, $completedby)) {
            $dbman->add_field($journeytable, $completedby);
        }
        $timecompleted = new xmldb_field(
            'timecompleted',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'completedby'
        );
        if (!$dbman->field_exists($journeytable, $timecompleted)) {
            $dbman->add_field($journeytable, $timecompleted);
        }
        $closuretable = new xmldb_table('attendancejourneys_closures');
        $journeyid = new xmldb_field(
            'journeyid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'attendancejourneysid'
        );
        if (!$dbman->field_exists($closuretable, $journeyid)) {
            $dbman->add_field($closuretable, $journeyid);
        }
        upgrade_mod_savepoint(true, 2026081234, 'attendancejourneys');
    }

    if ($oldversion < 2026081235) {
        upgrade_mod_savepoint(true, 2026081235, 'attendancejourneys');
    }

    if ($oldversion < 2026081236) {
        upgrade_mod_savepoint(true, 2026081236, 'attendancejourneys');
    }

    if ($oldversion < 2026081237) {
        upgrade_mod_savepoint(true, 2026081237, 'attendancejourneys');
    }

    if ($oldversion < 2026081238) {
        upgrade_mod_savepoint(true, 2026081238, 'attendancejourneys');
    }

    if ($oldversion < 2026081239) {
        upgrade_mod_savepoint(true, 2026081239, 'attendancejourneys');
    }

    if ($oldversion < 2026081240) {
        upgrade_mod_savepoint(true, 2026081240, 'attendancejourneys');
    }

    if ($oldversion < 2026081241) {
        upgrade_mod_savepoint(true, 2026081241, 'attendancejourneys');
    }

    if ($oldversion < 2026081242) {
        upgrade_mod_savepoint(true, 2026081242, 'attendancejourneys');
    }

    if ($oldversion < 2026081243) {
        upgrade_mod_savepoint(true, 2026081243, 'attendancejourneys');
    }

    if ($oldversion < 2026081244) {
        upgrade_mod_savepoint(true, 2026081244, 'attendancejourneys');
    }

    if ($oldversion < 2026081245) {
        upgrade_mod_savepoint(true, 2026081245, 'attendancejourneys');
    }

    if ($oldversion < 2026081246) {
        upgrade_mod_savepoint(true, 2026081246, 'attendancejourneys');
    }

    if ($oldversion < 2026081247) {
        upgrade_mod_savepoint(true, 2026081247, 'attendancejourneys');
    }

    if ($oldversion < 2026081248) {
        upgrade_mod_savepoint(true, 2026081248, 'attendancejourneys');
    }

    if ($oldversion < 2026081249) {
        upgrade_mod_savepoint(true, 2026081249, 'attendancejourneys');
    }

    if ($oldversion < 2026081250) {
        upgrade_mod_savepoint(true, 2026081250, 'attendancejourneys');
    }

    if ($oldversion < 2026081251) {
        upgrade_mod_savepoint(true, 2026081251, 'attendancejourneys');
    }

    if ($oldversion < 2026081252) {
        upgrade_mod_savepoint(true, 2026081252, 'attendancejourneys');
    }

    if ($oldversion < 2026081253) {
        upgrade_mod_savepoint(true, 2026081253, 'attendancejourneys');
    }

    if ($oldversion < 2026081254) {
        upgrade_mod_savepoint(true, 2026081254, 'attendancejourneys');
    }

    if ($oldversion < 2026081255) {
        upgrade_mod_savepoint(true, 2026081255, 'attendancejourneys');
    }

    if ($oldversion < 2026081256) {
        $table = new xmldb_table('attendancejourneys_members');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('journeyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('attemptnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timeassigned', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeended', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('activity', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
            $table->add_key('journey', XMLDB_KEY_FOREIGN, ['journeyid'], 'attendancejourneys_journeys', ['id']);
            $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('journeyuser', XMLDB_INDEX_UNIQUE, ['journeyid', 'userid']);
            $table->add_index(
                'activityuseractive',
                XMLDB_INDEX_NOTUNIQUE,
                ['attendancejourneysid', 'userid', 'active']
            );
            $dbman->create_table($table);
        }
        require_once(__DIR__ . '/../locallib.php');
        foreach ($DB->get_records('attendancejourneys_journeys') as $journey) {
            $cm = get_coursemodule_from_instance(
                'attendancejourneys',
                $journey->attendancejourneysid,
                0,
                false,
                IGNORE_MISSING
            );
            if ($cm) {
                attendancejourneys_assign_journey_members(context_module::instance($cm->id), $journey);
                // Preserve participants already represented by a historical closure even if
                // their enrolment or group membership is no longer active at upgrade time.
                $closures = $DB->get_records('attendancejourneys_closures', ['journeyid' => $journey->id]);
                foreach ($closures as $closure) {
                    if (
                        $DB->record_exists('attendancejourneys_members', [
                            'journeyid' => $journey->id, 'userid' => $closure->userid])
                    ) {
                        continue;
                    }
                    $attempt = 1 + (int) $DB->get_field_sql(
                        'SELECT COALESCE(MAX(attemptnumber), 0) FROM {attendancejourneys_members}
                          WHERE attendancejourneysid = ? AND userid = ?',
                        [$journey->attendancejourneysid, $closure->userid]
                    );
                    $DB->insert_record('attendancejourneys_members', (object) [
                        'attendancejourneysid' => $journey->attendancejourneysid,
                        'journeyid' => $journey->id,
                        'userid' => $closure->userid,
                        'attemptnumber' => $attempt,
                        'active' => (int) $journey->active,
                        'timeassigned' => (int) ($journey->timecreated ?? $closure->timeclosed),
                        'timeended' => empty($journey->active) ? (int) $journey->timecompleted : 0,
                    ]);
                }
                if (empty($journey->active)) {
                    $DB->set_field('attendancejourneys_members', 'active', 0, ['journeyid' => $journey->id]);
                    $DB->set_field(
                        'attendancejourneys_members',
                        'timeended',
                        (int) $journey->timecompleted,
                        ['journeyid' => $journey->id]
                    );
                }
            }
        }
        upgrade_mod_savepoint(true, 2026081256, 'attendancejourneys');
    }

    if ($oldversion < 2026081257) {
        upgrade_mod_savepoint(true, 2026081257, 'attendancejourneys');
    }

    if ($oldversion < 2026081258) {
        upgrade_mod_savepoint(true, 2026081258, 'attendancejourneys');
    }

    if ($oldversion < 2026081259) {
        upgrade_mod_savepoint(true, 2026081259, 'attendancejourneys');
    }

    if ($oldversion < 2026081260) {
        upgrade_mod_savepoint(true, 2026081260, 'attendancejourneys');
    }

    if ($oldversion < 2026081261) {
        upgrade_mod_savepoint(true, 2026081261, 'attendancejourneys');
    }

    if ($oldversion < 2026081262) {
        upgrade_mod_savepoint(true, 2026081262, 'attendancejourneys');
    }

    if ($oldversion < 2026081263) {
        upgrade_mod_savepoint(true, 2026081263, 'attendancejourneys');
    }

    if ($oldversion < 2026081264) {
        // Rebuild calendar events for sessions created before calendar integration.
        attendancejourneys_refresh_events();
        upgrade_mod_savepoint(true, 2026081264, 'attendancejourneys');
    }

    if ($oldversion < 2026081265) {
        upgrade_mod_savepoint(true, 2026081265, 'attendancejourneys');
    }

    if ($oldversion < 2026081266) {
        // Restore decimal precision and indexes omitted by early alpha upgrades.
        $closuretable = new xmldb_table('attendancejourneys_closures');
        $percentagefield = new xmldb_field(
            'percentage',
            XMLDB_TYPE_NUMBER,
            '7,3',
            null,
            null,
            null,
            null,
            'possibleminutes'
        );
        $thresholdfield = new xmldb_field(
            'threshold',
            XMLDB_TYPE_NUMBER,
            '5,2',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'percentage'
        );
        $dbman->change_field_precision($closuretable, $percentagefield);
        $dbman->change_field_precision($closuretable, $thresholdfield);

        $journeytable = new xmldb_table('attendancejourneys_journeys');
        $completedbyindex = new xmldb_index('completedby', XMLDB_INDEX_NOTUNIQUE, ['completedby']);
        if (!$dbman->index_exists($journeytable, $completedbyindex)) {
            $dbman->add_index($journeytable, $completedbyindex);
        }
        $sessiontable = new xmldb_table('attendancejourneys_sessions');
        $journeyindex = new xmldb_index('journey', XMLDB_INDEX_NOTUNIQUE, ['journeyid']);
        if (!$dbman->index_exists($sessiontable, $journeyindex)) {
            $dbman->add_index($sessiontable, $journeyindex);
        }

        // Recalculate values that may have been rounded by the incorrect column precision.
        $thresholds = $DB->get_records_menu('attendancejourneys', null, '', 'id,passinggrade');
        foreach ($DB->get_records('attendancejourneys_closures') as $closure) {
            $closure->percentage = (int) $closure->possibleminutes > 0 ?
                round(((int) $closure->presentminutes / (int) $closure->possibleminutes) * 100, 3) : null;
            $closure->threshold = $thresholds[$closure->attendancejourneysid] ?? $closure->threshold;
            $DB->update_record('attendancejourneys_closures', $closure);
        }
        upgrade_mod_savepoint(true, 2026081266, 'attendancejourneys');
    }

    if ($oldversion < 2026081267) {
        // XMLDB number precision is expressed as "total,decimals" in one argument.
        $closuretable = new xmldb_table('attendancejourneys_closures');
        $percentagefield = new xmldb_field(
            'percentage',
            XMLDB_TYPE_NUMBER,
            '7,3',
            null,
            null,
            null,
            null,
            'possibleminutes'
        );
        $thresholdfield = new xmldb_field(
            'threshold',
            XMLDB_TYPE_NUMBER,
            '5,2',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'percentage'
        );
        $dbman->change_field_precision($closuretable, $percentagefield);
        $dbman->change_field_precision($closuretable, $thresholdfield);

        $thresholds = $DB->get_records_menu('attendancejourneys', null, '', 'id,passinggrade');
        foreach ($DB->get_records('attendancejourneys_closures') as $closure) {
            $closure->percentage = (int) $closure->possibleminutes > 0 ?
                round(((int) $closure->presentminutes / (int) $closure->possibleminutes) * 100, 3) : null;
            $closure->threshold = $thresholds[$closure->attendancejourneysid] ?? $closure->threshold;
            $DB->update_record('attendancejourneys_closures', $closure);
        }
        upgrade_mod_savepoint(true, 2026081267, 'attendancejourneys');
    }

    if ($oldversion < 2026081268) {
        upgrade_mod_savepoint(true, 2026081268, 'attendancejourneys');
    }

    if ($oldversion < 2026081269) {
        upgrade_mod_savepoint(true, 2026081269, 'attendancejourneys');
    }

    if ($oldversion < 2026081270) {
        upgrade_mod_savepoint(true, 2026081270, 'attendancejourneys');
    }

    if ($oldversion < 2026081271) {
        upgrade_mod_savepoint(true, 2026081271, 'attendancejourneys');
    }

    if ($oldversion < 2026081273) {
        $activitytable = new xmldb_table('attendancejourneys');
        $calendarenabled = new xmldb_field(
            'calendarenabled',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'studentselfrecord'
        );
        if (!$dbman->field_exists($activitytable, $calendarenabled)) {
            $dbman->add_field($activitytable, $calendarenabled);
        }

        $journeytable = new xmldb_table('attendancejourneys_journeys');
        $defaultmodality = new xmldb_field(
            'defaultmodality',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'unspecified',
            'enddate'
        );
        if (!$dbman->field_exists($journeytable, $defaultmodality)) {
            $dbman->add_field($journeytable, $defaultmodality);
        }

        $sessiontable = new xmldb_table('attendancejourneys_sessions');
        $sessionfields = [
            new xmldb_field(
                'modality',
                XMLDB_TYPE_CHAR,
                '20',
                null,
                XMLDB_NOTNULL,
                null,
                'unspecified',
                'description'
            ),
            new xmldb_field('location', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'modality'),
            new xmldb_field('meetingurl', XMLDB_TYPE_TEXT, null, null, null, null, null, 'location'),
        ];
        foreach ($sessionfields as $field) {
            if (!$dbman->field_exists($sessiontable, $field)) {
                $dbman->add_field($sessiontable, $field);
            }
        }

        // Calendar publication becomes explicit opt-in. Remove events created by previous alpha versions
        // through Moodle's calendar API so observers and caches remain coherent.
        require_once($CFG->dirroot . '/calendar/lib.php');
        foreach ($DB->get_records('event', ['modulename' => 'attendancejourneys']) as $event) {
            calendar_event::load($event)->delete(false);
        }
        upgrade_mod_savepoint(true, 2026081273, 'attendancejourneys');
    }

    if ($oldversion < 2026081274) {
        upgrade_mod_savepoint(true, 2026081274, 'attendancejourneys');
    }

    if ($oldversion < 2026081275) {
        upgrade_mod_savepoint(true, 2026081275, 'attendancejourneys');
    }

    if ($oldversion < 2026081276) {
        upgrade_mod_savepoint(true, 2026081276, 'attendancejourneys');
    }

    if ($oldversion < 2026081277) {
        upgrade_mod_savepoint(true, 2026081277, 'attendancejourneys');
    }

    if ($oldversion < 2026081278) {
        upgrade_mod_savepoint(true, 2026081278, 'attendancejourneys');
    }

    if ($oldversion < 2026081279) {
        upgrade_mod_savepoint(true, 2026081279, 'attendancejourneys');
    }

    if ($oldversion < 2026081280) {
        upgrade_mod_savepoint(true, 2026081280, 'attendancejourneys');
    }

    if ($oldversion < 2026081281) {
        upgrade_mod_savepoint(true, 2026081281, 'attendancejourneys');
    }

    if ($oldversion < 2026081282) {
        upgrade_mod_savepoint(true, 2026081282, 'attendancejourneys');
    }

    if ($oldversion < 2026081283) {
        upgrade_mod_savepoint(true, 2026081283, 'attendancejourneys');
    }

    if ($oldversion < 2026081284) {
        upgrade_mod_savepoint(true, 2026081284, 'attendancejourneys');
    }

    if ($oldversion < 2026081285) {
        upgrade_mod_savepoint(true, 2026081285, 'attendancejourneys');
    }

    if ($oldversion < 2026081286) {
        upgrade_mod_savepoint(true, 2026081286, 'attendancejourneys');
    }

    if ($oldversion < 2026081287) {
        upgrade_mod_savepoint(true, 2026081287, 'attendancejourneys');
    }

    if ($oldversion < 2026081288) {
        upgrade_mod_savepoint(true, 2026081288, 'attendancejourneys');
    }

    if ($oldversion < 2026081289) {
        upgrade_mod_savepoint(true, 2026081289, 'attendancejourneys');
    }

    if ($oldversion < 2026081290) {
        upgrade_mod_savepoint(true, 2026081290, 'attendancejourneys');
    }

    if ($oldversion < 2026081291) {
        $sessiontable = new xmldb_table('attendancejourneys_sessions');
        foreach (
            [
            new xmldb_index('activitydate', XMLDB_INDEX_NOTUNIQUE, ['attendancejourneysid', 'sessiondate']),
            new xmldb_index('activityjourney', XMLDB_INDEX_NOTUNIQUE, ['attendancejourneysid', 'journeyid']),
            ] as $index
        ) {
            if (!$dbman->index_exists($sessiontable, $index)) {
                $dbman->add_index($sessiontable, $index);
            }
        }

        $recordtable = new xmldb_table('attendancejourneys_records');
        $useridindex = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($recordtable, $useridindex)) {
            $dbman->add_index($recordtable, $useridindex);
        }

        $closuretable = new xmldb_table('attendancejourneys_closures');
        $journeyindex = new xmldb_index(
            'journeyuseractive',
            XMLDB_INDEX_NOTUNIQUE,
            ['journeyid', 'userid', 'active']
        );
        if (!$dbman->index_exists($closuretable, $journeyindex)) {
            $dbman->add_index($closuretable, $journeyindex);
        }
        upgrade_mod_savepoint(true, 2026081291, 'attendancejourneys');
    }

    if ($oldversion < 2026081292) {
        // First release candidate. No schema change is required for this milestone.
        upgrade_mod_savepoint(true, 2026081292, 'attendancejourneys');
    }

    if ($oldversion < 2026081293) {
        // Student self-recording interface harmonisation. No schema change is required.
        upgrade_mod_savepoint(true, 2026081293, 'attendancejourneys');
    }

    if ($oldversion < 2026081294) {
        // Secure multi-session student self-recording. No schema change is required.
        upgrade_mod_savepoint(true, 2026081294, 'attendancejourneys');
    }

    if ($oldversion < 2026081295) {
        // Attendance-entry provenance labels. No schema change is required.
        upgrade_mod_savepoint(true, 2026081295, 'attendancejourneys');
    }

    if ($oldversion < 2026081296) {
        // Consistent staff lock across individual and multi-session self-recording.
        upgrade_mod_savepoint(true, 2026081296, 'attendancejourneys');
    }

    if ($oldversion < 2026081297) {
        // Attendance-source filter for staff review. No schema change is required.
        upgrade_mod_savepoint(true, 2026081297, 'attendancejourneys');
    }

    if ($oldversion < 2026081298) {
        // Preserve recorder provenance when a submitted row is unchanged.
        upgrade_mod_savepoint(true, 2026081298, 'attendancejourneys');
    }

    if ($oldversion < 2026081299) {
        $table = new xmldb_table('attendancejourneys_records');
        $fields = [
            new xmldb_field('approved', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'takenby'),
            new xmldb_field('approvedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'approved'),
            new xmldb_field('timeapproved', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'approvedby'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2026081299, 'attendancejourneys');
    }

    if ($oldversion < 2026081300) {
        // Approval supervision and filters. No schema change is required.
        upgrade_mod_savepoint(true, 2026081300, 'attendancejourneys');
    }

    if ($oldversion < 2026081301) {
        // Stable session action labels and participant-facing approval states.
        upgrade_mod_savepoint(true, 2026081301, 'attendancejourneys');
    }

    if ($oldversion < 2026081302) {
        // Approval audit columns in detailed exports and privacy exports.
        upgrade_mod_savepoint(true, 2026081302, 'attendancejourneys');
    }

    if ($oldversion < 2026081303) {
        // Staff approval work queue on the attendance session selector.
        upgrade_mod_savepoint(true, 2026081303, 'attendancejourneys');
    }

    if ($oldversion < 2026081304) {
        // Pending participant declarations are excluded from official outcomes until approved.
        upgrade_mod_savepoint(true, 2026081304, 'attendancejourneys');
    }

    if ($oldversion < 2026081305) {
        $table = new xmldb_table('attendancejourneys_records');
        $fields = [
            new xmldb_field(
                'changesrequested',
                XMLDB_TYPE_INTEGER,
                '1',
                null,
                XMLDB_NOTNULL,
                null,
                '0',
                'timeapproved'
            ),
            new xmldb_field('reviewnote', XMLDB_TYPE_TEXT, null, null, null, null, null, 'changesrequested'),
            new xmldb_field('reviewedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'reviewnote'),
            new xmldb_field('timereviewed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'reviewedby'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2026081305, 'attendancejourneys');
    }

    if ($oldversion < 2026081306) {
        // Separate correction-request supervision and audit exports.
        upgrade_mod_savepoint(true, 2026081306, 'attendancejourneys');
    }

    if ($oldversion < 2026081307) {
        $table = new xmldb_table('attendancejourneys_reviews');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('recordid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('action', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, '');
        $table->add_field('actorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('note', XMLDB_TYPE_TEXT, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('record', XMLDB_KEY_FOREIGN, ['recordid'], 'attendancejourneys_records', ['id']);
        $table->add_key('session', XMLDB_KEY_FOREIGN, ['sessionid'], 'attendancejourneys_sessions', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('recordtime', XMLDB_INDEX_NOTUNIQUE, ['recordid', 'timecreated']);
        $table->add_index('usertime', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026081307, 'attendancejourneys');
    }

    if ($oldversion < 2026081308) {
        upgrade_mod_savepoint(true, 2026081308, 'attendancejourneys');
    }

    if ($oldversion < 2026081309) {
        // Stabilisation release: no schema change.
        upgrade_mod_savepoint(true, 2026081309, 'attendancejourneys');
    }

    if ($oldversion < 2026081310) {
        // Documentation and production-readiness release: no schema change.
        upgrade_mod_savepoint(true, 2026081310, 'attendancejourneys');
    }

    if ($oldversion < 2026081311) {
        // Contextual Moodle help release: no schema change.
        upgrade_mod_savepoint(true, 2026081311, 'attendancejourneys');
    }

    if ($oldversion < 2026081312) {
        // Bilingual documentation release: no schema change.
        upgrade_mod_savepoint(true, 2026081312, 'attendancejourneys');
    }

    if ($oldversion < 2026081313) {
        // Accessibility and role-permission audit release: no schema change.
        upgrade_mod_savepoint(true, 2026081313, 'attendancejourneys');
    }

    if ($oldversion < 2026081314) {
        // Data lifecycle and privacy audit release: no schema change.
        upgrade_mod_savepoint(true, 2026081314, 'attendancejourneys');
    }

    if ($oldversion < 2026081315) {
        // XMLDB does not permit empty-string defaults on required character fields.
        foreach (
            [
            ['attendancejourneys', 'name', '255'],
            ['attendancejourneys_journeys', 'name', '255'],
            ['attendancejourneys_sessions', 'name', '255'],
            ['attendancejourneys_sessions', 'location', '255'],
            ['attendancejourneys_reviews', 'action', '30'],
            ] as [$tablename, $fieldname, $length]
        ) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field($fieldname, XMLDB_TYPE_CHAR, $length, null, XMLDB_NOTNULL, null, null);
            $dbman->change_field_default($table, $field);
        }
        upgrade_mod_savepoint(true, 2026081315, 'attendancejourneys');
    }

    if ($oldversion < 2026081316) {
        // Expanded automated test coverage release: no schema change.
        upgrade_mod_savepoint(true, 2026081316, 'attendancejourneys');
    }

    if ($oldversion < 2026081317) {
        // Backup and restore integration-test release: no schema change.
        upgrade_mod_savepoint(true, 2026081317, 'attendancejourneys');
    }

    if ($oldversion < 2026081318) {
        // Gradebook and completion integration-test release: no schema change.
        upgrade_mod_savepoint(true, 2026081318, 'attendancejourneys');
    }

    if ($oldversion < 2026081319) {
        // Moodle calendar integration-test release: no schema change.
        upgrade_mod_savepoint(true, 2026081319, 'attendancejourneys');
    }

    if ($oldversion < 2026081320) {
        // Student declaration locking and approval-workflow test release: no schema change.
        upgrade_mod_savepoint(true, 2026081320, 'attendancejourneys');
    }

    if ($oldversion < 2026081321) {
        // Session targeting, enrolment and journey-attempt test release: no schema change.
        upgrade_mod_savepoint(true, 2026081321, 'attendancejourneys');
    }

    if ($oldversion < 2026081322) {
        // Administrative deletion and participant-reset hardening release: no schema change.
        upgrade_mod_savepoint(true, 2026081322, 'attendancejourneys');
    }

    if ($oldversion < 2026081323) {
        // Session lifecycle and final-result protection test release: no schema change.
        upgrade_mod_savepoint(true, 2026081323, 'attendancejourneys');
    }

    if ($oldversion < 2026081324) {
        // Reports, exports and form-validation hardening release: no schema change.
        upgrade_mod_savepoint(true, 2026081324, 'attendancejourneys');
    }

    if ($oldversion < 2026081400) {
        // Reviewable session-series creation release: no schema change.
        upgrade_mod_savepoint(true, 2026081400, 'attendancejourneys');
    }

    if ($oldversion < 2026081401) {
        // Automatic naming and bulk series-preview actions release: no schema change.
        upgrade_mod_savepoint(true, 2026081401, 'attendancejourneys');
    }

    if ($oldversion < 2026081402) {
        // Per-occurrence delivery details in series previews release: no schema change.
        upgrade_mod_savepoint(true, 2026081402, 'attendancejourneys');
    }

    if ($oldversion < 2026081403) {
        // Series selection shortcuts and overlap protection release: no schema change.
        upgrade_mod_savepoint(true, 2026081403, 'attendancejourneys');
    }

    if ($oldversion < 2026081404) {
        // Detailed overlap-report release: no schema change.
        upgrade_mod_savepoint(true, 2026081404, 'attendancejourneys');
    }

    if ($oldversion < 2026081405) {
        // Bulk date and time shifting for series previews release: no schema change.
        upgrade_mod_savepoint(true, 2026081405, 'attendancejourneys');
    }

    if ($oldversion < 2026081406) {
        // Chronological sorting and series-summary release: no schema change.
        upgrade_mod_savepoint(true, 2026081406, 'attendancejourneys');
    }

    if ($oldversion < 2026081407) {
        // Responsive session-series review presentation release: no schema change.
        upgrade_mod_savepoint(true, 2026081407, 'attendancejourneys');
    }

    if ($oldversion < 2026081408) {
        // Session-series visual QA and action hierarchy release: no schema change.
        upgrade_mod_savepoint(true, 2026081408, 'attendancejourneys');
    }

    if ($oldversion < 2026081409) {
        // Journey and calendar lifecycle hardening release: no schema change.
        upgrade_mod_savepoint(true, 2026081409, 'attendancejourneys');
    }

    if ($oldversion < 2026081410) {
        // Existing-session bulk scheduling release: no schema change.
        upgrade_mod_savepoint(true, 2026081410, 'attendancejourneys');
    }

    if ($oldversion < 2026081411) {
        // Existing-session bulk details release: no schema change.
        upgrade_mod_savepoint(true, 2026081411, 'attendancejourneys');
    }

    if ($oldversion < 2026081412) {
        // Existing-session bulk audience and journey assignment release: no schema change.
        upgrade_mod_savepoint(true, 2026081412, 'attendancejourneys');
    }

    if ($oldversion < 2026081413) {
        // Existing-session bulk names and descriptions release: no schema change.
        upgrade_mod_savepoint(true, 2026081413, 'attendancejourneys');
    }

    if ($oldversion < 2026081414) {
        // Existing-session bulk duplication release: no schema change.
        upgrade_mod_savepoint(true, 2026081414, 'attendancejourneys');
    }

    if ($oldversion < 2026081415) {
        // Bulk-copy audience selection release: no schema change.
        upgrade_mod_savepoint(true, 2026081415, 'attendancejourneys');
    }

    if ($oldversion < 2026081416) {
        $roomtable = new xmldb_table('attendancejourneys_rooms');
        $roomtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $roomtable->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $roomtable->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $roomtable->add_field('code', XMLDB_TYPE_CHAR, '100');
        $roomtable->add_field('capacity', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $roomtable->add_field('location', XMLDB_TYPE_CHAR, '255');
        $roomtable->add_field('notes', XMLDB_TYPE_TEXT);
        $roomtable->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $roomtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $roomtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $roomtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $roomtable->add_key('activity', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
        $roomtable->add_index('activityactive', XMLDB_INDEX_NOTUNIQUE, ['attendancejourneysid', 'active']);
        if (!$dbman->table_exists($roomtable)) {
            $dbman->create_table($roomtable);
        }

        $sessiontable = new xmldb_table('attendancejourneys_sessions');
        $roomfield = new xmldb_field('roomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'groupid');
        if (!$dbman->field_exists($sessiontable, $roomfield)) {
            $dbman->add_field($sessiontable, $roomfield);
        }
        $roomkey = new xmldb_key('room', XMLDB_KEY_FOREIGN, ['roomid'], 'attendancejourneys_rooms', ['id']);
        $dbman->add_key($sessiontable, $roomkey);

        upgrade_mod_savepoint(true, 2026081416, 'attendancejourneys');
    }

    if ($oldversion < 2026081417) {
        // Native Moodle course-group workspace release: no schema change.
        upgrade_mod_savepoint(true, 2026081417, 'attendancejourneys');
    }

    if ($oldversion < 2026081418) {
        $table = new xmldb_table('attendancejourneys_journeys');
        $field = new xmldb_field(
            'capacity',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'defaultmodality'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026081418, 'attendancejourneys');
    }

    if ($oldversion < 2026081419) {
        $journeytable = new xmldb_table('attendancejourneys_journeys');
        $enabledfield = new xmldb_field(
            'waitlistenabled',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'capacity'
        );
        if (!$dbman->field_exists($journeytable, $enabledfield)) {
            $dbman->add_field($journeytable, $enabledfield);
        }

        $table = new xmldb_table('attendancejourneys_waitlist');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('journeyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'waiting');
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('updatedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeupdated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('activity', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
        $table->add_key('journey', XMLDB_KEY_FOREIGN, ['journeyid'], 'attendancejourneys_journeys', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('journeystatustime', XMLDB_INDEX_NOTUNIQUE, ['journeyid', 'status', 'timecreated']);
        $table->add_index('journeyuserstatus', XMLDB_INDEX_NOTUNIQUE, ['journeyid', 'userid', 'status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026081419, 'attendancejourneys');
    }

    if ($oldversion < 2026081420) {
        // Visible waiting-list decision history release: no schema change.
        upgrade_mod_savepoint(true, 2026081420, 'attendancejourneys');
    }

    if ($oldversion < 2026081421) {
        $table = new xmldb_table('attendancejourneys_equivalences');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('targetjourneyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('targetsessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcesessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('reason', XMLDB_TYPE_TEXT);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('decidedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timedecided', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('activity', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('targetjourney', XMLDB_KEY_FOREIGN, ['targetjourneyid'], 'attendancejourneys_journeys', ['id']);
        $table->add_key('targetsession', XMLDB_KEY_FOREIGN, ['targetsessionid'], 'attendancejourneys_sessions', ['id']);
        $table->add_key('sourcesession', XMLDB_KEY_FOREIGN, ['sourcesessionid'], 'attendancejourneys_sessions', ['id']);
        $table->add_index('targetuserstatus', XMLDB_INDEX_NOTUNIQUE, ['targetsessionid', 'userid', 'status']);
        $table->add_index('sourceuserstatus', XMLDB_INDEX_NOTUNIQUE, ['sourcesessionid', 'userid', 'status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026081421, 'attendancejourneys');
    }

    if ($oldversion < 2026081422) {
        // Staff-facing session-equivalence request workflow release: no schema change.
        upgrade_mod_savepoint(true, 2026081422, 'attendancejourneys');
    }

    if ($oldversion < 2026081423) {
        $table = new xmldb_table('attendancejourneys_equivalences');
        $field = new xmldb_field('decisionnote', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timedecided');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026081423, 'attendancejourneys');
    }

    if ($oldversion < 2026081424) {
        // Approved equivalences integrated into the central calculator: no schema change.
        upgrade_mod_savepoint(true, 2026081424, 'attendancejourneys');
    }

    if ($oldversion < 2026081426) {
        $table = new xmldb_table('attendancejourneys');
        $field = new xmldb_field(
            'experiencemode',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'professional',
            'introformat'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026081426, 'attendancejourneys');
    }

    if ($oldversion < 2026081427) {
        // Refined Light-mode session catalogue: no schema change.
        upgrade_mod_savepoint(true, 2026081427, 'attendancejourneys');
    }

    if ($oldversion < 2026081428) {
        // Refined Light-mode home page: no schema change.
        upgrade_mod_savepoint(true, 2026081428, 'attendancejourneys');
    }

    if ($oldversion < 2026081429) {
        // Refined Light-mode attendance and reporting workspaces: no schema change.
        upgrade_mod_savepoint(true, 2026081429, 'attendancejourneys');
    }

    if ($oldversion < 2026081430) {
        // Light-mode attendance filter syntax correction: no schema change.
        upgrade_mod_savepoint(true, 2026081430, 'attendancejourneys');
    }

    if ($oldversion < 2026081431) {
        // Refined Light-mode individual and self-recording workspaces: no schema change.
        upgrade_mod_savepoint(true, 2026081431, 'attendancejourneys');
    }

    if ($oldversion < 2026081432) {
        // Mode-aware report exports: no schema change.
        upgrade_mod_savepoint(true, 2026081432, 'attendancejourneys');
    }

    if ($oldversion < 2026081433) {
        // Light-mode action boundaries and orphan-safe restore: no schema change.
        upgrade_mod_savepoint(true, 2026081433, 'attendancejourneys');
    }

    if ($oldversion < 2026081503) {
        $table = new xmldb_table('attendancejourneys');
        $singular = new xmldb_field(
            'journeytermsingular',
            XMLDB_TYPE_CHAR,
            '100',
            null,
            null,
            null,
            null,
            'calendarenabled'
        );
        if (!$dbman->field_exists($table, $singular)) {
            $dbman->add_field($table, $singular);
        }
        $plural = new xmldb_field(
            'journeytermplural',
            XMLDB_TYPE_CHAR,
            '100',
            null,
            null,
            null,
            null,
            'journeytermsingular'
        );
        if (!$dbman->field_exists($table, $plural)) {
            $dbman->add_field($table, $plural);
        }
        upgrade_mod_savepoint(true, 2026081503, 'attendancejourneys');
    }

    if ($oldversion < 2026081504) {
        // Complete contextual terminology resolution and multilingual UI coverage.
        upgrade_mod_savepoint(true, 2026081504, 'attendancejourneys');
    }

    if ($oldversion < 2026081505) {
        global $CFG;
        $table = new xmldb_table('attendancejourneys_terms');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('langcode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('singular', XMLDB_TYPE_CHAR, '100');
        $table->add_field('plural', XMLDB_TYPE_CHAR, '100');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('activity', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
        $table->add_index('activitylanguage', XMLDB_INDEX_UNIQUE, ['attendancejourneysid', 'langcode']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $normaliselanguage = static function (string $language): string {
            $language = strtolower(trim($language));
            if (in_array($language, ['en', 'fr', 'es'], true)) {
                return $language;
            }
            $base = substr($language, 0, 2);
            return in_array($base, ['en', 'fr', 'es'], true) ? $base : 'en';
        };
        $sitelanguage = $normaliselanguage((string) ($CFG->lang ?? 'en'));
        $config = get_config('mod_attendancejourneys');
        foreach (['singular', 'plural'] as $number) {
            $legacyproperty = 'default_journeyterm' . $number;
            $newproperty = $legacyproperty . '_' . $sitelanguage;
            if (!empty($config->{$legacyproperty}) && !isset($config->{$newproperty})) {
                set_config($newproperty, trim((string) $config->{$legacyproperty}), 'mod_attendancejourneys');
            }
        }

        $activities = $DB->get_records_sql(
            "SELECT a.id, a.journeytermsingular, a.journeytermplural, c.lang
               FROM {attendancejourneys} a
               JOIN {course} c ON c.id = a.course
              WHERE " . $DB->sql_isnotempty('a', 'journeytermsingular', false, true) .
                " OR " . $DB->sql_isnotempty('a', 'journeytermplural', false, true)
        );
        foreach ($activities as $activity) {
            $langcode = $normaliselanguage((string) ($activity->lang ?: $sitelanguage));
            if (
                !$DB->record_exists(
                    'attendancejourneys_terms',
                    ['attendancejourneysid' => $activity->id, 'langcode' => $langcode]
                )
            ) {
                $DB->insert_record('attendancejourneys_terms', (object) [
                    'attendancejourneysid' => $activity->id,
                    'langcode' => $langcode,
                    'singular' => trim((string) $activity->journeytermsingular),
                    'plural' => trim((string) $activity->journeytermplural),
                ]);
            }
        }
        upgrade_mod_savepoint(true, 2026081505, 'attendancejourneys');
    }

    if ($oldversion < 2026081506) {
        $table = new xmldb_table('attendancejourneys_terms');
        $oldindex = new xmldb_index(
            'activitylanguage',
            XMLDB_INDEX_UNIQUE,
            ['attendancejourneysid', 'langcode']
        );
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $concept = new xmldb_field(
            'concept',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'journey',
            'attendancejourneysid'
        );
        if (!$dbman->field_exists($table, $concept)) {
            $dbman->add_field($table, $concept);
        }
        $newindex = new xmldb_index(
            'activityconceptlanguage',
            XMLDB_INDEX_UNIQUE,
            ['attendancejourneysid', 'concept', 'langcode']
        );
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        upgrade_mod_savepoint(true, 2026081506, 'attendancejourneys');
    }

    if ($oldversion < 2026081507) {
        // Compact, progressively disclosed Moodle activity terminology form.
        upgrade_mod_savepoint(true, 2026081507, 'attendancejourneys');
    }

    if ($oldversion < 2026081508) {
        // Integrated, role-aware trilingual documentation and visible terminology column headings.
        upgrade_mod_savepoint(true, 2026081508, 'attendancejourneys');
    }

    if ($oldversion < 2026100706) {
        $table = new xmldb_table('attendancejourneys_journeys');
        $field = new xmldb_field('passinggrade', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null, 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Null preserves the legacy activity-level rule; frozen closures are never recalculated here.
        upgrade_mod_savepoint(true, 2026100706, 'attendancejourneys');
    }

    if ($oldversion < 2026100707) {
        $activitytable = new xmldb_table('attendancejourneys');
        $fields = [
            new xmldb_field('journeygrading', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0'),
            new xmldb_field('nextgradeitem', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($activitytable, $field)) {
                $dbman->add_field($activitytable, $field);
            }
        }
        $journeytable = new xmldb_table('attendancejourneys_journeys');
        $fields = [
            new xmldb_field('gradeitemnumber', XMLDB_TYPE_INTEGER, '10', null, null, null, null),
            new xmldb_field('required', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($journeytable, $field)) {
                $dbman->add_field($journeytable, $field);
            }
        }
        // Preserve all historical grades and closures; conversion is a separate explicit operation.
        upgrade_mod_savepoint(true, 2026100707, 'attendancejourneys');
    }

    if ($oldversion < 2026100708) {
        $table = new xmldb_table('attendancejourneys_journeys');
        $field = new xmldb_field('audiencemode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Existing journeys keep their historical audience interpretation until explicit conversion.
        upgrade_mod_savepoint(true, 2026100708, 'attendancejourneys');
    }

    if ($oldversion < 2026100709) {
        $table = new xmldb_table('attendancejourneys_equivlog');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('equivalenceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('actorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'decision');
        $table->add_field('previousstatus', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('equivalence', XMLDB_KEY_FOREIGN, ['equivalenceid'], 'attendancejourneys_equivalences', ['id']);
        $table->add_key('activity', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('actor', XMLDB_INDEX_NOTUNIQUE, ['actorid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Only the latest known decision survives in the old schema; never invent previous decisions.
        $equivalences = $DB->get_recordset_select(
            'attendancejourneys_equivalences',
            'status <> :pending',
            ['pending' => 'pending']
        );
        foreach ($equivalences as $equivalence) {
            \mod_attendancejourneys\local\equivalence_history::baseline($equivalence);
        }
        $equivalences->close();
        upgrade_mod_savepoint(true, 2026100709, 'attendancejourneys');
    }

    if ($oldversion < 2026100710) {
        $sessions = new xmldb_table('attendancejourneys_sessions');
        $field = new xmldb_field('cancelled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($sessions, $field)) {
            $dbman->add_field($sessions, $field);
        }
        $table = new xmldb_table('attendancejourneys_sessionlog');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('actorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'cancel');
        $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('activity', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
        $table->add_key('session', XMLDB_KEY_FOREIGN, ['sessionid'], 'attendancejourneys_sessions', ['id']);
        $table->add_index('actor', XMLDB_INDEX_NOTUNIQUE, ['actorid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // All existing sessions remain scheduled; historical results are not recalculated here.
        upgrade_mod_savepoint(true, 2026100710, 'attendancejourneys');
    }

    if ($oldversion < 2026100711) {
        $table = new xmldb_table('attendancejourneys_obligations');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('journeyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('waived', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('starttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('actorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('revision', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('activity', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
        $table->add_key('journey', XMLDB_KEY_FOREIGN, ['journeyid'], 'attendancejourneys_journeys', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('journeyuser', XMLDB_INDEX_UNIQUE, ['journeyid', 'userid']);
        $table->add_index('actor', XMLDB_INDEX_NOTUNIQUE, ['actorid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $table = new xmldb_table('attendancejourneys_obligationlog');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('journeyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('waived', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('starttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('actorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('revision', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('previouswaived', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('previousstarttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('previousendtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('activity', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
        $table->add_key('journey', XMLDB_KEY_FOREIGN, ['journeyid'], 'attendancejourneys_journeys', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('journeyuser', XMLDB_INDEX_NOTUNIQUE, ['journeyid', 'userid']);
        $table->add_index('actor', XMLDB_INDEX_NOTUNIQUE, ['actorid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // No inferred enrolment boundary or fabricated individual decision is introduced.
        upgrade_mod_savepoint(true, 2026100711, 'attendancejourneys');
    }

    if ($oldversion < 2026100712) {
        $journeys = new xmldb_table('attendancejourneys_journeys');
        $field = new xmldb_field('rootjourneyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($journeys, $field)) {
            $dbman->add_field($journeys, $field);
        }
        $table = new xmldb_table('attendancejourneys_attempts');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('attendancejourneysid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('rootjourneyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('journeyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('attemptnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '2');
        $table->add_field('actorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('attendancejourneysid', XMLDB_KEY_FOREIGN, ['attendancejourneysid'], 'attendancejourneys', ['id']);
        $table->add_key('rootjourneyid', XMLDB_KEY_FOREIGN, ['rootjourneyid'], 'attendancejourneys_journeys', ['id']);
        $table->add_key('journeyid', XMLDB_KEY_FOREIGN, ['journeyid'], 'attendancejourneys_journeys', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('familyusernumber', XMLDB_INDEX_UNIQUE, ['rootjourneyid', 'userid', 'attemptnumber']);
        $table->add_index('journeyuser', XMLDB_INDEX_UNIQUE, ['journeyid', 'userid']);
        $table->add_index('actor', XMLDB_INDEX_NOTUNIQUE, ['actorid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Existing journeys stay independent; never infer retakes from names or membership numbers.
        upgrade_mod_savepoint(true, 2026100712, 'attendancejourneys');
    }

    if ($oldversion < 2026100713) {
        // Candidate release checkpoint; preserve all development data without schema changes.
        upgrade_mod_savepoint(true, 2026100713, 'attendancejourneys');
    }

    if ($oldversion < 2026100800) {
        // Missing audit dates are displayed neutrally; preserve stored timestamps and pedagogical data.
        upgrade_mod_savepoint(true, 2026100800, 'attendancejourneys');
    }

    if ($oldversion < 2026100801) {
        // Session workflow labels change without altering attendance, membership or final results.
        upgrade_mod_savepoint(true, 2026100801, 'attendancejourneys');
    }

    if ($oldversion < 2026100802) {
        // Documentation and journey labels change without altering pedagogical data or the schema.
        upgrade_mod_savepoint(true, 2026100802, 'attendancejourneys');
    }

    if ($oldversion < 2026100803) {
        // New settings preserve existing keys; capability cloning is handled by Moodle.
        upgrade_mod_savepoint(true, 2026100803, 'attendancejourneys');
    }

    if ($oldversion < 2026100804) {
        // The component transition is performed separately before this native upgrade.
        upgrade_mod_savepoint(true, 2026100804, 'attendancejourneys');
    }

    if ($oldversion < 2026100805) {
        // Promote the validated release without changing stored pedagogical data.
        upgrade_mod_savepoint(true, 2026100805, 'attendancejourneys');
    }

    if ($oldversion < 2026100806) {
        // Language ordering and test coverage metadata preserve all stored data.
        upgrade_mod_savepoint(true, 2026100806, 'attendancejourneys');
    }

    if ($oldversion < 2026100807) {
        // Coverage declarations remain compatible with Moodle 4.5 and later.
        upgrade_mod_savepoint(true, 2026100807, 'attendancejourneys');
    }

    return true;
}
