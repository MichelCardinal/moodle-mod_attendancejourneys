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
 * Student self-recording page for multiple eligible sessions.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);

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
}

$islight = attendancejourneys_is_light_mode($attendancejourneys);
if (
    !$attendancejourneys->studentselfrecord ||
        !is_enrolled($context, $USER, 'mod/attendancejourneys:canbelisted', true)
) {
    throw new moodle_exception('selfrecordunavailable', 'attendancejourneys');
}

$url = new moodle_url('/mod/attendancejourneys/selfbulk.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(attendancejourneys_get_string('myattendancestocomplete', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('mod_attendancejourneys/selfbulk', 'init');

$sessions = $DB->get_records(
    'attendancejourneys_sessions',
    ['attendancejourneysid' => $attendancejourneys->id],
    'sessiondate ASC'
);
$sessions = array_filter($sessions, fn($session) => attendancejourneys_user_can_self_record_session(
    $context,
    $attendancejourneys,
    $session,
    (int) $USER->id
));
$records = [];
if ($sessions) {
    [$insql, $params] = $DB->get_in_or_equal(array_keys($sessions), SQL_PARAMS_NAMED, 'selfsession');
    $params['selfuserid'] = $USER->id;
    foreach (
        $DB->get_records_select(
            'attendancejourneys_records',
            "userid = :selfuserid AND sessionid $insql",
            $params
        ) as $record
    ) {
        $records[$record->sessionid] = $record;
    }
}

$statuses = optional_param_array('status', [], PARAM_ALPHA);
$minutesvalues = optional_param_array('minutesabsent', [], PARAM_INT);
$remarksvalues = optional_param_array('remarks', [], PARAM_TEXT);
$sheetversions = optional_param_array('sheetversion', [], PARAM_INT);
$errors = [];
$changes = [];

if (data_submitted() && confirm_sesskey()) {
    foreach ($sessions as $session) {
        $record = $records[$session->id] ?? null;
        $locked = !attendancejourneys_self_record_is_editable($record, (int) $USER->id);
        $submittedstatus = $statuses[$session->id] ?? '';
        if ($locked || $submittedstatus === '' || $submittedstatus === 'unrecorded') {
            continue;
        }
        if ((int) ($sheetversions[$session->id] ?? -1) !== (int) $session->timemodified) {
            $errors[] = attendancejourneys_get_string(
                'selfbulksessionchanged',
                'attendancejourneys',
                format_string($session->name)
            );
            continue;
        }
        if (!in_array($submittedstatus, ['present', 'partial'], true)) {
            $errors[] = attendancejourneys_get_string('statusrequired', 'attendancejourneys');
            continue;
        }
        $submittedminutes = (int) ($minutesvalues[$session->id] ?? 0);
        if (
            $submittedstatus === 'partial' &&
                ($submittedminutes < 1 || $submittedminutes >= (int) $session->duration)
        ) {
            $errors[] = attendancejourneys_get_string('selfbulkpartialerror', 'attendancejourneys', format_string($session->name));
            continue;
        }
        $changes[$session->id] = (object) [
            'session' => $session,
            'record' => $record,
            'status' => $submittedstatus,
            'minutesabsent' => $submittedstatus === 'partial' ? $submittedminutes : 0,
            'remarks' => trim($remarksvalues[$session->id] ?? ''),
        ];
    }

    if (!$errors && !$changes) {
        $errors[] = attendancejourneys_get_string('selfbulkselectone', 'attendancejourneys');
    }
    if (!$errors) {
        $transaction = $DB->start_delegated_transaction();
        foreach ($changes as $change) {
            $saved = (object) [
                'sessionid' => $change->session->id,
                'userid' => $USER->id,
                'status' => $change->status,
                'minutesabsent' => $change->minutesabsent,
                'remarks' => $change->remarks,
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
            if ($change->record) {
                $saved->id = $change->record->id;
                $DB->update_record('attendancejourneys_records', $saved);
                if (!empty($change->record->changesrequested)) {
                    attendancejourneys_add_review_history(
                        (int) $change->record->id,
                        (int) $change->session->id,
                        (int) $USER->id,
                        'resubmitted',
                        (int) $USER->id
                    );
                } else {
                    attendancejourneys_add_review_history(
                        (int) $change->record->id,
                        (int) $change->session->id,
                        (int) $USER->id,
                        'updated',
                        (int) $USER->id,
                        attendancejourneys_record_audit_snapshot($saved)
                    );
                }
            } else {
                $saved->id = $DB->insert_record('attendancejourneys_records', $saved);
                attendancejourneys_add_review_history(
                    (int) $saved->id,
                    (int) $change->session->id,
                    (int) $USER->id,
                    'recorded',
                    (int) $USER->id,
                    attendancejourneys_record_audit_snapshot($saved)
                );
            }
            attendancejourneys_touch_session((int) $change->session->id);
            \mod_attendancejourneys\event\attendance_self_recorded::create([
                'objectid' => $change->session->id,
                'context' => $context,
                'other' => ['attendancejourneysid' => $attendancejourneys->id],
            ])->trigger();
        }
        attendancejourneys_update_completion($course, $cm, [$USER->id]);
        attendancejourneys_update_grades($attendancejourneys, $USER->id);
        $transaction->allow_commit();
        redirect(
            new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]),
            attendancejourneys_get_string('selfbulksaved', 'attendancejourneys', count($changes)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'home');
echo html_writer::start_div('attendancejourneys-selfbulk-page');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(
    attendancejourneys_get_string('attendanceworkflow', 'attendancejourneys'),
    'attendancejourneys-section-eyebrow'
);
echo $OUTPUT->heading(attendancejourneys_get_string('myattendancestocomplete', 'attendancejourneys'));
echo html_writer::tag('p', attendancejourneys_get_string('selfbulkintro', 'attendancejourneys'), ['class' => 'text-muted mb-0']);
echo html_writer::end_div();

foreach ($errors as $error) {
    echo $OUTPUT->notification($error, 'error');
}

if (!$sessions) {
    echo html_writer::start_div('attendancejourneys-empty-state');
    echo html_writer::tag('h3', attendancejourneys_get_string('selfbulknone', 'attendancejourneys'), ['class' => 'h5']);
    echo html_writer::tag('p', attendancejourneys_get_string(
        $islight ? 'selfbulknone_helplight' : 'selfbulknone_help',
        'attendancejourneys'
    ), ['class' => 'text-muted mb-3']);
    echo html_writer::link(
        new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]),
        attendancejourneys_get_string('backtoactivity', 'attendancejourneys'),
        ['class' => 'btn btn-outline-secondary']
    );
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('attendancejourneys-selfbulk-progress', [
        'data-label' => attendancejourneys_get_string('selfbulkselectioncount', 'attendancejourneys', '{count}'),
    ]);
    echo html_writer::tag(
        'strong',
        attendancejourneys_get_string('selfbulkselectioncount', 'attendancejourneys', 0),
        ['class' => 'attendancejourneys-selfbulk-progress-value']
    );
    echo html_writer::span(attendancejourneys_get_string('selfbulkselectionhelp', 'attendancejourneys'), 'text-muted small');
    echo html_writer::end_div();

    echo html_writer::start_tag('form', [
        'method' => 'post', 'action' => $url->out(false), 'id' => 'attendancejourneys-selfbulk-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::start_div('attendancejourneys-selfbulk-list');
    foreach ($sessions as $session) {
        $record = $records[$session->id] ?? null;
        $locked = !attendancejourneys_self_record_is_editable($record, (int) $USER->id);
        $selected = $statuses[$session->id] ?? ($record && !$locked ? $record->status : '');
        $minutes = $minutesvalues[$session->id] ?? ($record && !$locked ? $record->minutesabsent : 0);
        $remarks = $remarksvalues[$session->id] ?? ($record && !$locked ? $record->remarks : '');
        echo html_writer::start_div('attendancejourneys-selfbulk-row' . ($locked ? ' is-locked' : ''), [
            'data-duration' => (int) $session->duration,
            'data-template' => attendancejourneys_get_string('selfpresencepreview', 'attendancejourneys', (object) [
                'present' => '{present}', 'possible' => '{possible}', 'percent' => '{percent}',
            ]),
        ]);
        echo html_writer::empty_tag('input', [
            'type' => 'hidden', 'name' => 'sheetversion[' . $session->id . ']',
            'value' => (int) $session->timemodified,
        ]);
        echo html_writer::start_div('attendancejourneys-selfbulk-session');
        echo html_writer::tag('h3', format_string($session->name), ['class' => 'h5 mb-1']);
        echo html_writer::div(userdate(
            $session->sessiondate,
            get_string('strftimedatetimeshort', 'langconfig')
        ) . ' · ' .
            attendancejourneys_get_string('sessiondurationminutes', 'attendancejourneys', $session->duration), 'text-muted small');
        if (!$islight) {
            echo html_writer::div(attendancejourneys_session_delivery_details($session), 'mt-2');
        }
        echo html_writer::end_div();
        if ($locked) {
            echo html_writer::start_div('attendancejourneys-selfbulk-locked');
            echo html_writer::span(
                attendancejourneys_get_string(
                    'status' .
                    $record->status,
                    'attendancejourneys'
                ),
                'badge badge-secondary text-bg-secondary'
            );
            $lockedmessage = !empty($record->approved) && (int) $record->takenby === (int) $USER->id
                ? attendancejourneys_get_string('selfbulkapproved', 'attendancejourneys')
                : attendancejourneys_get_string('selfbulklocked', 'attendancejourneys');
            echo html_writer::div($lockedmessage, 'small text-muted mt-2');
            echo html_writer::end_div();
        } else {
            echo html_writer::start_div('attendancejourneys-selfbulk-entry');
            if ($record && !empty($record->changesrequested)) {
                echo html_writer::start_div('alert alert-warning py-2 attendancejourneys-review-request');
                echo html_writer::tag('strong', attendancejourneys_get_string('changesrequested', 'attendancejourneys'));
                if (trim((string) $record->reviewnote) !== '') {
                    echo html_writer::div(format_text($record->reviewnote, FORMAT_PLAIN), 'small mt-1');
                }
                echo html_writer::end_div();
            }
            echo html_writer::start_tag('fieldset', ['class' => 'attendancejourneys-selfbulk-status']);
            echo html_writer::tag(
                'legend',
                attendancejourneys_get_string(
                    'status',
                    'attendancejourneys'
                ),
                ['class' => 'sr-only visually-hidden']
            );
            $options = $record ? ['present', 'partial'] : ['unrecorded', 'present', 'partial'];
            foreach ($options as $option) {
                $inputid = 'selfbulk-' . $session->id . '-' . $option;
                $input = html_writer::empty_tag('input', [
                    'type' => 'radio', 'name' => 'status[' . $session->id . ']', 'value' => $option,
                    'id' => $inputid, 'class' => 'attendancejourneys-selfbulk-status-input',
                    'checked' => $selected === $option ? 'checked' : null,
                ]);
                $optionlabel = $option === 'unrecorded' ? attendancejourneys_get_string('selfbulklater', 'attendancejourneys') :
                    attendancejourneys_get_string('status' . $option, 'attendancejourneys');
                echo html_writer::tag('label', $input . html_writer::span($optionlabel), [
                    'for' => $inputid,
                    'class' => 'attendancejourneys-status-choice attendancejourneys-status-' . $option,
                ]);
            }
            echo html_writer::end_tag('fieldset');
            echo html_writer::start_div('attendancejourneys-selfbulk-partial', ['hidden' => $selected !== 'partial']);
            echo html_writer::tag('label', attendancejourneys_get_string('minutesabsent', 'attendancejourneys'), [
                'for' => 'selfbulk-minutes-' . $session->id, 'class' => 'small font-weight-bold',
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'number', 'name' => 'minutesabsent[' . $session->id . ']',
                'id' => 'selfbulk-minutes-' . $session->id, 'value' => $minutes, 'min' => 1,
                'max' => max(1, $session->duration - 1), 'class' => 'form-control form-control-sm',
                'disabled' => $selected !== 'partial' ? 'disabled' : null,
            ]);
            echo html_writer::end_div();
            echo html_writer::tag('label', attendancejourneys_get_string('remarks', 'attendancejourneys'), [
                'for' => 'selfbulk-remarks-' . $session->id, 'class' => 'sr-only visually-hidden',
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'text', 'name' => 'remarks[' . $session->id . ']', 'value' => $remarks,
                'maxlength' => 255, 'class' => 'form-control form-control-sm attendancejourneys-selfbulk-remarks',
                'id' => 'selfbulk-remarks-' . $session->id,
                'placeholder' => attendancejourneys_get_string('remarksoptional', 'attendancejourneys'),
            ]);
            echo html_writer::div('', 'attendancejourneys-selfbulk-calculation small', [
                'aria-live' => 'polite',
                'aria-atomic' => 'true',
            ]);
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    echo html_writer::start_div('attendancejourneys-selfbulk-actions');
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'class' => 'btn btn-primary', 'value'
            => attendancejourneys_get_string('selfbulksave', 'attendancejourneys'),
    ]);
    echo html_writer::link(
        new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]),
        get_string('cancel'),
        ['class' => 'btn btn-outline-secondary']
    );
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
}
echo html_writer::end_div();
echo $OUTPUT->footer();
