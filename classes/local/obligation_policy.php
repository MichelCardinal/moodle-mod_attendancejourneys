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
 * Explicit personal obligation boundaries, independent of enrolment audit dates.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class obligation_policy {
    /**
     * Default policy requires every session without an inferred enrolment boundary.
     *
     * @return \stdClass Unrestricted, non-waived obligation.
     */
    public static function unrestricted(): \stdClass {
        return (object) ['waived' => 0, 'starttime' => 0, 'endtime' => 0, 'reason' => ''];
    }

    /**
     * Validate a proposed decision; this does not authorise or persist it.
     *
     * @param bool $waived Whether the entire journey obligation is waived.
     * @param int $starttime Inclusive lower boundary; zero means unbounded.
     * @param int $endtime Exclusive upper boundary; zero means unbounded.
     * @param string $reason Required decision explanation.
     * @return \stdClass Policy ready for an authorised preview.
     */
    public static function decision(bool $waived, int $starttime, int $endtime, string $reason): \stdClass {
        if ($starttime < 0 || $endtime < 0 || ($endtime > 0 && $endtime <= $starttime)) {
            throw new \moodle_exception('obligationinvalidperiod', 'attendancejourneys');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new \moodle_exception('obligationreasonrequired', 'attendancejourneys');
        }
        return (object) ['waived' => (int) $waived, 'starttime' => $starttime,
            'endtime' => $endtime, 'reason' => $reason];
    }

    /**
     * Retain whole sessions intersecting the explicit period; never invent partial attendance.
     *
     * @param \stdClass $policy Validated or persisted obligation policy.
     * @param array $sessions Sessions indexed by their original keys.
     * @return array Required sessions; the input and attendance records are not changed.
     */
    public static function sessions(\stdClass $policy, array $sessions): array {
        if (!empty($policy->waived)) {
            return [];
        }
        return array_filter($sessions, static function ($session) use ($policy): bool {
            $start = (int) $session->sessiondate;
            $end = $start + max(0, (int) $session->duration) * MINSECS;
            return (empty($policy->starttime) || $end > (int) $policy->starttime) &&
                (empty($policy->endtime) || $start < (int) $policy->endtime);
        });
    }
}
