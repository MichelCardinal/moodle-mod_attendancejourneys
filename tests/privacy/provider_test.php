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

namespace mod_attendancejourneys\privacy;

use core_privacy\local\request\userlist;
use core_privacy\tests\provider_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_attendancejourneys\privacy\provider::class)]
/**
 * Privacy provider tests.
 *
 * @covers \mod_attendancejourneys\privacy\provider
 * @package    mod_attendancejourneys
 * @category   test
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends provider_testcase {
    public function test_context_erasure_removes_journey_authorship_without_touching_other_activities(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $actor = $generator->create_user();
        $first = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $second = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $firstjourney = $this->create_completed_journey($first->id, $actor->id);
        $secondjourney = $this->create_completed_journey($second->id, $actor->id);
        $firstcontext = \context_module::instance($first->cmid);
        $secondcontext = \context_module::instance($second->cmid);

        provider::export_user_data(new \core_privacy\local\request\approved_contextlist(
            $actor,
            'mod_attendancejourneys',
            [$firstcontext->id]
        ));
        $export = \core_privacy\local\request\writer::with_context($firstcontext)->get_data([]);
        $this->assertCount(1, $export->authored_actions);
        $this->assertSame($firstjourney, $export->authored_actions[0]->recordid);

        provider::delete_data_for_all_users_in_context($firstcontext);

        $this->assertSame(0, (int) $DB->get_field('attendancejourneys_journeys', 'completedby', ['id' => $firstjourney]));
        $this->assertSame(0, (int) $DB->get_field('attendancejourneys_journeys', 'active', ['id' => $firstjourney]));
        $this->assertSame(
            (int) $actor->id,
            (int) $DB->get_field('attendancejourneys_journeys', 'completedby', ['id' => $secondjourney])
        );
        $users = new userlist($firstcontext, 'mod_attendancejourneys');
        provider::get_users_in_context($users);
        $this->assertSame([], $users->get_userids());
        $this->assertEquals(
            [$secondcontext->id],
            provider::get_contexts_for_userid($actor->id)->get_contextids()
        );

        provider::delete_data_for_users(new \core_privacy\local\request\approved_userlist(
            $secondcontext,
            'mod_attendancejourneys',
            [$actor->id]
        ));
        $this->assertSame([], provider::get_contexts_for_userid($actor->id)->get_contextids());
    }

    public function test_erasure_rejects_a_course_context_with_a_colliding_module_identifier(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $actor = $generator->create_user();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $journeyid = $this->create_completed_journey($activity->id, $actor->id);
        // Model an identifier collision independently of the database's current auto-increment counters.
        $wrongcontext = clone \context_course::instance($course->id);
        $instanceid = new \ReflectionProperty($wrongcontext, '_instanceid');
        $instanceid->setValue($wrongcontext, (int) $activity->cmid);

        provider::delete_data_for_all_users_in_context($wrongcontext);
        provider::delete_data_for_users(new \core_privacy\local\request\approved_userlist(
            $wrongcontext,
            'mod_attendancejourneys',
            [$actor->id]
        ));
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $actor,
            'mod_attendancejourneys',
            [$wrongcontext->id]
        ));

        $this->assertSame(
            (int) $actor->id,
            (int) $DB->get_field('attendancejourneys_journeys', 'completedby', ['id' => $journeyid])
        );
    }

    /**
     * Create a completed journey whose only personal datum is its completing actor.
     *
     * @param int $activityid Owning activity identifier.
     * @param int $actorid Completing user identifier.
     * @return int Journey identifier.
     */
    private function create_completed_journey(int $activityid, int $actorid): int {
        global $DB;
        return $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activityid,
            'name' => 'Completed privacy fixture',
            'description' => '',
            'groupid' => 0,
            'startdate' => 0,
            'enddate' => 0,
            'defaultmodality' => 'unspecified',
            'active' => 0,
            'completedby' => $actorid,
            'timecompleted' => time(),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_all_stored_user_roles_are_discovered(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $context = \context_module::instance($activity->cmid);
        $users = [];
        foreach (range(1, 12) as $unused) {
            $users[] = $generator->create_user();
        }
        [
            $participant, $taker, $approver, $reviewer, $reviewactor, $closer, $reopener, $completer,
            $waitcreator, $waitupdater, $equivcreator, $equivdecider,
        ] = $users;
        $journeyid = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Privacy journey', 'description' => '', 'groupid' => 0,
            'startdate' => 0, 'enddate' => 0, 'defaultmodality' => 'unspecified', 'active' => 0,
            'completedby' => $completer->id, 'timecompleted' => time(), 'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $sessionid = $DB->insert_record('attendancejourneys_sessions', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'name' => 'Privacy session',
            'sessiondate' => time(), 'duration' => 60, 'groupid' => 0, 'description' => '',
            'modality' => 'unspecified', 'location' => '', 'meetingurl' => '',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $recordid = $DB->insert_record('attendancejourneys_records', (object) [
            'sessionid' => $sessionid, 'userid' => $participant->id, 'status' => 'present',
            'minutesabsent' => 0, 'remarks' => '', 'takenby' => $taker->id, 'approved' => 1,
            'approvedby' => $approver->id, 'timeapproved' => time(), 'changesrequested' => 0,
            'reviewnote' => '', 'reviewedby' => $reviewer->id, 'timereviewed' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('attendancejourneys_reviews', (object) [
            'recordid' => $recordid, 'sessionid' => $sessionid, 'userid' => $participant->id,
            'action' => 'approved', 'actorid' => $reviewactor->id, 'note' => '', 'timecreated' => time(),
        ]);
        $DB->insert_record('attendancejourneys_closures', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'userid' => $participant->id,
            'active' => 1, 'applicablesessions' => 1, 'recordedsessions' => 1, 'presentminutes' => 60,
            'possibleminutes' => 60, 'percentage' => 100, 'threshold' => 80, 'result' => 'passed',
            'closedby' => $closer->id, 'timeclosed' => time(), 'reopenedby' => $reopener->id,
            'timereopened' => time(),
        ]);
        $DB->insert_record('attendancejourneys_waitlist', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid, 'userid' => $participant->id,
            'status' => 'waiting', 'createdby' => $waitcreator->id, 'updatedby' => $waitupdater->id,
            'timecreated' => time(), 'timeupdated' => time(),
        ]);
        $source = $DB->get_record('attendancejourneys_sessions', ['id' => $sessionid]);
        unset($source->id);
        $source->name = 'Earlier equivalent session';
        $sourceid = $DB->insert_record('attendancejourneys_sessions', $source);
        $DB->insert_record('attendancejourneys_equivalences', (object) [
            'attendancejourneysid' => $activity->id, 'userid' => $participant->id,
            'targetjourneyid' => $journeyid, 'targetsessionid' => $sessionid, 'sourcesessionid' => $sourceid,
            'status' => 'rejected', 'reason' => 'Request written by the creator',
            'createdby' => $equivcreator->id, 'timecreated' => time(),
            'decidedby' => $equivdecider->id, 'timedecided' => time(),
            'decisionnote' => 'Decision written by the decider', 'timemodified' => time(),
        ]);

        $userlist = new userlist($context, 'mod_attendancejourneys');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing(
            array_map(static fn($user) => $user->id, $users),
            $userlist->get_userids()
        );
        $roles = [
            'participant', 'taker', 'approver', 'reviewer', 'review actor', 'closer', 'reopener', 'completer',
            'waiting-list creator', 'waiting-list updater', 'equivalence creator', 'equivalence decider',
        ];
        foreach ($users as $index => $user) {
            $contextids = array_map('intval', provider::get_contexts_for_userid($user->id)->get_contextids());
            $this->assertContains(
                (int) $context->id,
                $contextids,
                'Missing privacy context for ' . $roles[$index]
            );
        }

        $participantcontexts = new \core_privacy\local\request\approved_contextlist(
            $participant,
            'mod_attendancejourneys',
            [$context->id]
        );
        provider::export_user_data($participantcontexts);
        $this->assertTrue(\core_privacy\local\request\writer::with_context($context)->has_any_data());

        $expectedroles = [
            'takenby', 'approvedby', 'reviewedby', 'actorid', 'closedby', 'reopenedby', 'completedby',
            'createdby', 'updatedby', 'createdby', 'decidedby',
        ];
        foreach (array_slice($users, 1) as $index => $actor) {
            \core_privacy\local\request\writer::reset();
            provider::export_user_data(new \core_privacy\local\request\approved_contextlist(
                $actor,
                'mod_attendancejourneys',
                [$context->id]
            ));
            $export = \core_privacy\local\request\writer::with_context($context)->get_data([]);
            $this->assertSame([], $export->attendance_records, 'An actor must not receive unrelated participant records.');
            if ($expectedroles[$index] === 'actorid') {
                $this->assertCount(1, $export->review_history);
                $this->assertSame((int) $actor->id, $export->review_history[0]->performedby);
            } else {
                $this->assertCount(1, $export->authored_actions);
                $this->assertSame($expectedroles[$index], $export->authored_actions[0]->role);
                $this->assertObjectNotHasProperty('userid', $export->authored_actions[0]->details);
            }
            if ($actor->id === $equivcreator->id) {
                $this->assertSame('Request written by the creator', $export->authored_actions[0]->details->reason);
                $this->assertObjectNotHasProperty('decisionnote', $export->authored_actions[0]->details);
            }
            if ($actor->id === $equivdecider->id) {
                $this->assertSame('Decision written by the decider', $export->authored_actions[0]->details->decisionnote);
                $this->assertObjectNotHasProperty('reason', $export->authored_actions[0]->details);
            }
        }

        // Removing a staff member anonymises their authorship without deleting the student's record.
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $taker,
            'mod_attendancejourneys',
            [$context->id]
        ));
        $this->assertSame(0, (int) $DB->get_field('attendancejourneys_records', 'takenby', ['id' => $recordid]));
        $this->assertTrue($DB->record_exists('attendancejourneys_records', ['id' => $recordid]));

        provider::delete_data_for_user($participantcontexts);
        $this->assertFalse($DB->record_exists('attendancejourneys_records', ['id' => $recordid]));
        $this->assertFalse($DB->record_exists('attendancejourneys_reviews', ['recordid' => $recordid]));
        $this->assertFalse($DB->record_exists('attendancejourneys_closures', ['userid' => $participant->id]));
        $this->assertFalse($DB->record_exists('attendancejourneys_waitlist', ['userid' => $participant->id]));
        $this->assertFalse($DB->record_exists('attendancejourneys_equivalences', ['userid' => $participant->id]));
        $this->assertTrue($DB->record_exists('attendancejourneys_journeys', ['id' => $journeyid]));
    }
}
