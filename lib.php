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

/**
 * Core callbacks for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the site-wide default values and lock states supported by the activity form.
 *
 * @return array<string,array{default:mixed,locked:bool}>
 */
function attendancejourneys_get_admin_policies(): array {
    $fallbacks = [
        'experiencemode' => 'professional',
        'percentageenabled' => 1,
        'passinggrade' => 80.0,
        'gradeenabled' => 0,
        'excusedenabled' => 1,
        'excusedmode' => 'excluded',
        'studentselfrecord' => 0,
        'calendarenabled' => 0,
    ];
    $config = get_config('mod_attendancejourneys');
    $policies = [];
    foreach ($fallbacks as $field => $fallback) {
        $defaultproperty = 'default_' . $field;
        $lockproperty = 'lock_' . $field;
        $value = property_exists($config, $defaultproperty) ? $config->{$defaultproperty} : $fallback;
        if (is_int($fallback)) {
            $value = (int) $value;
        } else if (is_float($fallback)) {
            $value = (float) $value;
        }
        $policies[$field] = [
            'default' => $value,
            'locked' => !empty($config->{$lockproperty}),
        ];
    }
    if (!in_array($policies['experiencemode']['default'], ['light', 'professional'], true)) {
        $policies['experiencemode']['default'] = 'professional';
    }
    if (!in_array($policies['excusedmode']['default'], ['excluded', 'present', 'absent'], true)) {
        $policies['excusedmode']['default'] = 'excluded';
    }
    $policies['passinggrade']['default'] = max(0.0, min(
        100.0,
        (float) $policies['passinggrade']['default']
    ));
    return $policies;
}

/**
 * Enforces locked site policies before an activity record is stored.
 *
 * @param stdClass $data Submitted activity data.
 * @return stdClass
 */
function attendancejourneys_apply_admin_policies(stdClass $data): stdClass {
    foreach (attendancejourneys_get_admin_policies() as $field => $policy) {
        if ($policy['locked']) {
            $data->{$field} = $policy['default'];
        }
    }
    return $data;
}

/**
 * Read the optional site policy, including a boolean false forced in config.php.
 *
 * @return bool Whether ordinary journeys may receive individual thresholds.
 */
function attendancejourneys_journey_thresholds_allowed(): bool {
    $config = get_config('mod_attendancejourneys');
    return !property_exists($config, 'allowjourneythresholds') || !empty($config->allowjourneythresholds);
}

/**
 * Validate a proposed override without changing existing journey or final-result data.
 *
 * @param stdClass|null $journey Existing journey, or null for a new regular journey.
 * @param float|null $threshold Proposed explicit threshold, or null to inherit.
 * @return string|null Language string identifier when the proposal is prohibited.
 */
function attendancejourneys_journey_threshold_policy_error(?stdClass $journey, ?float $threshold): ?string {
    if (attendancejourneys_journey_thresholds_allowed() || !empty($journey->rootjourneyid)) {
        return null;
    }
    $existing = $journey && $journey->passinggrade !== null ? (float) $journey->passinggrade : null;
    return $existing === $threshold ? null : 'journeythresholdsitepolicy';
}

/**
 * Check a delegated decision in addition to the existing journey-management permission.
 *
 * @param context $context Activity context.
 * @param string $decision Specific decision capability suffix.
 * @return bool
 */
function attendancejourneys_can_decide(context $context, string $decision): bool {
    if (!in_array($decision, ['closejourneys', 'reopenjourneys', 'manageattempts', 'approveequivalences'], true)) {
        throw new coding_exception('Unknown Attendance Journeys decision capability');
    }
    return has_capability('mod/attendancejourneys:managejourneys', $context) &&
        has_capability('mod/attendancejourneys:' . $decision, $context);
}

/**
 * Languages for which Attendance Journeys ships a complete terminology interface.
 */
function attendancejourneys_terminology_languages(): array {
    return ['fr' => 'Français', 'en' => 'English', 'es' => 'Español'];
}

/**
 * Business concepts whose display terminology can be adapted.
 */
function attendancejourneys_terminology_concepts(): array {
    return [
        'journey' => ['singular' => 'journey', 'plural' => 'journeys'],
        'session' => ['singular' => 'session', 'plural' => 'sessions'],
        'participant' => ['singular' => 'participant', 'plural' => 'participants'],
        'room' => ['singular' => 'room', 'plural' => 'rooms'],
    ];
}

