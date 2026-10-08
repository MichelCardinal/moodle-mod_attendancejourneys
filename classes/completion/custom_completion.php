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

namespace mod_attendancejourneys\completion;

use core_completion\activity_custom_completion;

/**
 * Attendance-specific activity completion rules.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Returns the current state of a custom rule.
     *
     * @param string $rule Rule name.
     * @return int Moodle completion state.
     */
    public function get_state(string $rule): int {
        global $CFG, $DB;

        $this->validate_rule($rule);
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $activity = $DB->get_record('attendancejourneys', ['id' => $this->cm->instance], '*', MUST_EXIST);
        if (!empty($activity->journeygrading)) {
            return \mod_attendancejourneys\local\journey_grades::all_required_passed($activity, $this->userid)
                ? COMPLETION_COMPLETE_PASS : COMPLETION_INCOMPLETE;
        }
        $context = \context_module::instance($this->cm->id);
        $activejourney = attendancejourneys_get_user_active_journey($context, (int) $activity->id, $this->userid);
        $closure = $activejourney ? attendancejourneys_get_active_closure(
            (int) $activity->id,
            $this->userid,
            (int) $activejourney->id
        ) : attendancejourneys_get_active_closure((int) $activity->id, $this->userid);
        $targetjourneyid = $activejourney ? (int) $activejourney->id :
            ($closure && !empty($closure->journeyid) ? (int) $closure->journeyid : 0);
        $sessions = $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $activity->id]);
        $sessions = array_filter(
            $sessions,
            fn($session) => (!$targetjourneyid || (int) $session->journeyid === $targetjourneyid) &&
                attendancejourneys_session_applies_to_user($session, $this->userid) &&
            \mod_attendancejourneys\local\calculator::session_is_due($session)
        );
        $sessionids = array_keys($sessions);
        $records = [];
        if ($sessionids) {
            [$insql, $params] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'session');
            $params['userid'] = $this->userid;
            foreach (
                $DB->get_records_select(
                    'attendancejourneys_records',
                    "userid = :userid AND sessionid $insql",
                    $params
                ) as $record
            ) {
                if (\mod_attendancejourneys\local\calculator::record_is_official($record)) {
                    $records[$record->sessionid] = $record;
                }
            }
        }

        if ($rule === 'completionrecorded') {
            return $records ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }
        if ($rule === 'completionallsessions') {
            return $sessions && count($records) === count($sessions) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        if ($rule === 'completionclosed') {
            return $closure ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        if (!$activity->percentageenabled || !$closure || $closure->percentage === null) {
            return COMPLETION_INCOMPLETE;
        }
        return $closure->result === 'passed'
            ? COMPLETION_COMPLETE_PASS : COMPLETION_INCOMPLETE;
    }

    /**
     * List the attendance-specific completion rules.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionrecorded', 'completionallsessions', 'completionclosed', 'completionpass'];
    }

    /**
     * Describe each attendance completion requirement for display.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;
        if ($DB->get_field('attendancejourneys', 'journeygrading', ['id' => $this->cm->instance])) {
            return array_fill_keys(self::get_defined_custom_rules(), get_string('completiondetail:journeys', 'attendancejourneys'));
        }
        return [
            'completionrecorded' => get_string('completiondetail:recorded', 'attendancejourneys'),
            'completionallsessions' => get_string('completiondetail:allsessions', 'attendancejourneys'),
            'completionclosed' => get_string('completiondetail:closed', 'attendancejourneys'),
            'completionpass' => get_string('completiondetail:pass', 'attendancejourneys'),
        ];
    }

    /**
     * Order attendance requirements in the completion summary.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionrecorded', 'completionallsessions', 'completionclosed', 'completionpass'];
    }
}
