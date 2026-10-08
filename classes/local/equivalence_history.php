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

declare(strict_types=1);

namespace mod_attendancejourneys\local;

/**
 * Append-only persistence for equivalence decisions.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class equivalence_history {
    /**
     * Preserve the only known state of an older decision without inventing earlier transitions.
     *
     * @param \stdClass $equivalence Existing equivalence.
     * @return void
     */
    public static function baseline(\stdClass $equivalence): void {
        global $DB;
        if (
            $equivalence->status === 'pending' ||
                $DB->record_exists('attendancejourneys_equivlog', ['equivalenceid' => $equivalence->id])
        ) {
            return;
        }
        $DB->insert_record('attendancejourneys_equivlog', (object) [
            'equivalenceid' => $equivalence->id, 'attendancejourneysid' => $equivalence->attendancejourneysid,
            'userid' => $equivalence->userid, 'actorid' => $equivalence->decidedby,
            'action' => 'baseline', 'previousstatus' => '', 'status' => $equivalence->status,
            'note' => $equivalence->decisionnote, 'timecreated' => $equivalence->timedecided,
        ]);
    }

    /**
     * Save a validated decision and its history together, under the caller's activity write lock.
     *
     * The controller must validate the source, target, group visibility and session eligibility first.
     * This helper checks the expected state again to reject stale decisions.
     *
     * @param \stdClass $expected Equivalence before the decision.
     * @param string $status New decision state.
     * @param string $note Staff explanation.
     * @return void
     */
    public static function record_decision(\stdClass $expected, string $status, string $note): void {
        global $DB, $USER;
        $transitions = ['pending' => ['approved', 'rejected'], 'approved' => ['revoked']];
        $cm = get_coursemodule_from_instance('attendancejourneys', $expected->attendancejourneysid, 0, false, MUST_EXIST);
        require_capability('mod/attendancejourneys:managejourneys', \context_module::instance($cm->id));
        if (
            !in_array($status, $transitions[$expected->status] ?? [], true) ||
                ($status !== 'approved' && trim($note) === '')
        ) {
            throw new \moodle_exception('equivalencevalidationfailed', 'attendancejourneys');
        }
        $transaction = $DB->start_delegated_transaction();
        $current = $DB->get_record('attendancejourneys_equivalences', [
            'id' => $expected->id, 'attendancejourneysid' => $expected->attendancejourneysid,
            'userid' => $expected->userid,
        ], '*', MUST_EXIST);
        if ($current->status !== $expected->status || $current->timemodified != $expected->timemodified) {
            $transaction->rollback(new \moodle_exception('equivalencevalidationfailed', 'attendancejourneys'));
        }
        self::baseline($current);
        $now = time();
        $DB->insert_record('attendancejourneys_equivlog', (object) [
            'equivalenceid' => $current->id, 'attendancejourneysid' => $current->attendancejourneysid,
            'userid' => $current->userid, 'actorid' => $USER->id, 'action' => 'decision',
            'previousstatus' => $current->status, 'status' => $status,
            'note' => trim($note), 'timecreated' => $now,
        ]);
        $current->status = $status;
        $current->decisionnote = trim($note);
        $current->decidedby = $USER->id;
        $current->timedecided = $now;
        $current->timemodified = $now;
        $DB->update_record('attendancejourneys_equivalences', $current);
        $transaction->allow_commit();
    }
}
