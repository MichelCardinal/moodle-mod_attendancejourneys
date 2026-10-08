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
 * Load one logical obligation and its explicit personal retakes without publishing anything.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_family {
    /**
     * Resolve a physical journey to its independent logical obligation.
     *
     * @param \stdClass $activity Owning activity.
     * @param int $journeyid Physical journey identifier.
     * @return \stdClass Root journey in the matching activity.
     */
    public static function root(\stdClass $activity, int $journeyid): \stdClass {
        global $DB;
        $journey = $DB->get_record('attendancejourneys_journeys', [
            'attendancejourneysid' => $activity->id, 'id' => $journeyid,
        ], '*', MUST_EXIST);
        if (empty($journey->rootjourneyid)) {
            return $journey;
        }
        $root = $DB->get_record('attendancejourneys_journeys', [
            'attendancejourneysid' => $activity->id, 'id' => $journey->rootjourneyid,
        ], '*', MUST_EXIST);
        if (!empty($root->rootjourneyid) || (int) $root->id === (int) $journey->id) {
            throw new \coding_exception('An attendance retake must refer directly to an independent obligation.');
        }
        return $root;
    }

    /**
     * Batch-load a family for grade synchronisation or one participant's completion check.
     *
     * This does not authorise access; callers must apply their course and group permissions.
     *
     * @param \stdClass $activity Owning activity.
     * @param int $rootjourneyid Independent logical obligation identifier.
     * @param int $userid Optional participant scope; zero loads all family participants.
     * @return \stdClass Validated structural links and closures grouped by participant.
     */
    public static function load(\stdClass $activity, int $rootjourneyid, int $userid = 0): \stdClass {
        global $DB;
        $root = self::root($activity, $rootjourneyid);
        if ((int) $root->id !== $rootjourneyid) {
            throw new \coding_exception('Family loading requires the independent obligation identifier.');
        }
        $journeys = $DB->get_records_select(
            'attendancejourneys_journeys',
            'attendancejourneysid = :activity AND (id = :root OR rootjourneyid = :parent)',
            ['activity' => $activity->id, 'root' => $rootjourneyid, 'parent' => $rootjourneyid]
        );
        $conditions = ['attendancejourneysid' => $activity->id, 'rootjourneyid' => $rootjourneyid];
        if ($userid) {
            $conditions['userid'] = $userid;
        }
        $links = [];
        foreach ($DB->get_records('attendancejourneys_attempts', $conditions, 'attemptnumber, id') as $link) {
            $child = $journeys[$link->journeyid] ?? null;
            if (!$child || (int) $child->rootjourneyid !== $rootjourneyid || (int) $child->audiencemode !== 2) {
                throw new \coding_exception('A personal retake requires its matching explicit-audience journey.');
            }
            $links[(int) $link->userid][] = $link;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($journeys), SQL_PARAMS_NAMED, 'familyjourney');
        $params['activity'] = $activity->id;
        $where = "attendancejourneysid = :activity AND journeyid $insql";
        if ($userid) {
            $where .= ' AND userid = :participant';
            $params['participant'] = $userid;
        }
        $closures = [];
        foreach ($DB->get_records_select('attendancejourneys_closures', $where, $params, 'timeclosed, id') as $closure) {
            $closures[(int) $closure->userid][] = $closure;
        }
        return (object) ['activityid' => (int) $activity->id, 'root' => $root, 'links' => $links, 'closures' => $closures];
    }

    /**
     * Select one participant's current result using an already loaded family.
     *
     * Preserve the historical latest-active-closure rule when there is no explicit retake.
     *
     * @param \stdClass $family Snapshot produced by load().
     * @param int $userid Participant identifier.
     * @return \stdClass Current physical journey, personal attempt number and optional final result.
     */
    public static function select(\stdClass $family, int $userid): \stdClass {
        $links = $family->links[$userid] ?? [];
        $closures = $family->closures[$userid] ?? [];
        if ($links) {
            return attempt_policy::select($family->activityid, (int) $family->root->id, $userid, $links, $closures);
        }
        $selected = null;
        foreach ($closures as $closure) {
            if ((int) $closure->journeyid === (int) $family->root->id && !empty($closure->active)) {
                $selected = clone $closure;
            }
        }
        return (object) ['journeyid' => (int) $family->root->id, 'attemptnumber' => 1, 'closure' => $selected];
    }

    /**
     * Describe a physical attempt without presenting its historical result as the current grade.
     *
     * Callers must authorise the participant and physical journey before displaying this status.
     *
     * @param \stdClass $family Already loaded logical family.
     * @param int $userid Authorised participant identifier.
     * @param int $journeyid Physical journey represented in the report.
     * @param \stdClass|null $closure Physical journey closure, if any.
     * @param bool $waived Whether this physical attempt is individually waived.
     * @return string Moodle language key for the result status.
     */
    public static function result_status(
        \stdClass $family,
        int $userid,
        int $journeyid,
        ?\stdClass $closure,
        bool $waived = false
    ): string {
        $current = self::select($family, $userid);
        if ($current->journeyid !== $journeyid) {
            return $closure ? 'journeypreviousresult' : 'attemptprevious';
        }
        if ($closure) {
            return $current->closure && (int) $current->closure->id === (int) $closure->id ?
                'journeyresultpublished' : 'journeypreviousresult';
        }
        return $waived ? 'obligationwaived' : 'provisional';
    }
}
