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
 * Historical references must survive session and journey deletion attempts.
 *
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deletion_history_test extends \advanced_testcase {
    /**
     * Create two journeys and sessions without physical attendance records.
     *
     * @return array Activity, participant, journeys and sessions.
     */
    private function fixture(): array {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $participant = $generator->create_user();
        $journeys = [];
        $sessions = [];
        foreach (['Source', 'Target'] as $name) {
            $journeyid = $DB->insert_record('attendancejourneys_journeys', (object) [
                'attendancejourneysid' => $activity->id, 'name' => $name, 'active' => 1,
            ]);
            $journeys[] = $DB->get_record('attendancejourneys_journeys', ['id' => $journeyid]);
            $id = $DB->insert_record('attendancejourneys_sessions', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid,
                'name' => $name . ' session', 'duration' => 60, 'sessiondate' => time() - DAYSECS,
            ]);
            $sessions[] = $DB->get_record('attendancejourneys_sessions', ['id' => $id]);
        }
        return [$activity, $participant, $journeys, $sessions];
    }

    /**
     * Both ends remain protected for every request/decision state, even without physical records.
     */
    public function test_equivalences_protect_both_sessions_and_journeys(): void {
        global $DB;
        [$activity, $participant, $journeys, $sessions] = $this->fixture();
        $equivalenceid = $DB->insert_record('attendancejourneys_equivalences', (object) [
            'attendancejourneysid' => $activity->id, 'userid' => $participant->id,
            'targetjourneyid' => $journeys[1]->id, 'targetsessionid' => $sessions[1]->id,
            'sourcesessionid' => $sessions[0]->id, 'status' => 'pending',
        ]);
        foreach (['pending', 'approved', 'rejected', 'revoked'] as $status) {
            $DB->set_field('attendancejourneys_equivalences', 'status', $status, ['id' => $equivalenceid]);
            foreach ($sessions as $session) {
                $this->assertFalse(attendancejourneys_session_has_records((int) $session->id));
                $this->assertSame('sessiondeleteblockedequivalence', attendancejourneys_session_deletion_blocker($session));
            }
            foreach ($journeys as $journey) {
                $info = attendancejourneys_journey_deletion_info((int) $activity->id, (int) $journey->id);
                $this->assertFalse($info->candelete);
                $this->assertSame(1, $info->equivalences);
                try {
                    attendancejourneys_delete_journey((int) $activity->id, (int) $journey->id);
                    $this->fail('A referenced journey must remain intact.');
                } catch (\coding_exception $exception) {
                    $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
                }
            }
        }
        $this->assertSame(2, $DB->count_records('attendancejourneys_sessions'));
        $this->assertTrue($DB->record_exists('attendancejourneys_equivalences', ['id' => $equivalenceid]));
    }

    /**
     * Reopening keeps previous closure history; it does not authorise deleting session identifiers.
     */
    public function test_inactive_closure_still_protects_session_deletion(): void {
        global $DB;
        [$activity, $participant, $journeys, $sessions] = $this->fixture();
        $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeys[0]->id, 'userid' => $participant->id,
            'active' => 0, 'result' => 'passed', 'percentage' => 100, 'threshold' => 80,
        ]);
        $this->assertFalse(attendancejourneys_session_has_final_results($sessions[0]));
        $this->assertSame('sessiondeleteblockedhistory', attendancejourneys_session_deletion_blocker($sessions[0]));
        $this->assertNull(attendancejourneys_session_deletion_blocker($sessions[1]));
        $this->assertFalse(attendancejourneys_journey_deletion_info((int) $activity->id, (int) $journeys[0]->id)->candelete);
    }

    /**
     * Empty unreferenced sessions and journeys remain deletable.
     */
    public function test_empty_unreferenced_journey_remains_deletable(): void {
        global $DB;
        [$activity, , $journeys, $sessions] = $this->fixture();
        $this->assertNull(attendancejourneys_session_deletion_blocker($sessions[0]));
        $info = attendancejourneys_journey_deletion_info((int) $activity->id, (int) $journeys[0]->id);
        $this->assertTrue($info->candelete);
        $this->assertSame(0, $info->equivalences);
        $this->assertSame(1, attendancejourneys_delete_journey((int) $activity->id, (int) $journeys[0]->id));
        $this->assertFalse($DB->record_exists('attendancejourneys_sessions', ['id' => $sessions[0]->id]));
        $this->assertTrue($DB->record_exists('attendancejourneys_sessions', ['id' => $sessions[1]->id]));
    }

    /**
     * A restricted teacher may manage their own session, but not other-group or shared audiences.
     */
    public function test_session_management_respects_groups_and_parent_journey(): void {
        global $DB;
        [$activity, $participant, $journeys, $sessions] = $this->fixture();
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $generator->enrol_user($teacher->id, $activity->course, $roleid);
        $own = $generator->create_group(['courseid' => $activity->course]);
        $other = $generator->create_group(['courseid' => $activity->course]);
        $generator->create_group_member(['groupid' => $own->id, 'userid' => $teacher->id]);
        $context = \context_module::instance($activity->cmid);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $context->id);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $activity->cmid]);
        rebuild_course_cache($activity->course, true);
        $cm = get_coursemodule_from_id('attendancejourneys', $activity->cmid, 0, false, MUST_EXIST);
        $this->setUser($teacher);
        $sessions[0]->groupid = $own->id;
        $this->assertNull(attendancejourneys_require_session_management_access($cm, $context, $sessions[0]));
        foreach ([$other->id, 0] as $groupid) {
            $sessions[0]->groupid = $groupid;
            try {
                attendancejourneys_require_session_management_access($cm, $context, $sessions[0]);
                $this->fail('A restricted teacher cannot manage this whole audience.');
            } catch (\required_capability_exception $exception) {
                $this->assertSame('nopermissions', $exception->errorcode);
            }
        }
        $sessions[0]->groupid = $own->id;
        $DB->set_field('attendancejourneys_journeys', 'groupid', $other->id, ['id' => $journeys[0]->id]);
        try {
            attendancejourneys_require_session_management_access($cm, $context, $sessions[0]);
            $this->fail('The parent journey must also be visible.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $DB->set_field('attendancejourneys_journeys', 'groupid', 0, ['id' => $journeys[0]->id]);
        $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $sessions[0]->id, 'userid' => $participant->id, 'status' => 'present', 'takenby' => 2,
        ]);
        try {
            attendancejourneys_require_session_management_access($cm, $context, $sessions[0]);
            $this->fail('Historical attendance outside the visible audience must be protected.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $DB->delete_records('attendancejourneys_records', ['sessionid' => $sessions[0]->id]);
        $DB->set_field('attendancejourneys_journeys', 'audiencemode', 2, ['id' => $journeys[0]->id]);
        $DB->insert_record('attendancejourneys_members', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeys[0]->id, 'userid' => $participant->id,
        ]);
        try {
            attendancejourneys_require_session_management_access($cm, $context, $sessions[0]);
            $this->fail('A fixed audience outside the visible groups must be protected.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $this->assertSame(2, $DB->count_records('attendancejourneys_sessions'));
    }

    /**
     * New sessions and reassignment cannot add obligations to a frozen destination.
     */
    public function test_destination_scope_requires_reopening_without_changing_history(): void {
        global $DB;
        [$activity, $participant, $journeys, $sessions] = $this->fixture();
        $DB->set_field('attendancejourneys', 'journeygrading', 1, ['id' => $activity->id]);
        $cm = get_coursemodule_from_id('attendancejourneys', $activity->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $closureid = $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeys[1]->id, 'userid' => $participant->id,
            'active' => 1, 'result' => 'passed', 'percentage' => 100, 'threshold' => 80,
        ]);
        $before = $DB->get_record('attendancejourneys_closures', ['id' => $closureid]);
        $destination = clone $sessions[0];
        $destination->journeyid = $journeys[1]->id;
        try {
            attendancejourneys_require_session_destination_access($cm, $context, $destination);
            $this->fail('A new obligation cannot enter a scope with an active final result.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('sessiondestinationblockedfinal', $exception->errorcode);
        }
        $this->assertEquals($before, $DB->get_record('attendancejourneys_closures', ['id' => $closureid]));
        $DB->set_field('attendancejourneys_closures', 'active', 0, ['id' => $closureid]);
        $this->assertNull(attendancejourneys_require_session_destination_access($cm, $context, $destination));
        $DB->set_field('attendancejourneys_journeys', 'active', 0, ['id' => $journeys[1]->id]);
        try {
            attendancejourneys_require_session_destination_access($cm, $context, $destination);
            $this->fail('An archived journey also requires reopening.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('sessiondestinationblockedfinal', $exception->errorcode);
        }
        $this->assertSame(100.0, (float) $DB->get_field('attendancejourneys_closures', 'percentage', ['id' => $closureid]));
        $this->assertSame(2, $DB->count_records('attendancejourneys_sessions'));
        $destination->journeyid = 0;
        try {
            attendancejourneys_require_session_destination_access($cm, $context, $destination);
            $this->fail('The new grading mode must never create an orphan session.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('sessionjourneyrequired', $exception->errorcode);
        }
        $DB->set_field('attendancejourneys', 'journeygrading', 0, ['id' => $activity->id]);
        $this->assertNull(attendancejourneys_require_session_destination_access($cm, $context, $destination));
    }

    /**
     * Destination access considers the future audience, separately from source attendance.
     */
    public function test_destination_audience_respects_groups_and_fixed_members(): void {
        global $DB;
        [$activity, $participant, $journeys, $sessions] = $this->fixture();
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $generator->enrol_user($teacher->id, $activity->course, $roleid);
        $own = $generator->create_group(['courseid' => $activity->course]);
        $other = $generator->create_group(['courseid' => $activity->course]);
        $generator->create_group_member(['groupid' => $own->id, 'userid' => $teacher->id]);
        $context = \context_module::instance($activity->cmid);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $context->id);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $activity->cmid]);
        rebuild_course_cache($activity->course, true);
        $cm = get_coursemodule_from_id('attendancejourneys', $activity->cmid, 0, false, MUST_EXIST);
        $this->setUser($teacher);
        // A new duplicate has no source records, but changing its destination cannot broaden access.
        $destination = clone $sessions[0];
        $destination->groupid = $own->id;
        $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $sessions[0]->id, 'userid' => $participant->id, 'status' => 'present', 'takenby' => 2,
        ]);
        $this->assertNull(attendancejourneys_require_session_destination_access($cm, $context, $destination));
        foreach ([$other->id, 0] as $groupid) {
            $destination->groupid = $groupid;
            try {
                attendancejourneys_require_session_destination_access($cm, $context, $destination);
                $this->fail('A new destination cannot broaden a restricted teacher audience.');
            } catch (\required_capability_exception $exception) {
                $this->assertSame('nopermissions', $exception->errorcode);
            }
        }
        $destination->groupid = $own->id;
        $DB->set_field('attendancejourneys_journeys', 'audiencemode', 2, ['id' => $journeys[0]->id]);
        $DB->insert_record('attendancejourneys_members', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeys[0]->id, 'userid' => $participant->id,
        ]);
        try {
            attendancejourneys_require_session_destination_access($cm, $context, $destination);
            $this->fail('A hidden fixed participant remains protected in a new destination.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $this->assertSame(1, $DB->count_records('attendancejourneys_records'));
        $this->assertSame(2, $DB->count_records('attendancejourneys_sessions'));
    }
}
