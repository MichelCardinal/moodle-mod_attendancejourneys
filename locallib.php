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
 * Local helpers for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/lib.php');

/**
 * Returns the effective, display-only terminology for an attendance journey.
 *
 * Internal table names, identifiers, capabilities and APIs deliberately keep the
 * stable "journey" vocabulary. Only user-facing text is adapted.
 *
 * @param string $concept Terminology concept to resolve.
 * @param stdClass|null $activity Activity instance, when already available.
 * @param string|null $language Moodle language code; defaults to the current interface language.
 * @return array{singular:string,plural:string}
 */
function attendancejourneys_get_terminology(
    string $concept,
    ?stdClass $activity = null,
    ?string $language = null
): array {
    global $attendancejourneys, $DB, $PAGE;

    $concepts = attendancejourneys_terminology_concepts();
    if (!isset($concepts[$concept])) {
        throw new coding_exception('Unknown Attendance Journeys terminology concept: ' . $concept);
    }
    $langcode = attendancejourneys_normalise_terminology_language($language ?? current_language());
    $stringmanager = get_string_manager();
    $canonical = [
        'singular' => $stringmanager->get_string($concepts[$concept]['singular'], 'attendancejourneys', null, $langcode),
        'plural' => $stringmanager->get_string($concepts[$concept]['plural'], 'attendancejourneys', null, $langcode),
    ];
    if (
        $activity === null && isset($attendancejourneys) && is_object($attendancejourneys) &&
            !empty($attendancejourneys->id)
    ) {
        $activity = $attendancejourneys;
    }
    if (
        $activity === null && isset($GLOBALS['activity']) && is_object($GLOBALS['activity']) &&
            !empty($GLOBALS['activity']->id)
    ) {
        $activity = $GLOBALS['activity'];
    }
    if ($activity === null && isset($PAGE->cm) && $PAGE->cm && $PAGE->cm->modname === 'attendancejourneys') {
        $activity = $DB->get_record('attendancejourneys', ['id' => $PAGE->cm->instance], 'id', IGNORE_MISSING) ?: null;
    }
    $config = get_config('mod_attendancejourneys');
    $locked = !empty($config->{'lock_' . $concept . 'terminology'});
    $activityterms = ['singular' => '', 'plural' => ''];
    if (!$locked && $activity && !empty($activity->id)) {
        if (!isset($activity->_attendancejourneys_terminology)) {
            $activity->_attendancejourneys_terminology = [];
            foreach ($DB->get_records('attendancejourneys_terms', ['attendancejourneysid' => $activity->id]) as $term) {
                $activity->_attendancejourneys_terminology[$term->concept][$term->langcode] = [
                    'singular' => trim((string) $term->singular),
                    'plural' => trim((string) $term->plural),
                ];
            }
        }
        $activityterms = $activity->_attendancejourneys_terminology[$concept][$langcode] ?? $activityterms;
    }
    $result = [];
    foreach (['singular', 'plural'] as $number) {
        $defaultfield = 'default_' . $concept . 'term' . $number . '_' . $langcode;
        $sitevalue = core_text::substr(
            trim(clean_param((string) ($config->{$defaultfield} ?? ''), PARAM_TEXT)),
            0,
            100
        );
        $activityvalue = core_text::substr(
            trim(clean_param((string) ($activityterms[$number] ?? ''), PARAM_TEXT)),
            0,
            100
        );
        $result[$number] = (!$locked && $activityvalue !== '') ? $activityvalue
            : ($sitevalue !== '' ? $sitevalue : $canonical[$number]);
    }
    return $result;
}

/**
 * Backward-compatible accessor for journey terminology.
 *
 * @param stdClass|null $activity Attendance activity and its configuration.
 * @param string|null $language Language code; null uses the current language.
 */
function attendancejourneys_get_journey_terms(?stdClass $activity = null, ?string $language = null): array {
    return attendancejourneys_get_terminology('journey', $activity, $language);
}

/**
 * Preserves the basic capitalization of a translated source term.
 *
 * @param string $replacement Replacement term.
 * @param string $source Original text or term.
 */
function attendancejourneys_match_terminology_case(string $replacement, string $source): string {
    if (core_text::strtoupper($source) === $source) {
        return core_text::strtoupper($replacement);
    }
    if (core_text::strtolower($source) === $source) {
        return core_text::strtolower($replacement);
    }
    if (core_text::strtoupper(core_text::substr($source, 0, 1)) === core_text::substr($source, 0, 1)) {
        return core_text::strtoupper(core_text::substr($replacement, 0, 1)) . core_text::substr($replacement, 1);
    }
    return $replacement;
}

/**
 * Replaces one complete translated term without altering identifiers or larger words.
 *
 * @param string $text Text to process.
 * @param string $source Original text or term.
 * @param string $replacement Replacement term.
 */
function attendancejourneys_replace_terminology(string $text, string $source, string $replacement): string {
    if ($source === '' || $source === $replacement) {
        return $text;
    }
    $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($source, '/') . '(?![\p{L}\p{N}_])/iu';
    return preg_replace_callback($pattern, static function (array $match) use ($replacement): string {
        return attendancejourneys_match_terminology_case($replacement, $match[0]);
    }, $text) ?? $text;
}

/**
 * Replaces a French term whose canonical singular and plural are identical.
 *
 * French "parcours" cannot convey number by itself. Nearby determiners are
 * therefore used for singular occurrences, while unmarked occurrences retain
 * the plural-first behaviour used by headings and lists. Common contractions
 * are also adjusted when the replacement begins with a vowel or a silent h.
 *
 * @param string $text Text to process.
 * @param string $source Original text or term.
 * @param string $singular Singular display term.
 * @param string $plural Plural display term.
 */
function attendancejourneys_replace_ambiguous_french_terminology(
    string $text,
    string $source,
    string $singular,
    string $plural
): string {
    $tokens = [];
    $sourcepattern = preg_quote($source, '/');
    $pattern = '/(?<![\p{L}\p{N}_])'
        . '(d[’\']un|d[’\']une|un|une|le|la|du|ce|cet|cette|chaque|mon|ton|son|notre|votre|leur|au)'
        . '\s+' . $sourcepattern . '(?![\p{L}\p{N}_])/iu';
    $startsvowel = preg_match('/^[aeiouyàâäéèêëîïôöùûüÿh]/iu', trim($singular)) === 1;
    $protected = preg_replace_callback(
        $pattern,
        static function (array $match) use (&$tokens, $singular, $startsvowel): string {
            $prefix = $match[1];
            $normalised = core_text::strtolower(str_replace("'", '’', $prefix));
            $replacement = attendancejourneys_match_terminology_case($singular, $match[0]);
            $joiner = $prefix . ' ';
            if ($startsvowel) {
                $joiner = match ($normalised) {
                    'le', 'la' => 'l’',
                    'du' => 'de l’',
                    'au' => 'à l’',
                    'ce' => 'cet ',
                    default => $prefix . ' ',
                };
                if (
                    core_text::strtoupper(core_text::substr($prefix, 0, 1)) ===
                        core_text::substr($prefix, 0, 1)
                ) {
                    $joiner = core_text::strtoupper(core_text::substr($joiner, 0, 1))
                        . core_text::substr($joiner, 1);
                }
            }
            $token = 'ATTENDANCEPLUSPROTECTEDSINGULAR' . count($tokens) . 'TOKEN';
            $tokens[$token] = $joiner . core_text::strtolower($replacement);
            return $token;
        },
        $text
    ) ?? $text;
    $protected = attendancejourneys_replace_terminology($protected, $source, $plural);
    return strtr($protected, $tokens);
}

/**
 * Moodle-compatible get_string wrapper that applies configured journey labels.
 *
 * @param string $identifier Language-string identifier.
 * @param string $component Language component.
 * @param mixed $a Interpolation data.
 * @param stdClass|null $activity Activity instance.
 */
function attendancejourneys_get_string(
    string $identifier,
    string $component = '',
    $a = null,
    ?stdClass $activity = null
): string {
    $text = get_string($identifier, $component, $a);
    if ($component !== 'attendancejourneys') {
        return $text;
    }
    return attendancejourneys_apply_terminology($text, $activity);
}

/**
 * Applies all configured business terms to already formatted plugin content.
 *
 * @param string $text Text to process.
 * @param stdClass|null $activity Attendance activity and its configuration.
 */
function attendancejourneys_apply_terminology(string $text, ?stdClass $activity = null): string {
    foreach (attendancejourneys_terminology_concepts() as $concept => $canonicalids) {
        $canonical = [
            'singular' => get_string($canonicalids['singular'], 'attendancejourneys'),
            'plural' => get_string($canonicalids['plural'], 'attendancejourneys'),
        ];
        $terms = attendancejourneys_get_terminology($concept, $activity);
        if (
            attendancejourneys_normalise_terminology_language(current_language()) === 'fr' &&
                core_text::strtolower($canonical['singular']) === core_text::strtolower($canonical['plural'])
        ) {
            $text = attendancejourneys_replace_ambiguous_french_terminology(
                $text,
                $canonical['singular'],
                $terms['singular'],
                $terms['plural']
            );
            continue;
        }
        // Replace the plural first in languages where one form contains the other.
        $text = attendancejourneys_replace_terminology($text, $canonical['plural'], $terms['plural']);
        $text = attendancejourneys_replace_terminology($text, $canonical['singular'], $terms['singular']);
    }
    return $text;
}

/**
 * Applies business terminology to rendered HTML without changing technical URLs.
 *
 * Link and image paths can contain canonical terms such as "journey" or "session".
 * Those paths must remain stable even when an institution customises the visible
 * vocabulary. Other user-facing attributes, including alternative text, remain
 * eligible for terminology replacement.
 *
 * @param string $html HTML to process without altering links.
 * @param stdClass|null $activity Attendance activity and its configuration.
 */
function attendancejourneys_apply_terminology_to_html(string $html, ?stdClass $activity = null): string {
    $attributes = [];
    $protected = preg_replace_callback(
        '/\b(?:src|href)\s*=\s*(["\'])(.*?)\1/isu',
        static function (array $match) use (&$attributes): string {
            $token = 'ATTENDANCEPLUSPROTECTEDURL' . count($attributes) . 'TOKEN';
            $attributes[$token] = $match[0];
            return $token;
        },
        $html
    ) ?? $html;

    $protected = attendancejourneys_apply_terminology($protected, $activity);
    return strtr($protected, $attributes);
}

/**
 * Advances the optimistic-lock version of an attendance session.
 *
 * The value always increases, including when two writes occur during the same second.
 *
 * @param int $sessionid Attendance session identifier.
 */
function attendancejourneys_touch_session(int $sessionid): int {
    global $DB;
    $current = (int) $DB->get_field('attendancejourneys_sessions', 'timemodified', ['id' => $sessionid], MUST_EXIST);
    $version = max(time(), $current + 1);
    $DB->set_field('attendancejourneys_sessions', 'timemodified', $version, ['id' => $sessionid]);
    return $version;
}

/**
 * Whether submitted attendance meaningfully differs from the stored record.
 *
 * @param stdClass $existing Existing records used for comparison.
 * @param stdClass $submitted Submitted record values.
 */
function attendancejourneys_record_values_changed(stdClass $existing, stdClass $submitted): bool {
    return $existing->status !== $submitted->status ||
        (int) $existing->minutesabsent !== (int) $submitted->minutesabsent ||
        trim((string) $existing->remarks) !== trim((string) $submitted->remarks);
}

/**
 * Whether a participant may create or edit their own declaration.
 *
 * @param stdClass|null $record Attendance record.
 * @param int $userid User identifier.
 */