/**
 * Normalises a Moodle locale to one of the terminology languages.
 *
 * @param string $language Language code; null uses the current language.
 */
function attendancejourneys_normalise_terminology_language(string $language): string {
    $language = strtolower(trim($language));
    if (array_key_exists($language, attendancejourneys_terminology_languages())) {
        return $language;
    }
    $base = substr($language, 0, 2);
    return array_key_exists($base, attendancejourneys_terminology_languages()) ? $base : 'en';
}

/**
 * Removes multilingual form-only terminology fields from the activity record.
 *
 * @param stdClass $data Submitted form values.
 */
function attendancejourneys_extract_terminology(stdClass $data): array {
    $terms = [];
    foreach (attendancejourneys_terminology_concepts() as $concept => $canonical) {
        $locked = !empty(get_config('mod_attendancejourneys', 'lock_' . $concept . 'terminology'));
        foreach (attendancejourneys_terminology_languages() as $langcode => $label) {
            $singularfield = $concept . 'termsingular_' . $langcode;
            $pluralfield = $concept . 'termplural_' . $langcode;
            $terms[$concept][$langcode] = [
                'singular' => $locked ? '' : core_text::substr(
                    trim(clean_param((string) ($data->{$singularfield} ?? ''), PARAM_TEXT)),
                    0,
                    100
                ),
                'plural' => $locked ? '' : core_text::substr(
                    trim(clean_param((string) ($data->{$pluralfield} ?? ''), PARAM_TEXT)),
                    0,
                    100
                ),
            ];
            unset($data->{$singularfield}, $data->{$pluralfield});
        }
    }
    return $terms;
}

/**
 * Stores course-activity terminology overrides, removing empty rows.
 *
 * @param int $activityid Attendance activity identifier.
 * @param array $terms Terminology values grouped by language and concept.
 */
function attendancejourneys_save_terminology(int $activityid, array $terms): void {
    global $DB;
    foreach (attendancejourneys_terminology_concepts() as $concept => $canonical) {
        foreach (attendancejourneys_terminology_languages() as $langcode => $label) {
            $values = $terms[$concept][$langcode] ?? ['singular' => '', 'plural' => ''];
            $conditions = ['attendancejourneysid' => $activityid, 'concept' => $concept, 'langcode' => $langcode];
            $existing = $DB->get_record('attendancejourneys_terms', $conditions);
            if ($values['singular'] === '' && $values['plural'] === '') {
                if ($existing) {
                    $DB->delete_records('attendancejourneys_terms', ['id' => $existing->id]);
                }
                continue;
            }
            $record = (object) ($conditions + [
                'singular' => $values['singular'],
                'plural' => $values['plural'],
            ]);
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('attendancejourneys_terms', $record);
            } else {
                $DB->insert_record('attendancejourneys_terms', $record);
            }
        }
    }
}

/**
 * Declares supported Moodle features.
 *
 * @param string $feature Feature constant.
 * @return bool|null
 */
function attendancejourneys_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_GROUPS:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ADMINISTRATION;
        default:
            return null;
    }
}

/**
 * Supplies Moodle's cached course-module information and custom completion settings.
 *
 * @param stdClass $coursemodule Course module record.
 * @return cached_cm_info|false
 */
function attendancejourneys_get_coursemodule_info($coursemodule) {
    global $DB;

    $activity = $DB->get_record(
        'attendancejourneys',
        ['id' => $coursemodule->instance],
        'id,name,intro,introformat,journeygrading,completionrecorded,completionallsessions,completionclosed,completionpass'
    );
    if (!$activity) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $activity->name;
    $result->customdata['journeygrading'] = (int) $activity->journeygrading;
    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('attendancejourneys', $activity, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        foreach (['completionrecorded', 'completionallsessions', 'completionclosed', 'completionpass'] as $rule) {
            $result->customdata['customcompletionrules'][$rule] = $activity->$rule;
        }
    }
    return $result;
}

/**
 * Returns descriptions of active custom completion rules.
 *
 * @param cm_info|stdClass $cm Course module information.
 * @return array
 */
