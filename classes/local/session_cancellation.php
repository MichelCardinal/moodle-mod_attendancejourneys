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

namespace mod_attendancejourneys\local;

/**
 * Auditable session cancellation without deleting attendance or final-result history.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_cancellation {
    /**
     * Cancel or reinstate a session under the shared activity lock.
     *
     * @param \stdClass $cm Course module.
     * @param int $sessionid Session identifier.
     * @param bool $cancelled Requested cancellation state.
     * @param int $expected Previous state shown in the confirmation.
     * @param string $reason Required explanation.
     * @param int|null $expectedversion Session revision shown in an interactive confirmation.
     * @param write_lock|null $guard Existing controller lock, otherwise one is acquired.
     * @return \stdClass Updated session.
     */
    public static function change(
        \stdClass $cm,
        int $sessionid,
        bool $cancelled,
        int $expected,
        string $reason,
        ?int $expectedversion = null,
        ?write_lock $guard = null
    ): \stdClass {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $realcm = get_coursemodule_from_id('attendancejourneys', $cm->id, 0, false, MUST_EXIST);
        if ((int) $realcm->instance !== (int) $cm->instance) {
            throw new \coding_exception('Cancellation requires the matching activity.');
        }
        $context = \context_module::instance($realcm->id);
        require_capability('mod/attendancejourneys:managejourneys', $context);
        if ($guard !== null && !$guard->protects((int) $realcm->instance)) {
            throw new \coding_exception('Cancellation requires the matching activity lock.');
        }
        $guard = $guard ?? new write_lock((int) $realcm->instance);
        $activity = $DB->get_record('attendancejourneys', ['id' => $realcm->instance], '*', MUST_EXIST);
        if (empty($activity->journeygrading)) {
            throw new \moodle_exception('sessioncancellationrequiresjourneygrading', 'attendancejourneys');
        }
        $session = $DB->get_record('attendancejourneys_sessions', [
            'id' => $sessionid, 'attendancejourneysid' => $activity->id,
        ], '*', MUST_EXIST);
        \attendancejourneys_require_session_management_access($realcm, $context, $session);
        if (
            (int) $session->cancelled !== $expected || (bool) $session->cancelled === $cancelled ||
                ($expectedversion !== null && (int) $session->timemodified !== $expectedversion)
        ) {
            throw new \moodle_exception('sessioncancellationstale', 'attendancejourneys');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new \moodle_exception('sessioncancellationreasonrequired', 'attendancejourneys');
        }
        if (\attendancejourneys_session_has_final_results($session)) {
            throw new \moodle_exception('sessioncancellationblockedfinal', 'attendancejourneys');
        }
        if (
            !empty($session->journeyid) && !$DB->get_field(
                'attendancejourneys_journeys',
                'active',
                ['id' => $session->journeyid]
            )
        ) {
            throw new \moodle_exception('sessioncancellationblockedfinal', 'attendancejourneys');
        }
        if (
            $DB->record_exists_select(
                'attendancejourneys_equivalences',
                '(sourcesessionid = :source OR targetsessionid = :target) AND status IN (:pending, :approved)',
                ['source' => $sessionid, 'target' => $sessionid, 'pending' => 'pending', 'approved' => 'approved']
            )
        ) {
            throw new \moodle_exception('sessioncancellationblockedequivalence', 'attendancejourneys');
        }
        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $session->cancelled = (int) $cancelled;
        $session->timemodified = max($now, (int) $session->timemodified + 1);
        $DB->update_record('attendancejourneys_sessions', $session);
        $DB->insert_record('attendancejourneys_sessionlog', (object) [
            'attendancejourneysid' => $activity->id, 'sessionid' => $sessionid, 'actorid' => $USER->id,
            'action' => $cancelled ? 'cancel' : 'reinstate', 'reason' => $reason, 'timecreated' => $now,
        ]);
        \attendancejourneys_update_calendar_event($activity, $session);
        $participantids = array_keys(\attendancejourneys_get_session_participants($context, $session, 'u.id'));
        \attendancejourneys_update_completion(get_course($realcm->course), $realcm, $participantids);
        \attendancejourneys_update_grades($activity);
        $transaction->allow_commit();
        $event = \mod_attendancejourneys\event\session_cancellation_changed::create([
            'objectid' => $sessionid, 'context' => $context,
            'other' => ['attendancejourneysid' => (int) $activity->id, 'cancelled' => (int) $cancelled],
        ]);
        $event->add_record_snapshot('attendancejourneys_sessions', $session);
        $event->trigger();
        return $session;
    }
}
