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
 * Authorised, previewed personal retake openings with an immutable preceding history.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_opening {
    /**
     * Preview an opening without changing attendance, grade or completion.
     *
     * @param \stdClass $cm Course module.
     * @param int $rootid Logical obligation identifier.
     * @param int $userid Participant identifier.
     * @param string $name New physical journey name.
     * @param string $reason Required explanation.
     * @param write_lock|null $guard Optional matching activity guard.
     * @return \stdClass Validated proposal and consistency token.
     */
    public static function preview(
        \stdClass $cm,
        int $rootid,
        int $userid,
        string $name,
        string $reason,
        ?write_lock $guard = null
    ): \stdClass {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $realcm = get_coursemodule_from_id('attendancejourneys', $cm->id, 0, false, MUST_EXIST);
        if ((int) $realcm->instance !== (int) $cm->instance || ($guard && !$guard->protects((int) $realcm->instance))) {
            throw new \coding_exception('A retake opening requires the matching module and activity guard.');
        }
        $guard = $guard ?? new write_lock((int) $realcm->instance);
        $context = \context_module::instance($realcm->id);
        require_capability('mod/attendancejourneys:managejourneys', $context);
        require_capability('mod/attendancejourneys:manageattempts', $context);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('mod/attendancejourneys:viewreports', $context);
        $activity = $DB->get_record('attendancejourneys', ['id' => $realcm->instance], '*', MUST_EXIST);
        if (empty($activity->journeygrading)) {
            throw new \moodle_exception('attemptrequiresjourneygrading', 'attendancejourneys');
        }
        $family = attempt_family::load($activity, $rootid, $userid);
        $root = $family->root;
        \attendancejourneys_require_journey_access($realcm, $context, $root);
        $visible = \attendancejourneys_get_visible_participants($context, $realcm, 'u.id');
        $assigned = \attendancejourneys_get_journey_participants($context, $root, 'u.id');
        if (!isset($visible[$userid], $assigned[$userid])) {
            throw new \moodle_exception('invaliduser');
        }
        $current = attempt_family::select($family, $userid);
        if (!empty(individual_obligation::get($current->journeyid, $userid)->waived)) {
            throw new \moodle_exception('attemptwaived', 'attendancejourneys');
        }
        if (!$current->closure) {
            throw new \moodle_exception('attemptpreviousopen', 'attendancejourneys');
        }
        $protections = journey_grades::protections($activity, $rootid, [$userid]);
        if (isset($protections[$userid])) {
            throw new \moodle_exception($protections[$userid], 'attendancejourneys');
        }
        $name = trim(clean_param($name, PARAM_TEXT));
        $reason = trim(clean_param($reason, PARAM_TEXT));
        if ($name === '' || \core_text::strlen($name) > 255 || $reason === '') {
            throw new \moodle_exception('attemptdetailsrequired', 'attendancejourneys');
        }
        $token = hash('sha256', serialize([$activity, $family, $current, $userid, $name, $reason]));
        return (object) ['activity' => $activity, 'cm' => $realcm, 'root' => $root, 'current' => $current,
            'userid' => $userid, 'name' => $name, 'reason' => $reason,
            'attemptnumber' => $current->attemptnumber + 1, 'token' => $token];
    }

    /**
     * Confirm the current proposal and atomically withdraw the superseded outcome.
     *
     * @param \stdClass $cm Course module.
     * @param int $rootid Logical obligation identifier.
     * @param int $userid Participant identifier.
     * @param string $name New physical journey name.
     * @param string $reason Required explanation.
     * @param string $token Preview consistency token, not an authorisation token.
     * @param write_lock|null $guard Optional matching activity guard.
     * @return \stdClass Persisted personal opening.
     */
    public static function confirm(
        \stdClass $cm,
        int $rootid,
        int $userid,
        string $name,
        string $reason,
        string $token,
        ?write_lock $guard = null
    ): \stdClass {
        global $DB, $USER;
        $guard = $guard ?? new write_lock((int) $cm->instance);
        $preview = self::preview($cm, $rootid, $userid, $name, $reason, $guard);
        if (!hash_equals($preview->token, $token)) {
            throw new \moodle_exception('attemptstale', 'attendancejourneys');
        }
        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $child = clone $preview->root;
        unset($child->id);
        $child->name = $preview->name;
        $child->description = '';
        $child->rootjourneyid = $rootid;
        $child->gradeitemnumber = null;
        $child->audiencemode = 2;
        $child->passinggrade = \attendancejourneys_get_passinggrade($preview->activity, $rootid);
        $child->startdate = 0;
        $child->enddate = 0;
        $child->capacity = 0;
        $child->waitlistenabled = 0;
        $child->active = 1;
        $child->completedby = 0;
        $child->timecompleted = 0;
        $child->timecreated = $now;
        $child->timemodified = $now;
        $child->id = $DB->insert_record('attendancejourneys_journeys', $child);
        $assignmentnumber = 1 + (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(attemptnumber), 0) FROM {attendancejourneys_members}'
                . ' WHERE attendancejourneysid = ? AND userid = ?',
            [$preview->activity->id, $userid]
        );
        $DB->insert_record('attendancejourneys_members', (object) [
            'attendancejourneysid' => $preview->activity->id, 'journeyid' => $child->id, 'userid' => $userid,
            'attemptnumber' => $assignmentnumber, 'active' => 1, 'timeassigned' => $now, 'timeended' => 0,
        ]);
        $opening = (object) ['attendancejourneysid' => $preview->activity->id, 'rootjourneyid' => $rootid,
            'journeyid' => $child->id, 'userid' => $userid, 'attemptnumber' => $preview->attemptnumber,
            'actorid' => $USER->id, 'reason' => $preview->reason, 'timecreated' => $now];
        $opening->id = $DB->insert_record('attendancejourneys_attempts', $opening);
        \attendancejourneys_update_grades($preview->activity, $userid);
        \attendancejourneys_update_completion(get_course($preview->cm->course), $preview->cm, [$userid]);
        $transaction->allow_commit();
        $event = \mod_attendancejourneys\event\attempt_opened::create([
            'objectid' => $opening->id, 'relateduserid' => $userid,
            'context' => \context_module::instance($preview->cm->id),
            'other' => ['attendancejourneysid' => (int) $opening->attendancejourneysid,
                'rootjourneyid' => $rootid, 'journeyid' => (int) $opening->journeyid],
        ]);
        $event->add_record_snapshot('attendancejourneys_attempts', $opening);
        $event->trigger();
        return $opening;
    }
}