function mod_attendancejourneys_get_completion_active_rule_descriptions($cm): array {
    if (
        empty($cm->customdata['customcompletionrules']) ||
            $cm->completion != COMPLETION_TRACKING_AUTOMATIC
    ) {
        return [];
    }
    if (!empty($cm->customdata['journeygrading']) && array_filter($cm->customdata['customcompletionrules'])) {
        return [get_string('completiondetail:journeys', 'attendancejourneys')];
    }
    $descriptions = [];
    $strings = [
        'completionrecorded' => 'completiondetail:recorded',
        'completionallsessions' => 'completiondetail:allsessions',
        'completionclosed' => 'completiondetail:closed',
        'completionpass' => 'completiondetail:pass',
    ];
    foreach ($strings as $rule => $string) {
        if (!empty($cm->customdata['customcompletionrules'][$rule])) {
            $descriptions[] = get_string($string, 'attendancejourneys');
        }
    }
    return $descriptions;
}

/**
 * Validate percentage-dependent features in the final journey grading mode.
 *
 * @param stdClass $data Effective activity settings, including the completion tracking mode.
 * @return bool Whether percentage calculations are required but disabled.
 */
function attendancejourneys_journey_requires_percentage(stdClass $data): bool {
    return !empty($data->journeygrading) && !(int) ($data->percentageenabled ?? 1) &&
        (!empty($data->gradeenabled) || (int) ($data->completion ?? 0) === COMPLETION_TRACKING_AUTOMATIC);
}

/**
 * Creates an activity instance.
 *
 * @param stdClass $data Form data.
 * @param mod_attendancejourneys_mod_form|null $mform Form instance.
 * @return int
 */
function attendancejourneys_add_instance($data, $mform = null) {
    global $DB;

    $data = attendancejourneys_apply_admin_policies($data);
    $terms = attendancejourneys_extract_terminology($data);
    $data->journeygrading = (int) ($data->journeygrading ?? 1);
    if ($data->journeygrading) {
        $data->excusedmode = 'absent';
    }
    if (attendancejourneys_journey_requires_percentage($data)) {
        throw new moodle_exception('journeyrequirespercentage', 'attendancejourneys');
    }
    if (!empty($data->gradeenabled) && empty($data->percentageenabled)) {
        throw new moodle_exception('gradecalculationrequired', 'attendancejourneys');
    }
    if ($data->journeygrading && (int) ($data->completion ?? 0) === COMPLETION_TRACKING_AUTOMATIC) {
        $data->completionpass = 1;
    }
    $transaction = $DB->start_delegated_transaction();
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('attendancejourneys', $data);
    attendancejourneys_save_terminology((int) $data->id, $terms);
    if ($data->journeygrading) {
        $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $data->id,
            'name' => get_string('defaultjourneyname', 'attendancejourneys'),
            'audiencemode' => 1,
            'required' => 1,
            'active' => 1,
            'timecreated' => $data->timecreated,
            'timemodified' => $data->timecreated,
        ]);
    }
    attendancejourneys_grade_item_update($data);
    $transaction->allow_commit();
    return $data->id;
}

/**
 * Updates an activity instance.
 *
 * @param stdClass $data Form data.
 * @param mod_attendancejourneys_mod_form|null $mform Form instance.
 * @return bool
 */
function attendancejourneys_update_instance($data, $mform = null) {
    global $DB;

    $data = attendancejourneys_apply_admin_policies($data);
    $terms = attendancejourneys_extract_terminology($data);
    $data->id = $data->instance;
    // Ordinary settings updates must not convert historical grades or rewind the item allocator.
    // A migration must use a dedicated workflow that accounts for existing gradebook data.
    unset($data->journeygrading, $data->nextgradeitem);
    $stored = $DB->get_record('attendancejourneys', ['id' => $data->id], '*', MUST_EXIST);
    if (!empty($stored->journeygrading)) {
        $data->excusedmode = 'absent';
    }
    $effective = (object) array_merge((array) $stored, (array) $data);
    if (!isset($effective->completion)) {
        $cm = get_coursemodule_from_instance('attendancejourneys', $data->id, $stored->course, false, MUST_EXIST);
        $effective->completion = $cm->completion;
    }
    if (attendancejourneys_journey_requires_percentage($effective)) {
        throw new moodle_exception('journeyrequirespercentage', 'attendancejourneys');
    }
    if (!empty($effective->gradeenabled) && empty($effective->percentageenabled)) {
        throw new moodle_exception('gradecalculationrequired', 'attendancejourneys');
    }
    if (!empty($stored->journeygrading) && (int) $effective->completion === COMPLETION_TRACKING_AUTOMATIC) {
        $data->completionpass = 1;
    }
    $data->timemodified = time();
    $updated = $DB->update_record('attendancejourneys', $data);
    attendancejourneys_save_terminology((int) $data->id, $terms);
    attendancejourneys_grade_item_update($data);
    attendancejourneys_update_grades($data);
    attendancejourneys_refresh_events((int) $data->course, $data);
    return $updated;
}

