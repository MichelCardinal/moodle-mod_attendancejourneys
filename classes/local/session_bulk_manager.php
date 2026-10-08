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
 * Safe calculations for bulk session schedule changes.
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_bulk_manager {
    /**
     * Returns clones assigned to one group or one journey.
     *
     * @param array $sessions Attendance sessions indexed by identifier.
     * @param int $groupid Moodle group identifier; zero denotes no group restriction.
     * @param int $journeyid Journey identifier; zero denotes no specific journey.
     */
    public static function preview_audience(array $sessions, int $groupid, int $journeyid): array {
        if ($groupid < 0 || $journeyid < 0 || ($journeyid > 0 && $groupid < 0)) {
            throw new \invalid_parameter_exception('Invalid bulk session audience.');
        }
        $changed = [];
        foreach ($sessions as $session) {
            $copy = clone $session;
            $copy->groupid = $groupid;
            $copy->journeyid = $journeyid;
            $changed[(int) $copy->id] = $copy;
        }
        return $changed;
    }

    /**
     * Returns changed clones for a supported bulk-edit operation.
     *
     * @param array $sessions Attendance sessions indexed by identifier.
     * @param string $operation Supported bulk-edit operation.
     * @param string $value Requested value or shift amount.
     */
    public static function preview_change(array $sessions, string $operation, string $value): array {
        $allowed = ['duration', 'modality', 'location', 'meetingurl', 'rename', 'number', 'description'];
        if (!in_array($operation, $allowed, true)) {
            throw new \invalid_parameter_exception('Invalid bulk session change.');
        }
        if ($operation === 'duration' && (!ctype_digit($value) || (int) $value < 1 || (int) $value > 1440)) {
            throw new \invalid_parameter_exception('Invalid session duration.');
        }
        if ($operation === 'modality' && !in_array($value, ['unspecified', 'inperson', 'online', 'hybrid'], true)) {
            throw new \invalid_parameter_exception('Invalid session modality.');
        }
        if ($operation === 'meetingurl' && $value !== '' && !preg_match('#^https?://#i', $value)) {
            throw new \invalid_parameter_exception('Invalid meeting URL.');
        }
        if (in_array($operation, ['rename', 'number'], true) && trim($value) === '') {
            throw new \invalid_parameter_exception('A session name is required.');
        }
        $numberingsuffixlength = $operation === 'number' ? 1 + strlen((string) count($sessions)) : 0;
        if (
            in_array($operation, ['rename', 'number'], true) &&
                \core_text::strlen(trim($value)) + $numberingsuffixlength > 255
        ) {
            throw new \invalid_parameter_exception('The session name is too long.');
        }
        if ($operation === 'number') {
            usort($sessions, static function ($first, $second): int {
                return [(int) $first->sessiondate, (int) $first->id] <=>
                    [(int) $second->sessiondate, (int) $second->id];
            });
        }
        $changed = [];
        foreach ($sessions as $index => $session) {
            $copy = clone $session;
            if ($operation === 'rename') {
                $copy->name = trim($value);
            } else if ($operation === 'number') {
                $copy->name = trim($value) . ' ' . ($index + 1);
            } else {
                $copy->{$operation} = $operation === 'duration' ? (int) $value : trim($value);
            }
            $changed[(int) $copy->id] = $copy;
        }
        return $changed;
    }

    /**
     * Returns shifted clones while preserving duration and identifiers.
     *
     * @param array $sessions Attendance sessions indexed by identifier.
     * @param string $unit Unit used for the schedule shift.
     * @param int $value Requested value or shift amount.
     * @param \DateTimeZone $timezone Timezone used for calendar-day calculations.
     */
    public static function preview_shift(
        array $sessions,
        string $unit,
        int $value,
        \DateTimeZone $timezone
    ): array {
        if (!in_array($unit, ['days', 'minutes'], true) || $value === 0) {
            throw new \invalid_parameter_exception('Invalid bulk session shift.');
        }
        $shifted = [];
        foreach ($sessions as $session) {
            $copy = clone $session;
            if ($unit === 'days') {
                $local = new \DateTime('@' . (int) $session->sessiondate);
                $local->setTimezone($timezone);
                $local->modify(($value > 0 ? '+' : '') . $value . ' days');
                $copy->sessiondate = $local->getTimestamp();
            } else {
                $copy->sessiondate = (int) $session->sessiondate + ($value * MINSECS);
            }
            $shifted[(int) $copy->id] = $copy;
        }
        return $shifted;
    }

    /**
     * Returns shifted clones with temporary IDs so originals remain conflict candidates.
     *
     * @param array $sessions Attendance sessions indexed by identifier.
     * @param string $unit Unit used for the schedule shift.
     * @param int $value Requested value or shift amount.
     * @param \DateTimeZone $timezone Timezone used for calendar-day calculations.
     */
    public static function preview_copies(
        array $sessions,
        string $unit,
        int $value,
        \DateTimeZone $timezone
    ): array {
        $copies = self::preview_shift($sessions, $unit, $value, $timezone);
        $result = [];
        foreach ($copies as $sourceid => $copy) {
            $copy->sourceid = (int) $sourceid;
            $copy->id = -(int) $sourceid;
            $result[(int) $copy->id] = $copy;
        }
        return $result;
    }

    /**
     * Reports proposed-to-proposed and proposed-to-existing conflicts for the same audience.
     *
     * @param array $shifted Proposed sessions after the schedule change.
     * @param array $existing Existing records used for comparison.
     */
    public static function find_conflicts(array $shifted, array $existing): array {
        $conflicts = [];
        $values = array_values($shifted);
        foreach ($values as $index => $session) {
            $start = (int) $session->sessiondate;
            $end = $start + ((int) $session->duration * MINSECS);
            foreach (array_slice($values, $index + 1) as $other) {
                if (!self::same_audience($session, $other)) {
                    continue;
                }
                $otherstart = (int) $other->sessiondate;
                $otherend = $otherstart + ((int) $other->duration * MINSECS);
                if ($start < $otherend && $end > $otherstart) {
                    $conflicts[] = ['session' => $session, 'other' => $other];
                }
            }
            foreach ($existing as $other) {
                if (isset($shifted[(int) $other->id]) || !self::same_audience($session, $other)) {
                    continue;
                }
                $otherstart = (int) $other->sessiondate;
                $otherend = $otherstart + ((int) $other->duration * MINSECS);
                if ($start < $otherend && $end > $otherstart) {
                    $conflicts[] = ['session' => $session, 'other' => $other];
                }
            }
        }
        return $conflicts;
    }

    /**
     * Whether two sessions address the same journey or group audience.
     *
     * @param \stdClass $first First session to compare.
     * @param \stdClass $second Second session to compare.
     */
    private static function same_audience(\stdClass $first, \stdClass $second): bool {
        return (int) $first->journeyid === (int) $second->journeyid &&
            (int) $first->groupid === (int) $second->groupid;
    }
}
