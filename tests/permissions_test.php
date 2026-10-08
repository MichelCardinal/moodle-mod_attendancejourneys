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

#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_require_journey_access')]
/**
 * Default Moodle role-capability tests.
 *
 * @covers ::attendancejourneys_require_journey_access
 * @package    mod_attendancejourneys
 * @category   test
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class permissions_test extends \advanced_testcase {
    public function test_default_roles_follow_the_documented_separation_of_duties(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $context = \context_module::instance($activity->cmid);
        $coursecontext = \context_course::instance($course->id);
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'teacher');
        $editingteacher = $generator->create_and_enrol($course, 'editingteacher');

        $this->assertTrue(has_capability('mod/attendancejourneys:view', $context, $student));
        $this->assertTrue(has_capability('mod/attendancejourneys:selfrecord', $context, $student));
        $this->assertFalse(has_capability('mod/attendancejourneys:takeattendance', $context, $student));
        $this->assertFalse(has_capability('mod/attendancejourneys:viewreports', $context, $student));
        $this->assertFalse(has_capability('mod/attendancejourneys:managesessions', $context, $student));
        $this->assertFalse(has_capability('moodle/course:managegroups', $coursecontext, $student));

        $this->assertTrue(has_capability('mod/attendancejourneys:takeattendance', $context, $teacher));
        $this->assertTrue(has_capability('mod/attendancejourneys:viewreports', $context, $teacher));
        $this->assertFalse(has_capability('mod/attendancejourneys:managesessions', $context, $teacher));
        $this->assertFalse(has_capability('mod/attendancejourneys:managejourneys', $context, $teacher));
        $this->assertFalse(has_capability('mod/attendancejourneys:resetuserdata', $context, $teacher));
        $this->assertFalse(has_capability('moodle/course:managegroups', $coursecontext, $teacher));

        $this->assertTrue(has_capability('mod/attendancejourneys:takeattendance', $context, $editingteacher));
        $this->assertTrue(has_capability('mod/attendancejourneys:managesessions', $context, $editingteacher));
        $this->assertTrue(has_capability('mod/attendancejourneys:managejourneys', $context, $editingteacher));
        $this->assertTrue(has_capability('moodle/course:managegroups', $coursecontext, $editingteacher));
        $this->assertFalse(has_capability('mod/attendancejourneys:resetuserdata', $context, $editingteacher));
    }

    /**
     * Journey management respects separate groups, including direct links and whole-audience changes.
     */
    public function test_journey_access_respects_separate_groups(): void {
        global $DB;
        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id, 'journeygrading' => 1]);
        $cm = get_coursemodule_from_id('attendancejourneys', $activity->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $other = $generator->create_and_enrol($course, 'student');
        $first = $generator->create_group(['courseid' => $course->id]);
        $second = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $first->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $first->id, 'userid' => $student->id]);
        $generator->create_group_member(['groupid' => $second->id, 'userid' => $other->id]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $context->id);
        $this->setUser($teacher);
        $journey = $DB->get_record('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id], '*', MUST_EXIST);
        $this->assertTrue(has_capability('mod/attendancejourneys:managejourneys', $context));
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $context));
        attendancejourneys_require_journey_access($cm, $context, $journey);
        $blocked = 0;
        try {
            attendancejourneys_require_journey_access($cm, $context, $journey, true);
        } catch (\required_capability_exception $exception) {
            $blocked++;
        }
        $journey->groupid = $second->id;
        try {
            attendancejourneys_require_journey_access($cm, $context, $journey);
        } catch (\required_capability_exception $exception) {
            $blocked++;
        }
        $journey->groupid = $first->id;
        attendancejourneys_require_journey_access($cm, $context, $journey, true);
        $DB->insert_record('attendancejourneys_members', (object) [
            'journeyid' => $journey->id, 'userid' => $other->id, 'active' => 0,
        ]);
        try {
            attendancejourneys_require_journey_access($cm, $context, $journey, true);
        } catch (\required_capability_exception $exception) {
            $blocked++;
        }
        $this->assertSame(3, $blocked);
        $this->setAdminUser();
        attendancejourneys_require_journey_access($cm, $context, $journey, true);
        $journey->attendancejourneysid = -1;
        $this->expectException(\coding_exception::class);
        attendancejourneys_require_journey_access($cm, $context, $journey);
    }
}