/**
 * Deletes an activity instance and its dependent data.
 *
 * @param int $id Activity instance id.
 * @return bool
 */
function attendancejourneys_delete_instance($id) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');

    $activity = $DB->get_record('attendancejourneys', ['id' => $id]);
    if (!$activity) {
        return false;
    }
    attendancejourneys_delete_all_calendar_events((int) $id);

    $sessionids = $DB->get_fieldset_select(
        'attendancejourneys_sessions',
        'id',
        'attendancejourneysid = :attendancejourneysid',
        ['attendancejourneysid' => $id]
    );
    if ($sessionids) {
        attendancejourneys_delete_review_history(array_keys($DB->get_records_list(
            'attendancejourneys_records',
            'sessionid',
            $sessionids,
            '',
            'id'
        )));
        $DB->delete_records_list('attendancejourneys_records', 'sessionid', $sessionids);
    }
    $DB->delete_records('attendancejourneys_closures', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_members', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_attempts', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_obligationlog', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_obligations', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_waitlist', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_sessionlog', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_equivlog', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_equivalences', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_terms', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_sessions', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_rooms', ['attendancejourneysid' => $id]);
    $DB->delete_records('attendancejourneys_journeys', ['attendancejourneysid' => $id]);
    attendancejourneys_grade_item_delete($activity);
    $DB->delete_records('attendancejourneys', ['id' => $id]);
    return true;
}

/**
 * Adds Attendance Journeys to Moodle's standard course reset form.
 *
 * @param MoodleQuickForm $mform Moodle form instance.
 */
function attendancejourneys_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'attendancejourneysheader', get_string('modulenameplural', 'attendancejourneys'));
    $mform->addElement(
        'advcheckbox',
        'reset_attendancejourneys_all',
        get_string('resetcourseattendancejourneys', 'attendancejourneys')
    );
    $mform->addHelpButton('reset_attendancejourneys_all', 'resetcourseattendancejourneys', 'attendancejourneys');
}

/**
 * Leaves the destructive Attendance Journeys reset option unchecked by default.
 *
 * @param stdClass $course Course record.
 */
function attendancejourneys_reset_course_form_defaults($course): array {
    return ['reset_attendancejourneys_all' => 0];
}

/**
 * Removes Attendance Journeys operational data when Moodle resets a course.
 *
 * Activity instances and their configuration are retained. Sessions, journeys,
 * participant assignments, attendance records and final results are removed.
 *
 * @param stdClass $data Course reset options.
 */
