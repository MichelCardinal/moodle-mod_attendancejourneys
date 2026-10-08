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

use mod_attendancejourneys\privacy\provider;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\privacy\provider::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_delete_journey')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_journey_deletion_info')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_reset_user_data')]
/**
 * Explicit retake links survive native backups and implement Moodle personal data handling.
 *
 * @covers \mod_attendancejourneys\privacy\provider
 * @covers ::attendancejourneys_delete_journey
 * @covers ::attendancejourneys_journey_deletion_info
 * @covers ::attendancejourneys_reset_user_data
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_storage_test extends \core_privacy\tests\provider_testcase {
    /**
     * Create a private explicit retake family with no inferred historic assignments.
     *
     * @return array Course, activity, root, retake, learner, actor and opening.
     */
    private function fixture(): array {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id, 'journeygrading' => 1]);
        $learner = $generator->create_and_enrol($course, 'student');
        $actor = $generator->create_and_enrol($course, 'editingteacher');
        $root = $DB->get_record('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id], '*', MUST_EXIST);
        $retake = clone $root;
        unset($retake->id);
        $retake->name = 'Retake sessions';
        $retake->rootjourneyid = $root->id;
        $retake->gradeitemnumber = null;
        $retake->audiencemode = 2;
        $retake->id = $DB->insert_record('attendancejourneys_journeys', $retake);
        $opening = (object) ['attendancejourneysid' => $activity->id, 'rootjourneyid' => $root->id,
            'journeyid' => $retake->id, 'userid' => $learner->id, 'attemptnumber' => 2,
            'actorid' => $actor->id, 'reason' => 'Approved new attempt', 'timecreated' => time() - 3 * DAYSECS];
        $opening->id = $DB->insert_record('attendancejourneys_attempts', $opening);
        return [$course, $activity, $root, $retake, $learner, $actor, $opening];
    }

    /**
     * Both participant and author discover their data; participant exports the complete opening.
     */
    public function test_privacy_discovery_export_and_author_anonymisation(): void {
        global $DB;
        [, $activity, , $retake, $learner, $actor, $opening] = $this->fixture();
        $context = \context_module::instance($activity->cmid);
        foreach ([$learner, $actor] as $user) {
            $this->assertContains((int) $context->id, array_map('intval', provider::get_contexts_for_userid($user->id)
                ->get_contextids()));
        }
        $users = new \core_privacy\local\request\userlist($context, 'mod_attendancejourneys');
        provider::get_users_in_context($users);
        $this->assertContains((int) $learner->id, array_map('intval', $users->get_userids()));
        $this->assertContains((int) $actor->id, array_map('intval', $users->get_userids()));
        $approved = new \core_privacy\local\request\approved_contextlist($learner, 'mod_attendancejourneys', [$context->id]);
        provider::export_user_data($approved);
        $data = \core_privacy\local\request\writer::with_context($context)->get_data([]);
        $this->assertCount(1, $data->attendance_attempts);
        $this->assertEquals($opening->timecreated, $data->attendance_attempts[0]->timecreated);
        $this->assertSame('Approved new attempt', $data->attendance_attempts[0]->reason);
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $actor,
            'mod_attendancejourneys',
            [$context->id]
        ));
        $row = $DB->get_record('attendancejourneys_attempts', ['id' => $opening->id], '*', MUST_EXIST);
        $this->assertEquals(0, $row->actorid);
        $this->assertNull($row->reason);
        $this->assertEquals($opening->timecreated, $row->timecreated);
        $this->assertEquals($retake->id, $row->journeyid);
        provider::delete_data_for_user($approved);
        $this->assertSame(0, $DB->count_records('attendancejourneys_attempts'));
    }

    /**
     * A context-wide personal data erasure removes attempt decisions.
     */
    public function test_privacy_context_erasure_removes_openings(): void {
        [, $activity] = $this->fixture();
        provider::delete_data_for_all_users_in_context(\context_module::instance($activity->cmid));
        global $DB;
        $this->assertSame(0, $DB->count_records('attendancejourneys_attempts'));
    }

    /**
     * Participant reset removes the opening without deleting the shared retake structure.
     */
    public function test_participant_reset_removes_only_personal_attempt_data(): void {
        global $DB;
        [, $activity, $root, $retake, $learner, $actor] = $this->fixture();
        $cm = get_coursemodule_from_id('attendancejourneys', $activity->cmid, 0, false, MUST_EXIST);
        attendancejourneys_reset_user_data($activity, $cm, $learner->id, $actor->id);
        $this->assertSame(0, $DB->count_records('attendancejourneys_attempts'));
        $this->assertTrue($DB->record_exists('attendancejourneys_journeys', ['id' => $root->id]));
        $this->assertTrue($DB->record_exists('attendancejourneys_journeys', ['id' => $retake->id]));
    }

    /**
     * Deletion cannot orphan a linked retake or discard a personal opening history.
     */
    public function test_journey_deletion_preserves_linked_attempt_history(): void {
        global $DB;
        [, $activity, $root, $retake] = $this->fixture();
        foreach ([$root, $retake] as $journey) {
            $this->assertFalse(attendancejourneys_journey_deletion_info($activity->id, $journey->id)->candelete);
            try {
                attendancejourneys_delete_journey($activity->id, $journey->id);
                $this->fail('A linked attempt must not be deleted.');
            } catch (\coding_exception $exception) {
                $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
            }
        }
        $this->assertSame(1, $DB->count_records('attendancejourneys_attempts'));
        // A structure-only backup still protects the root from orphaning its retake.
        $DB->delete_records('attendancejourneys_attempts');
        $this->assertFalse(attendancejourneys_journey_deletion_info($activity->id, $root->id)->candelete);
    }

    /**
     * Native user-data backups remap both ends of the link and retain audit dates.
     */
    public function test_backup_with_users_preserves_opening_and_remaps_family(): void {
        $this->roundtrip(true);
    }

    /**
     * Structure backups keep the logical link without copying personal decisions.
     */
    public function test_structure_backup_omits_personal_openings(): void {
        $this->roundtrip(false);
    }

    /**
     * Exercise native Moodle backup and restore with a nonzero pedagogical date offset.
     *
     * @param bool $userdata Whether to include personal data.
     */
    private function roundtrip(bool $userdata): void {
        global $CFG, $DB, $USER;
        [$course, $activity, $root, $retake, $learner, $actor, $opening] = $this->fixture();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        $CFG->backup_file_logger_level = \backup::LOG_NONE;
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
        $newcourse = \restore_dbops::create_new_course('Restored attempts', 'attempts-' . uniqid(), $course->category);
        $restore = new \restore_controller(
            $backupid,
            $newcourse,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $restore->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $restore->get_plan()->get_setting('users')->set_value($userdata);
        $restore->get_plan()->get_setting('course_startdate')->set_value((int) $course->startdate + 14 * DAYSECS);
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();
        $restored = $DB->get_record('attendancejourneys', ['course' => $newcourse], '*', MUST_EXIST);
        $restoredretake = $DB->get_record('attendancejourneys_journeys', [
            'attendancejourneysid' => $restored->id, 'name' => $retake->name,
        ], '*', MUST_EXIST);
        $restoredroot = $DB->get_record('attendancejourneys_journeys', [
            'attendancejourneysid' => $restored->id, 'name' => $root->name,
        ], '*', MUST_EXIST);
        $this->assertNotEquals($root->id, $restoredroot->id);
        $this->assertNotEquals($retake->id, $restoredretake->id);
        $this->assertEquals($restoredroot->id, $restoredretake->rootjourneyid);
        $rows = $DB->get_records('attendancejourneys_attempts', ['attendancejourneysid' => $restored->id]);
        $this->assertCount($userdata ? 1 : 0, $rows);
        if ($userdata) {
            $row = reset($rows);
            $this->assertEquals($restoredroot->id, $row->rootjourneyid);
            $this->assertEquals($restoredretake->id, $row->journeyid);
            $this->assertEquals($learner->id, $row->userid);
            $this->assertEquals($actor->id, $row->actorid);
            $this->assertEquals(2, $row->attemptnumber);
            $this->assertEquals($opening->timecreated, $row->timecreated);
            $this->assertSame($opening->reason, $row->reason);
        }
        $this->assertEquals($opening, $DB->get_record('attendancejourneys_attempts', ['id' => $opening->id]));
    }
}
