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
 * Scope contracts needed by concurrent attendance journeys.
 *
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class multi_journey_scope_test extends \advanced_testcase {
    /**
     * Both assignments are returned without mixing inactive memberships.
     */
    public function test_plural_lookup_preserves_all_active_assignments(): void {
        global $DB;
        [$activity, $student, $context, $journeys] = $this->create_fixture();
        $actual = attendancejourneys_get_user_active_journeys($context, (int) $activity->id, (int) $student->id);
        $this->assertCount(2, $actual);
        $this->assertArrayHasKey($journeys[0]->id, $actual);
        $this->assertArrayHasKey($journeys[1]->id, $actual);
        $this->assertSame((int) $journeys[1]->id, (int) attendancejourneys_get_user_active_journey(
            $context,
            (int) $activity->id,
            (int) $student->id
        )->id);
        $DB->set_field('attendancejourneys_members', 'active', 0, ['journeyid' => $journeys[1]->id]);
        $actual = attendancejourneys_get_user_active_journeys($context, (int) $activity->id, (int) $student->id);
        $this->assertSame([(int) $journeys[0]->id], array_map('intval', array_keys($actual)));
        $this->assertSame([], attendancejourneys_get_user_active_journeys($context, (int) $activity->id, 0));
    }

    /**
     * Explicit reopening leaves the other result unchanged.
     */
    public function test_reopening_targets_only_the_requested_journey(): void {
        global $DB;
        [$activity, $student, $context, $journeys] = $this->create_fixture();
        $closures = [];
        foreach ($journeys as $journey) {
            $closures[] = $DB->insert_record('attendancejourneys_closures', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journey->id,
                'userid' => $student->id, 'active' => 1, 'percentage' => 100,
                'result' => 'passed', 'timeclosed' => time(),
            ]);
            $DB->set_field('attendancejourneys_members', 'active', 0, ['journeyid' => $journey->id]);
        }
        $this->assertTrue(attendancejourneys_reopen_user_journey($activity, (int) $student->id, 2, (int) $journeys[0]->id));
        $this->assertSame(0, (int) $DB->get_field('attendancejourneys_closures', 'active', ['id' => $closures[0]]));
        $this->assertSame(1, (int) $DB->get_field('attendancejourneys_closures', 'active', ['id' => $closures[1]]));
        $this->assertSame(0, (int) $DB->get_field('attendancejourneys_members', 'active', ['journeyid' => $journeys[1]->id]));
        $this->assertFalse(attendancejourneys_reopen_user_journey($activity, (int) $student->id, 2, (int) $journeys[0]->id));
        $this->assertSame(2, $DB->count_records('attendancejourneys_closures'));
    }

    /**
     * Membership and closure identifiers from another activity do not cross scopes.
     */
    public function test_other_activity_memberships_and_closures_are_isolated(): void {
        global $DB;
        [$activity, $student, $context, $journeys] = $this->create_fixture();
        $other = $this->getDataGenerator()->create_module('attendancejourneys', ['course' => $activity->course]);
        $foreign = clone $journeys[0];
        unset($foreign->id);
        $foreign->attendancejourneysid = $other->id;
        $foreign->id = $DB->insert_record('attendancejourneys_journeys', $foreign);
        $DB->insert_record('attendancejourneys_members', (object) [
            'attendancejourneysid' => $other->id, 'journeyid' => $foreign->id, 'userid' => $student->id,
            'attemptnumber' => 1, 'active' => 1, 'timeassigned' => time() + 100,
        ]);
        $closureid = $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $other->id, 'journeyid' => $foreign->id, 'userid' => $student->id,
            'active' => 1, 'percentage' => 100, 'result' => 'passed', 'timeclosed' => time(),
        ]);
        $actual = attendancejourneys_get_user_active_journeys($context, (int) $activity->id, (int) $student->id);
        $this->assertCount(2, $actual);
        $this->assertArrayNotHasKey($foreign->id, $actual);
        $this->assertFalse(attendancejourneys_reopen_user_journey($activity, (int) $student->id, 2, (int) $foreign->id));
        $this->assertSame(1, (int) $DB->get_field('attendancejourneys_closures', 'active', ['id' => $closureid]));
    }

    /**
     * Legacy group audiences are used only until explicit memberships exist.
     */
    public function test_legacy_group_audience_does_not_revive_closed_memberships(): void {
        global $DB;
        [$activity, $student, $context, $journeys] = $this->create_fixture();
        $DB->delete_records('attendancejourneys_members', ['attendancejourneysid' => $activity->id]);
        $generator = $this->getDataGenerator();
        $group = $generator->create_group(['courseid' => $activity->course]);
        $DB->set_field('attendancejourneys_journeys', 'groupid', $group->id, ['id' => $journeys[1]->id]);
        $actual = attendancejourneys_get_user_active_journeys($context, (int) $activity->id, (int) $student->id);
        $this->assertSame([(int) $journeys[0]->id], array_map('intval', array_keys($actual)));
        groups_add_member($group->id, $student->id);
        $this->assertCount(2, attendancejourneys_get_user_active_journeys($context, (int) $activity->id, (int) $student->id));
        $DB->insert_record('attendancejourneys_members', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeys[0]->id, 'userid' => $student->id,
            'attemptnumber' => 1, 'active' => 0, 'timeassigned' => time(), 'timeended' => time(),
        ]);
        $this->assertSame([], attendancejourneys_get_user_active_journeys($context, (int) $activity->id, (int) $student->id));
    }

    /**
     * New mode admits two obligations through the normal membership API.
     */
    public function test_new_mode_allows_multiple_memberships_without_duplicate_assignment(): void {
        global $DB;
        [$activity, $student, $context, $journeys] = $this->create_fixture();
        $DB->set_field('attendancejourneys', 'journeygrading', 1, ['id' => $activity->id]);
        $DB->delete_records('attendancejourneys_members', ['journeyid' => $journeys[1]->id]);
        $this->assertArrayHasKey($student->id, attendancejourneys_get_addable_journey_participants($context, $journeys[1]));
        $membershipid = attendancejourneys_assign_journey_member($context, $journeys[1], (int) $student->id);
        $this->assertGreaterThan(0, $membershipid);
        $this->assertCount(2, attendancejourneys_get_user_active_journeys($context, $activity->id, $student->id));
        $this->assertSame(0, attendancejourneys_assign_journey_member($context, $journeys[1], (int) $student->id));
    }

    /**
     * An ambiguous request must not silently calculate or close the latest journey.
     */
    public function test_new_mode_requires_explicit_scope_for_ambiguous_calculation(): void {
        global $DB;
        [$activity, $student] = $this->create_fixture();
        $DB->set_field('attendancejourneys', 'journeygrading', 1, ['id' => $activity->id]);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('journeyselectionrequired', 'attendancejourneys'));
        attendancejourneys_calculate_user_attendance($activity, $student->id);
    }

    /**
     * Self-recording works in either open journey and stops independently after closure.
     */
    public function test_self_recording_is_scoped_to_each_session_journey(): void {
        global $DB;
        [$activity, $student, $context, $journeys] = $this->create_fixture();
        $DB->set_field('attendancejourneys', 'journeygrading', 1, ['id' => $activity->id]);
        $sessions = [];
        foreach ($journeys as $journey) {
            $session = (object) ['attendancejourneysid' => $activity->id, 'journeyid' => $journey->id,
                'name' => 'Past session', 'sessiondate' => time() - HOURSECS, 'duration' => 60, 'groupid' => 0];
            $session->id = $DB->insert_record('attendancejourneys_sessions', $session);
            $sessions[] = $session;
            $this->assertTrue(attendancejourneys_user_can_self_record_session($context, $activity, $session, $student->id));
        }
        $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeys[0]->id, 'userid' => $student->id,
            'active' => 1, 'result' => 'passed', 'percentage' => 100,
        ]);
        $this->assertFalse(attendancejourneys_user_can_self_record_session($context, $activity, $sessions[0], $student->id));
        $this->assertTrue(attendancejourneys_user_can_self_record_session($context, $activity, $sessions[1], $student->id));
    }

    /**
     * A foreign module context cannot assign membership in this activity.
     */
    public function test_membership_rejects_another_module_context(): void {
        [$activity, $student, $context, $journeys] = $this->create_fixture();
        $other = $this->getDataGenerator()->create_module('attendancejourneys', ['course' => $activity->course]);
        $cm = get_coursemodule_from_instance('attendancejourneys', $other->id, $other->course, false, MUST_EXIST);
        $this->expectException(\dml_missing_record_exception::class);
        attendancejourneys_assign_journey_member(\context_module::instance($cm->id), $journeys[0], $student->id);
    }

    /**
     * Notices do not disclose journeys belonging to an inaccessible separate group.
     */
    public function test_other_journey_notice_respects_separate_groups(): void {
        global $DB;
        [$activity, $student, $context, $journeys] = $this->create_fixture();
        $generator = $this->getDataGenerator();
        $course = get_course($activity->course);
        $teacher = $generator->create_and_enrol($course, 'teacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('mod/attendancejourneys:managejourneys', CAP_ALLOW, $roleid, $context->id);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $context->id);
        $groups = [];
        foreach ($journeys as $journey) {
            $group = $generator->create_group(['courseid' => $activity->course]);
            $groups[] = $group;
            groups_add_member($group->id, $student->id);
            $DB->set_field('attendancejourneys_journeys', 'groupid', $group->id, ['id' => $journey->id]);
        }
        groups_add_member($groups[0]->id, $teacher->id);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $context->instanceid]);
        rebuild_course_cache($activity->course, true);
        $this->setUser($teacher);
        $this->assertSame([], attendancejourneys_other_journey_names($context, $activity->id, $student->id, $journeys[0]->id));
        $this->assertSame([(int) $journeys[0]->id], array_map(
            'intval',
            array_keys(attendancejourneys_get_user_report_journeys($context, $activity->id, $student->id))
        ));
        $this->assertSame([], attendancejourneys_find_journey_conflicts($context, $activity->id, $groups[0]->id, $journeys[0]->id));
        $this->setAdminUser();
        $this->assertSame(
            [$journeys[1]->name],
            attendancejourneys_other_journey_names($context, $activity->id, $student->id, $journeys[0]->id)
        );
    }

    /**
     * Report selection retains completed assignments without granting access to another student's reports.
     */
    public function test_report_selection_retains_closed_journeys_and_checks_reader(): void {
        global $DB;
        [$activity, $student, $context, $journeys] = $this->create_fixture();
        $DB->set_field('attendancejourneys_journeys', 'active', 0, ['id' => $journeys[0]->id]);
        $DB->set_field('attendancejourneys_members', 'active', 0, ['journeyid' => $journeys[0]->id]);
        $this->setUser($student);
        $visible = attendancejourneys_get_user_report_journeys($context, $activity->id, $student->id);
        $this->assertCount(2, $visible);
        $this->assertArrayHasKey($journeys[0]->id, $visible);
        $other = $this->getDataGenerator()->create_and_enrol(get_course($activity->course), 'student');
        $this->setUser($other);
        $this->assertSame([], attendancejourneys_get_user_report_journeys($context, $activity->id, $student->id));
    }

    /**
     * Creates two simultaneous memberships without changing the production admission policy.
     *
     * @return array
     */
    private function create_fixture(): array {
        global $DB;
        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $context = \context_module::instance($activity->cmid);
        $journeys = [];
        foreach (['Theory', 'Practice'] as $index => $name) {
            $journey = (object) ['attendancejourneysid' => $activity->id, 'name' => $name, 'description' => '',
                'active' => 1, 'timecreated' => time(), 'timemodified' => time()];
            $journey->id = $DB->insert_record('attendancejourneys_journeys', $journey);
            $DB->insert_record('attendancejourneys_members', (object) [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journey->id, 'userid' => $student->id,
                'attemptnumber' => $index + 1, 'active' => 1, 'timeassigned' => time() + $index,
            ]);
            $journeys[] = $journey;
        }
        return [$activity, $student, $context, $journeys];
    }
}