function attendancejourneys_reset_userdata($data): array {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
    if (empty($data->reset_attendancejourneys_all)) {
        return [];
    }

    $component = get_string('modulenameplural', 'attendancejourneys');
    $activities = $DB->get_records('attendancejourneys', ['course' => $data->courseid]);
    foreach ($activities as $activity) {
        attendancejourneys_delete_all_calendar_events((int) $activity->id);
        $sessionids = $DB->get_fieldset_select(
            'attendancejourneys_sessions',
            'id',
            'attendancejourneysid = :activity',
            ['activity' => $activity->id]
        );
        $transaction = $DB->start_delegated_transaction();
        if ($sessionids) {
            attendancejourneys_delete_review_history(array_keys($DB->get_records_list(
                'attendancejourneys_records',
                'sessionid',
                $sessionids,
                '',
                'id'
            )));
            $DB->delete_records_list('attendancejourneys_records', 'sessionid', $sessionids);
        }
        $DB->delete_records('attendancejourneys_closures', ['attendancejourneysid' => $activity->id]);
        $DB->delete_records('attendancejourneys_members', ['attendancejourneysid' => $activity->id]);
        $DB->delete_records('attendancejourneys_attempts', ['attendancejourneysid' => $activity->id]);
        $DB->delete_records('attendancejourneys_obligationlog', ['attendancejourneysid' => $activity->id]);
        $DB->delete_records('attendancejourneys_obligations', ['attendancejourneysid' => $activity->id]);
        $DB->delete_records('attendancejourneys_waitlist', ['attendancejourneysid' => $activity->id]);
        $DB->delete_records('attendancejourneys_sessionlog', ['attendancejourneysid' => $activity->id]);
        $DB->delete_records('attendancejourneys_equivlog', ['attendancejourneysid' => $activity->id]);
        $DB->delete_records('attendancejourneys_equivalences', ['attendancejourneysid' => $activity->id]);
        $DB->delete_records('attendancejourneys_sessions', ['attendancejourneysid' => $activity->id]);
        $DB->delete_records('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id]);
        $transaction->allow_commit();

        $cm = get_coursemodule_from_instance(
            'attendancejourneys',
            $activity->id,
            $data->courseid,
            false,
            IGNORE_MISSING
        );
        if ($cm) {
            attendancejourneys_update_grades($activity);
            $context = context_module::instance($cm->id);
            $userids = array_keys(get_enrolled_users(
                $context,
                'mod/attendancejourneys:canbelisted',
                0,
                'u.id',
                null,
                0,
                0,
                false
            ));
            attendancejourneys_update_completion(get_course($data->courseid), $cm, $userids);
        }
    }

    return [[
        'component' => $component,
        'item' => get_string('resetcourseattendancejourneys', 'attendancejourneys'),
        'error' => false,
    ]];
}

/**
 * Rebuilds Attendance Journeys session events in Moodle's calendar.
 *
 * @param int $courseid Course identifier; zero permits all courses.
 * @param int|null $instance Optional activity instance filter.
 * @param stdClass|cm_info $cm Course module record or cached course module information.
 */
function attendancejourneys_refresh_events($courseid = 0, $instance = null, $cm = null): bool {
    global $DB;
    require_once(__DIR__ . '/locallib.php');
    $conditions = $courseid ? ['course' => $courseid] : [];
    if ($instance !== null) {
        $instanceid = is_object($instance) ? (int) $instance->id : (int) $instance;
        $conditions['id'] = $instanceid;
    }
    foreach ($DB->get_records('attendancejourneys', $conditions) as $activity) {
        if (empty($activity->calendarenabled)) {
            attendancejourneys_delete_all_calendar_events((int) $activity->id);
            continue;
        }
        $sessions = $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $activity->id]);
        $validtypes = [];
        foreach ($sessions as $session) {
            attendancejourneys_update_calendar_event($activity, $session);
            $validtypes[] = 'session-' . $session->id;
        }
        $events = $DB->get_records('event', [
            'modulename' => 'attendancejourneys', 'instance' => $activity->id,
        ]);
        foreach ($events as $event) {
            if (strpos($event->eventtype, 'session-') === 0 && !in_array($event->eventtype, $validtypes, true)) {
                attendancejourneys_delete_calendar_event(
                    (int) $activity->id,
                    (int) substr($event->eventtype, strlen('session-'))
                );
            }
        }
    }
    return true;
}

/**
 * Creates or updates the Moodle grade item.
 *
 * @param stdClass $attendancejourneys Activity instance.
 * @param array|null $grades Optional grade records.
 * @return int Grade update status.
 */