function attendancejourneys_self_record_is_editable(?stdClass $record, int $userid): bool {
    if ($record === null) {
        return true;
    }
    return empty($record->approved) && (int) $record->takenby === $userid;
}

/**
 * Records one immutable declaration-review action.
 *
 * @param int $recordid Attendance record identifier.
 * @param int $sessionid Attendance session identifier.
 * @param int $userid User identifier.
 * @param string $action Audit action identifier.
 * @param int $actorid Identifier of the user performing the action.
 * @param string $note Explanation recorded with the action.
 */
function attendancejourneys_add_review_history(
    int $recordid,
    int $sessionid,
    int $userid,
    string $action,
    int $actorid,
    string $note = ''
): int {
    global $DB;
    $allowed = ['recorded', 'updated', 'approved', 'approvalwithdrawn', 'changesrequested', 'resubmitted'];
    if (!in_array($action, $allowed, true)) {
        throw new coding_exception('Invalid attendance review action.');
    }
    return (int) $DB->insert_record('attendancejourneys_reviews', (object) [
        'recordid' => $recordid,
        'sessionid' => $sessionid,
        'userid' => $userid,
        'action' => $action,
        'actorid' => $actorid,
        'note' => trim($note),
        'timecreated' => time(),
    ]);
}

/**
 * Produces a stable, language-neutral snapshot for the audit log.
 *
 * @param stdClass $record Attendance record.
 */
function attendancejourneys_record_audit_snapshot(stdClass $record): string {
    return 'status=' . $record->status . '; minutesabsent=' . (int) $record->minutesabsent .
        (trim((string) ($record->remarks ?? '')) !== '' ? '; remarks=' . trim($record->remarks) : '');
}

/**
 * Presents a stored audit snapshot in the current language without altering history.
 *
 * Unknown formats and staff-authored correction notes are preserved verbatim.
 * The result is plain text and must be escaped by HTML callers.
 *
 * @param stdClass $review Review action with its immutable note.
 * @return string Localised plain-text details.
 */
function attendancejourneys_format_review_note(stdClass $review): string {
    $note = (string) $review->note;
    if (!in_array($review->action, ['recorded', 'updated'], true)) {
        return $note;
    }
    $pattern = '/\Astatus=(present|absent|partial|excused|unrecorded); minutesabsent=(\d+)(?:; remarks=(.*))?\z/s';
    if (!preg_match($pattern, $note, $parts)) {
        return $note;
    }
    $details = attendancejourneys_get_string('reviewrecorddetails', 'attendancejourneys', (object) [
        'status' => attendancejourneys_get_string('status' . $parts[1], 'attendancejourneys'),
        'minutesabsent' => format_float((int) $parts[2], 0),
    ]);
    if (isset($parts[3]) && $parts[3] !== '') {
        $details .= "\n" . attendancejourneys_get_string('reviewremarkdetails', 'attendancejourneys', $parts[3]);
    }
    return $details;
}

/**
 * Deletes review history before deleting its parent attendance records.
 *
 * @param array $recordids Attendance record identifiers.
 */
function attendancejourneys_delete_review_history(array $recordids): void {
    global $DB;
    $recordids = array_values(array_unique(array_filter(array_map('intval', $recordids))));
    if ($recordids) {
        $DB->delete_records_list('attendancejourneys_reviews', 'recordid', $recordids);
    }
}

/**
 * Creates or updates the Moodle calendar event for one attendance session.
 *
 * @param stdClass $attendancejourneys Attendance activity and its configuration.
 * @param stdClass $session Attendance session.
 */
function attendancejourneys_update_calendar_event(stdClass $attendancejourneys, stdClass $session): void {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/calendar/lib.php');
    if (!empty($session->cancelled) || empty($attendancejourneys->calendarenabled)) {
        attendancejourneys_delete_calendar_event((int) $attendancejourneys->id, (int) $session->id);
        return;
    }
    $conditions = [
        'modulename' => 'attendancejourneys', 'instance' => $attendancejourneys->id,
        'eventtype' => 'session-' . $session->id,
    ];
    $event = $DB->get_record('event', $conditions) ?: new stdClass();
    $event->name = format_string($session->name);
    $details = [];
    if (!empty($session->description)) {
        $details[] = format_text($session->description, FORMAT_PLAIN);
    }
    $details[] = html_writer::tag('strong', attendancejourneys_get_string('sessionmodality', 'attendancejourneys')) . ': ' .
        attendancejourneys_get_string('modality' . ($session->modality ?? 'unspecified'), 'attendancejourneys');
    if (!empty($session->location)) {
        $details[] = html_writer::tag('strong', attendancejourneys_get_string('sessionlocation', 'attendancejourneys')) . ': ' .
            s($session->location);
    }
    if (!empty($session->meetingurl)) {
        $details[] = html_writer::link(
            $session->meetingurl,
            attendancejourneys_get_string('joinonlinesession', 'attendancejourneys')
        );
    }
    $event->description = implode(html_writer::empty_tag('br'), $details);
    $event->format = FORMAT_HTML;
    $event->courseid = $attendancejourneys->course;
    $event->groupid = (int) $session->groupid;
    $event->userid = 0;
    $event->modulename = 'attendancejourneys';
    $event->instance = $attendancejourneys->id;
    $event->eventtype = 'session-' . $session->id;
    $event->timestart = (int) $session->sessiondate;
    $event->timeduration = (int) $session->duration * MINSECS;
    $event->visible = 1;
    $event->customdata = ['sessionid' => (int) $session->id, 'journeyid' => (int) $session->journeyid];
    calendar_event::create($event, false);
}

/**
 * Returns the translated modality label for a session or journey default.
 *
 * @param string|null $modality Session delivery modality.
 */
function attendancejourneys_modality_label(?string $modality): string {
    $allowed = ['unspecified', 'inperson', 'online', 'hybrid'];
    $modality = in_array($modality, $allowed, true) ? $modality : 'unspecified';
    return attendancejourneys_get_string('modality' . $modality, 'attendancejourneys');
}

/**
 * Renders concise modality, location and meeting-link information for one session.
 *
 * @param stdClass $session Attendance session.
 * @param bool $includelink Whether to include the virtual meeting link.
 */
function attendancejourneys_session_delivery_details(stdClass $session, bool $includelink = true): string {
    $modality = $session->modality ?? 'unspecified';
    $classes = [
        'unspecified' => 'badge badge-light text-bg-light',
        'inperson' => 'badge badge-secondary text-bg-secondary',
        'online' => 'badge badge-info text-bg-info',
        'hybrid' => 'badge badge-primary text-bg-primary',
    ];
    $parts = [html_writer::span(
        attendancejourneys_modality_label($modality),
        $classes[$modality] ?? $classes['unspecified']
    )];
    if (!empty($session->location)) {
        $parts[] = html_writer::span(s($session->location), 'text-muted small');
    }
    if ($includelink && !empty($session->meetingurl)) {
        $parts[] = html_writer::link(
            $session->meetingurl,
            attendancejourneys_get_string('joinonlinesession', 'attendancejourneys'),
            [
            'class' => 'small', 'target' => '_blank', 'rel' => 'noopener noreferrer',
            ]
        );
    }
    return implode(' · ', $parts);
}

/**
 * Deletes the Moodle calendar event associated with one attendance session.
 *
 * @param int $attendancejourneysid Attendance activity identifier.
 * @param int $sessionid Attendance session identifier.
 */
function attendancejourneys_delete_calendar_event(int $attendancejourneysid, int $sessionid): void {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/calendar/lib.php');
    $events = $DB->get_records('event', [
        'modulename' => 'attendancejourneys', 'instance' => $attendancejourneysid,
        'eventtype' => 'session-' . $sessionid,
    ]);
    foreach ($events as $event) {
        calendar_event::load($event)->delete(false);
    }
}

/**
 * Deletes every Moodle calendar event belonging to one Attendance Journeys activity.
 *
 * @param int $attendancejourneysid Attendance activity identifier.
 */
function attendancejourneys_delete_all_calendar_events(int $attendancejourneysid): void {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/calendar/lib.php');
    $events = $DB->get_records('event', [
        'modulename' => 'attendancejourneys',
        'instance' => $attendancejourneysid,
    ]);
    foreach ($events as $event) {
        calendar_event::load($event)->delete(false);
    }
}

/**
 * Renders the activity-level navigation using Moodle tabs.
 *
 * @param cm_info|stdClass $cm Course module.
 * @param context_module $context Module context.
 * @param string $active Active tab identifier.
 */
function attendancejourneys_print_navigation($cm, context_module $context, string $active): void {
    global $DB, $OUTPUT;

    $islight = $DB->get_field('attendancejourneys', 'experiencemode', ['id' => $cm->instance]) === 'light';

    $tabs = [
        new tabobject(
            'home',
            new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]),
            attendancejourneys_get_string('navigationhome', 'attendancejourneys')
        ),
    ];

    if (has_capability('mod/attendancejourneys:managesessions', $context)) {
        $tabs[] = new tabobject(
            'sessions',
            new moodle_url('/mod/attendancejourneys/sessions.php', ['id' => $cm->id]),
            attendancejourneys_get_string('navigationsessions', 'attendancejourneys')
        );
        if (!$islight) {
            $tabs[] = new tabobject(
                'rooms',
                new moodle_url('/mod/attendancejourneys/rooms.php', ['id' => $cm->id]),
                attendancejourneys_get_string('navigationrooms', 'attendancejourneys')
            );
        }
    }
    $coursecontext = context_course::instance($cm->course);
    if (!$islight && has_capability('moodle/course:managegroups', $coursecontext)) {
        $tabs[] = new tabobject(
            'groups',
            new moodle_url('/mod/attendancejourneys/groups.php', ['id' => $cm->id]),
            attendancejourneys_get_string('navigationgroups', 'attendancejourneys')
        );
    }
    if (!$islight && has_capability('mod/attendancejourneys:managejourneys', $context)) {
        $tabs[] = new tabobject(
            'journeys',
            new moodle_url('/mod/attendancejourneys/journeys.php', ['id' => $cm->id]),
            attendancejourneys_get_string('navigationjourneys', 'attendancejourneys')
        );
    }
    if (has_capability('mod/attendancejourneys:takeattendance', $context)) {
        $tabs[] = new tabobject(
            'attendance',
            new moodle_url('/mod/attendancejourneys/attendance.php', ['id' => $cm->id]),
            attendancejourneys_get_string('navigationattendance', 'attendancejourneys')
        );
    }
    if (has_capability('mod/attendancejourneys:viewreports', $context)) {
        $tabs[] = new tabobject(
            'reports',
            new moodle_url('/mod/attendancejourneys/report.php', ['id' => $cm->id]),
            attendancejourneys_get_string('navigationreports', 'attendancejourneys')
        );
    }
    if (!$islight && has_capability('mod/attendancejourneys:resetuserdata', $context)) {
        $tabs[] = new tabobject(
            'administration',
            new moodle_url('/mod/attendancejourneys/admin.php', ['id' => $cm->id]),
            attendancejourneys_get_string('navigationadministration', 'attendancejourneys')
        );
    }
    if (
        !$DB->get_field('attendancejourneys', 'journeygrading', ['id' => $cm->instance]) &&
            has_capability('moodle/course:manageactivities', $context) &&
            has_capability('mod/attendancejourneys:managejourneys', $context) &&
            (groups_get_activity_groupmode($cm) != SEPARATEGROUPS || has_capability('moodle/site:accessallgroups', $context))
    ) {
        $tabs[] = new tabobject(
            'migration',
            new moodle_url('/mod/attendancejourneys/migration.php', ['id' => $cm->id]),
            get_string('migrationtitle', 'attendancejourneys')
        );
    }
    if (attendancejourneys_documentation_enabled()) {
        $tabs[] = new tabobject(
            'documentation',
            new moodle_url('/mod/attendancejourneys/documentation.php', ['id' => $cm->id]),
            attendancejourneys_get_string('navigationdocumentation', 'attendancejourneys')
        );
    }

    echo html_writer::div($OUTPUT->tabtree($tabs, $active), 'attendancejourneys-navigation');
}

