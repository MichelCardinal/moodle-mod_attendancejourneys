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
 * Generates and customises session-series previews without writing to the database.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class series_builder {
    /**
     * Generates regularly spaced occurrences in the user's timezone.
     *
     * @param string $name Base session name.
     * @param int $start First start timestamp.
     * @param int $durationseconds Duration in seconds.
     * @param int $count Number of sessions.
     * @param int $intervaldays Days between sessions.
     * @param string $namemode same or numbered.
     * @param \DateTimeZone $timezone User timezone.
     * @param array $details Shared modality, location, meeting URL and description.
     * @return array Preview occurrences.
     */
    public static function generate(
        string $name,
        int $start,
        int $durationseconds,
        int $count,
        int $intervaldays,
        string $namemode,
        \DateTimeZone $timezone,
        array $details = []
    ): array {
        if (
            $count < 1 || $count > 50 || $durationseconds < 60 || $intervaldays < 1 ||
                !in_array($namemode, ['same', 'numbered'], true)
        ) {
            throw new \invalid_parameter_exception('Invalid session-series parameters.');
        }
        $seriesstart = new \DateTime('@' . $start);
        $seriesstart->setTimezone($timezone);
        $occurrences = [];
        for ($index = 0; $index < $count; $index++) {
            $occurrence = clone $seriesstart;
            if ($index > 0) {
                $occurrence->modify('+' . ($index * $intervaldays) . ' days');
            }
            $occurrencestart = $occurrence->getTimestamp();
            $occurrences[] = [
                'name' => $namemode === 'numbered' ? $name . ' ' . ($index + 1) : $name,
                'sessiondate' => $occurrencestart,
                'enddate' => $occurrencestart + $durationseconds,
                'included' => 1,
                'modality' => $details['modality'] ?? 'unspecified',
                'location' => $details['location'] ?? '',
                'meetingurl' => $details['meetingurl'] ?? '',
                'description' => $details['description'] ?? '',
            ];
        }
        return $occurrences;
    }

    /**
     * Applies one change to selected preview occurrences.
     *
     * @param array $occurrences Current occurrences.
     * @param array $selected Selection flags keyed like occurrences.
     * @param string $operation number, rename or duration.
     * @param string $value Submitted value.
     * @param \DateTimeZone|null $timezone User timezone for calendar-day shifts.
     * @return array Updated occurrences.
     */
    public static function apply_bulk(
        array $occurrences,
        array $selected,
        string $operation,
        string $value,
        ?\DateTimeZone $timezone = null
    ): array {
        if (
            !in_array($operation, ['number', 'rename', 'duration', 'modality', 'location', 'meetingurl',
                'shiftdays', 'shiftminutes'], true)
        ) {
            throw new \invalid_parameter_exception('Invalid session-series bulk operation.');
        }
        $selectednumber = 0;
        foreach ($occurrences as $index => &$occurrence) {
            if (empty($selected[$index])) {
                continue;
            }
            $selectednumber++;
            if ($operation === 'number') {
                $occurrence['name'] = trim($value) . ' ' . $selectednumber;
            } else if ($operation === 'rename') {
                $occurrence['name'] = trim($value);
            } else if ($operation === 'duration') {
                $occurrence['enddate'] = (int) $occurrence['sessiondate'] + ((int) $value * MINSECS);
            } else if ($operation === 'shiftminutes') {
                $offset = (int) $value * MINSECS;
                $occurrence['sessiondate'] = (int) $occurrence['sessiondate'] + $offset;
                $occurrence['enddate'] = (int) $occurrence['enddate'] + $offset;
            } else if ($operation === 'shiftdays') {
                $timezone = $timezone ?? new \DateTimeZone('UTC');
                $duration = (int) $occurrence['enddate'] - (int) $occurrence['sessiondate'];
                $shifted = new \DateTime('@' . (int) $occurrence['sessiondate']);
                $shifted->setTimezone($timezone);
                $days = (int) $value;
                $shifted->modify(($days > 0 ? '+' : '') . $days . ' days');
                $occurrence['sessiondate'] = $shifted->getTimestamp();
                $occurrence['enddate'] = $shifted->getTimestamp() + $duration;
            } else {
                $occurrence[$operation] = trim($value);
            }
        }
        unset($occurrence);
        return $occurrences;
    }

    /**
     * Finds overlaps among proposed occurrences and against existing sessions.
     *
     * @param array $occurrences Proposed occurrences.
     * @param array $existing Existing session records for the same audience.
     * @return array Indexes of proposed occurrences involved in an overlap.
     */
    public static function find_overlaps(array $occurrences, array $existing = []): array {
        $overlaps = [];
        foreach (self::describe_overlaps($occurrences, $existing) as $detail) {
            $overlaps[$detail['proposedindex']] = true;
            if ($detail['source'] === 'proposed') {
                $overlaps[$detail['otherindex']] = true;
            }
        }
        return array_map('intval', array_keys($overlaps));
    }

    /**
     * Describes every distinct overlap involving a proposed occurrence.
     *
     * @param array $occurrences Proposed occurrences.
     * @param array $existing Existing session records for the same audience.
     * @return array Structured overlap details.
     */
    public static function describe_overlaps(array $occurrences, array $existing = []): array {
        $details = [];
        foreach ($occurrences as $firstindex => $first) {
            if (empty($first['included'])) {
                continue;
            }
            $firststart = (int) $first['sessiondate'];
            $firstend = (int) $first['enddate'];
            foreach ($occurrences as $secondindex => $second) {
                if ($secondindex <= $firstindex || empty($second['included'])) {
                    continue;
                }
                if ($firststart < (int) $second['enddate'] && $firstend > (int) $second['sessiondate']) {
                    $overlapstart = max($firststart, (int) $second['sessiondate']);
                    $overlapend = min($firstend, (int) $second['enddate']);
                    $details[] = [
                        'source' => 'proposed',
                        'proposedindex' => (int) $firstindex,
                        'otherindex' => (int) $secondindex,
                        'overlapstart' => $overlapstart,
                        'overlapend' => $overlapend,
                        'overlapseconds' => $overlapend - $overlapstart,
                    ];
                }
            }
            foreach ($existing as $session) {
                $existingstart = (int) $session->sessiondate;
                $existingend = $existingstart + ((int) $session->duration * MINSECS);
                if ($firststart < $existingend && $firstend > $existingstart) {
                    $overlapstart = max($firststart, $existingstart);
                    $overlapend = min($firstend, $existingend);
                    $details[] = [
                        'source' => 'existing',
                        'proposedindex' => (int) $firstindex,
                        'session' => $session,
                        'overlapstart' => $overlapstart,
                        'overlapend' => $overlapend,
                        'overlapseconds' => $overlapend - $overlapstart,
                    ];
                }
            }
        }
        return $details;
    }

    /**
     * Sorts occurrences chronologically while keeping equal starts stable.
     *
     * @param array $occurrences Preview occurrences.
     * @return array Re-indexed chronological occurrences.
     */
    public static function sort_chronologically(array $occurrences): array {
        foreach ($occurrences as $index => &$occurrence) {
            $occurrence['_originalorder'] = $index;
        }
        unset($occurrence);
        usort($occurrences, static function (array $first, array $second): int {
            return ((int) $first['sessiondate'] <=> (int) $second['sessiondate']) ?:
                ((int) $first['_originalorder'] <=> (int) $second['_originalorder']);
        });
        foreach ($occurrences as &$occurrence) {
            unset($occurrence['_originalorder']);
        }
        unset($occurrence);
        return array_values($occurrences);
    }

    /**
     * Summarises included and excluded preview occurrences.
     *
     * @param array $occurrences Preview occurrences.
     * @return array Counts, period, duration and modality counts.
     */
    public static function summarise(array $occurrences): array {
        $summary = [
            'included' => 0,
            'excluded' => 0,
            'totalminutes' => 0,
            'firststart' => null,
            'lastend' => null,
            'modalities' => [],
        ];
        foreach ($occurrences as $occurrence) {
            if (empty($occurrence['included'])) {
                $summary['excluded']++;
                continue;
            }
            $start = (int) $occurrence['sessiondate'];
            $end = (int) $occurrence['enddate'];
            $summary['included']++;
            $summary['totalminutes'] += max(0, (int) ceil(($end - $start) / MINSECS));
            $summary['firststart'] = $summary['firststart'] === null ? $start : min($summary['firststart'], $start);
            $summary['lastend'] = $summary['lastend'] === null ? $end : max($summary['lastend'], $end);
            $modality = $occurrence['modality'] ?? 'unspecified';
            $summary['modalities'][$modality] = ($summary['modalities'][$modality] ?? 0) + 1;
        }
        ksort($summary['modalities']);
        return $summary;
    }
}
