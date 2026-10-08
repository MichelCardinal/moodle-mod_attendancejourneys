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
 * Moodle backup and restore tests.
 *
 * @package    mod_attendancejourneys
 * @category   test
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_restore_test extends \advanced_testcase {
    public function test_restore_with_user_data_preserves_the_complete_attendance_history(): void {
        $restored = $this->backup_and_restore_fixture(true);
        $this->assertSame(1, $restored['journeys']);
        $this->assertSame(1, $restored['sessions']);
        $this->assertSame(1, $restored['rooms']);
        $this->assertTrue($restored['sessionroommapped']);
        $this->assertSame(1, $restored['members']);
        $this->assertSame(1, $restored['records']);
        $this->assertSame(1, $restored['reviews']);
        $this->assertSame(1, $restored['closures']);
        $this->assertSame(0, $restored['journeyactive']);
        $this->assertSame(12, $restored['journeycapacity']);
        $this->assertSame(65.5, $restored['journeypassinggrade']);
        $this->assertSame(2, $restored['audiencemode']);
        $this->assertSame(1, $restored['journeywaitlistenabled']);
        $this->assertSame(1, $restored['waitlist']);
        $this->assertSame('Horaire', $restored['journeytermsingular']);
        $this->assertSame('Horaires', $restored['journeytermplural']);
        $this->assertSame('Séance', $restored['sessiontermsingular']);
        $this->assertSame('Séances', $restored['sessiontermplural']);
    }

    public function test_restore_without_user_data_keeps_structure_and_reopens_the_journey(): void {
        $restored = $this->backup_and_restore_fixture(false);
        $this->assertSame(1, $restored['journeys']);
        $this->assertSame(1, $restored['sessions']);
        $this->assertSame(1, $restored['rooms']);
        $this->assertTrue($restored['sessionroommapped']);
        $this->assertSame(0, $restored['members']);
        $this->assertSame(0, $restored['records']);
        $this->assertSame(0, $restored['reviews']);
        $this->assertSame(0, $restored['closures']);
        $this->assertSame(1, $restored['journeyactive']);
        $this->assertSame(12, $restored['journeycapacity']);
        $this->assertSame(65.5, $restored['journeypassinggrade']);
        $this->assertSame(2, $restored['audiencemode']);
        $this->assertSame(1, $restored['journeywaitlistenabled']);
        $this->assertSame(0, $restored['waitlist']);
        $this->assertSame('Horaire', $restored['journeytermsingular']);
        $this->assertSame('Horaires', $restored['journeytermplural']);
        $this->assertSame('Séance', $restored['sessiontermsingular']);
        $this->assertSame('Séances', $restored['sessiontermplural']);
    }

    /**
     * Restoring into another course keeps grade item numbers independent of journey IDs.
     */
    public function test_backup_preserves_journey_grade_mapping(): void {
        $restored = $this->backup_and_restore_fixture(true, true);
        $this->assertSame(1, $restored['journeygrading']);
        $this->assertSame(17, $restored['gradeitemnumber']);
        $this->assertSame(18, $restored['nextgradeitem']);
        $this->assertSame(1, $restored['journeyrequired']);
        $this->assertSame(1, $restored['mappedgradeitems']);
        $this->assertSame(1, $restored['exemptrecords']);
    }

    /**
     * Two obligations retain their distinct thresholds, results and grade item numbers.
     */
    public function test_restore_preserves_two_independent_journey_results(): void {
        $restored = $this->backup_and_restore_fixture(true, true, true);
        $this->assertSame(2, $restored['journeys']);
        $this->assertSame(2, $restored['closures']);
        $this->assertSame(19, $restored['nextgradeitem']);
    }

    /**
     * Reusing the structure retains both thresholds without copying participant results.
     */
    public function test_structure_restore_reopens_both_obligations_without_grades(): void {
        $restored = $this->backup_and_restore_fixture(false, true, true);
        $this->assertSame(2, $restored['journeys']);
        $this->assertSame(0, $restored['closures']);
        $this->assertSame(0, $restored['records']);
        $this->assertSame(0, $restored['members']);
    }

    /**
     * Moving course dates adjusts pedagogical boundaries, never historical decision timestamps.
     */
    public function test_restore_offsets_pedagogical_dates_without_rewriting_audit_history(): void {
        $restored = $this->backup_and_restore_fixture(true, true, false, 14 * DAYSECS);
        $this->assertSame(1, $restored['journeys']);
        $this->assertSame(1, $restored['closures']);
    }

    /**
     * Back up and restore the fixture with the requested user-data setting.
     *
     * @param bool $userdata Whether user data is included in the backup.
     * @param bool $journeygrading Whether to include a separate journey grade item.
     * @param bool $multijourney Whether to include two independently graded obligations.
     * @param int $dateoffset Requested course start-date offset in seconds.
     * @return array
     */
    private function backup_and_restore_fixture(
        bool $userdata,
        bool $journeygrading = false,
        bool $multijourney = false,
        int $dateoffset = 0
    ): array {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        $CFG->backup_file_logger_level = \backup::LOG_NONE;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', [
            'course' => $course->id, 'journeygrading' => (int) $journeygrading,
            'nextgradeitem' => $journeygrading ? 17 : 0, 'gradeenabled' => (int) $journeygrading,
            'journeytermsingular_fr' => 'Horaire',
            'journeytermplural_fr' => 'Horaires',
            'sessiontermsingular_fr' => 'Séance',
            'sessiontermplural_fr' => 'Séances',
        ]);
        $participant = $generator->create_and_enrol($course, 'student');
        $staff = $generator->create_and_enrol($course, 'editingteacher');
        $journeydata = (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Backup journey', 'description' => '', 'groupid' => 0,
            'startdate' => time() - DAYSECS, 'enddate' => time() + DAYSECS,
            'defaultmodality' => 'hybrid', 'capacity' => 12, 'waitlistenabled' => 1,
            'passinggrade' => $multijourney ? 60 : 65.5,
            'audiencemode' => 2,
            'active' => 0, 'completedby' => $staff->id,
            'timecompleted' => time(), 'timecreated' => time(), 'timemodified' => time(),
        ];
        if ($journeygrading) {
            $journeydata->id = $DB->get_field(
                'attendancejourneys_journeys',
                'id',
                ['attendancejourneysid' => $activity->id],
                MUST_EXIST
            );
            $DB->update_record('attendancejourneys_journeys', $journeydata);
            $journeyid = $journeydata->id;
        } else {
            $journeyid = $DB->insert_record('attendancejourneys_journeys', $journeydata);
        }
        $roomid = $DB->insert_record('attendancejourneys_rooms', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Training room', 'code' => 'TR-1',
            'capacity' => 18, 'location' => 'Building A', 'notes' => 'Projector', 'active' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $sessionid = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'name' => 'Backup session',
            'sessiondate' => time() - HOURSECS, 'duration' => 60, 'groupid' => 0, 'roomid' => $roomid,
            'description' => 'Preserved',
            'modality' => 'hybrid', 'location' => 'Room 1', 'meetingurl' => 'https://example.invalid/meeting',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        if ($journeygrading) {
            $policy = (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'userid' => $participant->id,
                'waived' => 0, 'starttime' => time() - DAYSECS, 'endtime' => $dateoffset ? time() + DAYSECS : 0,
                'actorid' => $staff->id,
                'revision' => 1, 'reason' => 'Backup individual obligation', 'timemodified' => time(),
            ];
            $DB->insert_record('attendancejourneys_obligations', $policy);
            $history = clone $policy;
            unset($history->timemodified);
            $history->timecreated = time();
            $history->previouswaived = 0;
            $history->previousstarttime = $dateoffset ? time() - 2 * DAYSECS : 0;
            $history->previousendtime = 0;
            $DB->insert_record('attendancejourneys_obligationlog', $history);
        }
        $DB->insert_record('attendancejourneys_members', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'userid' => $participant->id,
            'attemptnumber' => 1, 'active' => 0, 'timeassigned' => time() - DAYSECS, 'timeended' => time(),
        ]);
        if ($journeygrading) {
            $exemptsession = $DB->get_record('attendancejourneys_sessions', ['id' => $sessionid], '*', MUST_EXIST);
            unset($exemptsession->id);
            $exemptsession->name = 'Exempt session';
            $exemptsessionid = $DB->insert_record('attendancejourneys_sessions', $exemptsession);
            $DB->insert_record('attendancejourneys_records', (object) [
                'sessionid' => $exemptsessionid, 'userid' => $participant->id, 'status' => 'exempt',
                'minutesabsent' => 0, 'takenby' => $staff->id, 'approved' => 1,
            ]);
        }
        $recordid = $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $sessionid, 'userid' => $participant->id, 'status' => 'partial',
            'minutesabsent' => 10, 'remarks' => 'Traffic', 'takenby' => $staff->id, 'approved' => 1,
            'approvedby' => $staff->id, 'timeapproved' => time(), 'changesrequested' => 0,
            'reviewnote' => 'Confirmed', 'reviewedby' => $staff->id, 'timereviewed' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('attendancejourneys_reviews', (object) [
            'recordid' => $recordid, 'sessionid' => $sessionid, 'userid' => $participant->id,
            'action' => 'approved', 'actorid' => $staff->id, 'note' => 'Confirmed', 'timecreated' => time(),
        ]);
        $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'userid' => $participant->id,
            'active' => 1, 'applicablesessions' => 1, 'recordedsessions' => 1, 'presentminutes' => 50,
            'possibleminutes' => 60, 'percentage' => 83.333,
            'threshold' => $multijourney ? 60 : 80, 'result' => 'passed',
            'closedby' => $staff->id, 'timeclosed' => time(), 'reopenedby' => 0, 'timereopened' => 0,
        ]);
        $DB->insert_record('attendancejourneys_waitlist', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'userid' => $participant->id,
            'status' => 'promoted', 'createdby' => $staff->id, 'timecreated' => time() - HOURSECS,
            'updatedby' => $staff->id, 'timeupdated' => time(),
        ]);

        if ($multijourney) {
            $second = clone $journeydata;
            unset($second->id);
            $second->name = 'Practical obligation';
            $second->passinggrade = 80;
            $second->gradeitemnumber = 18;
            $second->required = 1;
            $secondid = $DB->insert_record('attendancejourneys_journeys', $second);
            $DB->set_field('attendancejourneys', 'nextgradeitem', 19, ['id' => $activity->id]);
            $activity->nextgradeitem = 19;
            $secondsession = $DB->get_record('attendancejourneys_sessions', ['id' => $sessionid], '*', MUST_EXIST);
            unset($secondsession->id);
            $secondsession->journeyid = $secondid;
            $secondsession->name = 'Practical session';
            $secondsession->duration = 100;
            $secondsessionid = $DB->insert_record('attendancejourneys_sessions', $secondsession);
            $DB->insert_record('attendancejourneys_members', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $secondid, 'userid' => $participant->id,
                'attemptnumber' => 1, 'active' => 0, 'timeassigned' => time() - DAYSECS, 'timeended' => time(),
            ]);
            $DB->insert_record('attendancejourneys_records', (object) [
                'sessionid' => $secondsessionid, 'userid' => $participant->id, 'status' => 'partial',
                'minutesabsent' => 30, 'takenby' => $staff->id, 'approved' => 1,
            ]);
            $DB->insert_record('attendancejourneys_closures', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $secondid, 'userid' => $participant->id,
                'active' => 1, 'applicablesessions' => 1, 'recordedsessions' => 1, 'presentminutes' => 70,
                'possibleminutes' => 100, 'percentage' => 70, 'threshold' => 80, 'result' => 'failed',
                'closedby' => $staff->id, 'timeclosed' => time(), 'reopenedby' => 0, 'timereopened' => 0,
            ]);
        }
        if ($journeygrading) {
            attendancejourneys_update_grades($activity, $participant->id);
        }
        $sourcegrades = [];
        if ($multijourney) {
            foreach ([17 => 50 / 60 * 100, 18 => 70.0] as $number => $expected) {
                $itemid = $DB->get_field('grade_items', 'id', [
                    'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id, 'itemnumber' => $number,
                ], MUST_EXIST);
                $sourcegrades[$number] = $DB->get_record('grade_grades', [
                    'itemid' => $itemid, 'userid' => $participant->id,
                ], '*', MUST_EXIST);
                $this->assertEqualsWithDelta($expected, (float) $sourcegrades[$number]->rawgrade, 0.00001);
            }
        }

        $equivalenceid = $DB->insert_record('attendancejourneys_equivalences', (object) [
            'attendancejourneysid' => $activity->id, 'userid' => $participant->id,
            'targetjourneyid' => $journeyid, 'targetsessionid' => $sessionid, 'sourcesessionid' => $sessionid,
            'status' => 'revoked', 'decidedby' => $staff->id,
        ]);
        foreach (['approved', 'revoked'] as $status) {
            $DB->insert_record('attendancejourneys_equivlog', (object) [
                'equivalenceid' => $equivalenceid, 'attendancejourneysid' => $activity->id,
                'userid' => $participant->id, 'actorid' => $staff->id, 'action' => 'decision',
                'previousstatus' => $status === 'approved' ? 'pending' : 'approved', 'status' => $status,
                'note' => 'Backup ' . $status, 'timecreated' => time(),
            ]);
        }
        $DB->set_field('attendancejourneys_sessions', 'cancelled', 1, ['id' => $sessionid]);
        $DB->insert_record('attendancejourneys_sessionlog', (object) [
            'attendancejourneysid' => $activity->id, 'sessionid' => $sessionid, 'actorid' => $staff->id,
            'action' => 'cancel', 'reason' => 'Backup cancellation', 'timecreated' => time(),
        ]);
        $audittables = [
            'attendancejourneys_journeys' => ['timecompleted', 'timecreated', 'timemodified'],
            'attendancejourneys_sessions' => ['timecreated', 'timemodified'],
            'attendancejourneys_members' => ['timeassigned', 'timeended'],
            'attendancejourneys_closures' => ['timeclosed', 'timereopened'],
            'attendancejourneys_waitlist' => ['timecreated', 'timeupdated'],
            'attendancejourneys_equivalences' => ['timecreated', 'timedecided', 'timemodified'],
            'attendancejourneys_equivlog' => ['timecreated'],
            'attendancejourneys_sessionlog' => ['timecreated'],
            'attendancejourneys_obligations' => ['timemodified'],
            'attendancejourneys_obligationlog' => ['timecreated'],
        ];
        $originalaudit = [];
        if ($dateoffset) {
            foreach ($audittables as $table => $fields) {
                $originalaudit[$table] = array_values($DB->get_records($table, ['attendancejourneysid' => $activity->id], 'id'));
            }
        }
        $controller = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $controller->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $controller->get_plan()->get_setting('users')->set_value($userdata);
        $backupid = $controller->get_backupid();
        $controller->execute_plan();
        $controller->destroy();

        $newcourseid = \restore_dbops::create_new_course(
            'Restored attendance',
            'restored-' . uniqid(),
            $course->category
        );
        $restore = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $restore->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $restore->get_plan()->get_setting('users')->set_value($userdata);
        if ($dateoffset) {
            $restore->get_plan()->get_setting('course_startdate')->set_value((int) $course->startdate + $dateoffset);
        }
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();

        $restoredactivity = $DB->get_record('attendancejourneys', ['course' => $newcourseid], '*', MUST_EXIST);
        $restoredterm = $DB->get_record('attendancejourneys_terms', [
            'attendancejourneysid' => $restoredactivity->id,
            'concept' => 'journey',
            'langcode' => 'fr',
        ], '*', MUST_EXIST);
        $restoredsessionterm = $DB->get_record('attendancejourneys_terms', [
            'attendancejourneysid' => $restoredactivity->id,
            'concept' => 'session',
            'langcode' => 'fr',
        ], '*', MUST_EXIST);
        $restoredjourney = $DB->get_record(
            'attendancejourneys_journeys',
            ['attendancejourneysid' => $restoredactivity->id, 'name' => 'Backup journey'],
            '*',
            MUST_EXIST
        );
        $restoredsessions = $DB->get_records(
            'attendancejourneys_sessions',
            ['attendancejourneysid' => $restoredactivity->id]
        );
        $restoredrooms = $DB->get_records(
            'attendancejourneys_rooms',
            ['attendancejourneysid' => $restoredactivity->id]
        );
        $policies = array_values($DB->get_records('attendancejourneys_obligations', [
            'attendancejourneysid' => $restoredactivity->id,
        ]));
        $policyhistory = array_values($DB->get_records('attendancejourneys_obligationlog', [
            'attendancejourneysid' => $restoredactivity->id,
        ]));
        if ($userdata && $journeygrading) {
            $this->assertCount(1, $policies);
            $this->assertCount(1, $policyhistory);
            foreach ([$policies[0], $policyhistory[0]] as $row) {
                $this->assertEquals($restoredjourney->id, $row->journeyid);
                $this->assertEquals($participant->id, $row->userid);
                $this->assertEquals($staff->id, $row->actorid);
                $this->assertEquals(0, $row->waived);
                $this->assertEquals(empty($policy->endtime) ? 0 : (int) $policy->endtime + $dateoffset, $row->endtime);
                $this->assertEquals(1, $row->revision);
                $this->assertSame('Backup individual obligation', $row->reason);
            }
            $restoredsession = $DB->get_record('attendancejourneys_sessions', [
                'attendancejourneysid' => $restoredactivity->id, 'name' => 'Backup session',
            ], '*', MUST_EXIST);
            $originalsession = $DB->get_record('attendancejourneys_sessions', ['id' => $sessionid], '*', MUST_EXIST);
            $this->assertEquals(
                (int) $policy->starttime - (int) $originalsession->sessiondate,
                (int) $policies[0]->starttime - (int) $restoredsession->sessiondate
            );
            $this->assertEquals(empty($history->previousstarttime) ? 0 :
                (int) $history->previousstarttime + $dateoffset, $policyhistory[0]->previousstarttime);
            $this->assertEquals(0, $policyhistory[0]->previousendtime);
        } else {
            $this->assertSame([], $policies);
            $this->assertSame([], $policyhistory);
        }
        if ($dateoffset) {
            $this->assertEquals((int) $originalsession->sessiondate + $dateoffset, (int) $restoredsession->sessiondate);
            $this->assertEquals((int) $policy->starttime + $dateoffset, (int) $policies[0]->starttime);
            foreach ($audittables as $table => $fields) {
                $restoredrows = array_values($DB->get_records($table, ['attendancejourneysid' => $restoredactivity->id], 'id'));
                $this->assertCount(count($originalaudit[$table]), $restoredrows, $table);
                foreach ($restoredrows as $index => $row) {
                    foreach ($fields as $field) {
                        $this->assertEquals(
                            (int) $originalaudit[$table][$index]->$field,
                            (int) $row->$field,
                            $table . '.' . $field
                        );
                    }
                }
            }
        }
        $restoredsessionids = array_keys($restoredsessions);
        $restoredrecords = $restoredsessionids ? $DB->get_records_list(
            'attendancejourneys_records',
            'sessionid',
            $restoredsessionids,
            '',
            'id,status'
        ) : [];
        $recordcount = count($restoredrecords);
        $reviewcount = 0;
        if ($restoredsessionids) {
            $recordids = array_keys($restoredrecords);
            $reviewcount = $recordids ? count($DB->get_records_list(
                'attendancejourneys_reviews',
                'recordid',
                $recordids,
                '',
                'id'
            )) : 0;
        }
        $cancelledsessions = array_filter($restoredsessions, static fn($session) => !empty($session->cancelled));
        $this->assertCount(1, $cancelledsessions);
        $cancellationhistory = $DB->get_records('attendancejourneys_sessionlog', ['attendancejourneysid' => $restoredactivity->id]);
        $this->assertCount($userdata ? 1 : 0, $cancellationhistory);
        if ($userdata) {
            $entry = reset($cancellationhistory);
            $this->assertEquals(reset($cancelledsessions)->id, $entry->sessionid);
            $this->assertEquals($staff->id, $entry->actorid);
            $this->assertSame('Backup cancellation', $entry->reason);
        }
        $restoredhistory = array_values($DB->get_records('attendancejourneys_equivlog', [
            'attendancejourneysid' => $restoredactivity->id,
        ], 'id'));
        $restoredequivalence = $DB->get_record(
            'attendancejourneys_equivalences',
            ['attendancejourneysid' => $restoredactivity->id]
        );
        if ($userdata) {
            $this->assertCount(2, $restoredhistory);
            $this->assertSame(['approved', 'revoked'], array_column($restoredhistory, 'status'));
            $this->assertSame(['Backup approved', 'Backup revoked'], array_column($restoredhistory, 'note'));
            foreach ($restoredhistory as $entry) {
                $this->assertEquals($restoredequivalence->id, $entry->equivalenceid);
                $this->assertEquals($restoredequivalence->userid, $entry->userid);
                $this->assertEquals($restoredequivalence->decidedby, $entry->actorid);
            }
        } else {
            $this->assertSame([], $restoredhistory);
            $this->assertFalse($restoredequivalence);
        }
        if ($multijourney) {
            $second = $DB->get_record('attendancejourneys_journeys', [
                'attendancejourneysid' => $restoredactivity->id, 'name' => 'Practical obligation',
            ], '*', MUST_EXIST);
            $this->assertNotEquals($journeyid, $restoredjourney->id);
            $this->assertNotEquals($secondid, $second->id);
            $this->assertEquals(60, $restoredjourney->passinggrade);
            $this->assertEquals(80, $second->passinggrade);
            $this->assertEquals(17, $restoredjourney->gradeitemnumber);
            $this->assertEquals(18, $second->gradeitemnumber);
            foreach ([$restoredjourney, $second] as $journey) {
                $this->assertEquals($userdata ? 0 : 1, $journey->active);
                $this->assertEquals(1, $journey->required);
                $this->assertEquals(2, $journey->audiencemode);
                $item = $DB->get_record('grade_items', [
                    'itemmodule' => 'attendancejourneys', 'iteminstance' => $restoredactivity->id,
                    'itemnumber' => $journey->gradeitemnumber,
                ], '*', MUST_EXIST);
                $this->assertEquals((float) $journey->passinggrade, (float) $item->gradepass);
                $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $participant->id]);
                if ($userdata) {
                    $closure = $DB->get_record('attendancejourneys_closures', [
                        'attendancejourneysid' => $restoredactivity->id, 'journeyid' => $journey->id,
                        'userid' => $participant->id, 'active' => 1,
                    ], '*', MUST_EXIST);
                    $this->assertNotFalse($grade);
                    $sourcegrade = $sourcegrades[(int) $journey->gradeitemnumber];
                    $this->assertEquals($sourcegrade->rawgrade, $grade->rawgrade);
                    $this->assertEquals($sourcegrade->finalgrade, $grade->finalgrade);
                    $this->assertSame($journey->id === $second->id ? 'failed' : 'passed', $closure->result);
                    $member = $DB->get_record('attendancejourneys_members', [
                        'attendancejourneysid' => $restoredactivity->id, 'journeyid' => $journey->id,
                        'userid' => $participant->id,
                    ], '*', MUST_EXIST);
                    $this->assertEquals(0, $member->active);
                } else {
                    $this->assertFalse($grade);
                    $this->assertEquals(0, $journey->completedby);
                    $this->assertEquals(0, $journey->timecompleted);
                }
            }
        }
        return [
            'journeygrading' => (int) $restoredactivity->journeygrading,
            'nextgradeitem' => (int) $restoredactivity->nextgradeitem,
            'gradeitemnumber' => (int) $restoredjourney->gradeitemnumber,
            'journeyrequired' => (int) $restoredjourney->required,
            'audiencemode' => (int) $restoredjourney->audiencemode,
            'mappedgradeitems' => $DB->count_records('grade_items', [
                'itemtype' => 'mod', 'itemmodule' => 'attendancejourneys',
                'iteminstance' => $restoredactivity->id, 'itemnumber' => $restoredjourney->gradeitemnumber,
            ]),
            'journeys' => $DB->count_records(
                'attendancejourneys_journeys',
                ['attendancejourneysid' => $restoredactivity->id]
            ),
            'sessions' => count($restoredsessions),
            'rooms' => count($restoredrooms),
            'sessionroommapped' => $restoredrooms && $restoredsessions &&
                (int) reset($restoredsessions)->roomid === (int) reset($restoredrooms)->id,
            'members' => $DB->count_records(
                'attendancejourneys_members',
                ['attendancejourneysid' => $restoredactivity->id]
            ),
            'records' => $recordcount,
            'exemptrecords' => count(array_filter($restoredrecords, static fn($record) => $record->status === 'exempt')),
            'reviews' => $reviewcount,
            'closures' => $DB->count_records(
                'attendancejourneys_closures',
                ['attendancejourneysid' => $restoredactivity->id]
            ),
            'journeyactive' => (int) $restoredjourney->active,
            'journeycapacity' => (int) $restoredjourney->capacity,
            'journeypassinggrade' => (float) $restoredjourney->passinggrade,
            'journeywaitlistenabled' => (int) $restoredjourney->waitlistenabled,
            'waitlist' => $DB->count_records(
                'attendancejourneys_waitlist',
                ['attendancejourneysid' => $restoredactivity->id]
            ),
            'journeytermsingular' => $restoredterm->singular,
            'journeytermplural' => $restoredterm->plural,
            'sessiontermsingular' => $restoredsessionterm->singular,
            'sessiontermplural' => $restoredsessionterm->plural,
        ];
    }
}