function attendancejourneys_grade_item_update($attendancejourneys, $grades = null) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $stored = $DB->get_record('attendancejourneys', ['id' => $attendancejourneys->id], '*', MUST_EXIST);
    if (!empty($stored->journeygrading)) {
        if ($grades !== null) {
            throw new coding_exception('Separate journey grades must be synchronised with an explicit journey scope.');
        }
        $status = GRADE_UPDATE_OK;
        foreach (\mod_attendancejourneys\local\journey_grades::items($stored) as $journey) {
            $result = \mod_attendancejourneys\local\journey_grades::update_item($stored, $journey);
            if ($result !== GRADE_UPDATE_OK) {
                $status = $result;
            }
        }
        return $status;
    }

    $enabled = !empty($attendancejourneys->percentageenabled) && !empty($attendancejourneys->gradeenabled);
    $item = [
        'itemname' => $attendancejourneys->name,
        'gradetype' => $enabled ? GRADE_TYPE_VALUE : GRADE_TYPE_NONE,
        'grademin' => 0,
        'grademax' => 100,
        'gradepass' => (float) $attendancejourneys->passinggrade,
    ];
    $result = grade_update(
        'mod/attendancejourneys',
        $attendancejourneys->course,
        'mod',
        'attendancejourneys',
        $attendancejourneys->id,
        0,
        $grades,
        $item
    );
    if ($enabled && $result === GRADE_UPDATE_OK) {
        $gradeitem = grade_item::fetch([
            'courseid' => $attendancejourneys->course,
            'itemtype' => 'mod',
            'itemmodule' => 'attendancejourneys',
            'iteminstance' => $attendancejourneys->id,
            'itemnumber' => 0,
        ]);
        if ($gradeitem && grade_floats_different($gradeitem->gradepass, (float) $attendancejourneys->passinggrade)) {
            $gradeitem->gradepass = (float) $attendancejourneys->passinggrade;
            $gradeitem->update('mod/attendancejourneys');
        }
    }
    return $result;
}

/**
 * Synchronises calculated attendance percentages with Moodle grades.
 *
 * @param stdClass $attendancejourneys Activity instance.
 * @param int $userid Zero for all active participants.
 * @return void
 */
function attendancejourneys_update_grades($attendancejourneys, int $userid = 0): void {
    global $DB;
    require_once(__DIR__ . '/locallib.php');

    $stored = $DB->get_record('attendancejourneys', ['id' => $attendancejourneys->id], '*', MUST_EXIST);
    if (!empty($stored->journeygrading)) {
        \mod_attendancejourneys\local\journey_grades::sync($stored, $userid);
        return;
    }
    if (empty($attendancejourneys->percentageenabled) || empty($attendancejourneys->gradeenabled)) {
        attendancejourneys_grade_item_update($attendancejourneys);
        return;
    }
    $cm = get_coursemodule_from_instance(
        'attendancejourneys',
        $attendancejourneys->id,
        $attendancejourneys->course,
        false,
        MUST_EXIST
    );
    $context = context_module::instance($cm->id);
    if ($userid) {
        $userids = [$userid];
    } else {
        $userids = array_keys(get_enrolled_users(
            $context,
            'mod/attendancejourneys:canbelisted',
            0,
            'u.id',
            null,
            0,
            0,
            true
        ));
    }
    $grades = [];
    foreach ($userids as $participantid) {
        $activejourney = attendancejourneys_get_user_active_journey(
            $context,
            (int) $attendancejourneys->id,
            (int) $participantid
        );
        $closure = $activejourney ? attendancejourneys_get_active_closure(
            (int) $attendancejourneys->id,
            (int) $participantid,
            (int) $activejourney->id
        ) :
            attendancejourneys_get_active_closure((int) $attendancejourneys->id, (int) $participantid);
        $grades[(int) $participantid] = (object) [
            'userid' => (int) $participantid,
            'rawgrade' => $closure ? $closure->percentage : null,
        ];
    }
    attendancejourneys_grade_item_update($attendancejourneys, $grades);
}

/**
 * Removes the Moodle grade item for an activity.
 *
 * @param stdClass $attendancejourneys Activity instance.
 * @return int Grade update status.
 */
function attendancejourneys_grade_item_delete($attendancejourneys) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $items = grade_item::fetch_all([
        'courseid' => $attendancejourneys->course, 'itemtype' => 'mod', 'itemmodule' => 'attendancejourneys',
        'iteminstance' => $attendancejourneys->id,
    ]);
    $status = GRADE_UPDATE_OK;
    foreach ($items ?: [] as $item) {
        $result = grade_update(
            'mod/attendancejourneys',
            $attendancejourneys->course,
            'mod',
            'attendancejourneys',
            $attendancejourneys->id,
            $item->itemnumber,
            null,
            ['deleted' => 1]
        );
        if ($result !== GRADE_UPDATE_OK) {
            $status = $result;
        }
    }
    return $status;
}