/**
 * Whether the institution exposes the integrated help centre.
 */
function attendancejourneys_documentation_enabled(): bool {
    $configured = get_config('mod_attendancejourneys', 'documentationenabled');
    return $configured === false ? true : !empty($configured);
}

/**
 * Whether this activity presents the simplified Light workspace.
 *
 * @param stdClass $attendancejourneys Attendance activity and its configuration.
 */
function attendancejourneys_is_light_mode(stdClass $attendancejourneys): bool {
    return ($attendancejourneys->experiencemode ?? 'professional') === 'light';
}

/**
 * Prevents advanced workspaces from being used while the activity is in Light mode.
 *
 * @param stdClass $attendancejourneys Attendance activity and its configuration.
 * @param int $cmid Course module identifier.
 */
function attendancejourneys_require_professional_mode(stdClass $attendancejourneys, int $cmid): void {
    if (attendancejourneys_is_light_mode($attendancejourneys)) {
        redirect(
            new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cmid]),
            attendancejourneys_get_string('professionalfeaturehidden', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
}

/**
 * Renders the two read-only/exceptional administration sections.
 *
 * @param stdClass|cm_info $cm Course module record or cached course module information.
 * @param string $active Navigation item to mark as active.
 */
function attendancejourneys_print_admin_navigation($cm, string $active): void {
    global $OUTPUT;
    $tabs = [
        new tabobject(
            'participants',
            new moodle_url('/mod/attendancejourneys/admin.php', ['id' => $cm->id]),
            attendancejourneys_get_string('adminparticipants', 'attendancejourneys')
        ),
        new tabobject(
            'audit',
            new moodle_url('/mod/attendancejourneys/audit.php', ['id' => $cm->id]),
            attendancejourneys_get_string('auditlog', 'attendancejourneys')
        ),
    ];
    echo html_writer::div($OUTPUT->tabtree($tabs, $active), 'attendancejourneys-admin-navigation mb-3');
}

/**
 * Builds the immutable review-history query shared by the screen and exports.
 *
 * @param int $activityid Attendance activity identifier.
 * @param array $filters Filters for the activity audit query.
 */
function attendancejourneys_audit_query(int $activityid, array $filters): array {
    global $DB;
    $where = ['aps.attendancejourneysid = :activityid'];
    $params = ['activityid' => $activityid];
    if (!empty($filters['userid'])) {
        $where[] = 'arv.userid = :audituserid';
        $params['audituserid'] = (int) $filters['userid'];
    }
    if (!empty($filters['sessionid'])) {
        $where[] = 'arv.sessionid = :auditsessionid';
        $params['auditsessionid'] = (int) $filters['sessionid'];
    }
    if (!empty($filters['action'])) {
        $where[] = 'arv.action = :auditaction';
        $params['auditaction'] = $filters['action'];
    }
    if (isset($filters['groupid']) && (int) $filters['groupid'] >= 0) {
        $where[] = 'aps.groupid = :auditgroupid';
        $params['auditgroupid'] = (int) $filters['groupid'];
    }
    if (!empty($filters['from'])) {
        $where[] = 'arv.timecreated >= :auditfrom';
        $params['auditfrom'] = (int) $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[] = 'arv.timecreated <= :auditto';
        $params['auditto'] = (int) $filters['to'];
    }
    $sql = " FROM {attendancejourneys_reviews} arv
              JOIN {attendancejourneys_sessions} aps ON aps.id = arv.sessionid
              JOIN {user} participant ON participant.id = arv.userid
         LEFT JOIN {user} actor ON actor.id = arv.actorid
             WHERE " . implode(' AND ', $where);
    return [$sql, $params];
}

/**
 * Recalculates Moodle activity completion for selected participants.
 *
 * @param stdClass $course Course record.
 * @param stdClass|cm_info $cm Course module.
 * @param int[] $userids Participant IDs.
 */
function attendancejourneys_update_completion($course, $cm, array $userids): void {
    require_once(__DIR__ . '/../../lib/completionlib.php');

    $completion = new completion_info($course);
    if (!$completion->is_enabled($cm)) {
        return;
    }
    foreach (array_unique(array_map('intval', $userids)) as $userid) {
        if ($userid > 0) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }
}

/**
 * Renders a Moodle table inside a keyboard-focusable horizontal scroll region.
 *
 * @param html_table $table Table to render in the responsive wrapper.
 * @param string $label Accessible table label.
 */
function attendancejourneys_responsive_table(html_table $table, string $label = ''): string {
    $table = clone $table;
    if (property_exists($table, 'responsive')) {
        // The labelled keyboard-focusable wrapper must own the horizontal scrolling.
        $table->responsive = false;
    }
    $attributes = ['class' => 'attendancejourneys-table-scroll', 'tabindex' => '0'];
    if ($label !== '') {
        $attributes['role'] = 'region';
        $attributes['aria-label'] = $label;
    }
    return html_writer::div(html_writer::table($table), '', $attributes);
}

/**
 * Whether a journey uses an explicit participant list, even when that list is empty.
 *
 * @param stdClass $journey Journey record, or an object containing its id.
 * @return bool True for explicit membership; legacy records retain their previous interpretation.
 */
function attendancejourneys_journey_has_fixed_audience(stdClass $journey): bool {
    global $DB;
    if (empty($journey->id)) {
        return false;
    }
    $mode = isset($journey->audiencemode) ? (int) $journey->audiencemode :
        (int) $DB->get_field('attendancejourneys_journeys', 'audiencemode', ['id' => $journey->id], MUST_EXIST);
    if ($mode === 1) {
        return false;
    }
    if ($mode === 2) {
        return true;
    }
    return $DB->record_exists('attendancejourneys_members', ['journeyid' => $journey->id]);
}

/**
 * Returns the currently active participants targeted by a journey.
 *
 * @param context_module $context Module context.
 * @param stdClass $journey Journey record or object containing groupid.
 * @param string $fields User fields.
 * @return array Users keyed by ID.
 */
function attendancejourneys_get_journey_participants(
    context_module $context,
    stdClass $journey,
    string $fields = 'u.id'
): array {
    global $DB;
    if (attendancejourneys_journey_has_fixed_audience($journey)) {
        $sql = "SELECT $fields
                  FROM {user} u
                  JOIN {attendancejourneys_members} m ON m.userid = u.id
                 WHERE m.journeyid = :journeyid
              ORDER BY u.lastname, u.firstname";
        return $DB->get_records_sql($sql, ['journeyid' => $journey->id]);
    }
    return get_enrolled_users(
        $context,
        'mod/attendancejourneys:canbelisted',
        (int) ($journey->groupid ?? 0),
        $fields,
        'u.lastname ASC, u.firstname ASC',
        0,
        0,
        true
    );
}

/**
 * Freezes the current journey audience and allocates an attempt number for every participant.
 *
 * @param context_module $context Activity context.
 * @param stdClass $journey Journey record.
 */
function attendancejourneys_assign_journey_members(context_module $context, stdClass $journey): array {
    $participants = get_enrolled_users(
        $context,
        'mod/attendancejourneys:canbelisted',
        (int) $journey->groupid,
        'u.id',
        null,
        0,
        0,
        true
    );
    foreach (array_keys($participants) as $userid) {
        attendancejourneys_assign_journey_member($context, $journey, (int) $userid, false);
    }
    return array_keys($participants);
}

/**
 * Assigns one actively enrolled participant to a journey and returns the membership ID.
 *
 * @param context_module $context Activity context.
 * @param stdClass $journey Journey record.
 * @param int $userid User identifier.
 * @param bool $checkconflict Whether to reject conflicting journey membership.
 */
function attendancejourneys_assign_journey_member(
    context_module $context,
    stdClass $journey,
    int $userid,
    bool $checkconflict = true
): int {
    global $DB;
    $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid, 0, false, MUST_EXIST);
    $journey = $DB->get_record('attendancejourneys_journeys', [
        'id' => $journey->id, 'attendancejourneysid' => $cm->instance,
    ], '*', MUST_EXIST);
    if (empty($journey->active)) {
        throw new coding_exception('A participant cannot be assigned to a closed journey.');
    }
    if ((int) $journey->audiencemode === 1 && !empty($journey->groupid) && !groups_is_member((int) $journey->groupid, $userid)) {
        throw new coding_exception('An automatic journey follows its enrolled group audience.');
    }
    $multiple = (bool) $DB->get_field('attendancejourneys', 'journeygrading', ['id' => $cm->instance], MUST_EXIST);
    if ($DB->record_exists('attendancejourneys_members', ['journeyid' => $journey->id, 'userid' => $userid])) {
        return 0;
    }
    if (!is_enrolled($context, $userid, 'mod/attendancejourneys:canbelisted', true)) {
        throw new coding_exception('Only an actively enrolled participant can be assigned to a journey.');
    }
    if ($checkconflict && !$multiple) {
        $activejourney = attendancejourneys_get_user_active_journey($context, (int) $journey->attendancejourneysid, $userid);
        if ($activejourney && (int) $activejourney->id !== (int) $journey->id) {
            throw new coding_exception('The participant already belongs to another active journey.');
        }
    }
    $attempt = 1 + (int) $DB->get_field_sql(
        'SELECT COALESCE(MAX(attemptnumber), 0) FROM {attendancejourneys_members} WHERE attendancejourneysid = ? AND userid = ?',
        [$journey->attendancejourneysid, $userid]
    );
    return (int) $DB->insert_record('attendancejourneys_members', (object) [
        'attendancejourneysid' => $journey->attendancejourneysid, 'journeyid' => $journey->id,
        'userid' => $userid, 'attemptnumber' => $attempt, 'active' => 1,
        'timeassigned' => time(), 'timeended' => 0,
    ]);
}

/**
 * Whether a journey audience can still be adjusted without rewriting attendance history.
 *
 * @param stdClass $journey Journey record.
 */
function attendancejourneys_journey_audience_is_editable(stdClass $journey): bool {
    global $DB;
    if (empty($journey->active)) {
        return false;
    }
    if ((int) $DB->get_field('attendancejourneys_journeys', 'audiencemode', ['id' => $journey->id], MUST_EXIST) === 1) {
        return false;
    }
    $sql = "SELECT 1
              FROM {attendancejourneys_records} r
              JOIN {attendancejourneys_sessions} s ON s.id = r.sessionid
             WHERE s.journeyid = :journeyid";
    return !$DB->record_exists_sql($sql, ['journeyid' => $journey->id]) &&
        !$DB->record_exists('attendancejourneys_closures', ['journeyid' => $journey->id]);
}

/**
 * Returns active enrolled users who may safely be added to a journey.
 *
 * @param context_module $context Activity context.
 * @param stdClass $journey Journey record.
 */
