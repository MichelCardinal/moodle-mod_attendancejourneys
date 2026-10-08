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
 * Stable per-journey gradebook items and final completion decisions.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class journey_grades {
    /**
     * Allocate stable item numbers without reusing numbers of deleted journeys.
     *
     * @param \stdClass $activity Activity using separate journey grades.
     * @return array Journeys indexed by identifier, with allocated item numbers.
     */
    public static function items(\stdClass $activity): array {
        global $DB;
        if (empty($activity->journeygrading)) {
            return [];
        }
        $factory = \core\lock\lock_config::get_lock_factory('mod_attendancejourneys');
        $lock = $factory->get_lock('gradeitems:' . $activity->id, 30);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $next = (int) $DB->get_field('attendancejourneys', 'nextgradeitem', ['id' => $activity->id], MUST_EXIST);
            $journeys = $DB->get_records('attendancejourneys_journeys', [
                'attendancejourneysid' => $activity->id, 'rootjourneyid' => 0,
            ], 'id');
            $used = $DB->get_fieldset_select(
                'grade_items',
                'itemnumber',
                'itemtype = :type AND itemmodule = :module AND iteminstance = :activity',
                ['type' => 'mod', 'module' => 'attendancejourneys', 'activity' => $activity->id]
            );
            foreach ($journeys as $journey) {
                if ($journey->gradeitemnumber !== null) {
                    $used[] = (int) $journey->gradeitemnumber;
                }
            }
            if ($used) {
                $next = max($next, max($used) + 1);
            }
            foreach ($journeys as $journey) {
                if ($journey->gradeitemnumber === null) {
                    $journey->gradeitemnumber = $next++;
                    $DB->set_field(
                        'attendancejourneys_journeys',
                        'gradeitemnumber',
                        $journey->gradeitemnumber,
                        ['id' => $journey->id]
                    );
                }
            }
            $DB->set_field('attendancejourneys', 'nextgradeitem', $next, ['id' => $activity->id]);
            $transaction->allow_commit();
            return $journeys;
        } finally {
            $lock->release();
        }
    }

    /**
     * Update one item through Moodle's grade API, respecting its locking rules.
     *
     * @param \stdClass $activity Activity configuration.
     * @param \stdClass $journey Journey with an allocated grade item number.
     * @param array|null $grades Optional user grades; null updates metadata only.
     * @return int Moodle grade API status.
     */
    public static function update_item(\stdClass $activity, \stdClass $journey, ?array $grades = null): int {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        if ((int) $journey->attendancejourneysid !== (int) $activity->id || $journey->gradeitemnumber === null) {
            throw new \coding_exception('A grade item requires an allocated journey in this activity.');
        }
        $enabled = !empty($activity->percentageenabled) && !empty($activity->gradeenabled);
        $status = grade_update(
            'mod/attendancejourneys',
            $activity->course,
            'mod',
            'attendancejourneys',
            $activity->id,
            $journey->gradeitemnumber,
            $grades,
            [
                'itemname' => $activity->name . ' — ' . $journey->name,
                'gradetype' => $enabled ? GRADE_TYPE_VALUE : GRADE_TYPE_NONE,
                'grademin' => 0, 'grademax' => 100,
            ]
        );
        // The grade_update API deliberately does not accept gradepass as an itemdetail.
        if ($enabled && $status === GRADE_UPDATE_OK) {
            $item = \grade_item::fetch([
                'courseid' => $activity->course, 'itemtype' => 'mod', 'itemmodule' => 'attendancejourneys',
                'iteminstance' => $activity->id, 'itemnumber' => $journey->gradeitemnumber,
            ]);
            $threshold = attendancejourneys_get_passinggrade($activity, (int) $journey->id);
            $closures = $DB->get_records('attendancejourneys_closures', [
                'attendancejourneysid' => $activity->id, 'journeyid' => $journey->id, 'active' => 1,
            ], 'timeclosed, id', 'id, threshold', 0, 1);
            if ($closures) {
                $threshold = (float) reset($closures)->threshold;
            }
            if ($item && !$item->is_locked() && grade_floats_different((float) $item->gradepass, $threshold)) {
                $item->gradepass = $threshold;
                $item->update('mod/attendancejourneys');
            }
        }
        return $status;
    }

    /**
     * Remove an unused item's metadata without discarding manually entered results.
     *
     * @param \stdClass $activity Activity configuration.
     * @param \stdClass $journey Journey being deleted.
     */
    public static function delete_empty_item(\stdClass $activity, \stdClass $journey): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        if ((int) $journey->attendancejourneysid !== (int) $activity->id || $journey->gradeitemnumber === null) {
            throw new \coding_exception('A grade item requires an allocated journey in this activity.');
        }
        $item = \grade_item::fetch([
            'courseid' => $activity->course, 'itemtype' => 'mod', 'itemmodule' => 'attendancejourneys',
            'iteminstance' => $activity->id, 'itemnumber' => $journey->gradeitemnumber,
        ]);
        if (!$item) {
            return;
        }
        if (
            $item->is_locked() || $DB->record_exists_select(
                'grade_grades',
                'itemid = :item AND (rawgrade IS NOT NULL OR finalgrade IS NOT NULL)',
                ['item' => $item->id]
            )
        ) {
            throw new \coding_exception('A journey with protected or manually entered grades cannot be deleted.');
        }
        $status = grade_update(
            'mod/attendancejourneys',
            $activity->course,
            'mod',
            'attendancejourneys',
            $activity->id,
            $journey->gradeitemnumber,
            null,
            ['deleted' => 1]
        );
        if ($status !== GRADE_UPDATE_OK) {
            throw new \coding_exception('The journey grade item could not be deleted.');
        }
    }

    /**
     * Publish final snapshots only; reopening one journey clears only its own result.
     *
     * @param \stdClass $activity Activity configuration.
     * @param int $userid Zero synchronises all current or historically graded participants.
     * @return array Moodle update status indexed by journey identifier.
     */
    public static function sync(\stdClass $activity, int $userid = 0): array {
        global $DB;
        $items = self::items($activity);
        $enabled = !empty($activity->percentageenabled) && !empty($activity->gradeenabled);
        $statuses = [];
        foreach ($items as $journey) {
            $grades = null;
            if ($enabled) {
                $conditions = ['attendancejourneysid' => $activity->id, 'journeyid' => $journey->id];
                if ($userid) {
                    $conditions['userid'] = $userid;
                }
                // Include inactive snapshots so reopening also withdraws an earlier publication.
                $closures = $DB->get_records('attendancejourneys_closures', $conditions, 'timeclosed, id');
                $grades = $userid ? [$userid => (object) ['userid' => $userid, 'rawgrade' => null]] : [];
                if (!$userid) {
                    $sql = "SELECT gg.userid
                              FROM {grade_grades} gg
                              JOIN {grade_items} gi ON gi.id = gg.itemid
                             WHERE gi.itemtype = :type AND gi.itemmodule = :module
                               AND gi.iteminstance = :activity AND gi.itemnumber = :number";
                    foreach (
                        $DB->get_fieldset_sql($sql, ['type' => 'mod', 'module' => 'attendancejourneys',
                        'activity' => $activity->id, 'number' => $journey->gradeitemnumber]) as $participantid
                    ) {
                        $grades[$participantid] = (object) ['userid' => (int) $participantid, 'rawgrade' => null];
                    }
                }
                foreach ($closures as $closure) {
                    if (!isset($grades[$closure->userid])) {
                        $grades[$closure->userid] = (object) ['userid' => (int) $closure->userid, 'rawgrade' => null];
                    }
                    if (!empty($closure->active)) {
                        $grades[$closure->userid]->rawgrade = self::raw_grade($closure);
                    }
                }
                $family = attempt_family::load($activity, (int) $journey->id, $userid);
                foreach (array_keys($family->links) as $participantid) {
                    $selected = attempt_family::select($family, $participantid);
                    $grades[$participantid] = (object) ['userid' => $participantid,
                        'rawgrade' => $selected->closure ? self::raw_grade($selected->closure) : null];
                }
            }
            $statuses[$journey->id] = self::update_item($activity, $journey, $grades);
        }
        return $statuses;
    }

    /**
     * Identify protected gradebook results for participants already authorised by the caller.
     *
     * This read-only check does not allocate items, synchronise grades or expose other users.
     *
     * @param \stdClass $activity Activity configuration.
     * @param int $journeyid Journey in this activity.
     * @param array $userids Visible participant identifiers.
     * @return array Protection message keys indexed by user identifier.
     */
    public static function protections(\stdClass $activity, int $journeyid, array $userids): array {
        global $CFG, $DB;
        if (
            empty($activity->journeygrading) || empty($activity->gradeenabled) ||
                empty($activity->percentageenabled) || !$userids
        ) {
            return [];
        }
        require_once($CFG->libdir . '/gradelib.php');
        $journey = attempt_family::root($activity, $journeyid);
        if ($journey->gradeitemnumber === null) {
            return [];
        }
        $item = \grade_item::fetch([
            'courseid' => $activity->course, 'itemtype' => 'mod', 'itemmodule' => 'attendancejourneys',
            'iteminstance' => $activity->id, 'itemnumber' => $journey->gradeitemnumber,
        ]);
        if (!$item) {
            return [];
        }
        if ($item->is_locked()) {
            return array_fill_keys($userids, 'journeygradelocked');
        }
        $protected = [];
        foreach (\grade_grade::fetch_users_grades($item, $userids, false) as $userid => $grade) {
            $grade->grade_item = $item;
            if ($grade->is_locked()) {
                $protected[$userid] = 'journeygradelocked';
            } else if ($grade->is_overridden()) {
                $protected[$userid] = 'journeygradeoverridden';
            }
        }
        return $protected;
    }

    /**
     * Preserve the frozen decision when Moodle rounds numeric grades to five decimals.
     *
     * @param \stdClass $closure Frozen minutes, threshold and result.
     * @return float|null Final percentage; null is unpublished or not gradable.
     */
    private static function raw_grade(\stdClass $closure): ?float {
        if ($closure->percentage === null || $closure->result === 'none' || !$closure->possibleminutes) {
            return null;
        }
        $percentage = 100.0 * (int) $closure->presentminutes / (int) $closure->possibleminutes;
        if ($closure->result === 'failed' && round($percentage, 5) >= (float) $closure->threshold) {
            return max(0.0, (float) $closure->threshold - 0.00001);
        }
        return $percentage;
    }

    /**
     * Whether every assigned required journey has a successful active closure.
     *
     * @param \stdClass $activity Activity configuration.
     * @param int $userid Participant identifier.
     * @return bool No obligations, unclosed obligations and failed obligations never complete the activity.
     */
    public static function all_required_passed(\stdClass $activity, int $userid): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        if (empty($activity->percentageenabled)) {
            return false;
        }
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $activity->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        if (!is_enrolled($context, $userid, 'mod/attendancejourneys:canbelisted', true)) {
            return false;
        }
        $required = 0;
        foreach (
            $DB->get_records('attendancejourneys_journeys', [
            'attendancejourneysid' => $activity->id, 'required' => 1, 'rootjourneyid' => 0,
            ]) as $journey
        ) {
            $family = attempt_family::load($activity, (int) $journey->id, $userid);
            $selection = attempt_family::select($family, $userid);
            $closure = $selection->closure;
            $assigned = isset($family->links[$userid]) ||
                $DB->record_exists('attendancejourneys_members', ['journeyid' => $journey->id, 'userid' => $userid]);
            if (!$assigned && !$closure && !\attendancejourneys_journey_has_fixed_audience($journey)) {
                $assigned = !empty($journey->active) &&
                    (empty($journey->groupid) || groups_is_member((int) $journey->groupid, $userid));
            }
            if (!$assigned && !$closure) {
                continue;
            }
            if (
                !empty($activity->journeygrading) &&
                    !empty(individual_obligation::get($selection->journeyid, $userid)->waived)
            ) {
                continue;
            }
            $required++;
            if (!$closure || $closure->percentage === null || $closure->result !== 'passed') {
                return false;
            }
        }
        return $required > 0;
    }
}
