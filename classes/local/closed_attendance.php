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

use stdClass;

/**
 * Reviews a recorded attendance entry without exposing an intermediate reopened result.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class closed_attendance {
    /**
     * Check that the recorded session can be corrected within this final result.
     *
     * @param stdClass $activity Activity record.
     * @param stdClass $closure Active closure for the participant.
     * @param stdClass $session Session record.
     * @param stdClass $record Attendance entry.
     * @return bool
     */
    public static function applies(stdClass $activity, stdClass $closure, stdClass $session, stdClass $record): bool {
        return !empty($closure->active) && (int) $closure->attendancejourneysid === (int) $activity->id &&
            (int) $session->attendancejourneysid === (int) $activity->id &&
            (int) $closure->journeyid === (int) $session->journeyid &&
            (int) $record->userid === (int) $closure->userid && (int) $record->sessionid === (int) $session->id &&
            empty($session->cancelled) && empty($record->equivalenceid) && calculator::record_is_official($record) &&
            attendancejourneys_session_applies_to_user($session, (int) $record->userid) &&
            individual_obligation::required_sessions($activity, (int) $record->userid, [$session->id => $session]);
    }

    /**
     * Find dependencies that must be resolved before replacing a closed attendance entry.
     *
     * @param stdClass $activity Activity record.
     * @param stdClass $closure Active closure.
     * @param stdClass $record Attendance entry.
     * @return string|null Language key for the reason that the change is blocked.
     */
    public static function protection(stdClass $activity, stdClass $closure, stdClass $record): ?string {
        global $DB;
        $userid = (int) $closure->userid;
        $protections = journey_grades::protections($activity, (int) $closure->journeyid, [$userid], true);
        if (isset($protections[$userid])) {
            return $protections[$userid];
        }
        $linkexists = $DB->record_exists_select(
            'attendancejourneys_equivalences',
            "attendancejourneysid = :activity AND userid = :participant AND status IN ('pending', 'approved')
                AND (sourcesessionid = :source OR targetsessionid = :target)",
            ['activity' => $activity->id, 'participant' => $userid, 'source' => $record->sessionid, 'target' => $record->sessionid]
        );
        if ($linkexists) {
            return 'closedcorrectionequivalence';
        }
        return null;
    }

    /**
     * Prepare the revised entry and calculate the new result in memory.
     *
     * @param stdClass $activity Activity record.
     * @param stdClass $closure Active closure.
     * @param stdClass $record Current attendance entry.
     * @param string $status Validated new attendance status.
     * @param int $minutesabsent Validated absence duration.
     * @param string $reason Validated explanation.
     * @return stdClass
     */
    public static function preview(
        stdClass $activity,
        stdClass $closure,
        stdClass $record,
        string $status,
        int $minutesabsent,
        string $reason
    ): stdClass {
        $replacement = clone $record;
        $replacement->status = $status;
        $replacement->minutesabsent = $minutesabsent;
        $replacement->approved = 1;
        $calculation = calculator::calculate(
            $activity,
            (int) $closure->userid,
            (int) $closure->journeyid,
            replacement: $replacement
        );
        $calculation->threshold = (float) $closure->threshold;
        $state = serialize([$closure, $record, $calculation, $status, $minutesabsent, $reason]);
        $fingerprint = hash_hmac('sha256', $state, sesskey());
        return (object) ['record' => $replacement, 'calculation' => $calculation, 'fingerprint' => $fingerprint];
    }

    /**
     * Apply a verified correction and replace the final result in one transaction.
     *
     * @param stdClass $activity Activity record.
     * @param stdClass $course Course record.
     * @param stdClass $cm Course module record.
     * @param stdClass $closure Current closure verified under the write lock.
     * @param stdClass $original Original attendance entry.
     * @param stdClass $replacement Validated revised attendance entry.
     * @param string $reason Explanation stored in the attendance history.
     * @param write_lock $lock Activity write lock.
     * @return stdClass New final result.
     */
    public static function commit(
        stdClass $activity,
        stdClass $course,
        stdClass $cm,
        stdClass $closure,
        stdClass $original,
        stdClass $replacement,
        string $reason,
        write_lock $lock
    ): stdClass {
        global $DB, $USER, $CFG;
        if (!$lock->protects((int) $activity->id)) {
            throw new \coding_exception('A closed attendance correction requires the activity write lock.');
        }
        require_capability('mod/attendancejourneys:correctclosedattendance', \context_module::instance($cm->id));
        require_once($CFG->libdir . '/gradelib.php');
        $protection = self::protection($activity, $closure, $original);
        if ($protection !== null) {
            throw new \moodle_exception($protection, 'attendancejourneys');
        }
        $userid = (int) $closure->userid;
        $journeyid = (int) $closure->journeyid;
        $actor = (int) $USER->id;
        $now = time();
        $replacement->takenby = $actor;
        $replacement->approvedby = $actor;
        $replacement->timeapproved = $now;
        $replacement->timemodified = $now;
        $replacement->changesrequested = 0;
        $replacement->reviewnote = '';
        $replacement->reviewedby = 0;
        $replacement->timereviewed = 0;

        $transaction = $DB->start_delegated_transaction();
        try {
            if (!attendancejourneys_reopen_user_journey($activity, $userid, $actor, $journeyid)) {
                throw new \moodle_exception('closedcorrectionstale', 'attendancejourneys');
            }
            $DB->update_record('attendancejourneys_records', $replacement);
            attendancejourneys_add_review_history(
                (int) $original->id,
                (int) $original->sessionid,
                $userid,
                'closedcorrection',
                $actor,
                json_encode([
                    'before' => attendancejourneys_record_audit_snapshot($original),
                    'after' => attendancejourneys_record_audit_snapshot($replacement),
                    'reason' => $reason,
                ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)
            );
            attendancejourneys_touch_session((int) $original->sessionid);
            $final = attendancejourneys_close_user_journey(
                $activity,
                $userid,
                $actor,
                $journeyid,
                false,
                (float) $closure->threshold
            );
            if (!empty($activity->gradeenabled) && !empty($activity->percentageenabled)) {
                if (!empty($activity->journeygrading)) {
                    $rootid = (int) attempt_family::root($activity, $journeyid)->id;
                    $statuses = journey_grades::sync($activity, $userid, $rootid);
                    $gradestatus = $statuses[$rootid] ?? GRADE_UPDATE_FAILED;
                } else {
                    $active = attendancejourneys_get_user_active_journey(
                        \context_module::instance($cm->id),
                        (int) $activity->id,
                        $userid
                    );
                    $published = attendancejourneys_get_active_closure((int) $activity->id, $userid, $active?->id);
                    $gradestatus = attendancejourneys_grade_item_update(
                        $activity,
                        [$userid => (object) ['userid' => $userid, 'rawgrade' => $published?->percentage]]
                    );
                }
                $protection = self::protection($activity, $final, $replacement);
                if ($gradestatus !== GRADE_UPDATE_OK || $protection !== null) {
                    throw new \moodle_exception($protection ?? 'closedcorrectiongradefailed', 'attendancejourneys');
                }
            }
            attendancejourneys_update_completion($course, $cm, [$userid]);
            $transaction->allow_commit();
        } catch (\Exception $exception) {
            $transaction->rollback($exception);
            throw $exception;
        }
        return $final;
    }
}