function attendancejourneys_get_addable_journey_participants(context_module $context, stdClass $journey): array {
    global $DB;
    $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid, 0, false, MUST_EXIST);
    if ((int) $journey->attendancejourneysid !== (int) $cm->instance) {
        throw new coding_exception('The journey does not belong to this activity.');
    }
    if ((int) $DB->get_field('attendancejourneys_journeys', 'audiencemode', ['id' => $journey->id], MUST_EXIST) === 1) {
        // Every eligible enrolled participant already belongs to this automatic audience.
        return [];
    }
    $multiple = (bool) $DB->get_field('attendancejourneys', 'journeygrading', ['id' => $cm->instance], MUST_EXIST);
    $users = get_enrolled_users(
        $context,
        'mod/attendancejourneys:canbelisted',
        0,
        'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename',
        'u.lastname ASC, u.firstname ASC',
        0,
        0,
        true
    );
    if (!attendancejourneys_session_is_visible($cm, $context, $journey)) {
        return [];
    }
    $visible = attendancejourneys_get_visible_participants($context, $cm, 'u.id');
    $users = array_intersect_key($users, $visible);
    foreach (array_keys($users) as $userid) {
        if (
            $DB->record_exists('attendancejourneys_members', ['journeyid' => $journey->id, 'userid' => $userid]) ||
                (!$multiple && attendancejourneys_get_user_active_journey(
                    $context,
                    (int) $journey->attendancejourneysid,
                    (int) $userid
                ))
        ) {
            unset($users[$userid]);
        }
    }
    return $users;
}

/**
 * Finds participants who would belong to another active journey.
 *
 * @return array Conflicting users keyed by user ID, each with journey names.
 *
 * @param context_module $context Activity context.
 * @param int $attendancejourneysid Attendance activity identifier.
 * @param int $groupid Moodle group identifier; zero denotes no group restriction.
 * @param int $excludejourneyid Journey to exclude from conflict detection.
 */
function attendancejourneys_find_journey_conflicts(
    context_module $context,
    int $attendancejourneysid,
    int $groupid,
    int $excludejourneyid = 0
): array {
    global $DB;
    $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid, 0, false, MUST_EXIST);
    $candidateids = array_keys(attendancejourneys_get_journey_participants($context, (object) ['groupid' => $groupid]));
    if (!$candidateids) {
        return [];
    }
    $conflicts = [];
    $journeys = $DB->get_records(
        'attendancejourneys_journeys',
        ['attendancejourneysid' => $attendancejourneysid, 'active' => 1]
    );
    foreach ($journeys as $journey) {
        if ((int) $journey->id === $excludejourneyid || !attendancejourneys_session_is_visible($cm, $context, $journey)) {
            continue;
        }
        if (attendancejourneys_journey_has_fixed_audience($journey)) {
            $otherids = array_map('intval', $DB->get_fieldset_select(
                'attendancejourneys_members',
                'userid',
                'journeyid = :journeyid AND active = 1',
                ['journeyid' => $journey->id]
            ));
        } else {
            $otherids = array_keys(attendancejourneys_get_journey_participants($context, $journey));
        }
        foreach (array_intersect($candidateids, $otherids) as $userid) {
            $conflicts[$userid][] = format_string($journey->name);
        }
    }
    return $conflicts;
}

/**
 * Returns active journeys targeting an enrolled participant, indexed by journey ID.
 *
 * @param context_module $context Activity context.
 * @param int $attendancejourneysid Attendance activity identifier.
 * @param int $userid User identifier.
 */
function attendancejourneys_get_user_active_journeys(
    context_module $context,
    int $attendancejourneysid,
    int $userid
): array {
    global $DB;
    if (!is_enrolled($context, $userid, 'mod/attendancejourneys:canbelisted', true)) {
        return [];
    }
    $sql = "SELECT j.*
              FROM {attendancejourneys_journeys} j
              JOIN {attendancejourneys_members} m ON m.journeyid = j.id
             WHERE j.attendancejourneysid = :activity AND j.active = 1 AND m.userid = :userid AND m.active = 1
          ORDER BY m.timeassigned DESC, m.id DESC";
    $assigned = $DB->get_records_sql($sql, ['activity' => $attendancejourneysid, 'userid' => $userid]);
    $legacyfallback = !$assigned && !$DB->record_exists(
        'attendancejourneys_members',
        ['attendancejourneysid' => $attendancejourneysid]
    );
    $journeys = $assigned;
    foreach (
        $DB->get_records(
            'attendancejourneys_journeys',
            ['attendancejourneysid' => $attendancejourneysid, 'active' => 1]
        ) as $journey
    ) {
        $mode = (int) $journey->audiencemode;
        if ($mode === 2 || ($mode === 0 && !$legacyfallback)) {
            continue;
        }
        if (empty($journey->groupid) || groups_is_member((int) $journey->groupid, $userid)) {
            $journeys[$journey->id] = $journey;
        }
    }
    return $journeys;
}

/**
 * List the activity journeys visible under the current reader's group permissions.
 *
 * @param context_module $context Module context already authorised by the controller.
 * @param int $attendancejourneysid Activity identifier.
 * @return array Journeys indexed by identifier.
 */
function attendancejourneys_visible_journeys(context_module $context, int $attendancejourneysid): array {
    global $DB;
    $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid, 0, false, MUST_EXIST);
    if ((int) $cm->instance !== $attendancejourneysid) {
        return [];
    }
    return array_filter(
        $DB->get_records('attendancejourneys_journeys', ['attendancejourneysid' => $attendancejourneysid], 'name, id'),
        static fn($journey) => attendancejourneys_session_is_visible($cm, $context, $journey)
    );
}

/**
 * Enforce group boundaries before displaying or changing a journey.
 *
 * Changes to an entire course-wide journey require access to all groups in separate-group mode.
 *
 * @param stdClass $cm Course module.
 * @param context_module $context Module context.
 * @param stdClass $journey Journey belonging to this module.
 * @param bool $wholejourney Whether the action affects the entire journey.
 */
function attendancejourneys_require_journey_access(
    stdClass $cm,
    context_module $context,
    stdClass $journey,
    bool $wholejourney = false
): void {
    global $DB;
    if ((int) $context->instanceid !== (int) $cm->id || (int) $journey->attendancejourneysid !== (int) $cm->instance) {
        throw new coding_exception('Journey access requires the matching activity context.');
    }
    if (!empty($journey->groupid) && !$DB->record_exists('groups', ['id' => $journey->groupid, 'courseid' => $cm->course])) {
        throw new coding_exception('A journey group must belong to its activity course.');
    }
    $restricted = groups_get_activity_groupmode($cm) == SEPARATEGROUPS &&
        !has_capability('moodle/site:accessallgroups', $context);
    if (
        !attendancejourneys_session_is_visible($cm, $context, $journey) ||
            ($restricted && $wholejourney && empty($journey->groupid))
    ) {
        throw new required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
    }
    if ($restricted && $wholejourney && !empty($journey->id)) {
        $visible = attendancejourneys_get_visible_participants($context, $cm);
        foreach (attendancejourneys_get_journey_participants($context, $journey) as $participant) {
            if (!isset($visible[$participant->id])) {
                throw new required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
            }
        }
        $historical = $DB->get_fieldset_select('attendancejourneys_members', 'userid', 'journeyid = :journey', [
            'journey' => $journey->id,
        ]);
        $historical = array_merge($historical, $DB->get_fieldset_select(
            'attendancejourneys_closures',
            'userid',
            'journeyid = :journey',
            ['journey' => $journey->id]
        ));
        foreach ($historical as $userid) {
            if (!isset($visible[$userid])) {
                throw new required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
            }
        }
    }
}

/**
 * Return the participant's report journeys visible to the current reader.
 *
 * @param context_module $context Activity context.
 * @param int $attendancejourneysid Activity identifier.
 * @param int $userid Participant whose access is checked by the calling controller.
 * @return array Journeys indexed by identifier, including historical assignments.
 */
function attendancejourneys_get_user_report_journeys(context_module $context, int $attendancejourneysid, int $userid): array {
    global $DB, $USER;
    $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid, 0, false, MUST_EXIST);
    if (
        (int) $cm->instance !== $attendancejourneysid ||
            ((int) $USER->id !== $userid && !has_capability('mod/attendancejourneys:viewreports', $context))
    ) {
        return [];
    }
    $journeys = $DB->get_records('attendancejourneys_journeys', ['attendancejourneysid' => $attendancejourneysid], 'name, id');
    foreach ($journeys as $id => $journey) {
        $assigned = $DB->record_exists('attendancejourneys_members', ['journeyid' => $id, 'userid' => $userid]) ||
            $DB->record_exists('attendancejourneys_closures', ['attendancejourneysid' => $attendancejourneysid,
                'journeyid' => $id, 'userid' => $userid]);
        if (!$assigned && !attendancejourneys_journey_has_fixed_audience($journey)) {
            $assigned = !empty($journey->active) && is_enrolled($context, $userid, 'mod/attendancejourneys:canbelisted', true) &&
                (empty($journey->groupid) || groups_is_member((int) $journey->groupid, $userid));
        }
        if (!$assigned || !attendancejourneys_session_is_visible($cm, $context, $journey)) {
            unset($journeys[$id]);
        }
    }
    return $journeys;
}

/**
 * List other active journey names visible to the current staff member.
 *
 * @param context_module $context Activity context.
 * @param int $attendancejourneysid Activity identifier.
 * @param int $userid Participant identifier already authorised by the calling page.
 * @param int $excludejourneyid Journey currently being edited.
 * @return array Visible formatted names; this function does not confer participant access.
 */
function attendancejourneys_other_journey_names(
    context_module $context,
    int $attendancejourneysid,
    int $userid,
    int $excludejourneyid
): array {
    if (!has_capability('mod/attendancejourneys:managejourneys', $context)) {
        return [];
    }
    $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid, 0, false, MUST_EXIST);
    if ((int) $cm->instance !== $attendancejourneysid) {
        return [];
    }
    $names = [];
    foreach (attendancejourneys_get_user_active_journeys($context, $attendancejourneysid, $userid) as $journey) {
        if ((int) $journey->id !== $excludejourneyid && attendancejourneys_session_is_visible($cm, $context, $journey)) {
            $names[] = format_string($journey->name);
        }
    }
    return $names;
}

/**
 * Returns the first active journey using the legacy single-journey selection order.
 *
 * Callers handling simultaneous journeys must use the plural function and explicit scope.
 *
 * @param context_module $context Activity context.
 * @param int $attendancejourneysid Attendance activity identifier.
 * @param int $userid User identifier.
 * @return stdClass|null
 */
function attendancejourneys_get_user_active_journey(
    context_module $context,
    int $attendancejourneysid,
    int $userid
): ?stdClass {
    global $DB;
    $journeys = attendancejourneys_get_user_active_journeys($context, $attendancejourneysid, $userid);
    if (count($journeys) > 1 && $DB->get_field('attendancejourneys', 'journeygrading', ['id' => $attendancejourneysid])) {
        throw new moodle_exception('journeyselectionrequired', 'attendancejourneys');
    }
    return $journeys ? reset($journeys) : null;
}

/**
 * Returns deletion counts and whether a journey can be safely removed.
 *
 * @param int $attendancejourneysid Attendance activity identifier.
 * @param int $journeyid Journey identifier; zero denotes no specific journey.
 */
