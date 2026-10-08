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
 * Preview and confirm an authorised new personal attendance attempt.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$rootjourneyid = required_param('rootjourneyid', PARAM_INT);
$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:managejourneys', $context);
require_capability('mod/attendancejourneys:manageattempts', $context);
require_capability('moodle/course:manageactivities', $context);
require_capability('mod/attendancejourneys:viewreports', $context);
$activity = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
// Authorise even a read-only initial display through the same service as confirmation.
$initial = \mod_attendancejourneys\local\attempt_opening::preview($cm, $rootjourneyid, $userid, 'Preview', 'Preview');
$participant = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
$returnurl = new moodle_url('/mod/attendancejourneys/individual.php', [
    'id' => $id, 'userid' => $userid, 'journeyid' => $initial->current->journeyid,
]);
$url = new moodle_url('/mod/attendancejourneys/attempt.php', [
    'id' => $id, 'userid' => $userid, 'rootjourneyid' => $rootjourneyid,
]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(attendancejourneys_get_string('attemptopen', 'attendancejourneys'));
$form = new \mod_attendancejourneys\form\attempt_proposal($url);
$confirmation = new \mod_attendancejourneys\form\attempt_confirmation($url);
if ($form->is_cancelled() || $confirmation->is_cancelled()) {
    redirect($returnurl);
}
$preview = null;
if ($data = $confirmation->get_data()) {
    require_sesskey();
    if (!empty($data->editproposal)) {
        $form->set_data($data);
    } else {
        $opening = \mod_attendancejourneys\local\attempt_opening::confirm(
            $cm,
            $rootjourneyid,
            $userid,
            $data->name,
            $data->reason,
            (string) ($data->token ?? '')
        );
        redirect(new moodle_url('/mod/attendancejourneys/individual.php', [
            'id' => $id, 'userid' => $userid, 'journeyid' => $opening->journeyid,
        ]), attendancejourneys_get_string('attemptopened', 'attendancejourneys'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
} else if ($data = $form->get_data()) {
    require_sesskey();
    $preview = \mod_attendancejourneys\local\attempt_opening::preview($cm, $rootjourneyid, $userid, $data->name, $data->reason);
} else if (!data_submitted()) {
    $form->set_data((object) ['id' => $id, 'userid' => $userid, 'rootjourneyid' => $rootjourneyid]);
}

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'reports');
echo $OUTPUT->heading(attendancejourneys_get_string('attemptopen', 'attendancejourneys'));
echo $OUTPUT->heading(format_string($initial->root->name) . ' — ' . s(fullname($participant)), 3);
echo $OUTPUT->notification(attendancejourneys_get_string('attemptopeninghelp', 'attendancejourneys'), 'warning');
if ($preview) {
    $table = new html_table();
    $table->head = [attendancejourneys_get_string('obligationbefore', 'attendancejourneys'),
        attendancejourneys_get_string('obligationafter', 'attendancejourneys')];
    $table->data[] = [($preview->current->closure->percentage === null ? '—' :
        format_float($preview->current->closure->percentage, 2) . ' %'),
        attendancejourneys_get_string('attemptresultpending', 'attendancejourneys')];
    echo attendancejourneys_responsive_table($table, attendancejourneys_get_string('obligationpreview', 'attendancejourneys'));
    echo $OUTPUT->heading(s($preview->name), 3);
    echo html_writer::div(attendancejourneys_get_string('attemptpersonalnumber', 'attendancejourneys', $preview->attemptnumber));
    echo html_writer::div(get_string(
        'thresholdvalue',
        'attendancejourneys',
        format_float(attendancejourneys_get_passinggrade($activity, $rootjourneyid), 2) . ' %'
    ));
    echo html_writer::div(s($preview->reason), 'mb-3');
    $confirmation->set_data((object) ['id' => $id, 'userid' => $userid, 'rootjourneyid' => $rootjourneyid,
        'name' => $preview->name, 'reason' => $preview->reason, 'token' => $preview->token]);
    $confirmation->display();
} else {
    $form->display();
}
echo $OUTPUT->footer();
