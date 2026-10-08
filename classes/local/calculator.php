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
 * Single source of truth for attendance calculations.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calculator {
    /**
     * Whether a record is authoritative for grades, completion and final results.
     *
     * @param \stdClass $record Attendance record.
     */
    public static function record_is_official(\stdClass $record): bool {
        // Records created before the approval workflow are institutional legacy records.
        if (!property_exists($record, 'approved')) {
            return true;
        }
        return !empty($record->approved) || (int) $record->takenby !== (int) $record->userid;
    }

    /**
     * Returns present and possible minutes for one attendance record.
     *
     * @param \stdClass $activity Attendance activity and its configuration.
     * @param \stdClass $session Attendance session.
     * @param \stdClass $record Attendance record.
     */
    public static function record_minutes(\stdClass $activity, \stdClass $session, \stdClass $record): array {
        if (!empty($session->cancelled)) {
            return [0, 0];
        }
        $journeygrading = !empty($activity->journeygrading);
        if ($journeygrading && $record->status === 'exempt') {
            return [0, 0];
        }
        if (!$journeygrading && $record->status === 'excused' && $activity->excusedmode === 'excluded') {
            return [0, 0];
        }
        $possible = max(0, (int) $session->duration);
        if (
            $record->status === 'present' ||
                (!$journeygrading && $record->status === 'excused' && $activity->excusedmode === 'present')
        ) {
            return [$possible, $possible];
        }
        if ($record->status === 'partial') {
            return [max(0, $possible - (int) $record->minutesabsent), $possible];
        }
        return [0, $possible];
    }

    /**
     * Whether a session has ended and can be required by the all-sessions completion rule.
     *
     * @param \stdClass $session Attendance session.
     * @param int|null $now Reference timestamp; null uses the current time.
     */
    public static function session_is_due(\stdClass $session, ?int $now = null): bool {
        if (!empty($session->cancelled)) {
            return false;
        }
        $now = $now ?? time();
        return (int) $session->sessiondate + ((int) $session->duration * MINSECS) <= $now;
    }

    /**
     * Builds the official target-session record produced by one approved equivalence.
     *
     * @param \stdClass $activity Attendance activity and its configuration.
     * @param \stdClass $targetsession Session receiving the equivalent attendance.
     * @param \stdClass $sourcesession Session supplying the equivalent attendance.
     * @param \stdClass $sourcerecord Approved attendance from the source session.
     * @param \stdClass $equivalence Approved equivalence record.
     */
    public static function equivalent_record(
        \stdClass $activity,
        \stdClass $targetsession,
        \stdClass $sourcesession,
        \stdClass $sourcerecord,
        \stdClass $equivalence
    ): \stdClass {
        [$sourcepresent, $sourcepossible] = self::record_minutes($activity, $sourcesession, $sourcerecord);
        $targetduration = max(0, (int) $targetsession->duration);
        $credited = min($targetduration, $sourcepossible, max(0, $sourcepresent));
        return (object) [
            'sessionid' => (int) $targetsession->id, 'userid' => (int) $equivalence->userid,
            'status' => $credited >= $targetduration ? 'present' : 'partial',
            'minutesabsent' => max(0, $targetduration - $credited),
            'takenby' => (int) $equivalence->decidedby, 'approved' => 1,
            'equivalenceid' => (int) $equivalence->id,
            'sourcesessionid' => (int) $sourcesession->id,
            'remarks' => '', 'timemodified' => (int) $equivalence->timemodified,
        ];
    }

    /**
     * Aggregates already-loaded applicable sessions and records without additional database queries.
     *
     * @param \stdClass $activity Attendance activity and its configuration.
     * @param array $sessions Attendance sessions indexed by identifier.
     * @param array $records Attendance records to use in the calculation.
     * @param int $userid User identifier.
     */
    public static function aggregate(\stdClass $activity, array $sessions, array $records, int $userid = 0): \stdClass {
        $sessions = array_filter($sessions, static fn($session) => empty($session->cancelled));
        if ($userid > 0 && $sessions) {
            $records = self::apply_approved_equivalences($activity, $userid, $sessions, $records);
        }
        $present = 0;
        $possible = 0;
        $recorded = 0;
        $pendingapprovals = 0;
        foreach ($sessions as $session) {
            $record = $records[$session->id] ?? null;
            if (!$record) {
                continue;
            }
            if (!self::record_is_official($record)) {
                $pendingapprovals++;
                continue;
            }
            $recorded++;
            [$recordpresent, $recordpossible] = self::record_minutes($activity, $session, $record);
            $present += $recordpresent;
            $possible += $recordpossible;
        }
        return (object) [
            'presentminutes' => $present,
            'possibleminutes' => $possible,
            'recordedsessions' => $recorded,
            'pendingapprovals' => $pendingapprovals,
            'applicablesessions' => count($sessions),
            'percent' => $possible > 0 ? ($present / $possible) * 100 : null,
        ];
    }

    /**
     * Adds synthetic official records for approved cross-journey equivalences.
     *
     * @param \stdClass $activity Attendance activity and its configuration.
     * @param int $userid User identifier.
     * @param array $sessions Attendance sessions indexed by identifier.
     * @param array $records Attendance records to use in the calculation.
     */
    public static function apply_approved_equivalences(
        \stdClass $activity,
        int $userid,
        array $sessions,
        array $records
    ): array {
        global $DB;
        $equivalences = $DB->get_records('attendancejourneys_equivalences', [
            'attendancejourneysid' => $activity->id, 'userid' => $userid, 'status' => 'approved',
        ], 'timedecided ASC, id ASC');
        if (!$equivalences) {
            return $records;
        }
        $sessionids = [];
        foreach ($equivalences as $equivalence) {
            $sessionids[] = (int) $equivalence->targetsessionid;
            $sessionids[] = (int) $equivalence->sourcesessionid;
        }
        $relatedsessions = $DB->get_records_list('attendancejourneys_sessions', 'id', array_unique($sessionids));
        [$insql, $params] = $DB->get_in_or_equal(array_unique($sessionids), SQL_PARAMS_NAMED, 'equivsession');
        $params['equivuser'] = $userid;
        $relatedrecords = [];
        $selectedrecords = $DB->get_records_select(
            'attendancejourneys_records',
            "sessionid $insql AND userid = :equivuser",
            $params
        );
        foreach ($selectedrecords as $record) {
            $relatedrecords[(int) $record->sessionid] = $record;
        }
        $journeys = $DB->get_records('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id], '', 'id');
        $requiredrelated = individual_obligation::required_sessions($activity, $userid, $relatedsessions);
        $evidencesessions = individual_obligation::evidence_sessions($activity, $userid, $relatedsessions);
        $usedsources = [];
        $usedtargets = [];
        foreach ($equivalences as $equivalence) {
            $targetid = (int) $equivalence->targetsessionid;
            $sourceid = (int) $equivalence->sourcesessionid;
            $target = $relatedsessions[$targetid] ?? null;
            $source = $relatedsessions[$sourceid] ?? null;
            $sourcerecord = $relatedrecords[$sourceid] ?? null;
            if (
                !$target || !$source || !isset($requiredrelated[$targetid], $evidencesessions[$sourceid]) ||
                    !empty($target->cancelled) || !empty($source->cancelled) ||
                    !$sourcerecord || !self::record_is_official($sourcerecord) ||
                    !in_array($sourcerecord->status, ['present', 'partial'], true) ||
                    (int) $source->duration <= 0 || !self::session_is_due($source) ||
                    ($sourcerecord->status === 'partial' && ((int) $sourcerecord->minutesabsent <= 0 ||
                        (int) $sourcerecord->minutesabsent >= (int) $source->duration)) ||
                    (int) $target->attendancejourneysid !== (int) $activity->id ||
                    (int) $source->attendancejourneysid !== (int) $activity->id ||
                    (int) $target->journeyid !== (int) $equivalence->targetjourneyid ||
                    !isset($journeys[$equivalence->targetjourneyid]) ||
                    (int) $source->journeyid === (int) $target->journeyid ||
                    isset($usedsources[$sourceid]) || isset($usedtargets[$targetid])
            ) {
                continue;
            }
            // Reserve the first valid approval across the activity, including targets outside this report.
            // A later real target record does not silently free its source; staff must revoke the approval.
            $usedsources[$sourceid] = true;
            $usedtargets[$targetid] = true;
            if (!isset($sessions[$targetid]) || isset($records[$targetid])) {
                continue;
            }
            $records[$targetid] = self::equivalent_record(
                $activity,
                $sessions[$targetid],
                $source,
                $sourcerecord,
                $equivalence
            );
        }
        return $records;
    }

    /**
     * Calculates one participant's result for the supplied activity and optional journey.
     *
     * @param \stdClass $activity Attendance activity and its configuration.
     * @param int $userid User identifier.
     * @param int $journeyid Journey identifier; zero denotes no specific journey.
     * @param bool $dueonly Whether to include only sessions that are due.
     * @param int|null $now Reference timestamp; null uses the current time.
     */
    public static function calculate(
        \stdClass $activity,
        int $userid,
        int $journeyid = 0,
        bool $dueonly = false,
        ?int $now = null
    ): \stdClass {
        global $DB;

        $sessions = $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $activity->id]);
        $sessions = array_filter($sessions, static function ($session) use ($userid, $journeyid, $dueonly, $now) {
            if (!empty($session->cancelled) || ($journeyid > 0 && (int) $session->journeyid !== $journeyid)) {
                return false;
            }
            if (!attendancejourneys_session_applies_to_user($session, $userid)) {
                return false;
            }
            return !$dueonly || self::session_is_due($session, $now);
        });

        $sessions = individual_obligation::required_sessions($activity, $userid, $sessions);
        $records = [];
        if ($sessions) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($sessions), SQL_PARAMS_NAMED, 'calcsession');
            $params['calcuserid'] = $userid;
            foreach (
                $DB->get_records_select(
                    'attendancejourneys_records',
                    "userid = :calcuserid AND sessionid $insql",
                    $params
                ) as $record
            ) {
                $records[$record->sessionid] = $record;
            }
        }

        $result = self::aggregate($activity, $sessions, $records, $userid);
        $pendingconditions = [
            'attendancejourneysid' => $activity->id, 'userid' => $userid, 'status' => 'pending',
        ];
        if ($journeyid > 0) {
            $pendingconditions['targetjourneyid'] = $journeyid;
        }
        $result->futuresessions = count(array_filter(
            $sessions,
            static fn($session) => !self::session_is_due($session, $now)
        ));
        $result->pendingequivalences = $DB->count_records('attendancejourneys_equivalences', $pendingconditions);
        $result->waived = !empty($activity->journeygrading) && $journeyid > 0 &&
            !empty(individual_obligation::get($journeyid, $userid)->waived);
        $result->threshold = \attendancejourneys_get_passinggrade($activity, $journeyid);
        return $result;
    }
}