function attendancejourneys_journey_deletion_info(int $attendancejourneysid, int $journeyid): stdClass {
    global $DB;
    if (
        !$DB->record_exists('attendancejourneys_journeys', [
            'id' => $journeyid, 'attendancejourneysid' => $attendancejourneysid,
        ])
    ) {
        throw new coding_exception('The journey does not belong to this Attendance Journeys activity.');
    }
    $sessionids = array_keys($DB->get_records('attendancejourneys_sessions', [
        'attendancejourneysid' => $attendancejourneysid, 'journeyid' => $journeyid,
    ], '', 'id'));
    $records = 0;
    if ($sessionids) {
        [$insql, $params] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'journeysession');
        $records = $DB->count_records_select('attendancejourneys_records', "sessionid $insql", $params);
    }
    $closures = $DB->count_records('attendancejourneys_closures', [
        'attendancejourneysid' => $attendancejourneysid, 'journeyid' => $journeyid,
    ]);
    $equivalences = $DB->count_records_select(
        'attendancejourneys_equivalences',
        'attendancejourneysid = :activityid AND (targetjourneyid = :targetjourney
         OR sourcesessionid IN (SELECT id FROM {attendancejourneys_sessions} WHERE journeyid = :sourcejourney)
         OR targetsessionid IN (SELECT id FROM {attendancejourneys_sessions} WHERE journeyid = :sessionjourney))',
        ['activityid' => $attendancejourneysid, 'targetjourney' => $journeyid,
        'sourcejourney' => $journeyid,
        'sessionjourney' => $journeyid]
    );
    $cancellationhistory = $DB->record_exists_select(
        'attendancejourneys_sessions',
        'journeyid = :journey AND (cancelled = 1 OR id IN (SELECT sessionid FROM {attendancejourneys_sessionlog}))',
        ['journey' => $journeyid]
    );
    return (object) [
        'hascancellationhistory' => $cancellationhistory,
        'cancellations' => $DB->count_records_select(
            'attendancejourneys_sessionlog',
            'sessionid IN (SELECT id FROM {attendancejourneys_sessions} WHERE journeyid = :journey)',
            ['journey' => $journeyid]
        ),
        'equivalences' => $equivalences,
        'sessions' => count($sessionids),
        'records' => $records,
        'closures' => $closures,
        'candelete' => $records === 0 && $closures === 0 && $equivalences === 0 && !$cancellationhistory &&
            !$DB->record_exists('attendancejourneys_obligationlog', ['journeyid' => $journeyid]) &&
            !$DB->record_exists('attendancejourneys_journeys', ['rootjourneyid' => $journeyid]) &&
            !$DB->record_exists_select(
                'attendancejourneys_attempts',
                'rootjourneyid = :root OR journeyid = :retake',
                ['root' => $journeyid, 'retake' => $journeyid]
            ),
        'sessionids' => $sessionids,
    ];
}

/**
 * Deletes a journey and its record-free sessions.
 *
 * @param int $attendancejourneysid Attendance activity identifier.
 * @param int $journeyid Journey identifier; zero denotes no specific journey.
 */
function attendancejourneys_delete_journey(int $attendancejourneysid, int $journeyid): int {
    global $DB;
    $info = attendancejourneys_journey_deletion_info($attendancejourneysid, $journeyid);
    if (!$info->candelete) {
        throw new coding_exception('A journey containing attendance, final results or equivalences cannot be deleted.');
    }
    $activity = $DB->get_record('attendancejourneys', ['id' => $attendancejourneysid], '*', MUST_EXIST);
    $journey = $DB->get_record('attendancejourneys_journeys', [
        'id' => $journeyid, 'attendancejourneysid' => $attendancejourneysid,
    ], '*', MUST_EXIST);
    $transaction = $DB->start_delegated_transaction();
    try {
        if (!empty($activity->journeygrading) && $journey->gradeitemnumber !== null) {
            \mod_attendancejourneys\local\journey_grades::delete_empty_item($activity, $journey);
        }
        $DB->delete_records('attendancejourneys_members', ['journeyid' => $journeyid]);
        $DB->delete_records('attendancejourneys_waitlist', ['journeyid' => $journeyid]);
        $DB->delete_records_select(
            'attendancejourneys_equivlog',
            'equivalenceid IN (SELECT id FROM {attendancejourneys_equivalences} WHERE targetjourneyid = :journeyid)',
            ['journeyid' => $journeyid]
        );
        $DB->delete_records('attendancejourneys_equivalences', ['targetjourneyid' => $journeyid]);
        if ($info->sessionids) {
            [$targetsql, $targetparams] = $DB->get_in_or_equal($info->sessionids, SQL_PARAMS_NAMED, 'equivtarget');
            [$sourcesql, $sourceparams] = $DB->get_in_or_equal($info->sessionids, SQL_PARAMS_NAMED, 'equivsource');
            $DB->delete_records_select(
                'attendancejourneys_equivlog',
                "equivalenceid IN (SELECT id FROM {attendancejourneys_equivalences}
                 WHERE targetsessionid $targetsql OR sourcesessionid $sourcesql)",
                $targetparams + $sourceparams
            );
            $DB->delete_records_select(
                'attendancejourneys_equivalences',
                "targetsessionid $targetsql OR sourcesessionid $sourcesql",
                $targetparams + $sourceparams
            );
            foreach ($info->sessionids as $sessionid) {
                attendancejourneys_delete_calendar_event($attendancejourneysid, (int) $sessionid);
            }
            $DB->delete_records_list('attendancejourneys_sessions', 'id', $info->sessionids);
        }
        $DB->delete_records('attendancejourneys_journeys', [
            'id' => $journeyid, 'attendancejourneysid' => $attendancejourneysid,
        ]);
        $transaction->allow_commit();
    } catch (\Throwable $exception) {
        $transaction->rollback($exception);
    }
    return $info->sessions;
}

/**
 * Checks whether deleting a session would invalidate a frozen final result.
 *
 * Journey sessions are protected by closures for that journey. Independent
 * sessions are protected by independent closures (journey ID 0).
 *
 * @param stdClass $session Attendance session.
 */
function attendancejourneys_session_has_final_results(stdClass $session): bool {
    global $DB;

    return $DB->record_exists('attendancejourneys_closures', [
        'attendancejourneysid' => (int) $session->attendancejourneysid,
        'journeyid' => (int) $session->journeyid,
        'active' => 1,
    ]);
}

/**
 * Require access to the entire session audience before a management action.
 *
 * @param stdClass $cm Course module.
 * @param context_module $context Matching activity context.
 * @param stdClass $session Attendance session.
 * @return void
 */
function attendancejourneys_require_session_management_access(
    stdClass $cm,
    context_module $context,
    stdClass $session
): void {
    global $DB;
    if ((int) $context->instanceid !== (int) $cm->id || (int) $session->attendancejourneysid !== (int) $cm->instance) {
        throw new coding_exception('Session management requires the matching activity context.');
    }
    require_capability('mod/attendancejourneys:managesessions', $context);
    if (!empty($session->groupid) && !$DB->record_exists('groups', ['id' => $session->groupid, 'courseid' => $cm->course])) {
        throw new coding_exception('A session group must belong to its activity course.');
    }
    if (!empty($session->journeyid)) {
        $journey = $DB->get_record('attendancejourneys_journeys', [
            'id' => $session->journeyid, 'attendancejourneysid' => $cm->instance,
        ], '*', MUST_EXIST);
        attendancejourneys_require_journey_access($cm, $context, $journey);
    }
    $restricted = groups_get_activity_groupmode($cm) == SEPARATEGROUPS &&
        !has_capability('moodle/site:accessallgroups', $context);
    if (!attendancejourneys_session_is_visible($cm, $context, $session) || ($restricted && empty($session->groupid))) {
        throw new required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
    }
    if ($restricted) {
        $visible = attendancejourneys_get_visible_participants($context, $cm, 'u.id');
        $affected = $DB->get_fieldset_select(
            'attendancejourneys_records',
            'userid',
            'sessionid = :session',
            ['session' => (int) ($session->id ?? 0)]
        );
        if (!empty($session->journeyid) && attendancejourneys_journey_has_fixed_audience($journey)) {
            $affected = array_merge($affected, $DB->get_fieldset_select(
                'attendancejourneys_members',
                'userid',
                'journeyid = :journey',
                ['journey' => $journey->id]
            ));
        }
        foreach ($affected as $userid) {
            if (!isset($visible[$userid])) {
                throw new required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
            }
        }
    }
}

/**
 * Require access to a proposed audience and protect scopes with frozen results.
 *
 * Existing attendance is checked separately on the source session. A new session
 * has no historical attendance of its own, but its fixed journey audience must
 * still be visible. Adding or moving sessions requires reopening that scope first.
 *
 * @param stdClass $cm Course module.
 * @param context_module $context Matching activity context.
 * @param stdClass $session Proposed session audience.
 * @return void
 */
function attendancejourneys_require_session_destination_access(
    stdClass $cm,
    context_module $context,
    stdClass $session
): void {
    global $DB;
    $destination = clone $session;
    $destination->id = 0;
    attendancejourneys_require_session_management_access($cm, $context, $destination);
    if (empty($destination->journeyid) && $DB->get_field('attendancejourneys', 'journeygrading', ['id' => $cm->instance])) {
        throw new moodle_exception('sessionjourneyrequired', 'attendancejourneys');
    }
    if (
        attendancejourneys_session_has_final_results($destination) ||
            (!empty($destination->journeyid) && !$DB->get_field('attendancejourneys_journeys', 'active', [
                'id' => $destination->journeyid, 'attendancejourneysid' => $cm->instance,
            ]))
    ) {
        throw new moodle_exception('sessiondestinationblockedfinal', 'attendancejourneys');
    }
}

/**
 * Return the reason deletion would destroy a final-result or equivalence reference.
 *
 * Inactive closures still form part of the history after reopening. Editing an open
 * journey remains possible; removing its session identifiers would lose that history.
 * All equivalence states are retained, including pending, rejected and revoked decisions.
 *
 * @param stdClass $session Attendance session.
 * @return string|null Language key for a blocking message, otherwise null.
 */
function attendancejourneys_session_deletion_blocker(stdClass $session): ?string {
    global $DB;
    if (!empty($session->cancelled) || $DB->record_exists('attendancejourneys_sessionlog', ['sessionid' => $session->id])) {
        return 'sessiondeleteblockedcancellation';
    }
    if (
        $DB->record_exists('attendancejourneys_closures', [
        'attendancejourneysid' => (int) $session->attendancejourneysid, 'journeyid' => (int) $session->journeyid,
        ])
    ) {
        return 'sessiondeleteblockedhistory';
    }
    if (
        $DB->record_exists_select(
            'attendancejourneys_equivalences',
            'targetsessionid = :targetsession OR sourcesessionid = :sourcesession',
            ['targetsession' => $session->id, 'sourcesession' => $session->id]
        )
    ) {
        return 'sessiondeleteblockedequivalence';
    }
    return null;
}

/**
 * Returns whether a session already contains at least one attendance record.
 *
 * @param int $sessionid Attendance session identifier.
 */
function attendancejourneys_session_has_records(int $sessionid): bool {
    global $DB;

    return $DB->record_exists('attendancejourneys_records', ['sessionid' => $sessionid]);
}

/**
 * Clears one session's attendance and audit history unless a final result protects it.
 *
 * @param stdClass $session Attendance session.
 */
function attendancejourneys_clear_session_records(stdClass $session): array {
    global $DB;
    if (!empty($session->cancelled)) {
        throw new moodle_exception('sessioncancelledrecordingunavailable', 'attendancejourneys');
    }
    if (attendancejourneys_session_has_final_results($session)) {
        throw new coding_exception('Attendance contributing to an active final result cannot be cleared.');
    }
    $records = $DB->get_records('attendancejourneys_records', ['sessionid' => $session->id], '', 'id,userid');
    if (!$records) {
        return [];
    }
    $participantids = array_values(array_unique(array_map(
        static fn($record): int => (int) $record->userid,
        $records
    )));
    $transaction = $DB->start_delegated_transaction();
    attendancejourneys_delete_review_history(array_keys($records));
    $DB->delete_records('attendancejourneys_records', ['sessionid' => $session->id]);
    attendancejourneys_touch_session((int) $session->id);
    $transaction->allow_commit();
    return $participantids;
}

