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
 * Course-local room management.
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$roomid = optional_param('roomid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:managesessions', $context);
attendancejourneys_require_professional_mode($attendancejourneys, (int) $cm->id);

// Keep room changes atomic with session forms that validate and assign room IDs.
if (data_submitted() || ($action === 'delete' && $confirm)) {
    require_sesskey();
    $writelock = new \mod_attendancejourneys\local\write_lock((int) $attendancejourneys->id);
}

$baseurl = new moodle_url('/mod/attendancejourneys/rooms.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(attendancejourneys_get_string('managerooms', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'delete' && $roomid) {
    $room = $DB->get_record('attendancejourneys_rooms', [
        'id' => $roomid, 'attendancejourneysid' => $attendancejourneys->id,
    ], '*', MUST_EXIST);
    $sessioncount = $DB->count_records('attendancejourneys_sessions', ['roomid' => $room->id]);
    if ($sessioncount) {
        redirect(
            $baseurl,
            attendancejourneys_get_string('roomdeleteinuse', 'attendancejourneys', $sessioncount),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    if ($confirm && confirm_sesskey()) {
        $DB->delete_records('attendancejourneys_rooms', ['id' => $room->id]);
        redirect(
            $baseurl,
            attendancejourneys_get_string('roomdeleted', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'rooms');
    echo $OUTPUT->confirm(
        attendancejourneys_get_string('roomdeleteconfirm', 'attendancejourneys', format_string($room->name)),
        new moodle_url($baseurl, ['action' => 'delete', 'roomid' => $room->id,
        'confirm' => 1,
        'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'add' || ($action === 'edit' && $roomid)) {
    $formurl = new moodle_url($baseurl, ['action' => $action] + ($roomid ? ['roomid' => $roomid] : []));
    $form = new \mod_attendancejourneys\form\room($formurl);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    }
    if ($action === 'edit') {
        $room = $DB->get_record('attendancejourneys_rooms', [
            'id' => $roomid, 'attendancejourneysid' => $attendancejourneys->id,
        ], '*', MUST_EXIST);
        $room->id = $cm->id;
        $room->roomid = $roomid;
        $form->set_data($room);
    } else {
        $form->set_data((object) ['id' => $cm->id, 'active' => 1, 'capacity' => 0]);
    }
    if ($data = $form->get_data()) {
        $now = time();
        $record = (object) [
            'attendancejourneysid' => $attendancejourneys->id,
            'name' => trim($data->name),
            'code' => trim($data->code ?? ''),
            'capacity' => max(0, (int) $data->capacity),
            'location' => trim($data->location ?? ''),
            'notes' => trim($data->notes ?? ''),
            'active' => empty($data->active) ? 0 : 1,
            'timemodified' => $now,
        ];
        if (!empty($data->roomid)) {
            $existing = $DB->get_record('attendancejourneys_rooms', [
                'id' => $data->roomid, 'attendancejourneysid' => $attendancejourneys->id,
            ], '*', MUST_EXIST);
            $record->id = $existing->id;
            $DB->update_record('attendancejourneys_rooms', $record);
            $message = attendancejourneys_get_string('roomupdated', 'attendancejourneys');
        } else {
            $record->timecreated = $now;
            $DB->insert_record('attendancejourneys_rooms', $record);
            $message = attendancejourneys_get_string('roomcreated', 'attendancejourneys');
        }
        redirect($baseurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'rooms');
    echo html_writer::start_div('attendancejourneys-section-header');
    echo html_writer::div(
        attendancejourneys_get_string('coursefacilities', 'attendancejourneys'),
        'attendancejourneys-section-eyebrow'
    );
    echo $OUTPUT->heading(attendancejourneys_get_string($action === 'edit' ? 'editroom' : 'addroom', 'attendancejourneys'));
    echo html_writer::tag('p', attendancejourneys_get_string('roomformintro', 'attendancejourneys'), ['class' => 'text-muted']);
    echo html_writer::end_div();
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

$rooms = $DB->get_records('attendancejourneys_rooms', ['attendancejourneysid' => $attendancejourneys->id], 'active DESC, name ASC');
$used = $DB->get_records_sql_menu("SELECT roomid, COUNT(id) FROM {attendancejourneys_sessions}
    WHERE attendancejourneysid = :activityid AND roomid > 0 GROUP BY roomid", ['activityid' => $attendancejourneys->id]);

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'rooms');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(
    attendancejourneys_get_string('coursefacilities', 'attendancejourneys'),
    'attendancejourneys-section-eyebrow'
);
echo $OUTPUT->heading(attendancejourneys_get_string('managerooms', 'attendancejourneys'));
echo html_writer::tag('p', attendancejourneys_get_string('manageroomsintro', 'attendancejourneys'), ['class' => 'text-muted']);
echo html_writer::end_div();
echo $OUTPUT->single_button(
    new moodle_url($baseurl, ['action' => 'add']),
    attendancejourneys_get_string('addroom', 'attendancejourneys'),
    'get',
    ['class' => 'mb-3', 'type' => 'primary']
);

if (!$rooms) {
    echo html_writer::div(
        html_writer::tag('h3', attendancejourneys_get_string('norooms', 'attendancejourneys')) .
        html_writer::tag('p', attendancejourneys_get_string('norooms_help', 'attendancejourneys'), ['class' => 'text-muted']),
        'attendancejourneys-empty-state'
    );
} else {
    $table = new html_table();
    $table->head = [attendancejourneys_get_string(
        'room',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        'roomcapacity',
        'attendancejourneys'
    ),

        attendancejourneys_get_string(
            'roomlocation',
            'attendancejourneys'
        ),
    get_string('status'),
    attendancejourneys_get_string(
        'sessions',
        'attendancejourneys'
    ),

        get_string('actions')];
    foreach ($rooms as $room) {
        $title = html_writer::tag('strong', format_string($room->name));
        if ($room->code !== '') {
            $title .= html_writer::div(s($room->code), 'text-muted small');
        }
        if ($room->notes !== '') {
            $title .= html_writer::div(s($room->notes), 'text-muted small mt-1');
        }
        $actions = $OUTPUT->action_icon(new moodle_url(
            $baseurl,
            ['action' => 'edit', 'roomid' => $room->id]
        ), new pix_icon('t/edit', get_string('edit')));
        $actions .= $OUTPUT->action_icon(new moodle_url(
            $baseurl,
            ['action' => 'delete', 'roomid' => $room->id]
        ), new pix_icon('t/delete', get_string('delete')));
        $table->data[] = [$title, $room->capacity ? $room->capacity : attendancejourneys_get_string(
            'unlimited',
            'attendancejourneys'
        ),
            s($room->location), get_string($room->active ? 'active' : 'inactive'), (int) ($used[$room->id] ?? 0),
            $actions];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
