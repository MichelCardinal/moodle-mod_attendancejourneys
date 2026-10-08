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
 * Authorised individual obligation previews and auditable decisions.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class individual_obligation {
    /**
     * Read the explicit policy, or the all-sessions default without creating a row.
     *
     * @param int $journeyid Journey identifier.
     * @param int $userid Participant identifier.
     * @return \stdClass Current policy including its revision.
     */
    public static function get(int $journeyid, int $userid): \stdClass {
        global $DB;
        $policy = $DB->get_record('attendancejourneys_obligations', ['journeyid' => $journeyid, 'userid' => $userid]);
        if (!$policy) {
            $policy = obligation_policy::unrestricted();
            $policy->revision = 0;
        }
        return $policy;
    }

    /**
     * Select the participant's required sessions without changing the audience or attendance history.
     *
     * @param \stdClass $activity Activity configuration.
     * @param int $userid Participant identifier.
     * @param array $sessions Audience-filtered sessions, indexed by identifier.
     * @param array|null $policies Optional preloaded policies for this participant.
     * @return array Required sessions, preserving their original keys.
     */
    public static function required_sessions(
        \stdClass $activity,
        int $userid,
        array $sessions,
        ?array $policies = null
    ): array {
        global $DB;
        if (empty($activity->journeygrading)) {
            return $sessions;
        }
        $policies = $policies ?? $DB->get_records('attendancejourneys_obligations', [
            'attendancejourneysid' => $activity->id, 'userid' => $userid,
        ]);
        $byjourney = [];
        foreach ($policies as $policy) {
            if ((int) $policy->attendancejourneysid !== (int) $activity->id || (int) $policy->userid !== $userid) {
                throw new \coding_exception('Preloaded obligations must belong to the matching activity and participant.');
            }
            $byjourney[(int) $policy->journeyid] = $policy;
        }
        return array_filter($sessions, static function ($session) use ($byjourney): bool {
            $policy = $byjourney[(int) $session->journeyid] ?? null;
            return !$policy || !empty(obligation_policy::sessions($policy, [$session->id => $session]));
        });
    }

    /**
     * Keep historical source evidence when its whole journey obligation is waived.
     *
     * This only selects candidates; official attendance, visibility, cancellation,
     * approval, minute caps and single use must still be checked by the caller.
     * Individual period exclusions on a required journey remain in force.
     *
     * @param \stdClass $activity Activity configuration.
     * @param int $userid Participant identifier.
     * @param array $sessions Sessions indexed by identifier.
     * @return array Eligible evidence sessions, preserving their keys.
     */
    public static function evidence_sessions(\stdClass $activity, int $userid, array $sessions): array {
        global $DB;
        if (empty($activity->journeygrading)) {
            return $sessions;
        }
        $policies = $DB->get_records('attendancejourneys_obligations', [
            'attendancejourneysid' => $activity->id, 'userid' => $userid,
        ]);
        $required = self::required_sessions($activity, $userid, $sessions, $policies);
        $waived = [];
        foreach ($policies as $policy) {
            if (!empty($policy->waived)) {
                $waived[(int) $policy->journeyid] = true;
            }
        }
        return array_filter($sessions, static fn($session) =>
            isset($required[$session->id]) || isset($waived[(int) $session->journeyid]));
    }

    /**
     * Preview the exact required sessions and provisional minutes without a write.
     *
     * @param \stdClass $cm Course module.
     * @param int $journeyid Journey identifier.
     * @param int $userid Participant identifier.
     * @param \stdClass $proposed Proposed policy from the form.
     * @param write_lock|null $guard Existing matching activity lock.
     * @return \stdClass Before/after calculations, session lists and a consistency token.
     */
    public static function preview(
        \stdClass $cm,
        int $journeyid,
        int $userid,
        \stdClass $proposed,
        ?write_lock $guard = null
    ): \stdClass {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $realcm = get_coursemodule_from_id('attendancejourneys', $cm->id, 0, false, MUST_EXIST);
        if ((int) $realcm->instance !== (int) $cm->instance) {
            throw new \coding_exception('An obligation decision requires the matching activity.');
        }
        if ($guard !== null && !$guard->protects((int) $realcm->instance)) {
            throw new \coding_exception('An obligation decision requires the matching activity lock.');
        }
        $guard = $guard ?? new write_lock((int) $realcm->instance);
        $context = \context_module::instance($realcm->id);
        require_capability('mod/attendancejourneys:managejourneys', $context);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('mod/attendancejourneys:viewreports', $context);
        $activity = $DB->get_record('attendancejourneys', ['id' => $realcm->instance], '*', MUST_EXIST);
        if (empty($activity->journeygrading)) {
            throw new \moodle_exception('obligationrequiresjourneygrading', 'attendancejourneys');
        }
        $journey = $DB->get_record('attendancejourneys_journeys', [
            'id' => $journeyid, 'attendancejourneysid' => $activity->id,
        ], '*', MUST_EXIST);
        \attendancejourneys_require_journey_access($realcm, $context, $journey);
        $visible = \attendancejourneys_get_visible_participants($context, $realcm, 'u.id');
        $assigned = \attendancejourneys_get_journey_participants($context, $journey, 'u.id');
        if (!isset($visible[$userid]) || !isset($assigned[$userid])) {
            throw new \moodle_exception('invaliduser');
        }
        if (empty($journey->active) || \attendancejourneys_get_active_closure((int) $activity->id, $userid, $journeyid)) {
            throw new \moodle_exception('obligationblockedfinal', 'attendancejourneys');
        }
        $protections = journey_grades::protections($activity, $journeyid, [$userid]);
        if (isset($protections[$userid])) {
            throw new \moodle_exception($protections[$userid], 'attendancejourneys');
        }
        $proposed = obligation_policy::decision(
            !empty($proposed->waived),
            (int) $proposed->starttime,
            (int) $proposed->endtime,
            $proposed->reason
        );
        $current = self::get($journeyid, $userid);
        $sessions = $DB->get_records('attendancejourneys_sessions', [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journeyid,
        ], 'sessiondate, id');
        $sessions = array_filter($sessions, static fn($session) =>
            empty($session->cancelled) && \attendancejourneys_session_applies_to_user($session, $userid));
        $before = obligation_policy::sessions($current, $sessions);
        $after = obligation_policy::sessions($proposed, $sessions);
        $excluded = array_diff_key($before, $after);
        $equivalences = $DB->get_records('attendancejourneys_equivalences', [
            'attendancejourneysid' => $activity->id, 'userid' => $userid,
        ], 'id');
        foreach ($equivalences as $equivalence) {
            if (
                in_array($equivalence->status, ['pending', 'approved'], true) &&
                    (isset($excluded[$equivalence->targetsessionid]) ||
                        (empty($proposed->waived) && isset($excluded[$equivalence->sourcesessionid])))
            ) {
                throw new \moodle_exception('obligationblockedequivalence', 'attendancejourneys');
            }
        }
        // Include all participant records and related sessions so cross-journey credits cannot go stale unnoticed.
        $records = $DB->get_records_sql(
            "SELECT r.* FROM {attendancejourneys_records} r
               JOIN {attendancejourneys_sessions} s ON s.id = r.sessionid
              WHERE s.attendancejourneysid = :activity AND r.userid = :participant ORDER BY r.id",
            ['activity' => $activity->id, 'participant' => $userid]
        );
        $bysession = [];
        foreach ($records as $record) {
            $bysession[$record->sessionid] = $record;
        }
        $allsessions = $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $activity->id], 'id');
        $allpolicies = $DB->get_records('attendancejourneys_obligations', [
            'attendancejourneysid' => $activity->id, 'userid' => $userid,
        ], 'id');
        $beforecalculation = calculator::aggregate($activity, $before, $bysession, $userid);
        $aftercalculation = calculator::aggregate($activity, $after, $bysession, $userid);
        $now = time();
        $pending = count(array_filter($equivalences, static fn($equivalence) =>
            $equivalence->status === 'pending' && (int) $equivalence->targetjourneyid === $journeyid));
        foreach ([[$current, $before, $beforecalculation], [$proposed, $after, $aftercalculation]] as $state) {
            [$policy, $required, $calculation] = $state;
            $calculation->futuresessions = count(array_filter($required, static fn($session) =>
                !calculator::session_is_due($session, $now)));
            $calculation->pendingequivalences = $pending;
            $calculation->waived = !empty($policy->waived);
            $calculation->threshold = \attendancejourneys_get_passinggrade($activity, $journeyid);
        }
        // Also detect a session becoming due or a time-dependent source credit becoming usable.
        $token = hash('sha256', serialize([$activity, $journey, $userid, $current, $proposed,
            $allsessions, $records, $equivalences, $allpolicies, array_keys($before), array_keys($after),
            $beforecalculation, $aftercalculation]));
        return (object) [
            'current' => $current, 'proposed' => $proposed, 'activity' => $activity,
            'before' => $beforecalculation, 'after' => $aftercalculation,
            'sessions' => $after, 'excluded' => $excluded, 'restored' => array_diff_key($after, $before),
            'token' => $token,
        ];
    }

    /**
     * Apply the still-current preview and append its prior and new policy to history.
     *
     * @param \stdClass $cm Course module.
     * @param int $journeyid Journey identifier.
     * @param int $userid Participant identifier.
     * @param \stdClass $proposed Proposed policy.
     * @param string $token Consistency token returned by preview, not an authorisation token.
     * @param write_lock|null $guard Existing matching activity lock.
     * @return \stdClass Persisted decision.
     */
    public static function change(
        \stdClass $cm,
        int $journeyid,
        int $userid,
        \stdClass $proposed,
        string $token,
        ?write_lock $guard = null
    ): \stdClass {
        global $DB, $USER;
        $guard = $guard ?? new write_lock((int) $cm->instance);
        $preview = self::preview($cm, $journeyid, $userid, $proposed, $guard);
        if (!hash_equals($preview->token, $token)) {
            throw new \moodle_exception('obligationstale', 'attendancejourneys');
        }
        $transaction = $DB->start_delegated_transaction();
        $decision = clone $preview->proposed;
        $decision->attendancejourneysid = $preview->activity->id;
        $decision->journeyid = $journeyid;
        $decision->userid = $userid;
        $decision->actorid = $USER->id;
        $decision->revision = (int) $preview->current->revision + 1;
        $decision->timemodified = time();
        if (!empty($preview->current->id)) {
            $decision->id = $preview->current->id;
            $DB->update_record('attendancejourneys_obligations', $decision);
        } else {
            $decision->id = $DB->insert_record('attendancejourneys_obligations', $decision);
        }
        $history = clone $decision;
        unset($history->id, $history->timemodified);
        $history->timecreated = $decision->timemodified;
        $history->previouswaived = (int) $preview->current->waived;
        $history->previousstarttime = (int) $preview->current->starttime;
        $history->previousendtime = (int) $preview->current->endtime;
        $DB->insert_record('attendancejourneys_obligationlog', $history);
        \attendancejourneys_update_grades($preview->activity, $userid);
        \attendancejourneys_update_completion(get_course($cm->course), $cm, [$userid]);
        $transaction->allow_commit();
        $event = \mod_attendancejourneys\event\individual_obligation_changed::create([
            'objectid' => $decision->id, 'relateduserid' => $userid, 'context' => \context_module::instance($cm->id),
            'other' => ['attendancejourneysid' => (int) $decision->attendancejourneysid, 'journeyid' => $journeyid],
        ]);
        $event->add_record_snapshot('attendancejourneys_obligations', $decision);
        $event->trigger();
        return $decision;
    }
}