/**
 * Returns whether fields affecting attendance calculations or eligibility changed.
 *
 * @param stdClass $existing Existing records used for comparison.
 * @param stdClass $updated Proposed record values.
 */
function attendancejourneys_session_structure_changed(stdClass $existing, stdClass $updated): bool {
    foreach (['sessiondate', 'duration', 'groupid', 'journeyid'] as $field) {
        if ((int) $existing->{$field} !== (int) $updated->{$field}) {
            return true;
        }
    }
    return false;
}

/**
 * Returns whether the participant scope of a session changed.
 *
 * @param stdClass $existing Existing records used for comparison.
 * @param stdClass $updated Proposed record values.
 */
function attendancejourneys_session_audience_changed(stdClass $existing, stdClass $updated): bool {
    return (int) $existing->groupid !== (int) $updated->groupid ||
        (int) $existing->journeyid !== (int) $updated->journeyid;
}

/**
 * Resolve a journey threshold without accepting a journey from another activity.
 *
 * @param stdClass $activity Activity containing the journey.
 * @param int $journeyid Explicit journey, or zero for the activity rule.
 * @return float Required attendance percentage.
 */
function attendancejourneys_get_passinggrade(stdClass $activity, int $journeyid = 0): float {
    global $DB;
    if (!$journeyid) {
        return (float) $activity->passinggrade;
    }
    $journey = \mod_attendancejourneys\local\attempt_family::root($activity, $journeyid);
    return $journey->passinggrade === null ? (float) $activity->passinggrade : (float) $journey->passinggrade;
}

/**
 * Calculates one participant's attendance across every applicable session.
 *
 * @param stdClass $attendancejourneys Activity instance.
 * @param int $userid Participant ID.
 * @return stdClass Calculation details.
 *
 * @param int $journeyid Journey identifier; zero denotes no specific journey.
 */
function attendancejourneys_calculate_user_attendance(stdClass $attendancejourneys, int $userid, int $journeyid = 0): stdClass {
    if ($journeyid === 0) {
        $cm = get_coursemodule_from_instance(
            'attendancejourneys',
            $attendancejourneys->id,
            $attendancejourneys->course ?? 0,
            false,
            IGNORE_MISSING
        );
        if ($cm) {
            $activejourney = attendancejourneys_get_user_active_journey(
                context_module::instance($cm->id),
                (int) $attendancejourneys->id,
                $userid
            );
            $journeyid = $activejourney ? (int) $activejourney->id : 0;
        }
    }
    return \mod_attendancejourneys\local\calculator::calculate($attendancejourneys, $userid, $journeyid);
}

/**
 * Returns the participant's current closed result, if any.
 *
 * @param int $attendancejourneysid Activity ID.
 * @param int $userid Participant ID.
 * @return stdClass|null
 *
 * @param int|null $journeyid Journey identifier; zero denotes no specific journey.
 */
function attendancejourneys_get_active_closure(int $attendancejourneysid, int $userid, ?int $journeyid = null): ?stdClass {
    global $DB;
    $conditions = ['attendancejourneysid' => $attendancejourneysid, 'userid' => $userid, 'active' => 1];
    if ($journeyid !== null) {
        $conditions['journeyid'] = $journeyid;
    }
    $records = $DB->get_records('attendancejourneys_closures', $conditions, 'timeclosed DESC, id DESC', '*', 0, 1);
    return $records ? reset($records) : null;
}

/**
 * Returns the first reason why an attendance calculation cannot be finalised.
 *
 * @param stdClass $calculation Complete calculation for the participant's required sessions.
 * @return array|null Language key and interpolation value, or null when ready.
 */
function attendancejourneys_closure_blocker(stdClass $calculation): ?array {
    if (!empty($calculation->waived)) {
        return ['closureobligationwaived', null];
    }
    if (!empty($calculation->pendingapprovals)) {
        return ['closurependingapprovals', $calculation->pendingapprovals];
    }
    if (!empty($calculation->pendingequivalences)) {
        return ['closurependingequivalences', null];
    }
    if (empty($calculation->applicablesessions)) {
        return ['closureemptysessions', null];
    }
    if (!empty($calculation->futuresessions)) {
        return ['closurefuturesessions', $calculation->futuresessions];
    }
    $missing = (int) $calculation->applicablesessions - (int) $calculation->recordedsessions;
    if ($missing > 0) {
        return ['closuremissingsessions', $missing];
    }
    return null;
}

/**
 * Closes one participant's attendance journey and freezes the current result.
 *
 * @param stdClass $attendancejourneys Activity instance.
 * @param int $userid Participant ID.
 * @param int $closedby User performing the action.
 * @return stdClass Closure snapshot.
 *
 * @param int $journeyid Journey identifier; zero denotes no specific journey.
 */
function attendancejourneys_close_user_journey(
    stdClass $attendancejourneys,
    int $userid,
    int $closedby,
    int $journeyid = 0
): stdClass {
    global $DB;
    if ($journeyid === 0) {
        $cm = get_coursemodule_from_instance(
            'attendancejourneys',
            $attendancejourneys->id,
            $attendancejourneys->course ?? 0,
            false,
            IGNORE_MISSING
        );
        if ($cm) {
            $activejourney = attendancejourneys_get_user_active_journey(
                context_module::instance($cm->id),
                (int) $attendancejourneys->id,
                $userid
            );
            $journeyid = $activejourney ? (int) $activejourney->id : 0;
        }
    }
    $existingclosure = $journeyid > 0 ?
        attendancejourneys_get_active_closure((int) $attendancejourneys->id, $userid, $journeyid) :
        attendancejourneys_get_active_closure((int) $attendancejourneys->id, $userid);
    if ($existingclosure) {
        throw new coding_exception('The attendance journey is already closed.');
    }
    $calculation = attendancejourneys_calculate_user_attendance($attendancejourneys, $userid, $journeyid);
    $blocker = attendancejourneys_closure_blocker($calculation);
    if ($blocker) {
        throw new moodle_exception($blocker[0], 'attendancejourneys', '', $blocker[1]);
    }
    if (!empty($attendancejourneys->journeygrading) && $journeyid) {
        $thresholds = $DB->get_records('attendancejourneys_closures', [
            'attendancejourneysid' => $attendancejourneys->id, 'journeyid' => $journeyid, 'active' => 1,
        ], '', 'id, threshold');
        foreach ($thresholds as $previous) {
            if ((float) $previous->threshold !== $calculation->threshold) {
                throw new moodle_exception('journeythresholdlocked', 'attendancejourneys');
            }
        }
        // Freeze an inherited threshold when the first final result establishes the grade item policy.
        $DB->set_field('attendancejourneys_journeys', 'passinggrade', $calculation->threshold, [
            'id' => $journeyid, 'attendancejourneysid' => $attendancejourneys->id,
        ]);
    }
    $record = (object) [
        'attendancejourneysid' => $attendancejourneys->id,
        'journeyid' => $journeyid,
        'userid' => $userid,
        'active' => 1,
        'applicablesessions' => $calculation->applicablesessions,
        'recordedsessions' => $calculation->recordedsessions,
        'presentminutes' => $calculation->presentminutes,
        'possibleminutes' => $calculation->possibleminutes,
        'percentage' => $calculation->percent,
        'threshold' => $calculation->threshold,
        'result' => $calculation->percent === null || empty($attendancejourneys->percentageenabled) ? 'none' :
            ($calculation->percent >= $calculation->threshold ? 'passed' : 'failed'),
        'closedby' => $closedby,
        'timeclosed' => time(),
        'reopenedby' => 0,
        'timereopened' => 0,
    ];
    $record->id = $DB->insert_record('attendancejourneys_closures', $record);
    if ($journeyid > 0) {
        $DB->set_field('attendancejourneys_members', 'active', 0, [
            'journeyid' => $journeyid, 'userid' => $userid,
        ]);
        $DB->set_field('attendancejourneys_members', 'timeended', time(), [
            'journeyid' => $journeyid, 'userid' => $userid,
        ]);
    }
    $cm = get_coursemodule_from_instance(
        'attendancejourneys',
        $attendancejourneys->id,
        $attendancejourneys->course ?? 0,
        false,
        IGNORE_MISSING
    );
    if ($cm) {
        $event = \mod_attendancejourneys\event\journey_result_closed::create([
            'objectid' => $record->id, 'context' => context_module::instance($cm->id),
            'userid' => $closedby, 'relateduserid' => $userid,
            'other' => ['attendancejourneysid' => (int) $attendancejourneys->id, 'journeyid' => $journeyid,
                'percentage' => $record->percentage],
        ]);
        $event->add_record_snapshot('attendancejourneys_closures', $record);
        $event->trigger();
    }
    return $record;
}

/**
 * Completes a journey and freezes an individual result for every current participant.
 *
 * @param stdClass $attendancejourneys Attendance activity and its configuration.
 * @param stdClass $journey Journey record.
 * @param context_module $context Activity context.
 * @param int $completedby Identifier of the user completing the journey.
 */
function attendancejourneys_complete_journey(
    stdClass $attendancejourneys,
    stdClass $journey,
    context_module $context,
    int $completedby
): array {
    global $DB;
    if (empty($journey->active)) {
        return [];
    }
    $participants = attendancejourneys_get_journey_participants($context, $journey);
    if (!empty($attendancejourneys->journeygrading)) {
        $participants = array_filter($participants, static fn($participant) =>
            empty(\mod_attendancejourneys\local\individual_obligation::get((int) $journey->id, (int) $participant->id)->waived));
    }
    $pendingapprovals = 0;
    $pendingequivalences = 0;
    foreach (array_keys($participants) as $userid) {
        $participantcalculation = attendancejourneys_calculate_user_attendance(
            $attendancejourneys,
            (int) $userid,
            (int) $journey->id
        );
        if (!attendancejourneys_get_active_closure((int) $attendancejourneys->id, (int) $userid, (int) $journey->id)) {
            $blocker = attendancejourneys_closure_blocker($participantcalculation);
            if ($blocker) {
                throw new moodle_exception($blocker[0], 'attendancejourneys', '', $blocker[1]);
            }
        }
        $pendingapprovals += $participantcalculation->pendingapprovals;
        $pendingequivalences += $participantcalculation->pendingequivalences;
    }
    if ($pendingapprovals > 0) {
        throw new moodle_exception('journeyclosurependingapprovals', 'attendancejourneys', '', $pendingapprovals);
    }
    if ($pendingequivalences > 0) {
        throw new moodle_exception('journeyclosurependingequivalences', 'attendancejourneys', '', $pendingequivalences);
    }
    $closed = [];
    $transaction = $DB->start_delegated_transaction();
    foreach (array_keys($participants) as $userid) {
        if (!is_enrolled($context, (int) $userid, 'mod/attendancejourneys:canbelisted', true)) {
            continue;
        }
        if (!attendancejourneys_get_active_closure((int) $attendancejourneys->id, (int) $userid, (int) $journey->id)) {
            attendancejourneys_close_user_journey($attendancejourneys, (int) $userid, $completedby, (int) $journey->id);
            $closed[] = (int) $userid;
        }
    }
    $journey->active = 0;
    $journey->completedby = $completedby;
    $journey->timecompleted = time();
    $journey->timemodified = time();
    $DB->update_record('attendancejourneys_journeys', $journey);
    $DB->set_field('attendancejourneys_members', 'active', 0, ['journeyid' => $journey->id]);
    $DB->set_field('attendancejourneys_members', 'timeended', time(), ['journeyid' => $journey->id]);
    $transaction->allow_commit();
    $event = \mod_attendancejourneys\event\journey_completed::create([
        'objectid' => $journey->id, 'context' => $context, 'userid' => $completedby,
        'other' => ['attendancejourneysid' => (int) $attendancejourneys->id, 'participants' => count($closed)],
    ]);
    $event->add_record_snapshot('attendancejourneys_journeys', $journey);
    $event->trigger();
    return $closed;
}

