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
 * Select one attempt result for one logical attendance obligation.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_policy {
    /**
     * Select the latest explicitly opened attempt, preserving every historical input.
     *
     * Attempt numbers belong to this obligation and participant, not the activity.
     * An open or reopened selected attempt has no final result; an older success
     * cannot replace it. Closing an older attempt later does not change selection.
     * The caller must authorise the operation and load the current family data.
     *
     * @param int $activityid Owning activity identifier.
     * @param int $rootjourneyid Journey defining the logical obligation.
     * @param int $userid Participant identifier.
     * @param array $attempts Explicit links with rootjourneyid, journeyid and attemptnumber.
     * @param array $closures Existing closure snapshots for the activity.
     * @return \stdClass Selected journey, number and optional final snapshot.
     */
    public static function select(
        int $activityid,
        int $rootjourneyid,
        int $userid,
        array $attempts,
        array $closures
    ): \stdClass {
        if ($activityid <= 0 || $rootjourneyid <= 0 || $userid <= 0) {
            throw new \coding_exception('Attempt selection requires an activity, obligation and participant.');
        }
        $journeyid = $rootjourneyid;
        $number = 1;
        $numbers = [];
        $journeys = [];
        foreach ($attempts as $attempt) {
            if ((int) $attempt->rootjourneyid !== $rootjourneyid || (int) $attempt->userid !== $userid) {
                continue;
            }
            $attemptnumber = (int) $attempt->attemptnumber;
            $attemptjourney = (int) $attempt->journeyid;
            if (
                (int) $attempt->attendancejourneysid !== $activityid || $attemptnumber < 2 ||
                    $attemptjourney <= 0 || $attemptjourney === $rootjourneyid ||
                    isset($numbers[$attemptnumber]) || isset($journeys[$attemptjourney])
            ) {
                throw new \coding_exception('Attempt links must be unique within the matching obligation and participant.');
            }
            $numbers[$attemptnumber] = true;
            $journeys[$attemptjourney] = true;
            if ($attemptnumber > $number) {
                $journeyid = $attemptjourney;
                $number = $attemptnumber;
            }
        }
        $selected = null;
        foreach ($closures as $closure) {
            if ((int) $closure->userid !== $userid || (int) $closure->journeyid !== $journeyid) {
                continue;
            }
            if ((int) $closure->attendancejourneysid !== $activityid) {
                throw new \coding_exception('A selected attempt closure must belong to the matching activity.');
            }
            if (empty($closure->active)) {
                continue;
            }
            if ($selected !== null) {
                throw new \coding_exception('A selected attempt cannot have multiple active closures.');
            }
            $selected = clone $closure;
        }
        return (object) ['journeyid' => $journeyid, 'attemptnumber' => $number, 'closure' => $selected];
    }
}
