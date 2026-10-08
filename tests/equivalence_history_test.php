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

use mod_attendancejourneys\local\equivalence_history;
use mod_attendancejourneys\privacy\provider;

/**
 * Decision history persistence and privacy tests.
 *
 * @package mod_attendancejourneys
 * @category test
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class equivalence_history_test extends \core_privacy\tests\provider_testcase {
    /**
     * Create a synthetic equivalence and two distinct staff actors.
     *
     * @return array Activity, equivalence, participant and two actors.
     */
    private function fixture(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $participant = $generator->create_user();
        $actors = [$generator->create_user(), $generator->create_user()];
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        foreach ($actors as $actor) {
            $generator->enrol_user($actor->id, $course->id, $roleid);
        }
        $id = $DB->insert_record('attendancejourneys_equivalences', (object) [
            'attendancejourneysid' => $activity->id, 'userid' => $participant->id,
            'status' => 'pending', 'timemodified' => 100,
        ]);
        return [$activity, $DB->get_record('attendancejourneys_equivalences', ['id' => $id]), $participant, $actors];
    }

    /**
     * A later decision keeps the first actor and explanation, and rejects a stale submission.
     */
    public function test_approval_and_revocation_keep_both_decisions(): void {
        global $DB;
        [$activity, $equivalence, $participant, $actors] = $this->fixture();
        $this->setUser($actors[0]);
        equivalence_history::record_decision($equivalence, 'approved', 'First approval');
        $approved = $DB->get_record('attendancejourneys_equivalences', ['id' => $equivalence->id]);
        $this->setUser($actors[1]);
        equivalence_history::record_decision($approved, 'revoked', 'Correction');
        $history = array_values($DB->get_records('attendancejourneys_equivlog', ['equivalenceid' => $equivalence->id], 'id'));
        $this->assertCount(2, $history);
        $this->assertSame('pending', $history[0]->previousstatus);
        $this->assertSame('approved', $history[0]->status);
        $this->assertSame('First approval', $history[0]->note);
        $this->assertEquals($actors[0]->id, $history[0]->actorid);
        $this->assertSame('approved', $history[1]->previousstatus);
        $this->assertSame('revoked', $history[1]->status);
        $this->assertEquals($actors[1]->id, $history[1]->actorid);
        try {
            equivalence_history::record_decision($approved, 'revoked', 'Stale submission');
            $this->fail('Stale decision must fail.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('equivalencevalidationfailed', $exception->errorcode);
        }
        $this->assertSame(2, $DB->count_records('attendancejourneys_equivlog'));
        $this->assertSame('Correction', $DB->get_field(
            'attendancejourneys_equivalences',
            'decisionnote',
            ['id' => $equivalence->id]
        ));
    }

    /**
     * Old current state is represented honestly and baseline seeding is idempotent.
     */
    public function test_baseline_does_not_invent_previous_decisions(): void {
        global $DB;
        [, $equivalence, , $actors] = $this->fixture();
        $equivalence->status = 'approved';
        $equivalence->decidedby = $actors[0]->id;
        $equivalence->decisionnote = 'Only known note';
        $equivalence->timedecided = 0;
        $DB->update_record('attendancejourneys_equivalences', $equivalence);
        equivalence_history::baseline($equivalence);
        equivalence_history::baseline($equivalence);
        $history = $DB->get_record('attendancejourneys_equivlog', ['equivalenceid' => $equivalence->id], '*', MUST_EXIST);
        $this->assertSame(1, $DB->count_records('attendancejourneys_equivlog'));
        $this->assertSame('baseline', $history->action);
        $this->assertSame('', $history->previousstatus);
        $this->assertEquals(0, $history->timecreated);
        $this->setUser($actors[1]);
        equivalence_history::record_decision($equivalence, 'revoked', 'Later correction');
        $this->assertSame(2, $DB->count_records('attendancejourneys_equivlog'));
        $this->assertSame('Only known note', $DB->get_field('attendancejourneys_equivlog', 'note', ['id' => $history->id]));
    }

    /**
     * Unauthorised decisions cannot create history or change state.
     */
    public function test_participant_cannot_write_a_decision(): void {
        global $DB;
        [, $equivalence, $participant] = $this->fixture();
        $this->setUser($participant);
        try {
            equivalence_history::record_decision($equivalence, 'approved', 'Forbidden');
            $this->fail('Participant decision must fail.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys_equivlog'));
        $this->assertSame('pending', $DB->get_field('attendancejourneys_equivalences', 'status', ['id' => $equivalence->id]));
    }

    /**
     * Former actors remain discoverable and erasure preserves other participants without their authored text.
     */
    public function test_privacy_covers_overwritten_actor_and_participant(): void {
        global $DB;
        [$activity, $equivalence, $participant, $actors] = $this->fixture();
        $this->setUser($actors[0]);
        equivalence_history::record_decision($equivalence, 'approved', 'Private actor note');
        $approved = $DB->get_record('attendancejourneys_equivalences', ['id' => $equivalence->id]);
        $this->setUser($actors[1]);
        equivalence_history::record_decision($approved, 'revoked', 'Second actor note');
        $context = \context_module::instance($activity->cmid);
        $this->assertContains((int) $context->id, array_map(
            'intval',
            provider::get_contexts_for_userid($actors[0]->id)->get_contextids()
        ));
        $users = new \core_privacy\local\request\userlist($context, 'mod_attendancejourneys');
        provider::get_users_in_context($users);
        $this->assertContains((int) $actors[0]->id, array_map('intval', $users->get_userids()));
        $this->setAdminUser();
        $approvedcontexts = new \core_privacy\local\request\approved_contextlist(
            $actors[0],
            'mod_attendancejourneys',
            [$context->id]
        );
        provider::export_user_data($approvedcontexts);
        $export = \core_privacy\local\request\writer::with_context($context)->get_data([]);
        $this->assertCount(1, $export->authored_actions);
        $this->assertSame('Private actor note', $export->authored_actions[0]->details->note);
        $this->assertSame([], $export->equivalence_decision_history);
        provider::delete_data_for_user($approvedcontexts);
        $history = array_values($DB->get_records('attendancejourneys_equivlog', [], 'id'));
        $this->assertCount(2, $history);
        $this->assertEquals(0, $history[0]->actorid);
        $this->assertNull($history[0]->note);
        $this->assertEquals($actors[1]->id, $history[1]->actorid);
        $this->assertSame('Second actor note', $history[1]->note);
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $actors[1],
            'mod_attendancejourneys',
            [$context->id]
        ));
        $this->assertNull($DB->get_field('attendancejourneys_equivalences', 'decisionnote', ['id' => $equivalence->id]));
        $this->assertSame(0, $DB->count_records_select('attendancejourneys_equivlog', 'note IS NOT NULL'));
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $participant,
            'mod_attendancejourneys',
            [$context->id]
        ));
        $this->assertSame(0, $DB->count_records('attendancejourneys_equivlog'));
        $this->assertSame(0, $DB->count_records('attendancejourneys_equivalences'));
    }
}