/**
 * Reopens a completed journey and its associated individual closure snapshots.
 *
 * @param stdClass $journey Journey record.
 * @param int $reopenedby Identifier of the user reopening the journey.
 */
function attendancejourneys_reopen_completed_journey(stdClass $journey, int $reopenedby): array {
    global $DB;
    $closures = $DB->get_records(
        'attendancejourneys_closures',
        ['attendancejourneysid' => $journey->attendancejourneysid, 'journeyid' => $journey->id, 'active' => 1]
    );
    $userids = [];
    $transaction = $DB->start_delegated_transaction();
    foreach ($closures as $closure) {
        $closure->active = 0;
        $closure->reopenedby = $reopenedby;
        $closure->timereopened = time();
        $DB->update_record('attendancejourneys_closures', $closure);
        $userids[] = (int) $closure->userid;
    }
    $journey->active = 1;
    $journey->completedby = 0;
    $journey->timecompleted = 0;
    $journey->timemodified = time();
    $DB->update_record('attendancejourneys_journeys', $journey);
    $DB->set_field('attendancejourneys_members', 'active', 1, ['journeyid' => $journey->id]);
    $DB->set_field('attendancejourneys_members', 'timeended', 0, ['journeyid' => $journey->id]);
    $transaction->allow_commit();
    $cm = get_coursemodule_from_instance('attendancejourneys', $journey->attendancejourneysid, 0, false, IGNORE_MISSING);
    if ($cm) {
        $event = \mod_attendancejourneys\event\journey_reopened::create([
            'objectid' => $journey->id, 'context' => context_module::instance($cm->id),
            'userid' => $reopenedby,
            'other' => ['attendancejourneysid' => (int) $journey->attendancejourneysid,
                'participants' => count($userids)],
        ]);
        $event->add_record_snapshot('attendancejourneys_journeys', $journey);
        $event->trigger();
    }
    return $userids;
}

/**
 * Reopens one participant's journey while retaining its audit snapshot.
 *
 * @param stdClass $attendancejourneys Activity instance.
 * @param int $userid Participant ID.
 * @param int $reopenedby User performing the action.
 * @param int|null $journeyid Explicit journey scope; null preserves the legacy latest-result selection.
 * @return bool Whether a closure was reopened.
 */
function attendancejourneys_reopen_user_journey(
    stdClass $attendancejourneys,
    int $userid,
    int $reopenedby,
    ?int $journeyid = null
): bool {
    global $DB;
    if (
        $journeyid === null && !empty($attendancejourneys->journeygrading) &&
            $DB->count_records('attendancejourneys_closures', [
                'attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid, 'active' => 1,
            ]) > 1
    ) {
        throw new moodle_exception('journeyselectionrequired', 'attendancejourneys');
    }
    $closure = attendancejourneys_get_active_closure((int) $attendancejourneys->id, $userid, $journeyid);
    if (!$closure) {
        return false;
    }
    $closure->active = 0;
    $closure->reopenedby = $reopenedby;
    $closure->timereopened = time();
    $updated = $DB->update_record('attendancejourneys_closures', $closure);
    if (
        !empty($closure->journeyid) && $DB->record_exists('attendancejourneys_journeys', [
            'id' => $closure->journeyid, 'attendancejourneysid' => $attendancejourneys->id, 'active' => 1,
        ])
    ) {
        $DB->set_field('attendancejourneys_members', 'active', 1, [
            'journeyid' => $closure->journeyid, 'userid' => $userid,
        ]);
        $DB->set_field('attendancejourneys_members', 'timeended', 0, [
            'journeyid' => $closure->journeyid, 'userid' => $userid,
        ]);
    }
    $cm = get_coursemodule_from_instance(
        'attendancejourneys',
        $attendancejourneys->id,
        $attendancejourneys->course ?? 0,
        false,
        IGNORE_MISSING
    );
    if ($cm) {
        $event = \mod_attendancejourneys\event\journey_result_reopened::create([
            'objectid' => $closure->id, 'context' => context_module::instance($cm->id),
            'userid' => $reopenedby, 'relateduserid' => $userid,
            'other' => ['attendancejourneysid' => (int) $attendancejourneys->id,
                'journeyid' => (int) $closure->journeyid],
        ]);
        $event->add_record_snapshot('attendancejourneys_closures', $closure);
        $event->trigger();
    }
    return $updated;
}

/**
 * Permanently removes one participant's functional data from an activity.
 *
 * Enrolment, group membership, the user account and Moodle logs are deliberately untouched.
 *
 * @param stdClass $attendancejourneys Activity instance.
 * @param stdClass $cm Course module.
 * @param int $userid Participant ID.
 * @param int $resetby User performing the reset.
 * @return array{records:int,closures:int}
 */
