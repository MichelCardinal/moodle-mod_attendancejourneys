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
 * Read-only impact preview and conservative conversion of unfinished historical activities.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy_migration {
    /**
     * Inventory the current state without allocating items or changing participant data.
     *
     * @param \context_module $context Authorised activity context.
     * @return \stdClass Counts, proposed mapping, blockers and a state fingerprint.
     */
    public static function preview(\context_module $context): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid, 0, false, MUST_EXIST);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('mod/attendancejourneys:managejourneys', $context);
        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            require_capability('moodle/site:accessallgroups', $context);
        }
        $activity = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
        $journeys = $DB->get_records('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id], 'id');
        $sessions = $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $activity->id], 'id');
        $records = $DB->get_records_sql(
            "SELECT r.* FROM {attendancejourneys_records} r
               JOIN {attendancejourneys_sessions} s ON s.id = r.sessionid
              WHERE s.attendancejourneysid = :activity ORDER BY r.id",
            ['activity' => $activity->id]
        );
        $closures = $DB->get_records('attendancejourneys_closures', ['attendancejourneysid' => $activity->id], 'id');
        $items = $DB->get_records('grade_items', [
            'courseid' => $cm->course, 'itemtype' => 'mod', 'itemmodule' => 'attendancejourneys', 'iteminstance' => $activity->id,
        ], 'id');
        $grades = $items ? $DB->get_records_list('grade_grades', 'itemid', array_keys($items), 'id') : [];
        $completions = $DB->get_records('course_modules_completion', ['coursemoduleid' => $cm->id], 'id');
        $members = $journeys ? $DB->get_records_list('attendancejourneys_members', 'journeyid', array_keys($journeys), 'id') : [];
        $waitlist = $journeys ? $DB->get_records_list('attendancejourneys_waitlist', 'journeyid', array_keys($journeys), 'id') : [];
        $equivalences = $DB->get_records('attendancejourneys_equivalences', ['attendancejourneysid' => $activity->id], 'id');
        $blockers = [];
        if (!empty($activity->journeygrading)) {
            $blockers[] = 'migrationalreadyseparate';
        }
        if (count($journeys) > 1 || ($journeys && empty(reset($journeys)->active))) {
            $blockers[] = 'migrationcomplexjourneys';
        }
        $independent = count(array_filter($sessions, static fn($session) => empty($session->journeyid)));
        if (
            ($journeys && $independent) || count(array_filter(
                $sessions,
                static fn($session) => !empty($session->journeyid) && !isset($journeys[$session->journeyid])
            ))
        ) {
            $blockers[] = 'migrationmixedsessions';
        }
        if ($closures) {
            $blockers[] = 'migrationfinalresults';
        }
        $excused = 0;
        foreach ($records as $record) {
            if ($record->status === 'excused') {
                $excused++;
            } else if (!in_array($record->status, ['present', 'partial', 'absent'], true)) {
                $blockers[] = 'migrationrecordpolicy';
            }
            if (
                $record->status === 'partial' && ((int) $record->minutesabsent <= 0 ||
                    (int) $record->minutesabsent >= (int) $sessions[$record->sessionid]->duration)
            ) {
                $blockers[] = 'migrationrecordpolicy';
            }
        }
        if ($excused && $activity->excusedmode !== 'absent') {
            $blockers[] = 'migrationexcusedpolicy';
        }
        $published = 0;
        $protected = 0;
        foreach ($grades as $grade) {
            $published += (int) ($grade->rawgrade !== null || $grade->finalgrade !== null);
            $item = new \grade_item($items[$grade->itemid], false);
            $nativegrade = new \grade_grade($grade, false);
            $nativegrade->grade_item = $item;
            $protected += (int) ($nativegrade->is_locked() || $nativegrade->is_overridden());
        }
        foreach ($items as $item) {
            $nativeitem = new \grade_item($item, false);
            if ($nativeitem->is_locked()) {
                $blockers[] = 'migrationprotectedgrades';
            }
            if (
                (int) $item->itemnumber !== 0 || !empty($item->outcomeid) || !empty($item->calculation) ||
                    ((int) $item->gradetype !== GRADE_TYPE_NONE &&
                        ((int) $item->gradetype !== GRADE_TYPE_VALUE || (float) $item->grademin !== 0.0 ||
                            (float) $item->grademax !== 100.0))
            ) {
                $blockers[] = 'migrationgrademapping';
            }
        }
        if ($published) {
            $blockers[] = 'migrationpublishedgrades';
        }
        if ($protected) {
            $blockers[] = 'migrationprotectedgrades';
        }
        if (count(array_filter($completions, static fn($row) => !empty($row->completionstate) || !empty($row->overrideby)))) {
            $blockers[] = 'migrationexistingcompletion';
        }
        if ($equivalences) {
            $blockers[] = 'migrationequivalences';
        }
        $effective = clone $activity;
        $effective->journeygrading = 1;
        $effective->completion = $cm->completion;
        if (attendancejourneys_journey_requires_percentage($effective)) {
            $blockers[] = 'journeyrequirespercentage';
        }
        $journey = $journeys ? reset($journeys) : null;
        if ($journey && $journey->gradeitemnumber !== null && (int) $journey->gradeitemnumber !== 0) {
            $blockers[] = 'migrationgrademapping';
        }
        $audience = $journey && attendancejourneys_journey_has_fixed_audience($journey) ? 2 : 1;
        $threshold = $journey ? attendancejourneys_get_passinggrade(
            $activity,
            (int) $journey->id
        ) : (float) $activity->passinggrade;
        $snapshot = [$cm, $activity, $journeys, $sessions, $records, $closures, $items, $grades,
            $completions, $members, $waitlist, $equivalences];
        return (object) [
            'activity' => $activity, 'cm' => $cm, 'journeys' => $journeys,
            'sessions' => count($sessions), 'independent' => $independent, 'records' => count($records),
            'closures' => count($closures), 'excused' => $excused, 'published' => $published, 'protected' => $protected,
            'blockers' => array_values(array_unique($blockers)),
            'fingerprint' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'mapping' => (object) [
                'journeyid' => $journey ? (int) $journey->id : 0,
                'name' => $journey ? $journey->name : get_string('defaultjourneyname', 'attendancejourneys'),
                'audiencemode' => $audience, 'gradeitemnumber' => 0, 'threshold' => $threshold,
            ],
        ];
    }

    /**
     * Convert only the compatible unfinished activity reviewed by the administrator.
     *
     * @param \context_module $context Activity context.
     * @param string $fingerprint State previously displayed in the confirmation form.
     * @return int Stable identifier of the retained or newly created main journey.
     */
    public static function convert(\context_module $context, string $fingerprint): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid, 0, false, MUST_EXIST);
        $lock = new write_lock((int) $cm->instance);
        try {
            $transaction = $DB->start_delegated_transaction();
            $preview = self::preview($context);
            if (!hash_equals($preview->fingerprint, $fingerprint)) {
                throw new \moodle_exception('migrationstale', 'attendancejourneys');
            }
            if ($preview->blockers) {
                throw new \moodle_exception('migrationblocked', 'attendancejourneys');
            }
            $activity = $preview->activity;
            $journeyid = $preview->mapping->journeyid;
            if (!$journeyid) {
                $journeyid = $DB->insert_record('attendancejourneys_journeys', (object) [
                    'attendancejourneysid' => $activity->id, 'name' => $preview->mapping->name,
                    'audiencemode' => 1, 'required' => 1, 'active' => 1, 'gradeitemnumber' => 0,
                    'passinggrade' => $preview->mapping->threshold, 'timecreated' => time(), 'timemodified' => time(),
                ]);
                $DB->set_field('attendancejourneys_sessions', 'journeyid', $journeyid, ['attendancejourneysid' => $activity->id]);
            } else {
                $DB->update_record('attendancejourneys_journeys', (object) [
                    'id' => $journeyid, 'audiencemode' => $preview->mapping->audiencemode,
                    'required' => 1, 'gradeitemnumber' => 0, 'passinggrade' => $preview->mapping->threshold,
                    'timemodified' => time(),
                ]);
            }
            $activity->journeygrading = 1;
            $activity->nextgradeitem = max(1, (int) $activity->nextgradeitem);
            $activity->excusedmode = 'absent';
            $activity->completionrecorded = 0;
            $activity->completionallsessions = 0;
            $activity->completionclosed = 0;
            $activity->completionpass = (int) $cm->completion === COMPLETION_TRACKING_AUTOMATIC ? 1 : 0;
            $activity->timemodified = time();
            $DB->update_record('attendancejourneys', $activity);
            $status = attendancejourneys_grade_item_update($activity);
            if ($status !== GRADE_UPDATE_OK) {
                throw new \moodle_exception('migrationblocked', 'attendancejourneys');
            }
            \mod_attendancejourneys\event\grading_mode_changed::create([
                'objectid' => $activity->id, 'context' => $context,
                'other' => ['journeyid' => $journeyid],
            ])->trigger();
            $transaction->allow_commit();
            rebuild_course_cache((int) $cm->course, true);
            return (int) $journeyid;
        } catch (\Throwable $exception) {
            if (isset($transaction) && !$transaction->is_disposed()) {
                $transaction->rollback($exception);
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }
}
