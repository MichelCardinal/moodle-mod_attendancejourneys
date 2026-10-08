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
 * Takes attendance for one Attendance Journeys session.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_attendancejourneys\local\calculator;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 100;
$allowarchived = optional_param('allowarchived', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
$session = $DB->get_record('attendancejourneys_sessions', [
    'id' => $sessionid,
    'attendancejourneysid' => $attendancejourneys->id,
], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:takeattendance', $context);
// A shared activity lock also protects concurrent gradebook updates across sessions.
if (data_submitted()) {
    require_sesskey();
    $writelock = new \mod_attendancejourneys\local\write_lock((int) $attendancejourneys->id);
    $attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
    $session = $DB->get_record('attendancejourneys_sessions', [
        'id' => $sessionid, 'attendancejourneysid' => $attendancejourneys->id,
    ], '*', MUST_EXIST);
}

$islight = attendancejourneys_is_light_mode($attendancejourneys);
if (!attendancejourneys_session_is_visible($cm, $context, $session)) {
    throw new required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
}

if (!empty($session->cancelled)) {
    throw new moodle_exception('sessioncancelledrecordingunavailable', 'attendancejourneys');
}

$url = new moodle_url('/mod/attendancejourneys/take.php', ['id' => $cm->id, 'sessionid' => $session->id]);
$PAGE->set_url($url);
$PAGE->set_title(attendancejourneys_get_string('takeattendance', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$sessionjourney = !empty($session->journeyid) ? $DB->get_record('attendancejourneys_journeys', [
    'id' => $session->journeyid, 'attendancejourneysid' => $attendancejourneys->id,
]) : null;
if ($sessionjourney && empty($sessionjourney->active) && !$allowarchived) {
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'attendance');
    echo $OUTPUT->heading(attendancejourneys_get_string('archivedattendance', 'attendancejourneys'));
    echo $OUTPUT->confirm(
        attendancejourneys_get_string(
            'archivedattendanceconfirm',
            'attendancejourneys',
            format_string($sessionjourney->name)
        ),
        new moodle_url($url, ['allowarchived' => 1, 'sesskey' => sesskey()]),
        new moodle_url('/mod/attendancejourneys/attendance.php', ['id' => $cm->id])
    );
    echo $OUTPUT->footer();
    exit;
}
if ($sessionjourney && empty($sessionjourney->active) && $allowarchived && !confirm_sesskey()) {
    require_sesskey();
}
if ($sessionjourney && empty($sessionjourney->active) && $allowarchived) {
    $url->param('allowarchived', 1);
    $url->param('sesskey', sesskey());
    $PAGE->set_url($url);
}

$participants = attendancejourneys_get_session_participants(
    $context,
    $session,
    'u.*',
    'u.lastname ASC, u.firstname ASC, u.id ASC'
);
$totalparticipants = count($participants);
$page = min($page, max(0, (int) ceil($totalparticipants / $perpage) - 1));
$participants = array_slice($participants, $page * $perpage, $perpage, true);
$url->param('page', $page);
$PAGE->set_url($url);
$existingrecords = [];
foreach (
    $DB->get_records(
        'attendancejourneys_records',
        ['sessionid' => $session->id],
        '',
        'id,userid,status,minutesabsent,remarks,takenby,approved,approvedby,timeapproved,changesrequested,' .
        'reviewnote,reviewedby,timereviewed,timemodified'
    ) as $existingrecord
) {
    $existingrecords[$existingrecord->userid] = $existingrecord;
}
$approvers = [];
$approverids = array_values(array_unique(array_filter(array_map(
    static fn($record): int => (int) ($record->approvedby ?? 0),
    $existingrecords
))));
if ($approverids) {
    $approvers = $DB->get_records_list('user', 'id', $approverids, '', 'id,firstname,lastname');
}
$latestrecord = null;
foreach ($existingrecords as $existingrecord) {
    if ($latestrecord === null || (int) $existingrecord->timemodified > (int) $latestrecord->timemodified) {
        $latestrecord = $existingrecord;
    }
}
$sheetversion = (int) $session->timemodified;
$concurrenterror = '';

if (data_submitted() && confirm_sesskey()) {
    $submittedversion = required_param('sheetversion', PARAM_INT);
    if ($submittedversion !== $sheetversion) {
        $concurrenterror = attendancejourneys_get_string('attendanceconcurrentchange', 'attendancejourneys');
    }
    $statuses = optional_param_array('status', [], PARAM_ALPHA);
    $minutesvalues = optional_param_array('minutesabsent', [], PARAM_INT);
    $remarksvalues = optional_param_array('remarks', [], PARAM_TEXT);
    $approvalvalues = optional_param_array('approved', [], PARAM_INT);
    $changesrequestedvalues = optional_param_array('changesrequested', [], PARAM_INT);
    $reviewnotevalues = optional_param_array('reviewnote', [], PARAM_TEXT);
    $approveall = optional_param('approveall', 0, PARAM_BOOL);
    $allowedstatuses = ['present', 'absent', 'partial'];
    if (!empty($attendancejourneys->journeygrading)) {
        $allowedstatuses[] = 'exempt';
    }
    if ($attendancejourneys->excusedenabled) {
        $allowedstatuses[] = 'excused';
    }

    $errors = [];
    $records = [];
    $unrecordeduserids = [];
    foreach ($participants as $participant) {
        $userid = (int) $participant->id;
        $status = $statuses[$userid] ?? null;
        if ($status === null) {
            // A missing field must never erase an existing record (for example after request truncation).
            continue;
        }
        if ($status === 'unrecorded') {
            $unrecordeduserids[] = $userid;
            continue;
        }
        if (!in_array($status, $allowedstatuses, true)) {
            $errors[$userid] = attendancejourneys_get_string('statusrequired', 'attendancejourneys');
            continue;
        }

        if (
            $status === 'present' || $status === 'exempt' ||
                ($status === 'excused' && empty($attendancejourneys->journeygrading))
        ) {
            $minutesabsent = 0;
        } else if ($status === 'absent' || $status === 'excused') {
            $minutesabsent = (int) $session->duration;
        } else {
            $minutesabsent = isset($minutesvalues[$userid]) ? (int) $minutesvalues[$userid] : -1;
            if ($minutesabsent < 1 || $minutesabsent >= (int) $session->duration) {
                $errors[$userid] = attendancejourneys_get_string('partialminuteserror', 'attendancejourneys', $session->duration);
                continue;
            }
        }

        $records[$userid] = (object) [
            'sessionid' => $session->id,
            'userid' => $userid,
            'status' => $status,
            'minutesabsent' => $minutesabsent,
            'remarks' => trim($remarksvalues[$userid] ?? ''),
            'takenby' => $USER->id,
            'approved' => 1,
            'approvedby' => $USER->id,
            'timeapproved' => time(),
            'timemodified' => time(),
        ];
    }

    if (!$errors && !$concurrenterror) {
        $transaction = $DB->start_delegated_transaction();
        $changeduserids = [];
        $actualchangecount = 0;
        $approvalchangecount = 0;
        foreach ($records as $userid => $record) {
            if (isset($existingrecords[$userid])) {
                $existingrecord = $existingrecords[$userid];
                if (!attendancejourneys_record_values_changed($existingrecord, $record)) {
                    // Approval is independent from the declaration's author and content.
                    $isselfrecorded = (int) $existingrecord->takenby === (int) $userid;
                    $requestedchanges = $isselfrecorded && !empty($changesrequestedvalues[$userid]);
                    $requestedapproval = $isselfrecorded && !$requestedchanges &&
                        ($approveall || !empty($approvalvalues[$userid]));
                    $reviewnote = $requestedchanges ? trim($reviewnotevalues[$userid] ?? '') : '';
                    $reviewchanged = $requestedchanges !== !empty($existingrecord->changesrequested) ||
                        $reviewnote !== trim((string) ($existingrecord->reviewnote ?? ''));
                    if ($isselfrecorded && ($requestedapproval !== !empty($existingrecord->approved) || $reviewchanged)) {
                        $DB->update_record('attendancejourneys_records', (object) [
                            'id' => $existingrecord->id,
                            'approved' => $requestedapproval ? 1 : 0,
                            'approvedby' => $requestedapproval ? $USER->id : 0,
                            'timeapproved' => $requestedapproval ? time() : 0,
                            'changesrequested' => $requestedchanges ? 1 : 0,
                            'reviewnote' => $reviewnote,
                            'reviewedby' => $requestedchanges ? $USER->id : 0,
                            'timereviewed' => $requestedchanges ? time() : 0,
                        ]);
                        $reviewaction = $requestedchanges ? 'changesrequested' :
                            ($requestedapproval ? 'approved' : 'approvalwithdrawn');
                        attendancejourneys_add_review_history(
                            (int) $existingrecord->id,
                            (int) $session->id,
                            $userid,
                            $reviewaction,
                            (int) $USER->id,
                            $reviewnote
                        );
                        $changeduserids[] = $userid;
                        $actualchangecount++;
                        $approvalchangecount++;
                    }
                    // Preserve the original recorder and timestamp when staff merely saves the sheet.
                    continue;
                }
                $record->id = $existingrecords[$userid]->id;
                $DB->update_record('attendancejourneys_records', $record);
                attendancejourneys_add_review_history(
                    (int) $record->id,
                    (int) $session->id,
                    $userid,
                    'updated',
                    (int) $USER->id,
                    attendancejourneys_record_audit_snapshot($record)
                );
            } else {
                $record->id = $DB->insert_record('attendancejourneys_records', $record);
                attendancejourneys_add_review_history(
                    (int) $record->id,
                    (int) $session->id,
                    $userid,
                    'recorded',
                    (int) $USER->id,
                    attendancejourneys_record_audit_snapshot($record)
                );
            }
            $changeduserids[] = $userid;
            $actualchangecount++;
        }
        foreach ($unrecordeduserids as $userid) {
            if (isset($existingrecords[$userid])) {
                attendancejourneys_delete_review_history([(int) $existingrecords[$userid]->id]);
                $DB->delete_records('attendancejourneys_records', ['id' => $existingrecords[$userid]->id]);
                $changeduserids[] = $userid;
                $actualchangecount++;
            }
        }
        if ($actualchangecount > 0) {
            attendancejourneys_touch_session((int) $session->id);
        }
        $transaction->allow_commit();
        if ($actualchangecount > 0) {
            \mod_attendancejourneys\event\attendance_taken::create([
                'objectid' => $session->id,
                'context' => $context,
                'other' => ['attendancejourneysid' => $attendancejourneys->id, 'recordcount' => $actualchangecount],
            ])->trigger();
            if ($approvalchangecount > 0) {
                \mod_attendancejourneys\event\attendance_approval_changed::create([
                    'objectid' => $session->id,
                    'context' => $context,
                    'other' => [
                        'attendancejourneysid' => $attendancejourneys->id,
                        'recordcount' => $approvalchangecount,
                    ],
                ])->trigger();
            }
            $changeduserids = array_values(array_unique($changeduserids));
            attendancejourneys_update_completion($course, $cm, $changeduserids);
            foreach ($changeduserids as $changeduserid) {
                attendancejourneys_update_grades($attendancejourneys, $changeduserid);
            }
        }
        $recordedcount = 0;
        foreach ($participants as $participant) {
            $userid = (int) $participant->id;
            if (
                isset($records[$userid]) ||
                    (!in_array($userid, $unrecordeduserids, true) && isset($existingrecords[$userid]) &&
                    !array_key_exists($userid, $statuses))
            ) {
                $recordedcount++;
            }
        }
        $remainingcount = count($participants) - $recordedcount;
        $savedmessage = $remainingcount === 0
            ? attendancejourneys_get_string('attendancesavedcomplete', 'attendancejourneys')
            : attendancejourneys_get_string('attendancesavedpartial', 'attendancejourneys', (object) [
                'recorded' => $recordedcount,
                'total' => count($participants),
                'remaining' => $remainingcount,
            ]);
        redirect($url, $savedmessage, null, \core\output\notification::NOTIFY_SUCCESS);
    }
} else {
    $statuses = [];
    $minutesvalues = [];
    $remarksvalues = [];
    $errors = [];
}

$statusoptions = [
    'unrecorded' => attendancejourneys_get_string('statusunrecorded', 'attendancejourneys'),
    'present' => attendancejourneys_get_string('statuspresent', 'attendancejourneys'),
    'absent' => attendancejourneys_get_string('statusabsent', 'attendancejourneys'),
    'partial' => attendancejourneys_get_string('statuspartial', 'attendancejourneys'),
];
if (!empty($attendancejourneys->journeygrading)) {
    $statusoptions['exempt'] = get_string('statusexempt', 'attendancejourneys');
}
if ($attendancejourneys->excusedenabled) {
    $statusoptions['excused'] = attendancejourneys_get_string('statusexcused', 'attendancejourneys');
}

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'attendance');
echo html_writer::start_div('attendancejourneys-take-page');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(
    attendancejourneys_get_string('attendanceworkflow', 'attendancejourneys'),
    'attendancejourneys-section-eyebrow'
);
echo $OUTPUT->heading(attendancejourneys_get_string('takeattendance', 'attendancejourneys'));
if (!empty($attendancejourneys->journeygrading)) {
    echo $OUTPUT->notification(get_string('journeyabsencepolicy', 'attendancejourneys'), 'info');
}
echo html_writer::div(attendancejourneys_get_string('takeattendanceintro', 'attendancejourneys'), 'text-muted');
echo html_writer::end_div();

echo html_writer::start_div('attendancejourneys-session-summary');
echo html_writer::tag('h3', format_string($session->name), ['class' => 'mb-2']);
echo html_writer::start_div('attendancejourneys-session-summary-meta');
echo html_writer::tag('div', attendancejourneys_get_string('sessionperiod', 'attendancejourneys', (object) [
    'start' => userdate($session->sessiondate, get_string('strftimedatetime', 'langconfig')),
    'end' => userdate($session->sessiondate + ($session->duration * MINSECS), get_string('strftimedatetime', 'langconfig')),
]), ['class' => 'text-muted']);
echo html_writer::tag(
    'div',
    attendancejourneys_get_string(
        'minutesvalue',
        'attendancejourneys',
        $session->duration
    ),
    ['class' => 'font-weight-bold']
);
if (!$islight) {
    echo html_writer::div(attendancejourneys_session_delivery_details($session));
    echo html_writer::div(attendancejourneys_session_audience_badge($session));
    echo html_writer::div(html_writer::span(
        $sessionjourney ? format_string($sessionjourney->name) :
        attendancejourneys_get_string(
            'independentsession',
            'attendancejourneys'
        ),
        $sessionjourney ? 'badge badge-info text-bg-info' : 'badge badge-light text-bg-light'
    ));
}
echo html_writer::end_div();
if ($latestrecord) {
    $recorder = !empty($latestrecord->takenby) ? $DB->get_record(
        'user',
        ['id' => $latestrecord->takenby],
        'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
    ) : false;
    $recordername = $recorder ? fullname($recorder) : attendancejourneys_get_string(
        'attendanceunknownrecorder',
        'attendancejourneys'
    );
    echo html_writer::div(attendancejourneys_get_string('attendancelastupdated', 'attendancejourneys', (object) [
        'user' => $recordername,
        'date' => !empty($latestrecord->timemodified)
            ? userdate($latestrecord->timemodified, get_string('strftimedatetime', 'langconfig')) : '—',
    ]), 'attendancejourneys-session-audit');
} else {
    echo html_writer::div(
        attendancejourneys_get_string('attendancenotsavedyet', 'attendancejourneys'),
        'attendancejourneys-session-audit attendancejourneys-session-audit-empty'
    );
}
if ($sessionjourney && empty($sessionjourney->active)) {
    echo $OUTPUT->notification(attendancejourneys_get_string('archivedattendancemode', 'attendancejourneys'), 'warning');
}
echo html_writer::end_div();

if ($errors) {
    echo $OUTPUT->notification(attendancejourneys_get_string('attendancevalidationerrors', 'attendancejourneys'), 'error');
}
if ($concurrenterror) {
    echo $OUTPUT->notification($concurrenterror, 'warning');
}

if ($totalparticipants > $perpage) {
    echo $OUTPUT->notification(attendancejourneys_get_string('attendancepageinfo', 'attendancejourneys', (object) [
        'first' => $page * $perpage + 1,
        'last' => min(($page + 1) * $perpage, $totalparticipants),
        'total' => $totalparticipants,
    ]), 'info');
    echo $OUTPUT->paging_bar($totalparticipants, $page, $perpage, $url);
}

if (!$participants) {
    echo $OUTPUT->notification(attendancejourneys_get_string('noparticipants', 'attendancejourneys'), 'info');
} else {
    echo html_writer::start_tag('form', [
        'id' => 'attendancejourneys-take-form',
        'method' => 'post',
        'action' => $url->out(false),
        'class' => 'attendancejourneys-take-form mt-4',
        'data-duration' => $session->duration,
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sheetversion', 'value' => $sheetversion]);
    echo html_writer::start_div('attendancejourneys-take-tools');
    echo html_writer::start_div('attendancejourneys-participant-filters');
    echo html_writer::label(
        attendancejourneys_get_string('searchparticipants', 'attendancejourneys'),
        'attendancejourneys-participant-search',
        false,
        ['class' => 'sr-only visually-hidden']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'search',
        'id' => 'attendancejourneys-participant-search',
        'class' => 'form-control ignoredirty',
        'placeholder' => attendancejourneys_get_string('searchparticipants', 'attendancejourneys'),
        'autocomplete' => 'off',
    ]);
    echo html_writer::label(
        attendancejourneys_get_string('filterbystatus', 'attendancejourneys'),
        'attendancejourneys-participant-status-filter',
        false,
        ['class' => 'sr-only visually-hidden']
    );
    $filteroptions = [
        'all' => attendancejourneys_get_string('allparticipants', 'attendancejourneys'),
        'unrecorded' => attendancejourneys_get_string('notrecorded', 'attendancejourneys'),
    ] + array_filter($statusoptions, static fn($key) => $key !== 'unrecorded', ARRAY_FILTER_USE_KEY);
    echo html_writer::select($filteroptions, 'participantstatusfilter', 'all', false, [
        'id' => 'attendancejourneys-participant-status-filter',
        'class' => 'custom-select form-select ignoredirty',
    ]);
    echo html_writer::label(
        attendancejourneys_get_string('filterbysource', 'attendancejourneys'),
        'attendancejourneys-participant-source-filter',
        false,
        ['class' => 'sr-only visually-hidden']
    );
    echo html_writer::select([
        'all' => attendancejourneys_get_string('allrecordingsources', 'attendancejourneys'),
        'selfpending' => attendancejourneys_get_string('attendanceawaitingapproval', 'attendancejourneys'),
        'selfcorrection' => attendancejourneys_get_string('correctionsrequested', 'attendancejourneys'),
        'selfapproved' => attendancejourneys_get_string('attendanceapproveddeclaration', 'attendancejourneys'),
        'staff' => attendancejourneys_get_string('attendancesourcestaff', 'attendancejourneys'),
        'unrecorded' => attendancejourneys_get_string('statusunrecorded', 'attendancejourneys'),
    ], 'participantsourcefilter', 'all', false, [
        'id' => 'attendancejourneys-participant-source-filter',
        'class' => 'custom-select form-select ignoredirty',
    ]);
    $participantcountlabel = attendancejourneys_get_string('participantsdisplayed', 'attendancejourneys', (object) [
        'visible' => count($participants),
        'total' => count($participants),
    ]);
    $participantcounttemplate = attendancejourneys_get_string('participantsdisplayed', 'attendancejourneys', (object) [
        'visible' => '{visible}',
        'total' => '{total}',
    ]);
    echo html_writer::span($participantcountlabel, 'attendancejourneys-participant-count', [
        'aria-live' => 'polite', 'data-label' => $participantcounttemplate,
    ]);
    echo html_writer::end_div();
    echo html_writer::start_div('attendancejourneys-bulk-actions');
    echo html_writer::tag(
        'span',
        attendancejourneys_get_string('bulkstatusactions', 'attendancejourneys'),
        ['class' => 'attendancejourneys-field-label']
    );
    echo html_writer::start_div('attendancejourneys-bulk-status-buttons');
    $bulkstatuses = [
        'unrecorded' => ['bulkstatusunrecorded', 'btn-outline-secondary'],
        'present' => ['bulkstatuspresent', 'btn-outline-primary'],
        'absent' => ['bulkstatusabsent', 'btn-outline-danger'],
        'partial' => ['bulkstatuspartial', 'btn-outline-warning'],
    ];
    if (!empty($attendancejourneys->journeygrading)) {
        $bulkstatuses['exempt'] = ['bulkstatusexempt', 'btn-outline-secondary'];
    }
    if (!empty($attendancejourneys->excusedenabled)) {
        $bulkstatuses['excused'] = ['bulkstatusexcused', 'btn-outline-secondary'];
    }
    foreach ($bulkstatuses as $bulkstatus => [$bulkstring, $bulkclass]) {
        $attributes = [
            'type' => 'button',
            'class' => 'btn btn-sm ' . $bulkclass . ' attendancejourneys-bulk-status',
            'data-status' => $bulkstatus,
        ];
        if ($bulkstatus === 'unrecorded') {
            $attributes['data-confirm'] = attendancejourneys_get_string('bulkstatusunrecordedconfirm', 'attendancejourneys');
        }
        echo html_writer::tag('button', attendancejourneys_get_string($bulkstring, 'attendancejourneys'), $attributes);
    }
    echo html_writer::tag('button', attendancejourneys_get_string('approvepagedeclarations', 'attendancejourneys'), [
        'type' => 'submit', 'name' => 'approveall', 'value' => 1,
        'class' => 'btn btn-sm btn-outline-success',
    ]);
    echo html_writer::start_div('attendancejourneys-bulk-partial-minutes');
    echo html_writer::tag('label', attendancejourneys_get_string('bulkpartialminutes', 'attendancejourneys'), [
        'for' => 'attendancejourneys-bulk-partial-minutes', 'class' => 'sr-only visually-hidden',
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'number', 'id' => 'attendancejourneys-bulk-partial-minutes', 'value' => 1,
        'min' => 1, 'max' => max(1, $session->duration - 1),
        'class' => 'form-control form-control-sm ignoredirty',
        'title' => attendancejourneys_get_string('bulkpartialminutes', 'attendancejourneys'),
        'aria-label' => attendancejourneys_get_string('bulkpartialminutes', 'attendancejourneys'),
    ]);
    echo html_writer::span(get_string('minutes'), 'small text-muted');
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::tag(
        'small',
        attendancejourneys_get_string('bulkstatusactions_help', 'attendancejourneys'),
        ['class' => 'd-block text-muted mt-2']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();

    $recordedcount = 0;
    foreach ($participants as $participant) {
        $participantid = (int) $participant->id;
        $currentstatus = $statuses[$participantid] ?? ($existingrecords[$participantid]->status ?? 'unrecorded');
        if ($currentstatus !== 'unrecorded') {
            $recordedcount++;
        }
    }
    $participanttotal = count($participants);
    $recordedpercent = $participanttotal > 0 ? round(($recordedcount / $participanttotal) * 100) : 0;
    $progresslabel = attendancejourneys_get_string('attendanceentryprogresslabel', 'attendancejourneys', (object) [
        'recorded' => $recordedcount,
        'total' => $participanttotal,
    ]);
    $progresslabeltemplate = attendancejourneys_get_string('attendanceentryprogresslabel', 'attendancejourneys', (object) [
        'recorded' => '{recorded}',
        'total' => '{total}',
    ]);
    $remaininglabeltemplate = attendancejourneys_get_string('attendanceentryremaining', 'attendancejourneys', '{remaining}');
    echo html_writer::start_div('attendancejourneys-entry-progress', [
        'role' => 'status',
        'aria-live' => 'polite',
        'data-label' => $progresslabeltemplate,
        'data-remaining-label' => $remaininglabeltemplate,
        'data-complete-label' => attendancejourneys_get_string('attendanceentrycomplete', 'attendancejourneys'),
    ]);
    echo html_writer::start_div('attendancejourneys-entry-progress-header');
    echo html_writer::tag('strong', attendancejourneys_get_string('attendanceentryprogress', 'attendancejourneys'));
    echo html_writer::span($progresslabel, 'attendancejourneys-entry-progress-label');
    echo html_writer::end_div();
    echo html_writer::start_div('progress attendancejourneys-entry-progress-track');
    echo html_writer::div('', 'progress-bar', [
        'role' => 'progressbar',
        'aria-label' => attendancejourneys_get_string('attendanceentryprogress', 'attendancejourneys'),
        'aria-valuemin' => 0,
        'aria-valuemax' => $participanttotal,
        'aria-valuenow' => $recordedcount,
        'style' => 'width: ' . $recordedpercent . '%',
    ]);
    echo html_writer::end_div();
    echo html_writer::span(
        $recordedcount === $participanttotal
        ? attendancejourneys_get_string('attendanceentrycomplete', 'attendancejourneys')
        : attendancejourneys_get_string('attendanceentryremaining', 'attendancejourneys', $participanttotal - $recordedcount),
        'attendancejourneys-entry-progress-state'
    );
    echo html_writer::end_div();

    echo html_writer::start_div('attendancejourneys-attendance-list');

    foreach ($participants as $participant) {
        $userid = (int) $participant->id;
        $existing = $existingrecords[$userid] ?? null;
        $selectedstatus = $statuses[$userid] ?? ($existing->status ?? 'unrecorded');
        $selectedminutes = $minutesvalues[$userid] ?? ($existing->minutesabsent ?? 0);
        $selectedremarks = $remarksvalues[$userid] ?? ($existing->remarks ?? '');

        $statuscontrols = [];
        foreach ($statusoptions as $statusvalue => $statuslabel) {
            $radioid = 'attendancejourneys-status-' . $userid . '-' . $statusvalue;
            $radio = html_writer::empty_tag('input', [
                'type' => 'radio',
                'id' => $radioid,
                'name' => 'status[' . $userid . ']',
                'value' => $statusvalue,
                'checked' => $selectedstatus === $statusvalue ? 'checked' : null,
                'class' => 'attendancejourneys-status',
                'data-userid' => $userid,
                'aria-label' => attendancejourneys_get_string('statusforparticipant', 'attendancejourneys', (object) [
                    'status' => $statuslabel, 'participant' => fullname($participant),
                ]),
            ]);
            $statuscontrols[] = html_writer::tag(
                'label',
                $radio . html_writer::span($statuslabel),
                ['for' => $radioid, 'class' => 'attendancejourneys-status-choice attendancejourneys-status-' . $statusvalue]
            );
        }
        $select = html_writer::tag(
            'fieldset',
            html_writer::tag(
                'legend',
                attendancejourneys_get_string('statusfor', 'attendancejourneys', fullname($participant)),
                ['class' => 'sr-only visually-hidden']
            ) . implode('', $statuscontrols),
            ['class' => 'attendancejourneys-status-options']
        );
        $minutesid = 'attendancejourneys-minutes-' . $userid;
        $minuteserrorid = 'attendancejourneys-minutes-error-' . $userid;
        $minutes = html_writer::empty_tag('input', [
            'type' => 'number',
            'id' => $minutesid,
            'name' => 'minutesabsent[' . $userid . ']',
            'value' => $selectedminutes,
            'min' => 1,
            'max' => max(1, $session->duration - 1),
            'class' => 'form-control attendancejourneys-minutes',
            'data-userid' => $userid,
            'aria-invalid' => isset($errors[$userid]) ? 'true' : 'false',
            'aria-describedby' => isset($errors[$userid]) ? $minuteserrorid : null,
        ]);
        [$presentminutes, $possibleminutes] = calculator::record_minutes($attendancejourneys, $session, (object) [
            'status' => $selectedstatus,
            'minutesabsent' => (int) $selectedminutes,
        ]);
        $percent = $possibleminutes ? ($presentminutes / $possibleminutes) * 100 : 0;
        if ($selectedstatus === 'unrecorded') {
            $calculationtext = '—';
        } else if (!$possibleminutes) {
            $calculationtext = get_string('attendancecalculationexcluded', 'attendancejourneys');
        } else {
            $calculationtext = attendancejourneys_get_string('presencecalculation', 'attendancejourneys', (object) [
                'present' => $presentminutes,
                'total' => $possibleminutes,
                'percent' => format_float($percent, 1),
            ]);
        }
        $calculation = html_writer::span(
            $calculationtext,
            'attendancejourneys-calculation',
            ['data-userid' => $userid, 'aria-live' => 'polite']
        );
        $remarksid = 'attendancejourneys-remarks-' . $userid;
        $remarks = html_writer::empty_tag('input', [
            'type' => 'text',
            'id' => $remarksid,
            'name' => 'remarks[' . $userid . ']',
            'value' => $selectedremarks,
            'class' => 'form-control',
            'maxlength' => 255,
        ]);
        $participantcell = html_writer::tag('strong', s($participant->lastname), ['class' => 'attendancejourneys-lastname']) .
            html_writer::tag('span', s($participant->firstname), ['class' => 'attendancejourneys-firstname']);
        if ($existing) {
            $isselfrecorded = (int) $existing->takenby === $userid;
            $sourceclass = $isselfrecorded ? 'attendancejourneys-source-self' : 'attendancejourneys-source-staff';
            $participantcell .= html_writer::div(
                html_writer::span(attendancejourneys_get_string(
                    $isselfrecorded ? 'attendancesourceself' : 'attendancesourcestaff',
                    'attendancejourneys'
                ), 'attendancejourneys-source-badge ' . $sourceclass) .
                html_writer::span(userdate(
                    $existing->timemodified,
                    get_string('strftimedatetimeshort', 'langconfig')
                ), 'attendancejourneys-source-date'),
                'attendancejourneys-record-source'
            );
            if ($isselfrecorded) {
                $approvalid = 'attendancejourneys-approved-' . $userid;
                $approvalchecked = isset($approvalvalues[$userid]) ? !empty($approvalvalues[$userid]) :
                    !empty($existing->approved);
                $participantcell .= html_writer::div(
                    html_writer::empty_tag('input', [
                        'type' => 'checkbox', 'name' => 'approved[' . $userid . ']', 'value' => 1,
                        'id' => $approvalid, 'class' => 'attendancejourneys-approval-checkbox',
                        'checked' => $approvalchecked ? 'checked' : null,
                    ]) . html_writer::tag('label', attendancejourneys_get_string('approveattendance', 'attendancejourneys'), [
                        'for' => $approvalid, 'class' => 'mb-0',
                    ]),
                    'attendancejourneys-approval-control'
                );
                $requestid = 'attendancejourneys-changesrequested-' . $userid;
                $requestchecked = isset($changesrequestedvalues[$userid]) ?
                    !empty($changesrequestedvalues[$userid]) : !empty($existing->changesrequested);
                $participantcell .= html_writer::div(
                    html_writer::empty_tag('input', [
                        'type' => 'checkbox', 'name' => 'changesrequested[' . $userid . ']', 'value' => 1,
                        'id' => $requestid, 'checked' => $requestchecked ? 'checked' : null,
                    ]) . html_writer::tag('label', attendancejourneys_get_string('requestchanges', 'attendancejourneys'), [
                        'for' => $requestid, 'class' => 'mb-0',
                    ]),
                    'attendancejourneys-approval-control'
                );
                $participantcell .= html_writer::empty_tag('input', [
                    'type' => 'text', 'name' => 'reviewnote[' . $userid . ']',
                    'value' => $reviewnotevalues[$userid] ?? ($existing->reviewnote ?? ''),
                    'class' => 'form-control form-control-sm mt-1', 'maxlength' => 255,
                    'placeholder' => attendancejourneys_get_string('reviewnoteplaceholder', 'attendancejourneys'),
                    'aria-label' => attendancejourneys_get_string(
                        'reviewnoteforparticipant',
                        'attendancejourneys',
                        fullname($participant)
                    ),
                ]);
                if (!empty($existing->approved)) {
                    $approvername = isset($approvers[$existing->approvedby]) ?
                        fullname($approvers[$existing->approvedby]) : attendancejourneys_get_string(
                            'attendanceunknownrecorder',
                            'attendancejourneys'
                        );
                    $participantcell .= html_writer::div(
                        attendancejourneys_get_string('attendanceapprovedbyon', 'attendancejourneys', (object) [
                            'user' => $approvername,
                            'date' => userdate(
                                $existing->timeapproved,
                                get_string('strftimedatetimeshort', 'langconfig')
                            ),
                        ]),
                        'attendancejourneys-approval-meta'
                    );
                }
            }
        }
        if (isset($errors[$userid])) {
            $participantcell .= html_writer::div($errors[$userid], 'text-danger small mt-1', [
                'id' => $minuteserrorid,
                'role' => 'alert',
            ]);
        }
        echo html_writer::start_div('attendancejourneys-attendance-row', [
            'data-participant-name' => fullname($participant),
            'data-attendance-state' => $existing ? $selectedstatus : 'unrecorded',
            'data-record-source' => !$existing ? 'unrecorded' :
                ((int) $existing->takenby === $userid ?
                    (!empty($existing->approved) ? 'selfapproved' :
                        (!empty($existing->changesrequested) ? 'selfcorrection' : 'selfpending')) : 'staff'),
        ]);
        echo html_writer::div($participantcell, 'attendancejourneys-participant-cell');
        echo html_writer::div(
            html_writer::tag(
                'span',
                attendancejourneys_get_string(
                    'status',
                    'attendancejourneys'
                ),
                ['class' => 'attendancejourneys-field-label']
            ) .
            $select,
            'attendancejourneys-status-cell'
        );
        echo html_writer::start_div('attendancejourneys-details-cell');
        echo html_writer::start_div('attendancejourneys-minutes-field');
        echo html_writer::tag('label', attendancejourneys_get_string(
            'minutesabsentforparticipant',
            'attendancejourneys',
            fullname($participant)
        ), ['class' => 'attendancejourneys-field-label', 'for' => $minutesid]);
        echo html_writer::div($minutes, 'attendancejourneys-minutes-slot');
        echo html_writer::end_div();
        echo html_writer::div($calculation, 'attendancejourneys-calculation-slot');
        echo html_writer::end_div();
        echo html_writer::div(
            html_writer::tag('label', attendancejourneys_get_string(
                'remarksforparticipant',
                'attendancejourneys',
                fullname($participant)
            ), ['class' => 'attendancejourneys-field-label', 'for' => $remarksid]) . $remarks,
            'attendancejourneys-remarks-cell'
        );
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    echo html_writer::div(
        attendancejourneys_get_string('nofilteredparticipants', 'attendancejourneys'),
        'attendancejourneys-no-filtered-participants alert alert-info',
        ['hidden' => 'hidden', 'role' => 'status']
    );
    echo html_writer::start_div('attendancejourneys-form-actions');
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => attendancejourneys_get_string('saveattendance', 'attendancejourneys'),
        'class' => 'btn btn-primary',
    ]);
    $cancelurl = new moodle_url('/mod/attendancejourneys/sessions.php', ['id' => $cm->id]);
    echo html_writer::link($cancelurl, get_string('cancel'), ['class' => 'btn btn-secondary ml-2 ms-2']);
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo $OUTPUT->paging_bar($totalparticipants, $page, $perpage, $url);

    $PAGE->requires->js_call_amd('mod_attendancejourneys/take', 'init', [$session->duration, [
        'journeygrading' => !empty($attendancejourneys->journeygrading),
        'excusedmode' => $attendancejourneys->excusedmode,
        'decimalpoint' => get_string('decsep', 'langconfig'),
        'excludedlabel' => get_string('attendancecalculationexcluded', 'attendancejourneys'),
        'calculationtemplate' => attendancejourneys_get_string('presencecalculation', 'attendancejourneys', (object) [
            'present' => '{present}', 'total' => '{total}', 'percent' => '{percent}',
        ]),
    ]]);
    $PAGE->requires->js_call_amd('core_form/changechecker', 'watchFormById', ['attendancejourneys-take-form']);
}

echo html_writer::end_div();
echo $OUTPUT->footer();
