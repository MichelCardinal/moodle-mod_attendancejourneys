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
 * Attendance Journeys structure backup step.
 *
 * @package    mod_attendancejourneys
 * @category   backup
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_attendancejourneys_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the activity data structure and user-data elements.
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $activity = new backup_nested_element('attendancejourneys', ['id'], [
            'name', 'intro', 'introformat', 'experiencemode', 'percentageenabled', 'passinggrade', 'gradeenabled',
            'excusedenabled', 'excusedmode', 'journeygrading', 'nextgradeitem',
            'completionrecorded', 'completionallsessions', 'completionpass', 'completionclosed', 'studentselfrecord',
            'calendarenabled',
            'journeytermsingular', 'journeytermplural',
            'timecreated', 'timemodified',
        ]);
        $sessions = new backup_nested_element('sessions');
        $terminologies = new backup_nested_element('terminologies');
        $terminology = new backup_nested_element('terminology', ['id'], [
            'concept', 'langcode', 'singular', 'plural',
        ]);
        $rooms = new backup_nested_element('rooms');
        $room = new backup_nested_element('room', ['id'], [
            'name', 'code', 'capacity', 'location', 'notes', 'active', 'timecreated', 'timemodified',
        ]);
        $journeys = new backup_nested_element('journeys');
        $journey = new backup_nested_element('journey', ['id'], [
            'name', 'description', 'passinggrade', 'groupid', 'startdate', 'enddate', 'defaultmodality', 'rootjourneyid',
            'capacity', 'waitlistenabled', 'required', 'gradeitemnumber', 'audiencemode',
            'active', 'completedby',
            'timecompleted',
            'timecreated', 'timemodified',
        ]);
        $session = new backup_nested_element('session', ['id'], [
            'journeyid', 'name', 'sessiondate', 'duration', 'groupid', 'roomid', 'description', 'modality', 'location',
            'meetingurl', 'timecreated', 'timemodified', 'cancelled',
        ]);
        $cancellationhistory = new backup_nested_element('cancellationhistory');
        $sessionchange = new backup_nested_element('change', ['id'], ['actorid', 'action', 'reason', 'timecreated']);
        $records = new backup_nested_element('records');
        $record = new backup_nested_element('record', ['id'], [
            'userid', 'status', 'minutesabsent', 'remarks', 'takenby', 'approved', 'approvedby', 'timeapproved',
            'changesrequested', 'reviewnote', 'reviewedby', 'timereviewed', 'timemodified',
        ]);
        $reviews = new backup_nested_element('reviews');
        $review = new backup_nested_element('review', ['id'], [
            'sessionid', 'userid', 'action', 'actorid', 'note', 'timecreated',
        ]);
        $closures = new backup_nested_element('closures');
        $members = new backup_nested_element('members');
        $member = new backup_nested_element('member', ['id'], [
            'userid', 'attemptnumber', 'active', 'timeassigned', 'timeended',
        ]);
        $closure = new backup_nested_element('closure', ['id'], [
            'journeyid', 'userid', 'active', 'applicablesessions', 'recordedsessions', 'presentminutes', 'possibleminutes',
            'percentage', 'threshold', 'result', 'closedby', 'timeclosed', 'reopenedby', 'timereopened',
        ]);
        $waitlists = new backup_nested_element('waitlists');
        $waitlist = new backup_nested_element('waitlist', ['id'], [
            'journeyid', 'userid', 'status', 'createdby', 'timecreated', 'updatedby', 'timeupdated',
        ]);
        $equivalences = new backup_nested_element('equivalences');
        $equivalence = new backup_nested_element('equivalence', ['id'], [
            'userid', 'targetjourneyid', 'targetsessionid', 'sourcesessionid', 'status', 'reason',
            'createdby', 'timecreated', 'decidedby', 'timedecided', 'decisionnote', 'timemodified',
        ]);

        $equivlogs = new backup_nested_element('decisionhistory');
        $equivlog = new backup_nested_element('decision', ['id'], [
            'userid', 'actorid', 'action', 'previousstatus', 'status', 'note', 'timecreated',
        ]);

        $obligations = new backup_nested_element('obligations');
        $obligation = new backup_nested_element('obligation', ['id'], [
            'userid', 'waived', 'starttime', 'endtime', 'actorid', 'revision', 'reason', 'timemodified',
        ]);
        $obligationhistory = new backup_nested_element('obligationhistory');
        $obligationdecision = new backup_nested_element('obligationdecision', ['id'], [
            'userid', 'waived', 'starttime', 'endtime', 'actorid', 'revision', 'reason',
            'previouswaived', 'previousstarttime', 'previousendtime', 'timecreated',
        ]);

        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'rootjourneyid', 'journeyid', 'userid', 'attemptnumber', 'actorid', 'timecreated', 'reason',
        ]);
        $activity->add_child($journeys);
        $activity->add_child($terminologies);
        $terminologies->add_child($terminology);
        $journeys->add_child($journey);
        $journey->add_child($obligations);
        $obligations->add_child($obligation);
        $journey->add_child($obligationhistory);
        $obligationhistory->add_child($obligationdecision);
        $journey->add_child($members);
        $members->add_child($member);
        $activity->add_child($rooms);
        $rooms->add_child($room);
        $activity->add_child($sessions);
        $sessions->add_child($session);
        $session->add_child($cancellationhistory);
        $cancellationhistory->add_child($sessionchange);
        $session->add_child($records);
        $records->add_child($record);
        $record->add_child($reviews);
        $reviews->add_child($review);
        $activity->add_child($closures);
        $closures->add_child($closure);
        $activity->add_child($waitlists);
        $waitlists->add_child($waitlist);
        $activity->add_child($equivalences);
        $equivalences->add_child($equivalence);
        $equivalence->add_child($equivlogs);
        $equivlogs->add_child($equivlog);
        $activity->add_child($attempts);
        $attempts->add_child($attempt);

        $activity->set_source_table('attendancejourneys', ['id' => backup::VAR_ACTIVITYID]);
        $terminology->set_source_table('attendancejourneys_terms', ['attendancejourneysid' => backup::VAR_PARENTID]);
        $journey->set_source_table('attendancejourneys_journeys', ['attendancejourneysid' => backup::VAR_PARENTID]);
        $room->set_source_table('attendancejourneys_rooms', ['attendancejourneysid' => backup::VAR_PARENTID]);
        $session->set_source_table('attendancejourneys_sessions', ['attendancejourneysid' => backup::VAR_PARENTID]);
        if ($userinfo) {
            $attempt->set_source_table('attendancejourneys_attempts', ['attendancejourneysid' => backup::VAR_PARENTID]);
            $obligation->set_source_table('attendancejourneys_obligations', ['journeyid' => backup::VAR_PARENTID]);
            $obligationdecision->set_source_table('attendancejourneys_obligationlog', ['journeyid' => backup::VAR_PARENTID]);
            $sessionchange->set_source_table('attendancejourneys_sessionlog', ['sessionid' => backup::VAR_PARENTID]);
            $equivlog->set_source_table('attendancejourneys_equivlog', ['equivalenceid' => backup::VAR_PARENTID]);
            $member->set_source_table('attendancejourneys_members', ['journeyid' => backup::VAR_PARENTID]);
            $record->set_source_table('attendancejourneys_records', ['sessionid' => backup::VAR_PARENTID]);
            $review->set_source_table('attendancejourneys_reviews', ['recordid' => backup::VAR_PARENTID]);
            $closure->set_source_table('attendancejourneys_closures', ['attendancejourneysid' => backup::VAR_PARENTID]);
            $waitlist->set_source_table('attendancejourneys_waitlist', ['attendancejourneysid' => backup::VAR_PARENTID]);
            $equivalence->set_source_table(
                'attendancejourneys_equivalences',
                ['attendancejourneysid' => backup::VAR_PARENTID]
            );
        }

        $sessionchange->annotate_ids('user', 'actorid');
        $session->annotate_ids('group', 'groupid');
        $session->annotate_ids('attendancejourneys_room', 'roomid');
        $journey->annotate_ids('group', 'groupid');
        $attempt->annotate_ids('user', 'userid');
        $attempt->annotate_ids('user', 'actorid');
        $journey->annotate_ids('user', 'completedby');
        $obligation->annotate_ids('user', 'userid');
        $obligation->annotate_ids('user', 'actorid');
        $obligationdecision->annotate_ids('user', 'userid');
        $obligationdecision->annotate_ids('user', 'actorid');
        $member->annotate_ids('user', 'userid');
        $record->annotate_ids('user', 'userid');
        $record->annotate_ids('user', 'takenby');
        $record->annotate_ids('user', 'approvedby');
        $record->annotate_ids('user', 'reviewedby');
        $review->annotate_ids('user', 'userid');
        $review->annotate_ids('user', 'actorid');
        $closure->annotate_ids('user', 'userid');
        $closure->annotate_ids('user', 'closedby');
        $closure->annotate_ids('user', 'reopenedby');
        $waitlist->annotate_ids('attendancejourneys_journey', 'journeyid');
        $waitlist->annotate_ids('user', 'userid');
        $waitlist->annotate_ids('user', 'createdby');
        $waitlist->annotate_ids('user', 'updatedby');
        $equivlog->annotate_ids('user', 'userid');
        $equivlog->annotate_ids('user', 'actorid');
        $equivalence->annotate_ids('user', 'userid');
        $equivalence->annotate_ids('user', 'createdby');
        $equivalence->annotate_ids('user', 'decidedby');
        $equivalence->annotate_ids('attendancejourneys_journey', 'targetjourneyid');
        $equivalence->annotate_ids('attendancejourneys_session', 'targetsessionid');
        $equivalence->annotate_ids('attendancejourneys_session', 'sourcesessionid');
        $activity->annotate_files('mod_attendancejourneys', 'intro', null);

        return $this->prepare_activity_structure($activity);
    }
}
