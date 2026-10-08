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
 * Attendance Journeys structure restore step.
 *
 * @package    mod_attendancejourneys
 * @category   backup
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_attendancejourneys_activity_structure_step extends restore_activity_structure_step {
    /** @var array New journey identifiers mapped to their original logical obligation identifiers. */
    private $attemptroots = [];

    /**
     * Define the activity data structure and user-data elements.
     * @return array
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('attendancejourneys', '/activity/attendancejourneys'),
            new restore_path_element(
                'attendancejourneys_terminology',
                '/activity/attendancejourneys/terminologies/terminology'
            ),
            new restore_path_element('attendancejourneys_room', '/activity/attendancejourneys/rooms/room'),
            new restore_path_element('attendancejourneys_journey', '/activity/attendancejourneys/journeys/journey'),
            new restore_path_element('attendancejourneys_session', '/activity/attendancejourneys/sessions/session'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('attendancejourneys_attempt', '/activity/attendancejourneys/attempts/attempt');
            $paths[] = new restore_path_element(
                'attendancejourneys_obligation',
                '/activity/attendancejourneys/journeys/journey/obligations/obligation'
            );
            $paths[] = new restore_path_element(
                'attendancejourneys_obligationlog',
                '/activity/attendancejourneys/journeys/journey/obligationhistory/obligationdecision'
            );
            $paths[] = new restore_path_element(
                'attendancejourneys_member',
                '/activity/attendancejourneys/journeys/journey/members/member'
            );
            $paths[] = new restore_path_element(
                'attendancejourneys_sessionlog',
                '/activity/attendancejourneys/sessions/session/cancellationhistory/change'
            );
            $paths[] = new restore_path_element(
                'attendancejourneys_record',
                '/activity/attendancejourneys/sessions/session/records/record'
            );
            $paths[] = new restore_path_element(
                'attendancejourneys_review',
                '/activity/attendancejourneys/sessions/session/records/record/reviews/review'
            );
            $paths[] = new restore_path_element(
                'attendancejourneys_closure',
                '/activity/attendancejourneys/closures/closure'
            );
            $paths[] = new restore_path_element(
                'attendancejourneys_waitlist',
                '/activity/attendancejourneys/waitlists/waitlist'
            );
            $paths[] = new restore_path_element(
                'attendancejourneys_equivalence',
                '/activity/attendancejourneys/equivalences/equivalence'
            );
            $paths[] = new restore_path_element(
                'attendancejourneys_equivlog',
                '/activity/attendancejourneys/equivalences/equivalence/decisionhistory/decision'
            );
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore one activity record and remap its dependent identifiers.
     *
     * @param array $data Record values read from the backup XML.
     * @return void
     */
    protected function process_attendancejourneys($data) {
        global $DB;
        $data = (object) $data;
        $data->journeygrading = (int) ($data->journeygrading ?? 0);
        $data->nextgradeitem = (int) ($data->nextgradeitem ?? 0);
        $data->course = $this->get_courseid();
        $newid = $DB->insert_record('attendancejourneys', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore one session record and remap its dependent identifiers.
     *
     * @param array $data Record values read from the backup XML.
     * @return void
     */
    protected function process_attendancejourneys_session($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $data->sessiondate = $this->apply_date_offset($data->sessiondate);
        $data->groupid = $data->groupid ? $this->get_mappingid('group', $data->groupid, 0) : 0;
        $data->roomid = !empty($data->roomid) ?
            $this->get_mappingid('attendancejourneys_room', $data->roomid, 0) : 0;
        $data->journeyid = !empty($data->journeyid) ?
            $this->get_mappingid('attendancejourneys_journey', $data->journeyid, 0) : 0;
        $newid = $DB->insert_record('attendancejourneys_sessions', $data);
        $this->set_mapping('attendancejourneys_session', $oldid, $newid);
    }

    /**
     * Restore the session cancellation timeline with mapped actor and parent identifiers.
     *
     * @param array $data Historical change from backup XML.
     * @return void
     */
    protected function process_attendancejourneys_sessionlog($data) {
        global $DB;
        $data = (object) $data;
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $data->sessionid = $this->get_new_parentid('attendancejourneys_session');
        $data->actorid = $this->get_mappingid('user', $data->actorid, 0);
        if ($data->sessionid) {
            $DB->insert_record('attendancejourneys_sessionlog', $data);
        }
    }

    /**
     * Restore one terminology record and remap its dependent identifiers.
     *
     * @param array $data Record values read from the backup XML.
     * @return void
     */
    protected function process_attendancejourneys_terminology($data) {
        global $DB;
        $data = (object) $data;
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $data->concept = $data->concept ?? 'journey';
        $DB->insert_record('attendancejourneys_terms', $data);
    }

    /**
     * Restore one room record and remap its dependent identifiers.
     *
     * @param array $data Record values read from the backup XML.
     * @return void
     */
    protected function process_attendancejourneys_room($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $newid = $DB->insert_record('attendancejourneys_rooms', $data);
        $this->set_mapping('attendancejourneys_room', $oldid, $newid);
    }

    /**
     * Restore one journey record and remap its dependent identifiers.
     *
     * @param array $data Record values read from the backup XML.
     * @return void
     */
    protected function process_attendancejourneys_journey($data) {
        global $DB;
        $data = (object) $data;
        $data->passinggrade = isset($data->passinggrade) && $data->passinggrade !== '' ?
            (float) $data->passinggrade : null;
        $data->required = (int) ($data->required ?? 1);
        $data->audiencemode = (int) ($data->audiencemode ?? 0);
        $data->gradeitemnumber = isset($data->gradeitemnumber) && $data->gradeitemnumber !== '' ?
            (int) $data->gradeitemnumber : null;
        $oldid = $data->id;
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $data->groupid = $data->groupid ? $this->get_mappingid('group', $data->groupid, 0) : 0;
        $data->completedby = !empty($data->completedby) ? $this->get_mappingid('user', $data->completedby, 0) : 0;
        $data->startdate = $data->startdate ? $this->apply_date_offset($data->startdate) : 0;
        $data->enddate = $data->enddate ? $this->apply_date_offset($data->enddate) : 0;
        if (!$this->get_setting_value('userinfo')) {
            // A structure-only restore has no participant assignments or frozen results.
            // Reopen the journey so the copied activity starts in a coherent reusable state.
            $data->active = 1;
            $data->completedby = 0;
            $data->timecompleted = 0;
        }
        $oldroot = (int) ($data->rootjourneyid ?? 0);
        $data->rootjourneyid = 0;
        $newid = $DB->insert_record('attendancejourneys_journeys', $data);
        if ($oldroot) {
            $this->attemptroots[$newid] = $oldroot;
        }
        $this->set_mapping('attendancejourneys_journey', $oldid, $newid);
    }

    /**
     * Restore one record record and remap its dependent identifiers.
     *
     * @param array $data Record values read from the backup XML.
     * @return void
     */
    protected function process_attendancejourneys_record($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->sessionid = $this->get_new_parentid('attendancejourneys_session');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->takenby = $this->get_mappingid('user', $data->takenby, 0);
        $data->approvedby = $this->get_mappingid('user', $data->approvedby ?? 0, 0);
        $data->reviewedby = $this->get_mappingid('user', $data->reviewedby ?? 0, 0);
        if (!$data->userid) {
            return;
        }
        $newid = $DB->insert_record('attendancejourneys_records', $data);
        $this->set_mapping('attendancejourneys_record', $oldid, $newid);
    }

    /**
     * Restore one review record and remap its dependent identifiers.
     *
     * @param array $data Record values read from the backup XML.
     * @return void
     */
    protected function process_attendancejourneys_review($data) {
        global $DB;
        $data = (object) $data;
        $data->recordid = $this->get_new_parentid('attendancejourneys_record');
        $data->sessionid = $this->get_mappingid('attendancejourneys_session', $data->sessionid);
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->actorid = $this->get_mappingid('user', $data->actorid, 0);
        // A user may have been omitted from the target site. In that case the parent attendance record
        // is intentionally skipped too, so its review history must not become an orphan.
        if ($data->recordid && $data->sessionid && $data->userid) {
            $DB->insert_record('attendancejourneys_reviews', $data);
        }
    }

    /**
     * Restore one member record and remap its dependent identifiers.
     *
     * @param array $data Record values read from the backup XML.
     * @return void
     */
    protected function process_attendancejourneys_member($data) {
        global $DB;
        $data = (object) $data;
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $data->journeyid = $this->get_new_parentid('attendancejourneys_journey');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->userid) {
            $DB->insert_record('attendancejourneys_members', $data);
        }
    }

    /**
     * Restore one closure record and remap its dependent identifiers.
     *
     * @param array $data Record values read from the backup XML.
     * @return void
     */
    protected function process_attendancejourneys_closure($data) {
        global $DB;
        $data = (object) $data;
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $data->journeyid = !empty($data->journeyid) ?
            $this->get_mappingid('attendancejourneys_journey', $data->journeyid, 0) : 0;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->closedby = $this->get_mappingid('user', $data->closedby, 0);
        $data->reopenedby = $this->get_mappingid('user', $data->reopenedby, 0);
        if (!$data->userid) {
            return;
        }
        $DB->insert_record('attendancejourneys_closures', $data);
    }

    /**
     * Restore one waitlist record and remap its dependent identifiers.
     *
     * @param array $data Record values read from the backup XML.
     * @return void
     */
    protected function process_attendancejourneys_waitlist($data) {
        global $DB;
        $data = (object) $data;
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $data->journeyid = $this->get_mappingid('attendancejourneys_journey', $data->journeyid, 0);
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->createdby = $this->get_mappingid('user', $data->createdby, 0);
        $data->updatedby = $this->get_mappingid('user', $data->updatedby, 0);
        if ($data->journeyid && $data->userid) {
            $DB->insert_record('attendancejourneys_waitlist', $data);
        }
    }

    /**
     * Restore one equivalence record and remap its dependent identifiers.
     *
     * @param array $data Record values read from the backup XML.
     * @return void
     */
    protected function process_attendancejourneys_equivalence($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->targetjourneyid = $this->get_mappingid('attendancejourneys_journey', $data->targetjourneyid, 0);
        $data->targetsessionid = $this->get_mappingid('attendancejourneys_session', $data->targetsessionid, 0);
        $data->sourcesessionid = $this->get_mappingid('attendancejourneys_session', $data->sourcesessionid, 0);
        $data->createdby = $this->get_mappingid('user', $data->createdby, 0);
        $data->decidedby = $this->get_mappingid('user', $data->decidedby, 0);
        if ($data->userid && $data->targetjourneyid && $data->targetsessionid && $data->sourcesessionid) {
            $newid = $DB->insert_record('attendancejourneys_equivalences', $data);
            $this->set_mapping('attendancejourneys_equivalence', $oldid, $newid);
        }
    }

    /**
     * Restore a historical decision with mapped participant and actor identifiers.
     *
     * @param array $data Historical decision from backup XML.
     * @return void
     */
    protected function process_attendancejourneys_equivlog($data) {
        global $DB;
        $data = (object) $data;
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $data->equivalenceid = $this->get_new_parentid('attendancejourneys_equivalence');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->actorid = $this->get_mappingid('user', $data->actorid, 0);
        if ($data->userid && $data->equivalenceid) {
            $DB->insert_record('attendancejourneys_equivlog', $data);
        }
    }

    /**
     * Restore related files and finish remapping the restored activity data.
     * @return void
     */
    protected function after_execute() {
        global $CFG, $DB;
        foreach ($this->attemptroots as $journeyid => $oldroot) {
            $rootid = $this->get_mappingid('attendancejourneys_journey', $oldroot, 0);
            if (!$rootid) {
                throw new coding_exception('A restored retake requires its logical obligation.');
            }
            $DB->set_field('attendancejourneys_journeys', 'rootjourneyid', $rootid, ['id' => $journeyid]);
        }
        $this->add_related_files('mod_attendancejourneys', 'intro', null);
        require_once($CFG->dirroot . '/mod/attendancejourneys/lib.php');
        $activityid = $this->get_new_parentid('attendancejourneys');
        $activity = $DB->get_record('attendancejourneys', ['id' => $activityid]);
        if ($activity) {
            attendancejourneys_refresh_events((int) $activity->course, $activity);
        }
    }
    /**
     * Restore a personal attempt opening, preserving its audit date.
     *
     * @param array $data Backup values.
     * @return void
     */
    protected function process_attendancejourneys_attempt($data): void {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $data->rootjourneyid = $this->get_mappingid('attendancejourneys_journey', $data->rootjourneyid, 0);
        $data->journeyid = $this->get_mappingid('attendancejourneys_journey', $data->journeyid, 0);
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->actorid = $this->get_mappingid('user', $data->actorid, 0);
        if ($data->rootjourneyid && $data->journeyid && $data->userid) {
            $newid = $DB->insert_record('attendancejourneys_attempts', $data);
            $this->set_mapping('attendancejourneys_attempt', $oldid, $newid);
        }
    }
    /**
     * Restore a current participant policy and preserve its event mapping.
     *
     * @param array $data Backed-up policy fields.
     */
    protected function process_attendancejourneys_obligation($data): void {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data = $this->map_individual_obligation($data);
        if ($data->userid) {
            $newid = $DB->insert_record('attendancejourneys_obligations', $data);
            $this->set_mapping('attendancejourneys_obligation', $oldid, $newid);
        }
    }

    /**
     * Restore the decision history without inventing an additional decision.
     *
     * @param array $data Backed-up decision fields.
     */
    protected function process_attendancejourneys_obligationlog($data): void {
        global $DB;
        $data = $this->map_individual_obligation((object) $data);
        if ($data->userid) {
            $DB->insert_record('attendancejourneys_obligationlog', $data);
        }
    }

    /**
     * Map the owning activity, journey, people and nonzero pedagogical dates.
     *
     * @param \stdClass $data Policy or history row.
     * @return \stdClass Remapped row.
     */
    private function map_individual_obligation(\stdClass $data): \stdClass {
        $data->attendancejourneysid = $this->get_new_parentid('attendancejourneys');
        $data->journeyid = $this->get_new_parentid('attendancejourneys_journey');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->actorid = $this->get_mappingid('user', $data->actorid, 0);
        // Only pedagogical boundaries move with the course calendar; decision audit timestamps remain historical.
        foreach (['starttime', 'endtime', 'previousstarttime', 'previousendtime'] as $field) {
            if (property_exists($data, $field) && !empty($data->$field)) {
                $data->$field = $this->apply_date_offset($data->$field);
            }
        }
        return $data;
    }
}
