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
 * Session management for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$journeyid = optional_param('journeyid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$sort = optional_param('sort', 'date', PARAM_ALPHA);
$direction = optional_param('direction', 'asc', PARAM_ALPHA);
$groupfilter = optional_param('groupfilter', -1, PARAM_INT);
$query = trim(optional_param('q', '', PARAM_TEXT));
$period = optional_param('period', 'all', PARAM_ALPHA);
$bulkaction = optional_param('bulkaction', '', PARAM_ALPHA);
$selectedsessions = optional_param_array('selectedsessions', [], PARAM_INT);
$confirmbulk = optional_param('confirmbulk', 0, PARAM_BOOL);
$shiftunit = optional_param('shiftunit', 'days', PARAM_ALPHA);
$shiftvalue = optional_param('shiftvalue', 0, PARAM_INT);
$allowconflicts = optional_param('allowconflicts', 0, PARAM_BOOL);
$bulksnapshot = optional_param('bulksnapshot', '', PARAM_ALPHANUM);
$bulkoperation = optional_param('bulkoperation', '', PARAM_ALPHA);
$bulkvalue = trim(optional_param('bulkvalue', '', PARAM_TEXT));
$bulkmodality = optional_param('bulkmodality', '', PARAM_ALPHA);
$bulkaudience = optional_param('bulkaudience', '', PARAM_TEXT);
$bulkdescription = trim(optional_param('bulkdescription', '', PARAM_TEXT));
$bulkdescriptionpresent = optional_param('bulkdescriptionpresent', 0, PARAM_BOOL);
if ($bulkoperation === 'modality' && $bulkmodality !== '') {
    $bulkvalue = $bulkmodality;
} else if ($bulkoperation === 'description' && $bulkdescriptionpresent) {
    $bulkvalue = $bulkdescription;
}
$drafttoken = optional_param('draft', '', PARAM_ALPHANUM);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 25;
if (!in_array($period, ['all', 'upcoming', 'past'], true)) {
    $period = 'all';
}

$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:managesessions', $context);
// Coordinate administrative changes with attendance submissions before reading mutable state.
if (
    data_submitted() || (($action !== '' || $bulkaction !== '')
        && optional_param('sesskey', '', PARAM_RAW) !== '' && confirm_sesskey())
) {
    require_sesskey();
    $writelock = new \mod_attendancejourneys\local\write_lock((int) $attendancejourneys->id);
    $attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
}

$islight = attendancejourneys_is_light_mode($attendancejourneys);
if ($islight && (in_array($action, ['series', 'seriesreview'], true) || $bulkaction !== '')) {
    redirect(
        new moodle_url('/mod/attendancejourneys/sessions.php', ['id' => $cm->id]),
        attendancejourneys_get_string('professionalfeaturehidden', 'attendancejourneys'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

$baseurl = new moodle_url('/mod/attendancejourneys/sessions.php', ['id' => $cm->id]);
$allowedfilters = attendancejourneys_group_filter_options($cm);
if (!array_key_exists($groupfilter, $allowedfilters)) {
    $groupfilter = -1;
}
$filteredurl = new moodle_url($baseurl, $groupfilter >= 0 ? ['groupfilter' => $groupfilter] : []);
$PAGE->set_url($baseurl);
$PAGE->set_title(attendancejourneys_get_string('managesessions', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Series previews are transient, private to the current user and expire after two hours.
if (!isset($SESSION->attendancejourneys_series_drafts) || !is_array($SESSION->attendancejourneys_series_drafts)) {
    $SESSION->attendancejourneys_series_drafts = [];
}
foreach ($SESSION->attendancejourneys_series_drafts as $token => $stored) {
    if (empty($stored['timecreated']) || (int) $stored['timecreated'] < time() - (2 * HOURSECS)) {
        unset($SESSION->attendancejourneys_series_drafts[$token]);
    }
}

if ($action === 'seriesreview') {
    $draft = $SESSION->attendancejourneys_series_drafts[$drafttoken] ?? null;
    if (
        !$draft || (int) $draft['userid'] !== (int) $USER->id ||
            (int) $draft['cmid'] !== (int) $cm->id
    ) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('seriesdraftinvalid', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $reviewurl = new moodle_url($baseurl, ['action' => 'seriesreview', 'draft' => $drafttoken]);
    $draftcommon = (object) $draft['common'];
    attendancejourneys_require_session_destination_access($cm, $context, $draftcommon);
    $existingsessions = $DB->get_records('attendancejourneys_sessions', [
        'attendancejourneysid' => $attendancejourneys->id,
        'journeyid' => (int) $draftcommon->journeyid,
        'groupid' => (int) $draftcommon->groupid,
    ]);
    $reviewform = new \mod_attendancejourneys\form\series_review(
        $reviewurl,
        ['count' => count($draft['occurrences']), 'existing' => $existingsessions]
    );
    if ($reviewform->is_cancelled()) {
        unset($SESSION->attendancejourneys_series_drafts[$drafttoken]);
        redirect(new moodle_url($baseurl, ['action' => 'series']));
    }

    $defaults = (object) [
        'id' => $cm->id,
        'draft' => $drafttoken,
        'included' => [],
        'selected' => [],
        'sessionname' => [],
        'sessionstart' => [],
        'sessionend' => [],
        'sessionmodality' => [],
        'sessionlocation' => [],
        'sessionmeetingurl' => [],
        'sessiondescription' => [],
    ];
    foreach ($draft['occurrences'] as $index => $occurrence) {
        $defaults->included[$index] = !isset($occurrence['included']) || !empty($occurrence['included']);
        $defaults->selected[$index] = empty($occurrence['selected']) ? 0 : 1;
        $defaults->sessionname[$index] = $occurrence['name'];
        $defaults->sessionstart[$index] = $occurrence['sessiondate'];
        $defaults->sessionend[$index] = $occurrence['enddate'];
        $defaults->sessionmodality[$index] = $occurrence['modality'] ?? $draft['common']['modality'];
        $defaults->sessionlocation[$index] = $occurrence['location'] ?? $draft['common']['location'];
        $defaults->sessionmeetingurl[$index] = $occurrence['meetingurl'] ?? $draft['common']['meetingurl'];
        $defaults->sessiondescription[$index] = $occurrence['description'] ?? $draft['common']['description'];
    }
    $reviewform->set_data($defaults);

    if ($reviewdata = $reviewform->get_data()) {
        if (
            !empty($reviewdata->applybulk) || !empty($reviewdata->selectallbulk) ||
                !empty($reviewdata->clearbulkselection) || !empty($reviewdata->sortbydate)
        ) {
            $updated = [];
            $submittedcount = min(50, (int) ($reviewdata->occurrencecount ?? 0));
            for ($index = 0; $index < $submittedcount; $index++) {
                $updated[] = [
                    'name' => trim($reviewdata->sessionname[$index]),
                    'sessiondate' => (int) $reviewdata->sessionstart[$index],
                    'enddate' => (int) $reviewdata->sessionend[$index],
                    'included' => empty($reviewdata->included[$index]) ? 0 : 1,
                    'modality' => $reviewdata->sessionmodality[$index],
                    'location' => trim($reviewdata->sessionlocation[$index] ?? ''),
                    'meetingurl' => trim($reviewdata->sessionmeetingurl[$index] ?? ''),
                    'description' => trim($reviewdata->sessiondescription[$index] ?? ''),
                    'selected' => empty($reviewdata->selected[$index]) ? 0 : 1,
                ];
            }
            if (!empty($reviewdata->selectallbulk)) {
                foreach ($updated as &$occurrence) {
                    $occurrence['selected'] = empty($occurrence['included']) ? 0 : 1;
                }
                unset($occurrence);
                $message = attendancejourneys_get_string('seriesallselected', 'attendancejourneys');
            } else if (!empty($reviewdata->clearbulkselection)) {
                foreach ($updated as &$occurrence) {
                    $occurrence['selected'] = 0;
                }
                unset($occurrence);
                $message = attendancejourneys_get_string('seriesselectioncleared', 'attendancejourneys');
            } else if (!empty($reviewdata->sortbydate)) {
                $updated = \mod_attendancejourneys\local\series_builder::sort_chronologically($updated);
                $message = attendancejourneys_get_string('seriessorted', 'attendancejourneys');
            } else {
                $updated = \mod_attendancejourneys\local\series_builder::apply_bulk(
                    $updated,
                    $reviewdata->selected ?? [],
                    $reviewdata->bulkoperation,
                    $reviewdata->bulkoperation === 'modality' ? $reviewdata->bulkmodality :
                    trim($reviewdata->bulkvalue),
                    \core_date::get_user_timezone_object()
                );
                $message = attendancejourneys_get_string('seriesbulkchanged', 'attendancejourneys');
            }
            $SESSION->attendancejourneys_series_drafts[$drafttoken]['occurrences'] = $updated;
            $SESSION->attendancejourneys_series_drafts[$drafttoken]['timecreated'] = time();
            redirect($reviewurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
        }

        // Revalidate the shared audience and journey against current course state.
        $common = (object) $draft['common'];
        $journey = null;
        if (!empty($common->journeyid)) {
            $journey = $DB->get_record('attendancejourneys_journeys', [
                'id' => $common->journeyid,
                'attendancejourneysid' => $attendancejourneys->id,
                'active' => 1,
            ], '*', MUST_EXIST);
            $common->groupid = (int) $journey->groupid;
        } else if (!array_key_exists((int) $common->groupid, attendancejourneys_group_filter_options($cm))) {
            redirect(
                $reviewurl,
                attendancejourneys_get_string('invalidsessiongroup', 'attendancejourneys'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        $records = [];
        $submittedcount = min(50, (int) ($reviewdata->occurrencecount ?? 0));
        for ($index = 0; $index < $submittedcount; $index++) {
            if (empty($reviewdata->included[$index])) {
                continue;
            }
            $start = (int) $reviewdata->sessionstart[$index];
            $end = (int) $reviewdata->sessionend[$index];
            $occurrencerecord = clone $common;
            $occurrencerecord->name = trim($reviewdata->sessionname[$index]);
            $occurrencerecord->sessiondate = $start;
            $occurrencerecord->duration = (int) ceil(($end - $start) / MINSECS);
            $occurrencerecord->modality = $reviewdata->sessionmodality[$index];
            $occurrencerecord->location = trim($reviewdata->sessionlocation[$index] ?? '');
            $occurrencerecord->meetingurl = trim($reviewdata->sessionmeetingurl[$index] ?? '');
            $occurrencerecord->description = trim($reviewdata->sessiondescription[$index] ?? '');
            $occurrencerecord->timecreated = time();
            $occurrencerecord->timemodified = time();
            $records[] = $occurrencerecord;
        }

        $transaction = $DB->start_delegated_transaction();
        foreach ($records as $occurrencerecord) {
            $occurrencerecord->id = $DB->insert_record('attendancejourneys_sessions', $occurrencerecord);
            attendancejourneys_update_calendar_event($attendancejourneys, $occurrencerecord);
            \mod_attendancejourneys\event\session_created::create([
                'objectid' => $occurrencerecord->id,
                'context' => $context,
                'other' => ['attendancejourneysid' => $attendancejourneys->id],
            ])->trigger();
        }
        $transaction->allow_commit();
        unset($SESSION->attendancejourneys_series_drafts[$drafttoken]);

        $participantids = array_keys(get_enrolled_users(
            $context,
            'mod/attendancejourneys:canbelisted',
            0,
            'u.id',
            null,
            0,
            0,
            true
        ));
        attendancejourneys_update_completion($course, $cm, $participantids);
        attendancejourneys_update_grades($attendancejourneys);
        $savedurl = new moodle_url($baseurl, ['groupfilter' => (int) $common->groupid]);
        redirect(
            $savedurl,
            attendancejourneys_get_string('sessionseriescreated', 'attendancejourneys', count($records)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Build the conflict report from the submitted preview, including invalid submissions, or from the draft.
    $reportdata = $reviewform->get_submitted_data();
    $reportoccurrences = [];
    if ($reportdata && !empty($reportdata->occurrencecount)) {
        $reportcount = min(50, (int) $reportdata->occurrencecount);
        for ($index = 0; $index < $reportcount; $index++) {
            $reportoccurrences[] = [
                'name' => trim($reportdata->sessionname[$index] ?? ''),
                'sessiondate' => (int) ($reportdata->sessionstart[$index] ?? 0),
                'enddate' => (int) ($reportdata->sessionend[$index] ?? 0),
                'included' => empty($reportdata->included[$index]) ? 0 : 1,
                'modality' => $reportdata->sessionmodality[$index] ?? 'unspecified',
            ];
        }
    } else {
        $reportoccurrences = $draft['occurrences'];
    }
    $overlapdetails = \mod_attendancejourneys\local\series_builder::describe_overlaps(
        $reportoccurrences,
        $existingsessions
    );
    $seriessummary = \mod_attendancejourneys\local\series_builder::summarise($reportoccurrences);

    if (!empty($draftcommon->journeyid)) {
        $audiencerecord = $DB->get_record('attendancejourneys_journeys', [
            'id' => $draftcommon->journeyid,
            'attendancejourneysid' => $attendancejourneys->id,
        ]);
        $audiencename = $audiencerecord ? format_string($audiencerecord->name) :
            attendancejourneys_get_string('independentsession', 'attendancejourneys');
    } else if (!empty($draftcommon->groupid)) {
        $audiencename = groups_get_group_name($draftcommon->groupid) ?: attendancejourneys_get_string(
            'unknowngroup',
            'attendancejourneys'
        );
    } else {
        $audiencename = attendancejourneys_get_string('allactiveparticipants', 'attendancejourneys');
    }

    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'sessions');
    echo html_writer::start_div('attendancejourneys-section-header');
    echo html_writer::div(attendancejourneys_get_string('planning', 'attendancejourneys'), 'attendancejourneys-section-eyebrow');
    echo $OUTPUT->heading(attendancejourneys_get_string('reviewseriestitle', 'attendancejourneys'));
    echo html_writer::div(attendancejourneys_get_string('reviewseriesintro', 'attendancejourneys'), 'text-muted');
    echo html_writer::end_div();
    $summarycards = [];
    $summarycards[] = html_writer::div(html_writer::tag(
        'strong',
        $seriessummary['included'],
        ['class' => 'attendancejourneys-stat-value']
    ) . html_writer::span(
        attendancejourneys_get_string('seriessummaryincluded', 'attendancejourneys')
    ), 'attendancejourneys-stat');
    $summarycards[] = html_writer::div(html_writer::tag(
        'strong',
        $seriessummary['excluded'],
        ['class' => 'attendancejourneys-stat-value']
    ) . html_writer::span(
        attendancejourneys_get_string('seriessummaryexcluded', 'attendancejourneys')
    ), 'attendancejourneys-stat');
    $summarycards[] = html_writer::div(html_writer::tag(
        'strong',
        format_time($seriessummary['totalminutes'] * MINSECS),
        ['class' => 'attendancejourneys-stat-value attendancejourneys-stat-text']
    ) . html_writer::span(
        attendancejourneys_get_string('seriessummaryduration', 'attendancejourneys')
    ), 'attendancejourneys-stat');
    $periodtext = $seriessummary['firststart'] === null ? '—' :
        userdate($seriessummary['firststart'], get_string('strftimedateshort', 'langconfig')) . ' – ' .
        userdate($seriessummary['lastend'], get_string('strftimedateshort', 'langconfig'));
    $summarycards[] = html_writer::div(html_writer::tag(
        'strong',
        $periodtext,
        ['class' => 'attendancejourneys-stat-value attendancejourneys-stat-text']
    ) . html_writer::span(
        attendancejourneys_get_string('seriessummaryperiod', 'attendancejourneys')
    ), 'attendancejourneys-stat');
    $summarycards[] = html_writer::div(html_writer::tag(
        'strong',
        count($overlapdetails),
        ['class' => 'attendancejourneys-stat-value']
    ) . html_writer::span(
        attendancejourneys_get_string('seriessummaryconflicts', 'attendancejourneys')
    ), 'attendancejourneys-stat');
    $modalitylabels = [];
    foreach ($seriessummary['modalities'] as $modality => $modalitycount) {
        $modalitylabels[] = attendancejourneys_get_string('modality' . $modality, 'attendancejourneys') . ' : ' . $modalitycount;
    }
    $summarycards[] = html_writer::div(html_writer::tag(
        'strong',
        $modalitylabels ? implode(', ', $modalitylabels) : '—',
        ['class' => 'attendancejourneys-stat-value attendancejourneys-stat-text']
    ) . html_writer::span(
        attendancejourneys_get_string('seriessummarymodalities', 'attendancejourneys')
    ), 'attendancejourneys-stat');
    echo html_writer::div(implode('', $summarycards), 'attendancejourneys-summary-grid mb-4');
    if ($overlapdetails) {
        echo html_writer::start_div('alert alert-warning attendancejourneys-overlap-report');
        echo html_writer::tag('h3', attendancejourneys_get_string('seriesoverlapreport', 'attendancejourneys'), ['class' => 'h5']);
        echo html_writer::tag('p', attendancejourneys_get_string('seriesoverlapreportintro', 'attendancejourneys'));
        $overlaptable = new html_table();
        $overlaptable->head = [
            attendancejourneys_get_string('proposedsession', 'attendancejourneys'),
            attendancejourneys_get_string('conflictswith', 'attendancejourneys'),
            attendancejourneys_get_string('overlapduration', 'attendancejourneys'),
            attendancejourneys_get_string('sessionaudience', 'attendancejourneys'),
            attendancejourneys_get_string('source', 'attendancejourneys'),
            get_string('actions'),
        ];
        foreach ($overlapdetails as $detail) {
            $proposed = $reportoccurrences[$detail['proposedindex']];
            $proposedtext = html_writer::tag('strong', format_string($proposed['name'])) . html_writer::empty_tag('br') .
                userdate($proposed['sessiondate'], get_string('strftimedatetimeshort', 'langconfig')) . ' – ' .
                userdate($proposed['enddate'], get_string('strftimetime', 'langconfig'));
            if ($detail['source'] === 'proposed') {
                $other = $reportoccurrences[$detail['otherindex']];
                $othertext = html_writer::tag('strong', format_string($other['name'])) .
                    html_writer::empty_tag('br') . userdate(
                        $other['sessiondate'],
                        get_string('strftimedatetimeshort', 'langconfig')
                    ) . ' – ' .
                    userdate($other['enddate'], get_string('strftimetime', 'langconfig'));
                $source = attendancejourneys_get_string('seriesoverlapsourcenew', 'attendancejourneys');
                $actioncell = '—';
            } else {
                $other = $detail['session'];
                $otherend = (int) $other->sessiondate + ((int) $other->duration * MINSECS);
                $othertext = html_writer::tag('strong', format_string($other->name)) .
                    html_writer::empty_tag('br') . userdate(
                        $other->sessiondate,
                        get_string('strftimedatetimeshort', 'langconfig')
                    ) . ' – ' .
                    userdate($otherend, get_string('strftimetime', 'langconfig'));
                $source = attendancejourneys_get_string('seriesoverlapsourceexisting', 'attendancejourneys');
                $actioncell = html_writer::link(new moodle_url($baseurl, [
                    'action' => 'edit', 'sessionid' => $other->id,
                ]), attendancejourneys_get_string('reviewexistingsession', 'attendancejourneys'), [
                    'target' => '_blank', 'rel' => 'noopener',
                ]);
            }
            $overlaptable->data[] = [
                $proposedtext,
                $othertext,
                attendancejourneys_get_string(
                    'minutesvalue',
                    'attendancejourneys',
                    (int) ceil($detail['overlapseconds'] / MINSECS)
                ),
                format_string($audiencename),
                $source,
                $actioncell,
            ];
        }
        echo attendancejourneys_responsive_table(
            $overlaptable,
            attendancejourneys_get_string('seriesoverlapreport', 'attendancejourneys')
        );
        echo html_writer::end_div();
    }
    echo html_writer::start_div('attendancejourneys-form-card attendancejourneys-series-review');
    $reviewform->display();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

if ($bulkaction === 'editaudience') {
    require_sesskey();
    $selectedsessions = array_values(array_unique(array_map('intval', $selectedsessions)));
    $selected = $selectedsessions ? $DB->get_records_list('attendancejourneys_sessions', 'id', $selectedsessions) : [];
    foreach ($selected as $selectedsession) {
        attendancejourneys_require_session_management_access($cm, $context, $selectedsession);
    }
    if (!$selected) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkselectsessions', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    $audienceoptions = empty($attendancejourneys->journeygrading) ?
        ['common' => attendancejourneys_get_string('allactiveparticipants', 'attendancejourneys')] : [];
    foreach ($allowedfilters as $filterid => $filtername) {
        if (empty($attendancejourneys->journeygrading) && (int) $filterid > 0) {
            $audienceoptions['group:' . $filterid] = get_string('group') . ' — ' . $filtername;
        }
    }
    $activejourneys = $DB->get_records('attendancejourneys_journeys', [
        'attendancejourneysid' => $attendancejourneys->id, 'active' => 1,
    ], 'name ASC');
    foreach ($activejourneys as $activejourney) {
        if (!attendancejourneys_session_is_visible($cm, $context, $activejourney)) {
            continue;
        }
        $audienceoptions['journey:' . $activejourney->id] = attendancejourneys_get_string('journey', 'attendancejourneys') .
            ' — ' . format_string($activejourney->name);
    }
    $targetgroupid = 0;
    $targetjourneyid = 0;
    $validaudience = isset($audienceoptions[$bulkaudience]);
    if ($validaudience && str_starts_with($bulkaudience, 'group:')) {
        $targetgroupid = (int) substr($bulkaudience, 6);
    } else if ($validaudience && str_starts_with($bulkaudience, 'journey:')) {
        $targetjourneyid = (int) substr($bulkaudience, 8);
        $targetjourney = $activejourneys[$targetjourneyid] ?? null;
        $validaudience = (bool) $targetjourney;
        $targetgroupid = $targetjourney ? (int) $targetjourney->groupid : 0;
    }
    if ($bulkaudience !== '' && !$validaudience) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkaudienceinvalid', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $eligible = [];
    $protected = [];
    foreach ($selected as $selectedsession) {
        if (
            attendancejourneys_session_has_records((int) $selectedsession->id) ||
                attendancejourneys_session_has_final_results($selectedsession)
        ) {
            $protected[$selectedsession->id] = $selectedsession;
        } else {
            $eligible[$selectedsession->id] = $selectedsession;
        }
    }
    if (!$eligible) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkaudiencenone', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    ksort($eligible);
    $snapshotparts = [];
    foreach ($eligible as $session) {
        $snapshotparts[] = implode(':', [$session->id, $session->timemodified,
            $session->groupid, $session->journeyid]);
    }
    $currentsnapshot = hash('sha256', implode('|', $snapshotparts));
    if ($confirmbulk && !hash_equals($currentsnapshot, $bulksnapshot)) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkshiftsessionschanged', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'sessions');
    echo $OUTPUT->heading(attendancejourneys_get_string('bulkaudiencesessions', 'attendancejourneys'));
    echo $OUTPUT->notification(attendancejourneys_get_string('bulkaudienceintro', 'attendancejourneys'), 'info');
    if ($protected) {
        echo $OUTPUT->notification(
            attendancejourneys_get_string(
                'bulkaudienceprotected',
                'attendancejourneys',
                count($protected)
            ),
            'warning'
        );
    }
    if (!$validaudience) {
        echo html_writer::start_div('attendancejourneys-form-card');
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bulkaction', 'value' => 'editaudience']);
        foreach (array_keys($selected) as $selectedid) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectedsessions[]',
                'value' => $selectedid]);
        }
        echo html_writer::tag(
            'label',
            attendancejourneys_get_string('bulkaudiencetarget', 'attendancejourneys'),
            ['for' => 'attendancejourneys-audience-target', 'class' => 'font-weight-bold']
        );
        echo html_writer::select(
            $audienceoptions,
            'bulkaudience',
            '',
            ['' => get_string('choosedots')],
            ['id' => 'attendancejourneys-audience-target', 'class' => 'form-control mb-3']
        );
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string(
                'bulkaudiencetargethelp',
                'attendancejourneys'
            ),
            ['class' => 'text-muted']
        );
        echo html_writer::tag(
            'button',
            attendancejourneys_get_string('previewchanges', 'attendancejourneys'),
            ['type' => 'submit', 'class' => 'btn btn-primary mr-2 me-2']
        );
        echo html_writer::link($filteredurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }

    $changed = \mod_attendancejourneys\local\session_bulk_manager::preview_audience(
        $eligible,
        $targetgroupid,
        $targetjourneyid
    );
    foreach ($changed as $changedsession) {
        attendancejourneys_require_session_destination_access($cm, $context, $changedsession);
    }
    $conflicts = \mod_attendancejourneys\local\session_bulk_manager::find_conflicts(
        $changed,
        $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $attendancejourneys->id])
    );
    $audiencelabel = static function ($session) use ($allowedfilters, $DB): string {
        if (!empty($session->journeyid)) {
            return format_string($DB->get_field(
                'attendancejourneys_journeys',
                'name',
                ['id' => $session->journeyid]
            ) ?: '—');
        }
        return $allowedfilters[(int) $session->groupid] ?? attendancejourneys_get_string(
            'allactiveparticipants',
            'attendancejourneys'
        );
    };
    $preview = new html_table();
    $preview->head = [attendancejourneys_get_string(
        'sessionname',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        'currentvalue',
        'attendancejourneys'
    ),

        attendancejourneys_get_string('newvalue', 'attendancejourneys')];
    foreach ($changed as $changedsession) {
        $preview->data[] = [format_string($changedsession->name),
            $audiencelabel($eligible[$changedsession->id]), $audiencelabel($changedsession)];
    }
    echo attendancejourneys_responsive_table($preview, attendancejourneys_get_string('bulkaudiencepreview', 'attendancejourneys'));
    if ($conflicts) {
        echo $OUTPUT->notification(attendancejourneys_get_string(
            'bulkshiftconflicts',
            'attendancejourneys',
            count($conflicts)
        ), 'warning');
        $conflicttable = new html_table();
        $conflicttable->head = [attendancejourneys_get_string('proposedsession', 'attendancejourneys'),
            attendancejourneys_get_string('conflictswith', 'attendancejourneys')];
        foreach ($conflicts as $conflict) {
            $conflicttable->data[] = [format_string($conflict['session']->name) . html_writer::empty_tag('br') .
                userdate($conflict['session']->sessiondate, get_string('strftimedatetime', 'langconfig')),
                format_string($conflict['other']->name) . html_writer::empty_tag('br') .
                userdate($conflict['other']->sessiondate, get_string('strftimedatetime', 'langconfig'))];
        }
        echo attendancejourneys_responsive_table(
            $conflicttable,
            attendancejourneys_get_string(
                'bulkshiftconflictsheading',
                'attendancejourneys'
            )
        );
    }
    if ($confirmbulk && (!$conflicts || $allowconflicts)) {
        $transaction = $DB->start_delegated_transaction();
        foreach ($changed as $changedsession) {
            $changedsession->timemodified = max(time(), (int) $changedsession->timemodified + 1);
            $DB->update_record('attendancejourneys_sessions', $changedsession);
            attendancejourneys_update_calendar_event($attendancejourneys, $changedsession);
            \mod_attendancejourneys\event\session_updated::create([
                'objectid' => $changedsession->id, 'context' => $context,
                'other' => ['attendancejourneysid' => $attendancejourneys->id],
            ])->trigger();
        }
        $transaction->allow_commit();
        $participantids = array_keys(get_enrolled_users(
            $context,
            'mod/attendancejourneys:canbelisted',
            0,
            'u.id',
            null,
            0,
            0,
            true
        ));
        attendancejourneys_update_completion($course, $cm, $participantids);
        attendancejourneys_update_grades($attendancejourneys);
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkaudiencesuccess', 'attendancejourneys', count($changed)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'mt-3']);
    foreach (
        ['sesskey' => sesskey(), 'bulkaction' => 'editaudience', 'bulkaudience' => $bulkaudience,
            'confirmbulk' => 1, 'bulksnapshot' => $currentsnapshot] as $name => $value
    ) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    foreach (array_keys($eligible) as $eligibleid) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectedsessions[]',
            'value' => $eligibleid]);
    }
    if ($conflicts) {
        echo html_writer::checkbox(
            'allowconflicts',
            1,
            false,
            attendancejourneys_get_string('bulkshiftallowconflicts', 'attendancejourneys'),
            ['class' => 'mb-3']
        );
    }
    echo html_writer::tag(
        'button',
        attendancejourneys_get_string('confirmchanges', 'attendancejourneys'),
        ['type' => 'submit', 'class' => 'btn btn-primary mr-2 me-2']
    );
    echo html_writer::link($filteredurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

if ($bulkaction === 'editdetails') {
    require_sesskey();
    $selectedsessions = array_values(array_unique(array_map('intval', $selectedsessions)));
    $selected = $selectedsessions ? $DB->get_records_list('attendancejourneys_sessions', 'id', $selectedsessions) : [];
    foreach ($selected as $selectedsession) {
        attendancejourneys_require_session_management_access($cm, $context, $selectedsession);
    }
    if (!$selected) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkselectsessions', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    $validoperations = ['duration', 'modality', 'location', 'meetingurl', 'rename', 'number', 'description'];
    $numberingsuffixlength = $bulkoperation === 'number' ? 1 + strlen((string) count($selected)) : 0;
    $validchange = in_array($bulkoperation, $validoperations, true) &&
        (($bulkoperation === 'duration' && ctype_digit($bulkvalue) && (int) $bulkvalue >= 1 &&
                (int) $bulkvalue <= 1440) ||
            ($bulkoperation === 'modality' && in_array(
                $bulkvalue,
                ['unspecified', 'inperson', 'online', 'hybrid'],
                true
            )) ||
            (in_array($bulkoperation, ['rename', 'number'], true) && $bulkvalue !== '' &&
                core_text::strlen($bulkvalue) + $numberingsuffixlength <= 255) ||
            $bulkoperation === 'description' ||
            $bulkoperation === 'location' ||
            ($bulkoperation === 'meetingurl' && ($bulkvalue === '' || preg_match('#^https?://#i', $bulkvalue))));
    if ($bulkoperation !== '' && !$validchange) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkdetailsinvalid', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $eligible = [];
    $protected = [];
    foreach ($selected as $selectedsession) {
        if ($bulkoperation === 'duration' && attendancejourneys_session_has_final_results($selectedsession)) {
            $protected[$selectedsession->id] = $selectedsession;
        } else {
            $eligible[$selectedsession->id] = $selectedsession;
        }
    }
    if (!$eligible) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkdetailsnone', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    ksort($eligible);
    $snapshotparts = [];
    foreach ($eligible as $session) {
        $snapshotparts[] = implode(':', [$session->id, $session->timemodified, $session->duration,
            $session->modality, $session->location, $session->meetingurl,
            hash('sha256', $session->name), hash('sha256', $session->description)]);
    }
    $currentsnapshot = hash('sha256', implode('|', $snapshotparts));
    if ($confirmbulk && !hash_equals($currentsnapshot, $bulksnapshot)) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkshiftsessionschanged', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'sessions');
    echo $OUTPUT->heading(attendancejourneys_get_string('bulkdetailsessions', 'attendancejourneys'));
    echo $OUTPUT->notification(attendancejourneys_get_string('bulkdetailsintro', 'attendancejourneys'), 'info');
    if ($protected) {
        echo $OUTPUT->notification(
            attendancejourneys_get_string(
                'bulkdetailsprotected',
                'attendancejourneys',
                count($protected)
            ),
            'warning'
        );
    }
    if (!$validchange) {
        echo html_writer::start_div('attendancejourneys-form-card');
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bulkaction', 'value' => 'editdetails']);
        foreach (array_keys($selected) as $selectedid) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectedsessions[]',
                'value' => $selectedid]);
        }
        echo html_writer::tag(
            'label',
            attendancejourneys_get_string('bulkdetailsoperation', 'attendancejourneys'),
            ['for' => 'attendancejourneys-details-operation', 'class' => 'font-weight-bold']
        );
        echo html_writer::select(
            [
            'duration' => attendancejourneys_get_string('duration', 'attendancejourneys'),
            'modality' => attendancejourneys_get_string('sessionmodality', 'attendancejourneys'),
            'location' => attendancejourneys_get_string('sessionlocation', 'attendancejourneys'),
            'meetingurl' => attendancejourneys_get_string('sessionmeetingurl', 'attendancejourneys'),
            'rename' => attendancejourneys_get_string('bulkrenamecommon', 'attendancejourneys'),
            'number' => attendancejourneys_get_string('bulkrenamenumbered', 'attendancejourneys'),
            'description' => get_string('description'),
            ],
            'bulkoperation',
            '',
            ['' => get_string('choosedots')],
            ['id' => 'attendancejourneys-details-operation', 'class' => 'form-control mb-3']
        );
        echo html_writer::start_div('', ['id' => 'attendancejourneys-details-text-wrap']);
        echo html_writer::tag(
            'label',
            attendancejourneys_get_string('bulkdetailsvalue', 'attendancejourneys'),
            ['for' => 'attendancejourneys-details-value', 'class' => 'font-weight-bold']
        );
        echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'bulkvalue',
            'id' => 'attendancejourneys-details-value', 'class' => 'form-control mb-3']);
        echo html_writer::end_div();
        echo html_writer::start_div('', ['id' => 'attendancejourneys-details-description-wrap', 'hidden' => 'hidden']);
        echo html_writer::tag(
            'label',
            get_string('description'),
            ['for' => 'attendancejourneys-details-description', 'class' => 'font-weight-bold']
        );
        echo html_writer::tag('textarea', '', ['name' => 'bulkdescription',
            'id' => 'attendancejourneys-details-description', 'class' => 'form-control mb-3', 'rows' => 5]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bulkdescriptionpresent', 'value' => 1]);
        echo html_writer::end_div();
        echo html_writer::start_div('', ['id' => 'attendancejourneys-details-modality-wrap', 'hidden' => 'hidden']);
        echo html_writer::tag(
            'label',
            attendancejourneys_get_string('sessionmodality', 'attendancejourneys'),
            ['for' => 'attendancejourneys-details-modality', 'class' => 'font-weight-bold']
        );
        echo html_writer::select(
            [
            'unspecified' => attendancejourneys_get_string('modalityunspecified', 'attendancejourneys'),
            'inperson' => attendancejourneys_get_string('modalityinperson', 'attendancejourneys'),
            'online' => attendancejourneys_get_string('modalityonline', 'attendancejourneys'),
            'hybrid' => attendancejourneys_get_string('modalityhybrid', 'attendancejourneys'),
            ],
            'bulkmodality',
            'unspecified',
            false,
            ['id' => 'attendancejourneys-details-modality', 'class' => 'form-control mb-3']
        );
        echo html_writer::end_div();
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string('bulkdetailsvaluehelp', 'attendancejourneys'),
            ['class' => 'text-muted']
        );
        echo html_writer::tag(
            'button',
            attendancejourneys_get_string('previewchanges', 'attendancejourneys'),
            ['type' => 'submit', 'class' => 'btn btn-primary mr-2 me-2']
        );
        echo html_writer::link($filteredurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
        $PAGE->requires->js_call_amd('mod_attendancejourneys/bulkdetails', 'init');
        echo $OUTPUT->footer();
        exit;
    }

    $changed = \mod_attendancejourneys\local\session_bulk_manager::preview_change(
        $eligible,
        $bulkoperation,
        $bulkvalue
    );
    $conflicts = $bulkoperation === 'duration' ?
        \mod_attendancejourneys\local\session_bulk_manager::find_conflicts(
            $changed,
            $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $attendancejourneys->id])
        ) : [];
    $preview = new html_table();
    $preview->head = [attendancejourneys_get_string(
        'sessionname',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        'currentvalue',
        'attendancejourneys'
    ),

        attendancejourneys_get_string('newvalue', 'attendancejourneys')];
    foreach ($changed as $changedsession) {
        $original = $eligible[$changedsession->id];
        if ($bulkoperation === 'duration') {
            $oldvalue = attendancejourneys_get_string('minutesvalue', 'attendancejourneys', $original->duration);
            $newvalue = attendancejourneys_get_string('minutesvalue', 'attendancejourneys', $changedsession->duration);
        } else if ($bulkoperation === 'modality') {
            $oldvalue = attendancejourneys_get_string('modality' . $original->modality, 'attendancejourneys');
            $newvalue = attendancejourneys_get_string('modality' . $changedsession->modality, 'attendancejourneys');
        } else if (in_array($bulkoperation, ['rename', 'number'], true)) {
            $oldvalue = format_string($original->name);
            $newvalue = format_string($changedsession->name);
        } else if ($bulkoperation === 'description') {
            $oldvalue = s($original->description) ?: '—';
            $newvalue = s($changedsession->description) ?: '—';
        } else {
            $oldvalue = s($original->{$bulkoperation}) ?: '—';
            $newvalue = s($changedsession->{$bulkoperation}) ?: '—';
        }
        $preview->data[] = [format_string($original->name), $oldvalue, $newvalue];
    }
    echo attendancejourneys_responsive_table($preview, attendancejourneys_get_string('bulkdetailspreview', 'attendancejourneys'));
    if ($conflicts) {
        echo $OUTPUT->notification(attendancejourneys_get_string(
            'bulkshiftconflicts',
            'attendancejourneys',
            count($conflicts)
        ), 'warning');
        $conflicttable = new html_table();
        $conflicttable->head = [attendancejourneys_get_string('proposedsession', 'attendancejourneys'),
            attendancejourneys_get_string('conflictswith', 'attendancejourneys')];
        foreach ($conflicts as $conflict) {
            $conflicttable->data[] = [format_string($conflict['session']->name) . html_writer::empty_tag('br') .
                userdate($conflict['session']->sessiondate, get_string('strftimedatetime', 'langconfig')),
                format_string($conflict['other']->name) . html_writer::empty_tag('br') .
                userdate($conflict['other']->sessiondate, get_string('strftimedatetime', 'langconfig'))];
        }
        echo attendancejourneys_responsive_table(
            $conflicttable,
            attendancejourneys_get_string(
                'bulkshiftconflictsheading',
                'attendancejourneys'
            )
        );
    }
    if ($confirmbulk && (!$conflicts || $allowconflicts)) {
        $transaction = $DB->start_delegated_transaction();
        foreach ($changed as $changedsession) {
            $changedsession->timemodified = max(time(), (int) $changedsession->timemodified + 1);
            $DB->update_record('attendancejourneys_sessions', $changedsession);
            attendancejourneys_update_calendar_event($attendancejourneys, $changedsession);
            \mod_attendancejourneys\event\session_updated::create([
                'objectid' => $changedsession->id, 'context' => $context,
                'other' => ['attendancejourneysid' => $attendancejourneys->id],
            ])->trigger();
        }
        $transaction->allow_commit();
        $participantids = array_keys(get_enrolled_users(
            $context,
            'mod/attendancejourneys:canbelisted',
            0,
            'u.id',
            null,
            0,
            0,
            true
        ));
        attendancejourneys_update_completion($course, $cm, $participantids);
        attendancejourneys_update_grades($attendancejourneys);
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkdetailssuccess', 'attendancejourneys', count($changed)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'mt-3']);
    foreach (
        ['sesskey' => sesskey(), 'bulkaction' => 'editdetails', 'bulkoperation' => $bulkoperation,
            'bulkvalue' => $bulkvalue, 'confirmbulk' => 1, 'bulksnapshot' => $currentsnapshot] as $name => $value
    ) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    foreach (array_keys($eligible) as $eligibleid) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectedsessions[]',
            'value' => $eligibleid]);
    }
    if ($conflicts) {
        echo html_writer::checkbox(
            'allowconflicts',
            1,
            false,
            attendancejourneys_get_string('bulkshiftallowconflicts', 'attendancejourneys'),
            ['class' => 'mb-3']
        );
    }
    echo html_writer::tag(
        'button',
        attendancejourneys_get_string('confirmchanges', 'attendancejourneys'),
        ['type' => 'submit', 'class' => 'btn btn-primary mr-2 me-2']
    );
    echo html_writer::link($filteredurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

if ($bulkaction === 'shiftschedule') {
    require_sesskey();
    $selectedsessions = array_values(array_unique(array_map('intval', $selectedsessions)));
    $selected = $selectedsessions ? $DB->get_records_list('attendancejourneys_sessions', 'id', $selectedsessions) : [];
    foreach ($selected as $selectedsession) {
        attendancejourneys_require_session_management_access($cm, $context, $selectedsession);
    }
    if (!$selected) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkselectsessions', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    $eligible = [];
    $protected = [];
    foreach ($selected as $selectedsession) {
        if (attendancejourneys_session_has_final_results($selectedsession)) {
            $protected[$selectedsession->id] = $selectedsession;
        } else {
            $eligible[$selectedsession->id] = $selectedsession;
        }
    }
    if (!$eligible) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkshiftnone', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    ksort($eligible);
    $currentsnapshotparts = [];
    foreach ($eligible as $eligiblesession) {
        $currentsnapshotparts[] = implode(':', [
            $eligiblesession->id,
            $eligiblesession->timemodified,
            $eligiblesession->sessiondate,
            $eligiblesession->duration,
            $eligiblesession->groupid,
            $eligiblesession->journeyid,
        ]);
    }
    $currentsnapshot = hash('sha256', implode('|', $currentsnapshotparts));
    if ($confirmbulk && !hash_equals($currentsnapshot, $bulksnapshot)) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkshiftsessionschanged', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $validshift = in_array($shiftunit, ['days', 'minutes'], true) && $shiftvalue !== 0 &&
        (($shiftunit === 'days' && abs($shiftvalue) <= 366) ||
            ($shiftunit === 'minutes' && abs($shiftvalue) <= 1440));
    if ($shiftvalue !== 0 && !$validshift) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkshiftinvalid', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'sessions');
    echo $OUTPUT->heading(attendancejourneys_get_string('bulkshiftsessions', 'attendancejourneys'));
    echo $OUTPUT->notification(attendancejourneys_get_string('bulkshiftintro', 'attendancejourneys'), 'info');
    if ($protected) {
        echo $OUTPUT->notification(attendancejourneys_get_string(
            'bulkshiftprotected',
            'attendancejourneys',
            count($protected)
        ), 'warning');
    }

    if (!$validshift) {
        echo html_writer::start_div('attendancejourneys-form-card');
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bulkaction', 'value' => 'shiftschedule']);
        foreach (array_keys($selected) as $selectedid) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectedsessions[]',
                'value' => $selectedid]);
        }
        echo html_writer::tag(
            'label',
            attendancejourneys_get_string('bulkshiftunit', 'attendancejourneys'),
            ['for' => 'attendancejourneys-shift-unit', 'class' => 'font-weight-bold']
        );
        echo html_writer::select([
            'days' => attendancejourneys_get_string('bulkshiftdays', 'attendancejourneys'),
            'minutes' => attendancejourneys_get_string('bulkshiftminutes', 'attendancejourneys'),
        ], 'shiftunit', 'days', false, ['id' => 'attendancejourneys-shift-unit', 'class' => 'form-control mb-3']);
        echo html_writer::tag(
            'label',
            attendancejourneys_get_string('bulkshiftvalue', 'attendancejourneys'),
            ['for' => 'attendancejourneys-shift-value', 'class' => 'font-weight-bold']
        );
        echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'shiftvalue', 'value' => '',
            'required' => 'required', 'id' => 'attendancejourneys-shift-value', 'class' => 'form-control mb-3']);
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string('bulkshiftvaluehelp', 'attendancejourneys'),
            ['class' => 'text-muted']
        );
        echo html_writer::tag(
            'button',
            attendancejourneys_get_string('previewchanges', 'attendancejourneys'),
            ['type' => 'submit', 'class' => 'btn btn-primary mr-2 me-2']
        );
        echo html_writer::link($filteredurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }

    $shifted = \mod_attendancejourneys\local\session_bulk_manager::preview_shift(
        $eligible,
        $shiftunit,
        $shiftvalue,
        \core_date::get_user_timezone_object()
    );
    $allsessionsforconflicts = $DB->get_records('attendancejourneys_sessions', [
        'attendancejourneysid' => $attendancejourneys->id,
    ]);
    $conflicts = \mod_attendancejourneys\local\session_bulk_manager::find_conflicts(
        $shifted,
        $allsessionsforconflicts
    );
    $preview = new html_table();
    $preview->head = [attendancejourneys_get_string(
        'sessionname',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        'currentdate',
        'attendancejourneys'
    ),

        attendancejourneys_get_string('newdate', 'attendancejourneys')];
    foreach ($shifted as $shiftedsession) {
        $original = $eligible[$shiftedsession->id];
        $preview->data[] = [format_string($shiftedsession->name),
            userdate($original->sessiondate, get_string('strftimedatetime', 'langconfig')),
            userdate($shiftedsession->sessiondate, get_string('strftimedatetime', 'langconfig'))];
    }
    echo attendancejourneys_responsive_table($preview, attendancejourneys_get_string('bulkshiftpreview', 'attendancejourneys'));
    if ($conflicts) {
        echo $OUTPUT->notification(attendancejourneys_get_string(
            'bulkshiftconflicts',
            'attendancejourneys',
            count($conflicts)
        ), 'warning');
        $conflicttable = new html_table();
        $conflicttable->head = [attendancejourneys_get_string('proposedsession', 'attendancejourneys'),
            attendancejourneys_get_string('conflictswith', 'attendancejourneys')];
        foreach ($conflicts as $conflict) {
            $conflicttable->data[] = [format_string($conflict['session']->name) . html_writer::empty_tag('br') .
                userdate($conflict['session']->sessiondate, get_string('strftimedatetime', 'langconfig')),
                format_string($conflict['other']->name) . html_writer::empty_tag('br') .
                userdate($conflict['other']->sessiondate, get_string('strftimedatetime', 'langconfig'))];
        }
        echo attendancejourneys_responsive_table(
            $conflicttable,
            attendancejourneys_get_string(
                'bulkshiftconflictsheading',
                'attendancejourneys'
            )
        );
    }

    if ($confirmbulk && (!$conflicts || $allowconflicts)) {
        $transaction = $DB->start_delegated_transaction();
        foreach ($shifted as $shiftedsession) {
            $shiftedsession->timemodified = max(time(), (int) $shiftedsession->timemodified + 1);
            $DB->update_record('attendancejourneys_sessions', $shiftedsession);
            attendancejourneys_update_calendar_event($attendancejourneys, $shiftedsession);
            \mod_attendancejourneys\event\session_updated::create([
                'objectid' => $shiftedsession->id, 'context' => $context,
                'other' => ['attendancejourneysid' => $attendancejourneys->id],
            ])->trigger();
        }
        $transaction->allow_commit();
        $participantids = array_keys(get_enrolled_users(
            $context,
            'mod/attendancejourneys:canbelisted',
            0,
            'u.id',
            null,
            0,
            0,
            true
        ));
        attendancejourneys_update_completion($course, $cm, $participantids);
        attendancejourneys_update_grades($attendancejourneys);
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkshiftsuccess', 'attendancejourneys', count($shifted)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'mt-3']);
    foreach (
        ['sesskey' => sesskey(), 'bulkaction' => 'shiftschedule', 'shiftunit' => $shiftunit,
            'shiftvalue' => $shiftvalue, 'confirmbulk' => 1, 'bulksnapshot' => $currentsnapshot] as $name => $value
    ) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    foreach (array_keys($eligible) as $eligibleid) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectedsessions[]',
            'value' => $eligibleid]);
    }
    if ($conflicts) {
        echo html_writer::checkbox(
            'allowconflicts',
            1,
            false,
            attendancejourneys_get_string('bulkshiftallowconflicts', 'attendancejourneys'),
            ['class' => 'mb-3']
        );
    }
    echo html_writer::tag(
        'button',
        attendancejourneys_get_string('confirmchanges', 'attendancejourneys'),
        ['type' => 'submit', 'class' => 'btn btn-primary mr-2 me-2']
    );
    echo html_writer::link($filteredurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

if ($bulkaction === 'duplicateschedule') {
    require_sesskey();
    $selectedsessions = array_values(array_unique(array_map('intval', $selectedsessions)));
    $selected = $selectedsessions ? $DB->get_records_list('attendancejourneys_sessions', 'id', $selectedsessions) : [];
    foreach ($selected as $selectedsession) {
        attendancejourneys_require_session_management_access($cm, $context, $selectedsession);
    }
    if (!$selected) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkselectsessions', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    $audienceoptions = ['keep' => attendancejourneys_get_string('bulkduplicatekeepsource', 'attendancejourneys')];
    if (empty($attendancejourneys->journeygrading)) {
        $audienceoptions['common'] = attendancejourneys_get_string('allactiveparticipants', 'attendancejourneys');
    }
    foreach ($allowedfilters as $filterid => $filtername) {
        if (empty($attendancejourneys->journeygrading) && (int) $filterid > 0) {
            $audienceoptions['group:' . $filterid] = get_string('group') . ' — ' . $filtername;
        }
    }
    $activejourneys = $DB->get_records('attendancejourneys_journeys', [
        'attendancejourneysid' => $attendancejourneys->id, 'active' => 1,
    ], 'name ASC');
    foreach ($activejourneys as $activejourney) {
        if (!attendancejourneys_session_is_visible($cm, $context, $activejourney)) {
            continue;
        }
        $audienceoptions['journey:' . $activejourney->id] = attendancejourneys_get_string('journey', 'attendancejourneys') .
            ' — ' . format_string($activejourney->name);
    }
    $targetgroupid = 0;
    $targetjourneyid = 0;
    $validaudience = isset($audienceoptions[$bulkaudience]);
    if ($validaudience && str_starts_with($bulkaudience, 'group:')) {
        $targetgroupid = (int) substr($bulkaudience, 6);
    } else if ($validaudience && str_starts_with($bulkaudience, 'journey:')) {
        $targetjourneyid = (int) substr($bulkaudience, 8);
        $targetjourney = $activejourneys[$targetjourneyid] ?? null;
        $validaudience = (bool) $targetjourney;
        $targetgroupid = $targetjourney ? (int) $targetjourney->groupid : 0;
    }
    if ($bulkaudience !== '' && !$validaudience) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkaudienceinvalid', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $eligible = [];
    $protected = [];
    foreach ($selected as $selectedsession) {
        if (attendancejourneys_session_has_final_results($selectedsession)) {
            $protected[$selectedsession->id] = $selectedsession;
        } else {
            $eligible[$selectedsession->id] = $selectedsession;
        }
    }
    if (!$eligible) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkduplicatenone', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    ksort($eligible);
    $snapshotparts = [];
    foreach ($eligible as $session) {
        $snapshotparts[] = implode(':', [$session->id, $session->timemodified, $session->sessiondate,
            $session->duration, $session->groupid, $session->journeyid]);
    }
    $currentsnapshot = hash('sha256', implode('|', $snapshotparts));
    if ($confirmbulk && !hash_equals($currentsnapshot, $bulksnapshot)) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkshiftsessionschanged', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $validshift = in_array($shiftunit, ['days', 'minutes'], true) && $shiftvalue !== 0 &&
        (($shiftunit === 'days' && abs($shiftvalue) <= 366) ||
            ($shiftunit === 'minutes' && abs($shiftvalue) <= 1440));
    if ($shiftvalue !== 0 && !$validshift) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkshiftinvalid', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'sessions');
    echo $OUTPUT->heading(attendancejourneys_get_string('bulkduplicatesessions', 'attendancejourneys'));
    echo $OUTPUT->notification(attendancejourneys_get_string('bulkduplicateintro', 'attendancejourneys'), 'info');
    if ($protected) {
        echo $OUTPUT->notification(
            attendancejourneys_get_string('bulkduplicateprotected', 'attendancejourneys', count($protected)),
            'warning'
        );
    }
    if (!$validshift || !$validaudience) {
        echo html_writer::start_div('attendancejourneys-form-card');
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bulkaction',
            'value' => 'duplicateschedule']);
        foreach (array_keys($selected) as $selectedid) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectedsessions[]',
                'value' => $selectedid]);
        }
        echo html_writer::tag(
            'label',
            attendancejourneys_get_string('bulkshiftunit', 'attendancejourneys'),
            ['for' => 'attendancejourneys-copy-unit', 'class' => 'font-weight-bold']
        );
        echo html_writer::select([
            'days' => attendancejourneys_get_string('bulkshiftdays', 'attendancejourneys'),
            'minutes' => attendancejourneys_get_string('bulkshiftminutes', 'attendancejourneys'),
        ], 'shiftunit', 'days', false, ['id' => 'attendancejourneys-copy-unit', 'class' => 'form-control mb-3']);
        echo html_writer::tag(
            'label',
            attendancejourneys_get_string('bulkshiftvalue', 'attendancejourneys'),
            ['for' => 'attendancejourneys-copy-value', 'class' => 'font-weight-bold']
        );
        echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'shiftvalue', 'value' => '',
            'required' => 'required', 'id' => 'attendancejourneys-copy-value', 'class' => 'form-control mb-3']);
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string('bulkshiftvaluehelp', 'attendancejourneys'),
            ['class' => 'text-muted']
        );
        echo html_writer::tag(
            'label',
            attendancejourneys_get_string('bulkaudiencetarget', 'attendancejourneys'),
            ['for' => 'attendancejourneys-copy-audience', 'class' => 'font-weight-bold']
        );
        echo html_writer::select(
            $audienceoptions,
            'bulkaudience',
            'keep',
            false,
            ['id' => 'attendancejourneys-copy-audience', 'class' => 'form-control mb-3']
        );
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string('bulkduplicateaudiencehelp', 'attendancejourneys'),
            ['class' => 'text-muted']
        );
        echo html_writer::tag(
            'button',
            attendancejourneys_get_string('previewchanges', 'attendancejourneys'),
            ['type' => 'submit', 'class' => 'btn btn-primary mr-2 me-2']
        );
        echo html_writer::link($filteredurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }

    $copies = \mod_attendancejourneys\local\session_bulk_manager::preview_copies(
        $eligible,
        $shiftunit,
        $shiftvalue,
        \core_date::get_user_timezone_object()
    );
    if ($bulkaudience !== 'keep') {
        $copies = \mod_attendancejourneys\local\session_bulk_manager::preview_audience(
            $copies,
            $targetgroupid,
            $targetjourneyid
        );
    }
    foreach ($copies as $copy) {
        attendancejourneys_require_session_destination_access($cm, $context, $copy);
    }
    $allsessions = $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $attendancejourneys->id]);
    $conflicts = \mod_attendancejourneys\local\session_bulk_manager::find_conflicts($copies, $allsessions);
    $preview = new html_table();
    $audiencelabel = static function ($session) use ($allowedfilters, $DB): string {
        if (!empty($session->journeyid)) {
            return attendancejourneys_get_string('journey', 'attendancejourneys') . ' — ' . format_string($DB->get_field(
                'attendancejourneys_journeys',
                'name',
                ['id' => $session->journeyid]
            ) ?: '—');
        }
        if (!empty($session->groupid)) {
            return get_string('group') . ' — ' .
                ($allowedfilters[(int) $session->groupid] ?? get_string('unknown'));
        }
        return attendancejourneys_get_string('allactiveparticipants', 'attendancejourneys');
    };
    $preview->head = [attendancejourneys_get_string(
        'sessionname',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        'currentdate',
        'attendancejourneys'
    ),

        attendancejourneys_get_string('newdate', 'attendancejourneys'),
            attendancejourneys_get_string('currentvalue', 'attendancejourneys'),
        attendancejourneys_get_string('newvalue', 'attendancejourneys')];
    foreach ($copies as $copy) {
        $original = $eligible[$copy->sourceid];
        $preview->data[] = [format_string($copy->name),
            userdate($original->sessiondate, get_string('strftimedatetime', 'langconfig')),
            userdate($copy->sessiondate, get_string('strftimedatetime', 'langconfig')),
            $audiencelabel($original), $audiencelabel($copy)];
    }
    echo attendancejourneys_responsive_table($preview, attendancejourneys_get_string('bulkduplicatepreview', 'attendancejourneys'));
    if ($conflicts) {
        echo $OUTPUT->notification(attendancejourneys_get_string(
            'bulkshiftconflicts',
            'attendancejourneys',
            count($conflicts)
        ), 'warning');
        $conflicttable = new html_table();
        $conflicttable->head = [attendancejourneys_get_string('proposedsession', 'attendancejourneys'),
            attendancejourneys_get_string('conflictswith', 'attendancejourneys')];
        foreach ($conflicts as $conflict) {
            $conflicttable->data[] = [format_string($conflict['session']->name) . html_writer::empty_tag('br') .
                userdate($conflict['session']->sessiondate, get_string('strftimedatetime', 'langconfig')),
                format_string($conflict['other']->name) . html_writer::empty_tag('br') .
                userdate($conflict['other']->sessiondate, get_string('strftimedatetime', 'langconfig'))];
        }
        echo attendancejourneys_responsive_table(
            $conflicttable,
            attendancejourneys_get_string(
                'bulkshiftconflictsheading',
                'attendancejourneys'
            )
        );
    }
    if ($confirmbulk && (!$conflicts || $allowconflicts)) {
        $transaction = $DB->start_delegated_transaction();
        $created = 0;
        foreach ($copies as $copy) {
            unset($copy->id, $copy->sourceid);
            $copy->timecreated = time();
            $copy->timemodified = time();
            $copy->cancelled = 0;
            $copy->id = $DB->insert_record('attendancejourneys_sessions', $copy);
            attendancejourneys_update_calendar_event($attendancejourneys, $copy);
            \mod_attendancejourneys\event\session_created::create([
                'objectid' => $copy->id, 'context' => $context,
                'other' => ['attendancejourneysid' => $attendancejourneys->id],
            ])->trigger();
            $created++;
        }
        $transaction->allow_commit();
        $participantids = array_keys(get_enrolled_users(
            $context,
            'mod/attendancejourneys:canbelisted',
            0,
            'u.id',
            null,
            0,
            0,
            true
        ));
        attendancejourneys_update_completion($course, $cm, $participantids);
        attendancejourneys_update_grades($attendancejourneys);
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkduplicatesuccess', 'attendancejourneys', $created),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'mt-3']);
    foreach (
        ['sesskey' => sesskey(), 'bulkaction' => 'duplicateschedule', 'shiftunit' => $shiftunit,
            'shiftvalue' => $shiftvalue, 'bulkaudience' => $bulkaudience, 'confirmbulk' => 1,
            'bulksnapshot' => $currentsnapshot] as $name => $value
    ) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    foreach (array_keys($eligible) as $eligibleid) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectedsessions[]',
            'value' => $eligibleid]);
    }
    if ($conflicts) {
        echo html_writer::checkbox(
            'allowconflicts',
            1,
            false,
            attendancejourneys_get_string('bulkshiftallowconflicts', 'attendancejourneys'),
            ['class' => 'mb-3']
        );
    }
    echo html_writer::tag(
        'button',
        attendancejourneys_get_string('bulkduplicateconfirm', 'attendancejourneys'),
        ['type' => 'submit', 'class' => 'btn btn-primary mr-2 me-2']
    );
    echo html_writer::link($filteredurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

if ($bulkaction === 'deleteempty') {
    require_sesskey();
    $selectedsessions = array_values(array_unique(array_map('intval', $selectedsessions)));
    $selected = $selectedsessions ? $DB->get_records_list('attendancejourneys_sessions', 'id', $selectedsessions) : [];
    foreach ($selected as $selectedsession) {
        attendancejourneys_require_session_management_access($cm, $context, $selectedsession);
    }
    if (!$selected) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkselectsessions', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    $deletable = [];
    $blocked = [];
    foreach ($selected as $selectedsession) {
        if (
            attendancejourneys_session_has_records((int) $selectedsession->id) ||
                attendancejourneys_session_deletion_blocker($selectedsession) !== null
        ) {
            $blocked[$selectedsession->id] = $selectedsession;
        } else {
            $deletable[$selectedsession->id] = $selectedsession;
        }
    }
    if (!$deletable) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('bulkdeletesessionsnone', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    if (!$confirmbulk) {
        echo $OUTPUT->header();
        attendancejourneys_print_navigation($cm, $context, 'sessions');
        echo $OUTPUT->heading(attendancejourneys_get_string('bulkdeletesessions', 'attendancejourneys'));
        echo $OUTPUT->notification(attendancejourneys_get_string('bulkdeletesessionswarning', 'attendancejourneys'), 'warning');
        $preview = new html_table();
        $preview->head = [attendancejourneys_get_string(
            'sessionname',
            'attendancejourneys'
        ),
        attendancejourneys_get_string(
            'sessiondate',
            'attendancejourneys'
        ),

            get_string('status')];
        foreach ($selected as $selectedsession) {
            $preview->data[] = [
                format_string($selectedsession->name),
                userdate($selectedsession->sessiondate, get_string('strftimedatetime', 'langconfig')),
                isset($deletable[$selectedsession->id]) ? attendancejourneys_get_string(
                    'bulkdeletesessioneligible',
                    'attendancejourneys'
                ) :
                    attendancejourneys_get_string('bulkdeletesessionblocked', 'attendancejourneys'),
            ];
        }
        echo attendancejourneys_responsive_table(
            $preview,
            attendancejourneys_get_string('bulkdeletesessions', 'attendancejourneys')
        );
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bulkaction', 'value' => 'deleteempty']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirmbulk', 'value' => 1]);
        foreach (array_keys($deletable) as $deletableid) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectedsessions[]',
                'value' => $deletableid]);
        }
        echo html_writer::tag('button', get_string('confirm'), ['type' => 'submit', 'class' => 'btn btn-danger mr-2 me-2']);
        echo html_writer::link($filteredurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
        echo $OUTPUT->footer();
        exit;
    }
    $transaction = $DB->start_delegated_transaction();
    foreach ($deletable as $deletablesession) {
        $event = \mod_attendancejourneys\event\session_deleted::create([
            'objectid' => $deletablesession->id,
            'context' => $context,
            'other' => ['attendancejourneysid' => (int) $attendancejourneys->id],
        ]);
        $event->add_record_snapshot('attendancejourneys_sessions', $deletablesession);
        attendancejourneys_delete_calendar_event((int) $attendancejourneys->id, (int) $deletablesession->id);
        $DB->delete_records('attendancejourneys_sessions', ['id' => $deletablesession->id]);
        $event->trigger();
    }
    $transaction->allow_commit();
    $participantids = array_keys(get_enrolled_users(
        $context,
        'mod/attendancejourneys:canbelisted',
        0,
        'u.id',
        null,
        0,
        0,
        true
    ));
    attendancejourneys_update_completion($course, $cm, $participantids);
    attendancejourneys_update_grades($attendancejourneys);
    redirect(
        $filteredurl,
        attendancejourneys_get_string('bulkdeletesessionssuccess', 'attendancejourneys', count($deletable)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if (in_array($action, ['cancel', 'reinstate', 'cancellationhistory'], true) && $sessionid) {
    require_capability('mod/attendancejourneys:managejourneys', $context);
    if (empty($attendancejourneys->journeygrading)) {
        throw new moodle_exception('sessioncancellationrequiresjourneygrading', 'attendancejourneys');
    }
    $session = $DB->get_record('attendancejourneys_sessions', [
        'id' => $sessionid, 'attendancejourneysid' => $attendancejourneys->id,
    ], '*', MUST_EXIST);
    attendancejourneys_require_session_management_access($cm, $context, $session);
    if ($action === 'cancellationhistory') {
        echo $OUTPUT->header();
        attendancejourneys_print_navigation($cm, $context, 'sessions');
        echo $OUTPUT->heading(attendancejourneys_get_string('sessioncancellationhistory', 'attendancejourneys'));
        echo $OUTPUT->heading(format_string($session->name), 3);
        $historytable = new html_table();
        $historytable->head = [get_string('date'), get_string('action'),
            attendancejourneys_get_string('performedby', 'attendancejourneys'),
            attendancejourneys_get_string('sessioncancellationreason', 'attendancejourneys')];
        foreach ($DB->get_records('attendancejourneys_sessionlog', ['sessionid' => $sessionid], 'id') as $entry) {
            $actor = $entry->actorid ? core_user::get_user($entry->actorid) : false;
            $historytable->data[] = [userdate($entry->timecreated),
                attendancejourneys_get_string('sessionaction' . $entry->action, 'attendancejourneys'),
                s($actor ? fullname($actor) : get_string('unknownuser')),
                format_text($entry->reason ?? '', FORMAT_PLAIN)];
        }
        echo attendancejourneys_responsive_table(
            $historytable,
            attendancejourneys_get_string('sessioncancellationhistory', 'attendancejourneys')
        );
        echo html_writer::link($filteredurl, get_string('back'));
        echo $OUTPUT->footer();
        exit;
    }
    $title = attendancejourneys_get_string($action === 'cancel' ? 'cancelsession' : 'reinstatesession', 'attendancejourneys');
    $form = new \mod_attendancejourneys\form\session_cancellation(
        new moodle_url($baseurl, ['sessionid' => $sessionid, 'action' => $action]),
        ['submitlabel' => $title]
    );
    $form->set_data((object) ['id' => $cm->id, 'sessionid' => $sessionid, 'action' => $action,
        'expected' => $action === 'cancel' ? 0 : 1, 'version' => (int) $session->timemodified]);
    if ($form->is_cancelled()) {
        redirect($filteredurl);
    }
    if ($data = $form->get_data()) {
        \mod_attendancejourneys\local\session_cancellation::change(
            $cm,
            $sessionid,
            $action === 'cancel',
            (int) $data->expected,
            $data->reason,
            required_param('version', PARAM_INT),
            $writelock ?? null
        );
        redirect(
            $filteredurl,
            attendancejourneys_get_string('sessioncancellationsaved', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'sessions');
    echo $OUTPUT->heading($title);
    echo $OUTPUT->heading(format_string($session->name), 3);
    echo $OUTPUT->notification(attendancejourneys_get_string('sessioncancellationhelp', 'attendancejourneys'), 'info');
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'delete' && $sessionid) {
    $session = $DB->get_record('attendancejourneys_sessions', [
        'id' => $sessionid,
        'attendancejourneysid' => $attendancejourneys->id,
    ], '*', MUST_EXIST);

    attendancejourneys_require_session_management_access($cm, $context, $session);

    if ($deletionblocker = attendancejourneys_session_deletion_blocker($session)) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string($deletionblocker, 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    if (optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        $event = \mod_attendancejourneys\event\session_deleted::create([
            'objectid' => $session->id, 'context' => $context,
            'other' => ['attendancejourneysid' => $attendancejourneys->id],
        ]);
        $event->add_record_snapshot('attendancejourneys_sessions', $session);
        attendancejourneys_delete_calendar_event((int) $attendancejourneys->id, (int) $session->id);
        attendancejourneys_delete_review_history(array_keys($DB->get_records(
            'attendancejourneys_records',
            ['sessionid' => $session->id],
            '',
            'id'
        )));
        $DB->delete_records('attendancejourneys_records', ['sessionid' => $session->id]);
        $DB->delete_records('attendancejourneys_sessions', ['id' => $session->id]);
        $event->trigger();
        $participantids = array_keys(get_enrolled_users(
            $context,
            'mod/attendancejourneys:canbelisted',
            0,
            'u.id',
            null,
            0,
            0,
            true
        ));
        attendancejourneys_update_completion($course, $cm, $participantids);
        attendancejourneys_update_grades($attendancejourneys);
        redirect(
            $filteredurl,
            attendancejourneys_get_string(
                'sessiondeleted',
                'attendancejourneys'
            ),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'sessions');
    echo $OUTPUT->heading(attendancejourneys_get_string('deletesession', 'attendancejourneys'));
    $confirmurl = new moodle_url($baseurl, [
        'action' => 'delete',
        'sessionid' => $session->id,
        'confirm' => 1,
        'sesskey' => sesskey(),
        'groupfilter' => $groupfilter,
    ]);
    echo $OUTPUT->confirm(
        attendancejourneys_get_string('deletesessionconfirm', 'attendancejourneys', format_string($session->name)),
        $confirmurl,
        $filteredurl
    );
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'clear' && $sessionid) {
    $session = $DB->get_record('attendancejourneys_sessions', [
        'id' => $sessionid,
        'attendancejourneysid' => $attendancejourneys->id,
    ], '*', MUST_EXIST);

    attendancejourneys_require_session_management_access($cm, $context, $session);
    if (!empty($session->cancelled)) {
        throw new moodle_exception('sessioncancelledrecordingunavailable', 'attendancejourneys');
    }

    if (attendancejourneys_session_has_final_results($session)) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('sessionclearblockedfinal', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $records = $DB->get_records('attendancejourneys_records', ['sessionid' => $session->id], '', 'id,userid');
    if (!$records) {
        redirect(
            $filteredurl,
            attendancejourneys_get_string('sessionclearalreadyempty', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }

    if (optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        $participantids = attendancejourneys_clear_session_records($session);
        \mod_attendancejourneys\event\session_attendance_cleared::create([
            'objectid' => $session->id,
            'context' => $context,
            'other' => [
                'attendancejourneysid' => (int) $attendancejourneys->id,
                'recordcount' => count($records),
            ],
        ])->trigger();
        attendancejourneys_update_completion($course, $cm, $participantids);
        attendancejourneys_update_grades($attendancejourneys);
        redirect(
            $filteredurl,
            attendancejourneys_get_string('sessioncleared', 'attendancejourneys', count($records)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'sessions');
    echo $OUTPUT->heading(attendancejourneys_get_string('clearsessionattendance', 'attendancejourneys'));
    $confirmurl = new moodle_url($baseurl, [
        'action' => 'clear',
        'sessionid' => $session->id,
        'confirm' => 1,
        'sesskey' => sesskey(),
        'groupfilter' => $groupfilter,
    ]);
    echo $OUTPUT->confirm(
        attendancejourneys_get_string('clearsessionattendanceconfirm', 'attendancejourneys', (object) [
            'name' => format_string($session->name),
            'records' => count($records),
        ]),
        $confirmurl,
        $filteredurl
    );
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'add' || $action === 'series' || (($action === 'edit' || $action === 'duplicate') && $sessionid)) {
    $journeys = $DB->get_records(
        'attendancejourneys_journeys',
        ['attendancejourneysid' => $attendancejourneys->id, 'active' => 1],
        'name ASC'
    );
    $rooms = $DB->get_records_select(
        'attendancejourneys_rooms',
        'attendancejourneysid = :activityid AND (active = 1 OR id = :selectedroom)',
        ['activityid' => $attendancejourneys->id, 'selectedroom' => 0],
        'name ASC'
    );
    $loadedsession = null;
    if (($action === 'edit' || $action === 'duplicate') && $sessionid) {
        $loadedsession = $DB->get_record('attendancejourneys_sessions', [
            'id' => $sessionid,
            'attendancejourneysid' => $attendancejourneys->id,
        ], '*', MUST_EXIST);
        attendancejourneys_require_session_management_access($cm, $context, $loadedsession);
        if (!empty($loadedsession->roomid) && !isset($rooms[$loadedsession->roomid])) {
            $historicalroom = $DB->get_record('attendancejourneys_rooms', [
                'id' => $loadedsession->roomid, 'attendancejourneysid' => $attendancejourneys->id,
            ]);
            if ($historicalroom) {
                $rooms[$historicalroom->id] = $historicalroom;
            }
        }
        if ($action === 'edit' && !empty($loadedsession->journeyid) && !isset($journeys[$loadedsession->journeyid])) {
            $historicaljourney = $DB->get_record('attendancejourneys_journeys', [
                'id' => $loadedsession->journeyid,
                'attendancejourneysid' => $attendancejourneys->id,
            ]);
            if ($historicaljourney) {
                $journeys[$historicaljourney->id] = $historicaljourney;
            }
        }
    }
    foreach ($journeys as $candidateid => $candidate) {
        if (!attendancejourneys_session_is_visible($cm, $context, $candidate)) {
            unset($journeys[$candidateid]);
        }
    }
    $customdata = ['cm' => $cm, 'attendancejourneys' => $attendancejourneys, 'series' => $action === 'series',
        'journeys' => $journeys, 'rooms' => $rooms];
    $formurlparams = ['id' => $cm->id, 'action' => $action];
    if ($groupfilter >= 0) {
        $formurlparams['groupfilter'] = $groupfilter;
    }
    if ($sessionid) {
        $formurlparams['sessionid'] = $sessionid;
    }
    if ($journeyid) {
        $formurlparams['journeyid'] = $journeyid;
    }
    $formurl = new moodle_url('/mod/attendancejourneys/sessions.php', $formurlparams);
    $form = new \mod_attendancejourneys\form\session($formurl, $customdata);

    if ($form->is_cancelled()) {
        redirect($filteredurl);
    }

    if ($action === 'edit' || $action === 'duplicate') {
        $session = clone $loadedsession;
        $session->id = $cm->id;
        $session->sessionid = $action === 'edit' ? $sessionid : 0;
        $session->enddate = $session->sessiondate + ($session->duration * MINSECS);
        $form->set_data($session);
    } else {
        $defaultstart = time();
        if (!$journeyid && !empty($attendancejourneys->journeygrading) && count($journeys) === 1) {
            $journeyid = (int) array_key_first($journeys);
        }
        $defaultjourney = isset($journeys[$journeyid]) ? $journeys[$journeyid] : null;
        $form->set_data((object) [
            'id' => $cm->id,
            'sessiondate' => $defaultstart,
            'enddate' => $defaultstart + HOURSECS,
            'groupid' => $groupfilter >= 0 ? $groupfilter : 0,
            'journeyid' => $defaultjourney ? $journeyid : 0,
            'modality' => $defaultjourney ? $defaultjourney->defaultmodality : 'unspecified',
        ]);
    }

    if ($data = $form->get_data()) {
        $journeyid = (int) ($data->journeyid ?? 0);
        $journey = null;
        if ($journeyid) {
            $journeyconditions = ['id' => $journeyid, 'attendancejourneysid' => $attendancejourneys->id];
            $editinghistoricaljourney = $action === 'edit' && $loadedsession &&
                (int) $loadedsession->journeyid === $journeyid;
            if (!$editinghistoricaljourney) {
                $journeyconditions['active'] = 1;
            }
            $journey = $DB->get_record('attendancejourneys_journeys', $journeyconditions, '*', MUST_EXIST);
        }
        $durationseconds = $data->enddate - $data->sessiondate;
        $durationminutes = (int) ceil($durationseconds / MINSECS);
        $record = (object) [
            'attendancejourneysid' => $attendancejourneys->id,
            'journeyid' => $journeyid,
            'name' => trim($data->name),
            'sessiondate' => $data->sessiondate,
            'duration' => $durationminutes,
            'groupid' => $journey ? (int) $journey->groupid : (int) $data->groupid,
            'roomid' => (int) ($data->roomid ?? 0),
            'description' => trim($data->description ?? ''),
            'modality' => $data->modality ?? 'unspecified',
            'location' => trim($data->location ?? ''),
            'meetingurl' => trim($data->meetingurl ?? ''),
            'timemodified' => time(),
        ];
        if ($record->roomid) {
            $selectedroom = $rooms[$record->roomid] ?? null;
            if (!$selectedroom) {
                throw new moodle_exception('invalidroom', 'attendancejourneys');
            }
            $record->location = trim($selectedroom->name);
            if (!empty($selectedroom->location)) {
                $record->location .= ' — ' . trim($selectedroom->location);
            }
        }

        if ($action !== 'edit' || attendancejourneys_session_audience_changed($loadedsession, $record)) {
            attendancejourneys_require_session_destination_access($cm, $context, $record);
        }

        if ($action === 'series') {
            $count = (int) $data->repeatcount;
            $intervaldays = (int) $data->repeatinterval;
            $occurrences = \mod_attendancejourneys\local\series_builder::generate(
                $record->name,
                (int) $data->sessiondate,
                (int) $durationseconds,
                $count,
                $intervaldays,
                $data->seriesnamemode ?? 'same',
                \core_date::get_user_timezone_object(),
                [
                    'modality' => $record->modality,
                    'location' => $record->location,
                    'meetingurl' => $record->meetingurl,
                    'description' => $record->description,
                ]
            );

            if (!empty($data->reviewseries)) {
                $drafttoken = bin2hex(random_bytes(16));
                $SESSION->attendancejourneys_series_drafts[$drafttoken] = [
                    'userid' => (int) $USER->id,
                    'cmid' => (int) $cm->id,
                    'timecreated' => time(),
                    'common' => (array) $record,
                    'occurrences' => $occurrences,
                ];
                redirect(new moodle_url($baseurl, ['action' => 'seriesreview', 'draft' => $drafttoken]));
            }

            $transaction = $DB->start_delegated_transaction();
            foreach ($occurrences as $occurrence) {
                $record->sessiondate = $occurrence['sessiondate'];
                $record->timecreated = time();
                $occurrencerecord = clone $record;
                $occurrencerecord->id = $DB->insert_record('attendancejourneys_sessions', $occurrencerecord);
                attendancejourneys_update_calendar_event($attendancejourneys, $occurrencerecord);
                \mod_attendancejourneys\event\session_created::create([
                    'objectid' => $occurrencerecord->id, 'context' => $context,
                    'other' => ['attendancejourneysid' => $attendancejourneys->id],
                ])->trigger();
            }
            $transaction->allow_commit();
            $message = attendancejourneys_get_string('sessionseriescreated', 'attendancejourneys', $count);
        } else if (!empty($data->sessionid)) {
            $existing = $DB->get_record('attendancejourneys_sessions', [
                'id' => $data->sessionid,
                'attendancejourneysid' => $attendancejourneys->id,
            ], '*', MUST_EXIST);
            attendancejourneys_require_session_management_access($cm, $context, $existing);
            $record->id = $existing->id;
            $record->cancelled = (int) $existing->cancelled;
            $record->timemodified = max(time(), (int) $existing->timemodified + 1);
            if (
                attendancejourneys_session_has_final_results($existing) &&
                    attendancejourneys_session_structure_changed($existing, $record)
            ) {
                redirect(
                    $formurl,
                    attendancejourneys_get_string('sessioneditblockedfinal', 'attendancejourneys'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            if (
                attendancejourneys_session_has_records((int) $existing->id) &&
                    attendancejourneys_session_audience_changed($existing, $record)
            ) {
                redirect(
                    $formurl,
                    attendancejourneys_get_string('sessioneditblockedaudience', 'attendancejourneys'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            $DB->update_record('attendancejourneys_sessions', $record);
            attendancejourneys_update_calendar_event($attendancejourneys, $record);
            \mod_attendancejourneys\event\session_updated::create([
                'objectid' => $record->id, 'context' => $context,
                'other' => ['attendancejourneysid' => $attendancejourneys->id],
            ])->trigger();
            $message = attendancejourneys_get_string('sessionupdated', 'attendancejourneys');
        } else {
            $record->timecreated = time();
            $record->id = $DB->insert_record('attendancejourneys_sessions', $record);
            attendancejourneys_update_calendar_event($attendancejourneys, $record);
            \mod_attendancejourneys\event\session_created::create([
                'objectid' => $record->id, 'context' => $context,
                'other' => ['attendancejourneysid' => $attendancejourneys->id],
            ])->trigger();
            $message = attendancejourneys_get_string('sessioncreated', 'attendancejourneys');
        }
        $participantids = array_keys(get_enrolled_users(
            $context,
            'mod/attendancejourneys:canbelisted',
            0,
            'u.id',
            null,
            0,
            0,
            true
        ));
        attendancejourneys_update_completion($course, $cm, $participantids);
        attendancejourneys_update_grades($attendancejourneys);
        $savedurl = new moodle_url($baseurl, ['groupfilter' => (int) $record->groupid]);
        redirect($savedurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'sessions');
    echo html_writer::start_div('attendancejourneys-section-header');
    echo html_writer::div(attendancejourneys_get_string('planning', 'attendancejourneys'), 'attendancejourneys-section-eyebrow');
    echo $OUTPUT->heading(($action === 'add' || $action === 'series')
        ? attendancejourneys_get_string($action === 'series' ? 'addsessionseries' : 'addsession', 'attendancejourneys')
        : ($action === 'duplicate' ? attendancejourneys_get_string('duplicatesession', 'attendancejourneys') :
            attendancejourneys_get_string('editsession', 'attendancejourneys')));
    echo html_writer::div(attendancejourneys_get_string('sessionformintro', 'attendancejourneys'), 'text-muted');
    echo html_writer::end_div();
    echo html_writer::start_div('attendancejourneys-form-card');
    $form->display();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$sortmap = [
    'date' => 'sessiondate',
    'name' => 'name',
    'duration' => 'duration',
];
if (!isset($sortmap[$sort])) {
    $sort = 'date';
}
$direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
$orderby = $sortmap[$sort] . ' ' . $direction . ', id ASC';
$allsessions = $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $attendancejourneys->id], $orderby);
$sessions = $allsessions;
$sessions = attendancejourneys_filter_sessions($sessions, $groupfilter);
$sessions = attendancejourneys_filter_session_catalog($sessions, $query, $period);
$totalfiltered = count($sessions);
$lastpage = max(0, (int) ceil($totalfiltered / $perpage) - 1);
$page = min($page, $lastpage);
$sessions = array_slice(array_values($sessions), $page * $perpage, $perpage);
$recordedsessionids = [];
if ($sessions) {
    [$recordedsql, $recordedparams] = $DB->get_in_or_equal(array_map(
        static fn($session): int => (int) $session->id,
        $sessions
    ), SQL_PARAMS_NAMED, 'catalogsession');
    $recordedsessionids = array_fill_keys($DB->get_fieldset_sql(
        "SELECT DISTINCT sessionid FROM {attendancejourneys_records} WHERE sessionid $recordedsql",
        $recordedparams
    ), true);
}

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'sessions');
echo html_writer::start_div('attendancejourneys-sessions-page');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(attendancejourneys_get_string('planning', 'attendancejourneys'), 'attendancejourneys-section-eyebrow');
echo $OUTPUT->heading(attendancejourneys_get_string('managesessions', 'attendancejourneys'));
echo html_writer::div(attendancejourneys_get_string('managesessionsintro', 'attendancejourneys'), 'text-muted');
echo html_writer::end_div();

$upcomingcount = 0;
$pastcount = 0;
$totalminutes = 0;
foreach ($allsessions as $statsession) {
    if (!empty($statsession->cancelled)) {
        continue;
    }
    $totalminutes += (int) $statsession->duration;
    if ($statsession->sessiondate + ($statsession->duration * MINSECS) < time()) {
        $pastcount++;
    } else {
        $upcomingcount++;
    }
}
echo html_writer::start_div('attendancejourneys-session-metrics');
foreach (
    [[count($allsessions), attendancejourneys_get_string('allsessions', 'attendancejourneys')],
        [$upcomingcount, attendancejourneys_get_string('periodupcoming', 'attendancejourneys')],
        [$pastcount, attendancejourneys_get_string('periodpast', 'attendancejourneys')],
        [format_time($totalminutes * MINSECS), attendancejourneys_get_string(
            'plannedtime',
            'attendancejourneys'
        )]] as [$value, $label]
) {
    echo html_writer::div(
        html_writer::tag('strong', (string) $value) . html_writer::span($label),
        'attendancejourneys-session-metric'
    );
}
echo html_writer::end_div();

echo html_writer::start_div('attendancejourneys-session-toolbar');
echo html_writer::start_div('attendancejourneys-session-toolbar-filters');
$groupfilterurl = new moodle_url($baseurl, [
    'q' => $query, 'period' => $period, 'sort' => $sort, 'direction' => strtolower($direction),
]);
if (!$islight) {
    attendancejourneys_print_group_filter($groupfilterurl, $cm, $groupfilter);
}
attendancejourneys_print_session_catalog_filters(
    $baseurl,
    $groupfilter,
    $query,
    $period,
    ['sort' => $sort, 'direction' => strtolower($direction)]
);
echo html_writer::end_div();

$addparams = ['action' => 'add'];
if ($groupfilter >= 0) {
    $addparams['groupfilter'] = $groupfilter;
}
$addurl = new moodle_url($baseurl, $addparams);
$addlabel = attendancejourneys_get_string('addsession', 'attendancejourneys');
$seriesparams = ['action' => 'series'];
if ($groupfilter >= 0) {
    $seriesparams['groupfilter'] = $groupfilter;
}
$seriesurl = new moodle_url($baseurl, $seriesparams);
$journeysurl = new moodle_url('/mod/attendancejourneys/journeys.php', ['id' => $cm->id, 'action' => 'add']);
$managejourneysurl = new moodle_url('/mod/attendancejourneys/journeys.php', ['id' => $cm->id]);

if ($sessions || $groupfilter < 0) {
    echo html_writer::start_div('attendancejourneys-session-actions');
    echo $OUTPUT->single_button($addurl, $addlabel, 'get', ['class' => 'mb-0 mr-2 me-2', 'type' => 'primary']);
    if (!$islight) {
        echo $OUTPUT->single_button(
            $seriesurl,
            attendancejourneys_get_string('addsessionseries', 'attendancejourneys'),
            'get',
            ['class' => 'mb-0 mr-2 me-2', 'type' => 'secondary']
        );
        echo $OUTPUT->single_button(
            $journeysurl,
            attendancejourneys_get_string('createjourney', 'attendancejourneys'),
            'get',
            ['class' => 'mb-0 mr-2 me-2', 'type' => 'secondary']
        );
        echo html_writer::link(
            $managejourneysurl,
            attendancejourneys_get_string('managejourneys', 'attendancejourneys'),
            ['class' => 'btn btn-outline-secondary']
        );
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();

if (!$sessions) {
    echo html_writer::start_div('attendancejourneys-empty-state');
    if ($query !== '' || $period !== 'all') {
        echo html_writer::tag('h3', attendancejourneys_get_string('nosearchsessions', 'attendancejourneys'));
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string('nosearchsessions_help', 'attendancejourneys'),
            ['class' => 'text-muted']
        );
        $clearparams = ['id' => $cm->id];
        if ($groupfilter >= 0) {
            $clearparams['groupfilter'] = $groupfilter;
        }
        echo html_writer::link(
            new moodle_url($baseurl, $clearparams),
            attendancejourneys_get_string('clearfilters', 'attendancejourneys')
        );
    } else if ($groupfilter >= 0) {
        echo html_writer::tag('h3', attendancejourneys_get_string('nofilteredsessions', 'attendancejourneys'));
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string(
                'nofilteredsessions_help',
                'attendancejourneys'
            ),
            ['class' => 'text-muted']
        );
        echo $OUTPUT->single_button(
            $addurl,
            attendancejourneys_get_string('addsession', 'attendancejourneys'),
            'get',
            ['class' => 'mb-2', 'type' => 'primary']
        );
        if (!$islight) {
            echo html_writer::div(html_writer::link(
                $seriesurl,
                attendancejourneys_get_string('createseriesfor', 'attendancejourneys', $allowedfilters[$groupfilter])
            ), 'mb-3');
        }
        $allurl = new moodle_url('/mod/attendancejourneys/sessions.php', ['id' => $cm->id, 'groupfilter' => -1]);
        echo html_writer::link(
            $allurl,
            '← ' . attendancejourneys_get_string('backtoallsessions', 'attendancejourneys'),
            ['class' => 'attendancejourneys-back-link']
        );
    } else {
        echo html_writer::tag('h3', attendancejourneys_get_string('nosessions', 'attendancejourneys'));
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string('nosessions_help', 'attendancejourneys'),
            ['class' => 'text-muted mb-0']
        );
    }
    echo html_writer::end_div();
} else {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false),
        'class' => 'attendancejourneys-bulk-form']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable attendancejourneys-sessions';

    $headers = [
        'date' => attendancejourneys_get_string('sessiondate', 'attendancejourneys'),
        'name' => attendancejourneys_get_string('sessionname', 'attendancejourneys'),
        'duration' => attendancejourneys_get_string('duration', 'attendancejourneys'),
    ];
    foreach ($headers as $key => $label) {
        $nextdirection = ($sort === $key && $direction === 'ASC') ? 'desc' : 'asc';
        $url = new moodle_url($baseurl, ['sort' => $key, 'direction' => $nextdirection,
            'groupfilter' => $groupfilter, 'q' => $query, 'period' => $period]);
        $indicator = $sort === $key ? ($direction === 'ASC' ? ' ↑' : ' ↓') : '';
        $table->head[] = html_writer::link($url, $label . $indicator);
    }
    if (!$islight) {
        $table->head[] = attendancejourneys_get_string('sessionmodality', 'attendancejourneys');
        $table->head[] = attendancejourneys_get_string('sessionaudience', 'attendancejourneys');
        $table->head[] = attendancejourneys_get_string('journey', 'attendancejourneys');
    }
    $table->head[] = attendancejourneys_get_string('attendanceprogress', 'attendancejourneys');
    if (!$islight) {
        $table->head[] = html_writer::checkbox('selectallsessions', 1, false, '', [
            'id' => 'attendancejourneys-select-all-sessions',
            'title' => attendancejourneys_get_string('selectallsessions', 'attendancejourneys'),
            'aria-label' => attendancejourneys_get_string('selectallsessions', 'attendancejourneys'),
        ]);
    }
    $table->head[] = get_string('actions');

    foreach ($sessions as $session) {
        $editurl = new moodle_url($baseurl, ['action' => 'edit', 'sessionid' => $session->id,
            'groupfilter' => $groupfilter]);
        $duplicateurl = new moodle_url($baseurl, ['action' => 'duplicate', 'sessionid' => $session->id,
            'groupfilter' => $groupfilter]);
        $deleteurl = new moodle_url($baseurl, ['action' => 'delete', 'sessionid' => $session->id,
            'groupfilter' => $groupfilter]);
        $clearurl = new moodle_url($baseurl, ['action' => 'clear', 'sessionid' => $session->id,
            'groupfilter' => $groupfilter]);
        $takeurl = new moodle_url('/mod/attendancejourneys/take.php', ['id' => $cm->id, 'sessionid' => $session->id]);
        $progress = attendancejourneys_session_progress($context, $session);
        $hasrecords = isset($recordedsessionids[$session->id]);
        $complete = $progress['total'] > 0 && $progress['recorded'] >= $progress['total'];
        $actions = $OUTPUT->action_icon(
            $takeurl,
            new pix_icon(
                'i/grades',
                attendancejourneys_get_string(
                    'takeattendance',
                    'attendancejourneys'
                )
            )
        ) .
        ' ' .

            $OUTPUT->action_icon(
                $duplicateurl,
                new pix_icon(
                    't/copy',
                    attendancejourneys_get_string(
                        'duplicatesession',
                        'attendancejourneys'
                    )
                )
            ) .
        ' ' .

            $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit'))) . ' ' .
            ($hasrecords ? $OUTPUT->action_icon(
                $clearurl,
                new pix_icon('t/reset', attendancejourneys_get_string('clearsessionattendance', 'attendancejourneys'))
            ) . ' ' : '') .
            $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')));
        if (!empty($attendancejourneys->journeygrading) && has_capability('mod/attendancejourneys:managejourneys', $context)) {
            $cancellationaction = empty($session->cancelled) ? 'cancel' : 'reinstate';
            $cancellationlabel = empty($session->cancelled) ? 'cancelsession' : 'reinstatesession';
            $actions .= ' ' . html_writer::link(
                new moodle_url($baseurl, [
                'sessionid' => $session->id, 'action' => $cancellationaction,
                ]),
                attendancejourneys_get_string($cancellationlabel, 'attendancejourneys'),
                ['class' => 'btn btn-sm btn-outline-secondary']
            );
            $actions .= ' ' . html_writer::link(new moodle_url($baseurl, [
                'sessionid' => $session->id, 'action' => 'cancellationhistory',
            ]), attendancejourneys_get_string('sessioncancellationhistory', 'attendancejourneys'), ['class' => 'd-block small']);
        }
        if (!empty($session->cancelled)) {
            // Staff recording and clearing are unavailable until the session is reinstated.
            $actions = has_capability('mod/attendancejourneys:managejourneys', $context) ?
                html_writer::link(
                    new moodle_url($baseurl, ['sessionid' => $session->id, 'action' => 'reinstate']),
                    attendancejourneys_get_string('reinstatesession', 'attendancejourneys'),
                    ['class' => 'btn btn-sm btn-outline-secondary']
                ) .
                ' ' . html_writer::link(
                    new moodle_url($baseurl, ['sessionid' => $session->id, 'action' => 'cancellationhistory']),
                    attendancejourneys_get_string('sessioncancellationhistory', 'attendancejourneys'),
                    ['class' => 'd-block small']
                ) : '';
        }
        $row = [
            html_writer::tag('div', userdate($session->sessiondate, get_string('strftimedatetime', 'langconfig'))) .
                html_writer::tag('small', attendancejourneys_get_string(
                    'endvalue',
                    'attendancejourneys',
                    userdate(
                        $session->sessiondate + ($session->duration * MINSECS),
                        get_string('strftimedatetime', 'langconfig')
                    )
                ), ['class' => 'text-muted']),
            html_writer::tag('strong', format_string($session->name), ['class' => 'attendancejourneys-session-title']),
            attendancejourneys_get_string('minutesvalue', 'attendancejourneys', $session->duration),
        ];
        if (!$islight) {
            $row[] = attendancejourneys_session_delivery_details($session);
            $row[] = attendancejourneys_session_audience_badge($session);
            $row[] = empty($session->journeyid) ?
                html_writer::span(attendancejourneys_get_string('independent', 'attendancejourneys'), 'text-muted') :
                format_string($DB->get_field('attendancejourneys_journeys', 'name', ['id' => $session->journeyid]) ?: '—');
        }
        $row[] =
            html_writer::div(html_writer::span(
                $progress['recorded'] . ' / ' . $progress['total'],
                $complete ? 'badge badge-success text-bg-success' : 'badge badge-secondary text-bg-secondary'
            ) . html_writer::span(
                attendancejourneys_get_string($complete ? 'attendancecomplete' : 'attendancetodo', 'attendancejourneys'),
                'd-block small text-muted mt-1'
            ), 'attendancejourneys-session-progress');
        if (!empty($session->cancelled)) {
            $row[count($row) - 1] = html_writer::span(
                attendancejourneys_get_string('sessioncancelled', 'attendancejourneys'),
                'badge badge-secondary text-bg-secondary'
            );
        }
        if (!$islight) {
            $row[] = html_writer::checkbox('selectedsessions[]', (int) $session->id, false, '', [
                'class' => 'attendancejourneys-session-select',
                'aria-label' => attendancejourneys_get_string('selectsession', 'attendancejourneys', format_string($session->name)),
            ]);
        }
        $row[] = $actions;
        $table->data[] = $row;
    }
    echo attendancejourneys_responsive_table($table, attendancejourneys_get_string('managesessions', 'attendancejourneys'));
    if (!$islight) {
        $bulksessionbuttons = html_writer::tag(
            'button',
            attendancejourneys_get_string('bulkaudiencesessions', 'attendancejourneys'),
            [
            'type' => 'submit', 'name' => 'bulkaction', 'value' => 'editaudience', 'class' => 'btn btn-secondary',
            'id' => 'attendancejourneys-audience-selected-sessions',
            ]
        );
        $bulksessionbuttons .= html_writer::tag(
            'button',
            attendancejourneys_get_string('bulkdetailsessions', 'attendancejourneys'),
            [
            'type' => 'submit', 'name' => 'bulkaction', 'value' => 'editdetails', 'class' => 'btn btn-secondary',
            'id' => 'attendancejourneys-edit-selected-sessions',
            ]
        );
        $bulksessionbuttons .= html_writer::tag(
            'button',
            attendancejourneys_get_string('bulkshiftsessions', 'attendancejourneys'),
            [
            'type' => 'submit', 'name' => 'bulkaction', 'value' => 'shiftschedule', 'class' => 'btn btn-secondary',
            'id' => 'attendancejourneys-shift-selected-sessions',
            ]
        );
        $bulksessionbuttons .= html_writer::tag(
            'button',
            attendancejourneys_get_string('bulkduplicatesessions', 'attendancejourneys'),
            [
            'type' => 'submit', 'name' => 'bulkaction', 'value' => 'duplicateschedule',
            'class' => 'btn btn-secondary', 'id' => 'attendancejourneys-duplicate-selected-sessions',
            ]
        );
        $bulksessionbuttons .= html_writer::tag(
            'button',
            attendancejourneys_get_string('bulkdeletesessions', 'attendancejourneys'),
            [
            'type' => 'submit', 'name' => 'bulkaction', 'value' => 'deleteempty', 'class' => 'btn btn-outline-danger',
            'id' => 'attendancejourneys-delete-selected-sessions',
            ]
        );
        echo html_writer::div($bulksessionbuttons, 'mt-3 attendancejourneys-session-bulk-actions');
        $PAGE->requires->js_call_amd('mod_attendancejourneys/selection', 'init', [
        'attendancejourneys-select-all-sessions',
        '.attendancejourneys-session-select',
        [
            'attendancejourneys-audience-selected-sessions',
            'attendancejourneys-edit-selected-sessions',
            'attendancejourneys-shift-selected-sessions',
            'attendancejourneys-duplicate-selected-sessions',
            'attendancejourneys-delete-selected-sessions',
        ],
        ]);
    }
    echo html_writer::end_tag('form');
    $pagingurl = new moodle_url($baseurl, [
        'groupfilter' => $groupfilter, 'q' => $query, 'period' => $period,
        'sort' => $sort, 'direction' => strtolower($direction),
    ]);
    echo $OUTPUT->paging_bar($totalfiltered, $page, $perpage, $pagingurl);
}

$returnurl = new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]);
echo html_writer::div(html_writer::link($returnurl, attendancejourneys_get_string('backtoactivity', 'attendancejourneys')), 'mt-4');
echo html_writer::end_div();
echo $OUTPUT->footer();