function attendancejourneys_reset_user_data(stdClass $attendancejourneys, stdClass $cm, int $userid, int $resetby): array {
    global $CFG, $DB;

    require_once(__DIR__ . '/lib.php');
    require_once($CFG->libdir . '/completionlib.php');

    $sessionids = $DB->get_fieldset_select(
        'attendancejourneys_sessions',
        'id',
        'attendancejourneysid = :attendancejourneysid',
        ['attendancejourneysid' => $attendancejourneys->id]
    );
    $recordcount = 0;
    $transaction = $DB->start_delegated_transaction();
    if ($sessionids) {
        [$insql, $params] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'resetsession');
        $params['userid'] = $userid;
        $resetrecords = $DB->get_records_select(
            'attendancejourneys_records',
            "userid = :userid AND sessionid $insql",
            $params
        );
        $recordcount = count($resetrecords);
        attendancejourneys_delete_review_history(array_keys($resetrecords));
        $DB->delete_records_select('attendancejourneys_records', "userid = :userid AND sessionid $insql", $params);
    }
    $closureconditions = ['attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid];
    $closurecount = $DB->count_records('attendancejourneys_closures', $closureconditions);
    $DB->delete_records('attendancejourneys_closures', $closureconditions);
    $DB->delete_records('attendancejourneys_members', ['attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid]);
    $DB->delete_records('attendancejourneys_waitlist', ['attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid]);
    $DB->delete_records('attendancejourneys_attempts', $closureconditions);
    $DB->delete_records('attendancejourneys_obligationlog', $closureconditions);
    $DB->delete_records('attendancejourneys_obligations', $closureconditions);
    $DB->delete_records('attendancejourneys_equivlog', $closureconditions);
    $DB->delete_records(
        'attendancejourneys_equivalences',
        ['attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid]
    );

    attendancejourneys_update_grades($attendancejourneys, $userid);
    require_once($CFG->libdir . '/gradelib.php');
    $gradeitems = grade_item::fetch_all([
        'courseid' => $attendancejourneys->course, 'itemtype' => 'mod', 'itemmodule' => 'attendancejourneys',
        'iteminstance' => $attendancejourneys->id,
    ]);
    foreach ($gradeitems ?: [] as $gradeitem) {
        grade_regrade_final_grades((int) $attendancejourneys->course, $userid, $gradeitem);
    }
    $completion = new completion_info(get_course($cm->course));
    if ($completion->is_enabled($cm)) {
        $completion->update_state($cm, COMPLETION_INCOMPLETE, $userid);
    }

    $event = \mod_attendancejourneys\event\user_data_reset::create([
        'objectid' => $attendancejourneys->id,
        'context' => context_module::instance($cm->id),
        'relateduserid' => $userid,
        'userid' => $resetby,
        'other' => ['records' => $recordcount, 'closures' => $closurecount],
    ]);
    $event->add_record_snapshot('attendancejourneys', $attendancejourneys);
    $event->trigger();
    $transaction->allow_commit();

    return ['records' => $recordcount, 'closures' => $closurecount];
}

/**
 * Returns active participants targeted by a session.
 *
 * @param context_module $context Module context.
 * @param stdClass $session Session record.
 * @param string $fields User fields.
 * @param string|null $sort Sort expression.
 * @return array
 */
function attendancejourneys_get_session_participants(
    context_module $context,
    stdClass $session,
    string $fields = 'u.*',
    ?string $sort = null
): array {
    global $DB;
    $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
    $hasfrozenjourney = !empty($session->journeyid) &&
        attendancejourneys_journey_has_fixed_audience((object) ['id' => $session->journeyid]);
    if (
        groups_get_activity_groupmode($cm) == SEPARATEGROUPS &&
            !has_capability('moodle/site:accessallgroups', $context)
    ) {
        $allowedgroups = groups_get_activity_allowed_groups($cm);
        if (!empty($session->groupid) && !$hasfrozenjourney) {
            if (!isset($allowedgroups[$session->groupid])) {
                return [];
            }
            $participants = get_enrolled_users(
                $context,
                'mod/attendancejourneys:canbelisted',
                (int) $session->groupid,
                $fields,
                $sort,
                0,
                0,
                true
            );
            return attendancejourneys_filter_journey_participants($participants, $session);
        }
        $participants = [];
        foreach (array_keys($allowedgroups) as $groupid) {
            $participants += get_enrolled_users(
                $context,
                'mod/attendancejourneys:canbelisted',
                (int) $groupid,
                $fields,
                $sort,
                0,
                0,
                true
            );
        }
        return attendancejourneys_filter_journey_participants($participants, $session);
    }
    // Once a journey audience is frozen, its membership is authoritative. A participant may be added
    // individually after the journey was created and therefore may not belong to its original Moodle group.
    // Start from every actively enrolled, listable user and intersect with the frozen membership below.
    $groupid = $hasfrozenjourney ? 0 : (int) $session->groupid;
    $participants = get_enrolled_users(
        $context,
        'mod/attendancejourneys:canbelisted',
        $groupid,
        $fields,
        $sort,
        0,
        0,
        true
    );
    return attendancejourneys_filter_journey_participants($participants, $session);
}

/**
 * Restricts current session participants to the frozen audience of its journey.
 *
 * @param array $participants Participants to filter.
 * @param stdClass $session Attendance session.
 */
function attendancejourneys_filter_journey_participants(array $participants, stdClass $session): array {
    global $DB;
    if (
        empty($session->journeyid) ||
            !attendancejourneys_journey_has_fixed_audience((object) ['id' => $session->journeyid])
    ) {
        return $participants;
    }
    $memberids = $DB->get_fieldset_select(
        'attendancejourneys_members',
        'userid',
        'journeyid = :journeyid AND active = 1',
        ['journeyid' => $session->journeyid]
    );
    return array_intersect_key($participants, array_fill_keys(array_map('intval', $memberids), true));
}

/**
 * Returns report participants visible to the current user under Moodle group rules.
 *
 * @param context_module $context Module context.
 * @param stdClass|cm_info $cm Course module.
 * @param string $fields User fields.
 * @param string|null $sort Sort expression.
 * @return array Users keyed by ID.
 */
function attendancejourneys_get_visible_participants(
    context_module $context,
    $cm,
    string $fields = 'u.*',
    ?string $sort = null
): array {
    if (
        groups_get_activity_groupmode($cm) != SEPARATEGROUPS ||
            has_capability('moodle/site:accessallgroups', $context)
    ) {
        return get_enrolled_users(
            $context,
            'mod/attendancejourneys:canbelisted',
            0,
            $fields,
            $sort,
            0,
            0,
            true
        );
    }
    $participants = [];
    foreach (array_keys(groups_get_activity_allowed_groups($cm)) as $groupid) {
        $participants += get_enrolled_users(
            $context,
            'mod/attendancejourneys:canbelisted',
            (int) $groupid,
            $fields,
            $sort,
            0,
            0,
            true
        );
    }
    return $participants;
}

/**
 * Whether a session applies to one participant.
 *
 * @param stdClass $session Session record.
 * @param int $userid User ID.
 * @return bool
 */
function attendancejourneys_session_applies_to_user(stdClass $session, int $userid): bool {
    global $DB;
    if (
        !empty($session->journeyid) &&
            attendancejourneys_journey_has_fixed_audience((object) ['id' => $session->journeyid])
    ) {
        return $DB->record_exists('attendancejourneys_members', [
            'journeyid' => $session->journeyid, 'userid' => $userid,
        ]);
    }
    return empty($session->groupid) || groups_is_member((int) $session->groupid, $userid);
}

/**
 * Whether a participant may still self-record attendance for this session.
 *
 * @param context_module $context Activity context.
 * @param stdClass $attendancejourneys Attendance activity and its configuration.
 * @param stdClass $session Attendance session.
 * @param int $userid User identifier.
 */
function attendancejourneys_user_can_self_record_session(
    context_module $context,
    stdClass $attendancejourneys,
    stdClass $session,
    int $userid
): bool {
    global $DB;
    if (!empty($session->cancelled) || (int) $session->sessiondate > time()) {
        return false;
    }
    if (!attendancejourneys_session_applies_to_user($session, $userid)) {
        return false;
    }
    $activejourneys = attendancejourneys_get_user_active_journeys($context, (int) $attendancejourneys->id, $userid);
    if (!empty($session->journeyid)) {
        $journey = $DB->get_record('attendancejourneys_journeys', [
            'id' => $session->journeyid, 'attendancejourneysid' => $attendancejourneys->id,
        ]);
        if (
            !$journey || empty($journey->active) || !isset($activejourneys[(int) $session->journeyid])
        ) {
            return false;
        }
        if (
            attendancejourneys_journey_has_fixed_audience((object) ['id' => $session->journeyid]) &&
                !$DB->record_exists('attendancejourneys_members', [
                    'journeyid' => $session->journeyid, 'userid' => $userid, 'active' => 1,
                ])
        ) {
            return false;
        }
        return !attendancejourneys_get_active_closure(
            (int) $attendancejourneys->id,
            $userid,
            (int) $session->journeyid
        );
    }
    if ($activejourneys) {
        return false;
    }
    return !attendancejourneys_get_active_closure((int) $attendancejourneys->id, $userid, 0);
}

/**
 * Whether the current user may access a session under Moodle separate-group rules.
 *
 * @param stdClass|cm_info $cm Course module record or cached course module information.
 * @param context_module $context Activity context.
 * @param stdClass $session Attendance session.
 */
function attendancejourneys_session_is_visible($cm, context_module $context, stdClass $session): bool {
    if (
        empty($session->groupid) || groups_get_activity_groupmode($cm) != SEPARATEGROUPS ||
            has_capability('moodle/site:accessallgroups', $context)
    ) {
        return true;
    }
    return isset(groups_get_activity_allowed_groups($cm)[$session->groupid]);
}

/**
 * Human-readable session audience.
 *
 * @param stdClass $session Session record.
 * @return string
 */
function attendancejourneys_session_audience(stdClass $session): string {
    if (empty($session->groupid)) {
        return attendancejourneys_get_string('allactiveparticipants', 'attendancejourneys');
    }
    $name = groups_get_group_name((int) $session->groupid);
    return $name ? format_string($name) : attendancejourneys_get_string('unknowngroup', 'attendancejourneys');
}

/**
 * Returns the number of current target participants and those already recorded.
 *
 * @param context_module $context Module context.
 * @param stdClass $session Session record.
 * @return array{recorded:int,total:int}
 */
function attendancejourneys_session_progress(context_module $context, stdClass $session): array {
    global $DB;

    $participantids = array_keys(attendancejourneys_get_session_participants($context, $session, 'u.id'));
    if (!$participantids) {
        return ['recorded' => 0, 'total' => 0];
    }
    [$insql, $params] = $DB->get_in_or_equal($participantids, SQL_PARAMS_NAMED, 'participant');
    $params['sessionid'] = $session->id;
    $recorded = $DB->count_records_select(
        'attendancejourneys_records',
        "sessionid = :sessionid AND userid $insql",
        $params
    );
    return ['recorded' => $recorded, 'total' => count($participantids)];
}

/**
 * Returns group filter options allowed for the current user and activity.
 *
 * @param cm_info|stdClass $cm Course module.
 * @return array
 */
function attendancejourneys_group_filter_options($cm): array {
    $options = [
        -1 => attendancejourneys_get_string('allsessions', 'attendancejourneys'),
        0 => attendancejourneys_get_string('generalsessions', 'attendancejourneys'),
    ];
    foreach (groups_get_activity_allowed_groups($cm) as $group) {
        $options[(int) $group->id] = format_string($group->name);
    }
    return $options;
}

/**
 * Prints a Moodle-native group filter for session lists.
 *
 * @param moodle_url $url Base page URL.
 * @param cm_info|stdClass $cm Course module.
 * @param int $selected Current filter.
 */
function attendancejourneys_print_group_filter(moodle_url $url, $cm, int $selected): void {
    global $OUTPUT;

    $options = attendancejourneys_group_filter_options($cm);
    if (!array_key_exists($selected, $options)) {
        $selected = -1;
    }
    $select = new single_select($url, 'groupfilter', $options, $selected, null, 'attendancejourneys-group-filter');
    $select->set_label(attendancejourneys_get_string('filtersessionsbygroup', 'attendancejourneys'));
    echo html_writer::div($OUTPUT->render($select), 'attendancejourneys-group-filter');
}

/**
 * Returns group choices for the collective report.
 *
 * @param cm_info|stdClass $cm Course module.
 * @return array
 */
function attendancejourneys_report_group_filter_options($cm): array {
    $options = [-1 => attendancejourneys_get_string('allactiveparticipants', 'attendancejourneys')];
    foreach (groups_get_activity_allowed_groups($cm) as $group) {
        $options[(int) $group->id] = format_string($group->name);
    }
    return $options;
}

/**
 * Prints the group filter used by the collective report.
 *
 * @param moodle_url $url Base report URL.
 * @param cm_info|stdClass $cm Course module.
 * @param int $selected Current group ID, or -1 for all active participants.
 */
function attendancejourneys_print_report_group_filter(moodle_url $url, $cm, int $selected): void {
    global $OUTPUT;

    $options = attendancejourneys_report_group_filter_options($cm);
    if (!array_key_exists($selected, $options)) {
        $selected = -1;
    }
    $select = new single_select($url, 'groupfilter', $options, $selected, null, 'attendancejourneys-report-group-filter');
    $select->set_label(attendancejourneys_get_string('filterreportbygroup', 'attendancejourneys'));
    echo html_writer::div($OUTPUT->render($select), 'attendancejourneys-group-filter');
}

/**
 * Filters sessions by their explicit audience.
 *
 * @param array $sessions Session records.
 * @param int $groupfilter -1 for all, 0 for common sessions, or a group ID.
 * @return array
 */
function attendancejourneys_filter_sessions(array $sessions, int $groupfilter): array {
    if ($groupfilter < 0) {
        return $sessions;
    }
    return array_filter($sessions, fn($session) => (int) $session->groupid === $groupfilter);
}

/**
 * Filters a session catalogue by name and relative date.
 *
 * @param array $sessions Session records.
 * @param string $query Name search.
 * @param string $period all, upcoming, or past.
 * @param int|null $now Reference timestamp, primarily for tests.
 * @return array
 */
function attendancejourneys_filter_session_catalog(
    array $sessions,
    string $query,
    string $period,
    ?int $now = null
): array {
    $now = $now ?? time();
    $query = core_text::strtolower(trim($query));
    return array_filter($sessions, static function ($session) use ($query, $period, $now) {
        if ($query !== '' && core_text::strpos(core_text::strtolower($session->name), $query) === false) {
            return false;
        }
        $endtime = (int) $session->sessiondate + ((int) $session->duration * MINSECS);
        if ($period === 'upcoming') {
            return $endtime >= $now;
        }
        if ($period === 'past') {
            return $endtime < $now;
        }
        return true;
    });
}

/**
 * Prints search and date controls for a session catalogue.
 *
 * @param moodle_url $url Page URL.
 * @param int $groupfilter Current group filter.
 * @param string $query Current search.
 * @param string $period Current period.
 * @param array $preserve Additional query parameters to preserve.
 */
function attendancejourneys_print_session_catalog_filters(
    moodle_url $url,
    int $groupfilter,
    string $query,
    string $period,
    array $preserve = []
): void {
    $hidden = ['id' => $url->get_param('id')] + $preserve;
    if ($groupfilter >= 0) {
        $hidden['groupfilter'] = $groupfilter;
    }
    $content = '';
    foreach ($hidden as $name => $value) {
        $content .= html_writer::empty_tag('input', [
            'type' => 'hidden', 'name' => $name, 'value' => $value,
        ]);
    }
    $content .= html_writer::label(
        attendancejourneys_get_string('searchsessions', 'attendancejourneys'),
        'attendancejourneys-session-search',
        false,
        ['class' => 'sr-only visually-hidden']
    );
    $content .= html_writer::empty_tag('input', [
        'type' => 'search', 'id' => 'attendancejourneys-session-search', 'name' => 'q', 'value' => $query,
        'placeholder' => attendancejourneys_get_string('searchsessions', 'attendancejourneys'), 'class' => 'form-control',
    ]);
    $content .= html_writer::label(
        attendancejourneys_get_string('sessionperiodfilter', 'attendancejourneys'),
        'attendancejourneys-session-period',
        false,
        ['class' => 'sr-only visually-hidden']
    );
    $content .= html_writer::select([
        'all' => attendancejourneys_get_string('periodall', 'attendancejourneys'),
        'upcoming' => attendancejourneys_get_string('periodupcoming', 'attendancejourneys'),
        'past' => attendancejourneys_get_string('periodpast', 'attendancejourneys'),
    ], 'period', $period, false, ['id' => 'attendancejourneys-session-period', 'class' => 'custom-select form-select']);
    $content .= html_writer::tag('button', attendancejourneys_get_string('applyfilters', 'attendancejourneys'), [
        'type' => 'submit', 'class' => 'btn btn-secondary',
    ]);
    if ($query !== '' || $period !== 'all') {
        $clearparams = ['id' => $url->get_param('id')];
        if ($groupfilter >= 0) {
            $clearparams['groupfilter'] = $groupfilter;
        }
        $clearurl = new moodle_url($url->get_path(), $clearparams + $preserve);
        $content .= html_writer::link(
            $clearurl,
            attendancejourneys_get_string('clearfilters', 'attendancejourneys'),
            ['class' => 'attendancejourneys-clear-filters']
        );
    }
    echo html_writer::tag('form', $content, [
        'method' => 'get', 'action' => $url->out_omit_querystring(),
        'class' => 'attendancejourneys-session-filters',
    ]);
}

/**
 * Renders an audience badge for a session.
 *
 * @param stdClass $session Session record.
 * @return string
 */
function attendancejourneys_session_audience_badge(stdClass $session): string {
    return html_writer::span(
        attendancejourneys_session_audience($session),
        empty($session->groupid) ? 'badge badge-secondary text-bg-secondary' : 'badge badge-info text-bg-info'
    );
}
