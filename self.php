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
 * Student self-recording page for one Attendance Journeys session.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
$session = $DB->get_record('attendancejourneys_sessions', [
    'id' => $sessionid,
    'attendancejourneysid' => $attendancejourneys->id,
], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:view', $context);
require_capability('mod/attendancejourneys:canbelisted', $context);
require_capability('mod/attendancejourneys:selfrecord', $context);
// A shared activity lock also protects concurrent gradebook updates across sessions.
if (data_submitted()) {
    require_sesskey();
    $writelock = new \mod_attendancejourneys\local\write_lock((int) $attendancejourneys->id);
    $attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
    $session = $DB->get_record('attendancejourneys_sessions', [
        'id' => $sessionid, 'attendancejourneysid' => $attendancejourneys->id,
    ], '*', MUST_EXIST);
}

if (!is_enrolled($context, $USER, 'mod/attendancejourneys:canbelisted', true)) {
    throw new moodle_exception('selfrecordunavailable', 'attendancejourneys');
}
if (!$attendancejourneys->studentselfrecord) {
    throw new moodle_exception('selfrecordunavailable', 'attendancejourneys');
}
if (!attendancejourneys_user_can_self_record_session($context, $attendancejourneys, $session, (int) $USER->id)) {
    throw new moodle_exception('selfrecordunavailable', 'attendancejourneys');
}

$url = new moodle_url('/mod/attendancejourneys/self.php', ['id' => $cm->id, 'sessionid' => $session->id]);
$PAGE->set_url($url);
$PAGE->set_title(attendancejourneys_get_string('savemyattendance', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('mod_attendancejourneys/self', 'init', [(int) $session->duration]);

$existing = $DB->get_record('attendancejourneys_records', ['sessionid' => $session->id, 'userid' => $USER->id]);
if (!attendancejourneys_self_record_is_editable($existing ?: null, (int) $USER->id)) {
    throw new moodle_exception('selfrecordlockedbystaff', 'attendancejourneys');
}
$status = optional_param('status', $existing->status ?? 'present', PARAM_ALPHA);
$minutesabsent = optional_param('minutesabsent', $existing->minutesabsent ?? 0, PARAM_INT);
$remarks = optional_param('remarks', $existing->remarks ?? '', PARAM_TEXT);
$error = '';

if (data_submitted() && confirm_sesskey()) {
    if (!in_array($status, ['present', 'partial'], true)) {
        $error = attendancejourneys_get_string('statusrequired', 'attendancejourneys');
    } else if ($status === 'partial' && ($minutesabsent < 1 || $minutesabsent >= (int) $session->duration)) {
        $error = attendancejourneys_get_string('partialminuteserror', 'attendancejourneys', $session->duration);
    } else {
        $record = (object) [
            'sessionid' => $session->id,
            'userid' => $USER->id,
            'status' => $status,
            'minutesabsent' => $status === 'present' ? 0 : $minutesabsent,
            'remarks' => trim($remarks),
            'takenby' => $USER->id,
            'approved' => 0,
            'approvedby' => 0,
            'timeapproved' => 0,
            'changesrequested' => 0,
            'reviewnote' => '',
            'reviewedby' => 0,
            'timereviewed' => 0,
            'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('attendancejourneys_records', $record);
            if (!empty($existing->changesrequested)) {
                attendancejourneys_add_review_history(
                    (int) $existing->id,
                    (int) $session->id,
                    (int) $USER->id,
                    'resubmitted',
                    (int) $USER->id
                );
            } else {
                attendancejourneys_add_review_history(
                    (int) $existing->id,
                    (int) $session->id,
                    (int) $USER->id,
                    'updated',
                    (int) $USER->id,
                    attendancejourneys_record_audit_snapshot($record)
                );
            }
        } else {
            $record->id = $DB->insert_record('attendancejourneys_records', $record);
            attendancejourneys_add_review_history(
                (int) $record->id,
                (int) $session->id,
                (int) $USER->id,
                'recorded',
                (int) $USER->id,
                attendancejourneys_record_audit_snapshot($record)
            );
        }
        attendancejourneys_touch_session((int) $session->id);
        \mod_attendancejourneys\event\attendance_self_recorded::create([
            'objectid' => $session->id,
            'context' => $context,
            'other' => ['attendancejourneysid' => $attendancejourneys->id],
        ])->trigger();
        attendancejourneys_update_completion($course, $cm, [$USER->id]);
        attendancejourneys_update_grades($attendancejourneys, $USER->id);
        redirect(
            new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]),
            attendancejourneys_get_string('attendancesaved', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'home');
echo html_writer::start_div('attendancejourneys-self-page');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(
    attendancejourneys_get_string('attendanceworkflow', 'attendancejourneys'),
    'attendancejourneys-section-eyebrow'
);
echo $OUTPUT->heading(attendancejourneys_get_string('savemyattendance', 'attendancejourneys'));
echo html_writer::end_div();
if ($existing && !empty($existing->changesrequested)) {
    echo html_writer::start_div('alert alert-warning attendancejourneys-review-request');
    echo html_writer::tag('strong', attendancejourneys_get_string('changesrequested', 'attendancejourneys'));
    if (trim((string) $existing->reviewnote) !== '') {
        echo html_writer::div(format_text($existing->reviewnote, FORMAT_PLAIN), 'mt-1');
    }
    echo html_writer::div(attendancejourneys_get_string('changesrequestedhelp', 'attendancejourneys'), 'small mt-1');
    echo html_writer::end_div();
}
echo html_writer::start_div('attendancejourneys-self-session attendancejourneys-session-summary');
echo html_writer::start_div('attendancejourneys-self-session-heading');
echo html_writer::tag('h3', format_string($session->name), ['class' => 'h4 mb-1']);
echo attendancejourneys_session_delivery_details($session);
echo html_writer::end_div();
$period = attendancejourneys_get_string('sessionperiod', 'attendancejourneys', (object) [
    'start' => userdate($session->sessiondate, get_string('strftimedatetime', 'langconfig')),
    'end' => userdate($session->sessiondate + ($session->duration * MINSECS), get_string('strftimedatetime', 'langconfig')),
]);
echo html_writer::start_div('attendancejourneys-self-session-facts');
foreach (
    [
    [attendancejourneys_get_string('sessiondate', 'attendancejourneys'), $period],
    [attendancejourneys_get_string(
        'duration',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        'sessiondurationminutes',
        'attendancejourneys',
        $session->duration
    )],

    [attendancejourneys_get_string('sessionaudience', 'attendancejourneys'), attendancejourneys_session_audience($session)],
    ] as [$label, $value]
) {
    echo html_writer::start_div('attendancejourneys-self-session-fact');
    echo html_writer::span($label, 'attendancejourneys-self-session-label');
    echo html_writer::tag('strong', $value);
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo html_writer::end_div();
if ($error) {
    echo $OUTPUT->notification($error, 'error');
}

echo html_writer::start_div('attendancejourneys-self-notice');
echo html_writer::tag('strong', attendancejourneys_get_string('selfrecordnotice', 'attendancejourneys'));
echo html_writer::div(attendancejourneys_get_string('selfrecordnotice_help', 'attendancejourneys'));
echo html_writer::end_div();

echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $url->out(false), 'class' => 'attendancejourneys-self-form',
    'id' => 'attendancejourneys-self-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('h3', attendancejourneys_get_string('selfdeclaration', 'attendancejourneys'), ['class' => 'h5 mb-1']);
echo html_writer::div(attendancejourneys_get_string('selfrecordintro', 'attendancejourneys'), 'text-muted mb-4');

echo html_writer::start_tag('fieldset', ['class' => 'attendancejourneys-self-status-fieldset']);
echo html_writer::tag('legend', attendancejourneys_get_string('status', 'attendancejourneys'), ['class' => 'font-weight-bold']);
echo html_writer::start_div('attendancejourneys-status-options attendancejourneys-self-status-options');
foreach (['present', 'partial'] as $option) {
    $inputid = 'attendancejourneys-self-status-' . $option;
    $input = html_writer::empty_tag('input', [
        'type' => 'radio', 'name' => 'status', 'value' => $option, 'id' => $inputid,
        'class' => 'attendancejourneys-self-status', 'checked' => $status === $option ? 'checked' : null,
    ]);
    $label = html_writer::span(attendancejourneys_get_string('status' . $option, 'attendancejourneys'));
    echo html_writer::tag('label', $input . $label, [
        'for' => $inputid, 'class' => 'attendancejourneys-status-choice attendancejourneys-status-' . $option,
    ]);
}
echo html_writer::end_div();
echo html_writer::end_tag('fieldset');

echo html_writer::start_div('attendancejourneys-self-partial', ['hidden' => $status !== 'partial']);
echo html_writer::tag('label', attendancejourneys_get_string('minutesabsent', 'attendancejourneys'), [
    'for' => 'attendancejourneys-self-minutes', 'class' => 'font-weight-bold',
]);
echo html_writer::div(attendancejourneys_get_string('minutesabsent_help', 'attendancejourneys'), 'text-muted small mb-2');
echo html_writer::empty_tag('input', [
    'type' => 'number', 'name' => 'minutesabsent', 'value' => $minutesabsent, 'min' => 1,
    'max' => max(1, $session->duration - 1), 'class' => 'form-control', 'id' => 'attendancejourneys-self-minutes',
    'disabled' => $status !== 'partial' ? 'disabled' : null,
]);
echo html_writer::end_div();

$presentminutes = $status === 'partial' ? max(0, $session->duration - $minutesabsent) : $session->duration;
$percent = $session->duration ? format_float(($presentminutes / $session->duration) * 100, 1) : format_float(0, 1);
echo html_writer::start_div('attendancejourneys-self-calculation', [
    'aria-live' => 'polite',
    'aria-atomic' => 'true',
    'data-template' => attendancejourneys_get_string('selfpresencepreview', 'attendancejourneys', (object) [
        'present' => '{present}', 'possible' => '{possible}', 'percent' => '{percent}',
    ]),
]);
echo html_writer::span(
    attendancejourneys_get_string('calculatedpresence', 'attendancejourneys'),
    'attendancejourneys-self-calculation-label'
);
echo html_writer::tag('strong', attendancejourneys_get_string('selfpresencepreview', 'attendancejourneys', (object) [
    'present' => $presentminutes, 'possible' => $session->duration, 'percent' => $percent,
]), ['class' => 'attendancejourneys-self-calculation-value']);
echo html_writer::end_div();

echo html_writer::start_div('attendancejourneys-self-remarks-group');
echo html_writer::tag('label', attendancejourneys_get_string('remarks', 'attendancejourneys'), [
    'for' => 'attendancejourneys-self-remarks', 'class' => 'font-weight-bold',
]);
echo html_writer::tag('textarea', s($remarks), [
    'name' => 'remarks', 'maxlength' => 255, 'rows' => 3,
    'class' => 'form-control', 'id' => 'attendancejourneys-self-remarks',
]);
echo html_writer::end_div();

echo html_writer::start_div('attendancejourneys-self-actions');
echo html_writer::empty_tag(
    'input',
    ['type' => 'submit',
    'value' => attendancejourneys_get_string(
        'savemyattendance',
        'attendancejourneys'
    ),

    'class' => 'btn btn-primary']
);
echo html_writer::link(
    new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]),
    get_string('cancel'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo $OUTPUT->footer();
