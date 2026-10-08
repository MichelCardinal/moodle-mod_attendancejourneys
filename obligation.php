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
 * Preview and confirm an authorised individual obligation decision.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$journeyid = required_param('journeyid', PARAM_INT);
$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:managejourneys', $context);
require_capability('moodle/course:manageactivities', $context);
require_capability('mod/attendancejourneys:viewreports', $context);
$activity = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
if (empty($activity->journeygrading)) {
    throw new moodle_exception('obligationrequiresjourneygrading', 'attendancejourneys');
}
$journey = $DB->get_record(
    'attendancejourneys_journeys',
    ['id' => $journeyid, 'attendancejourneysid' => $activity->id],
    '*',
    MUST_EXIST
);
attendancejourneys_require_journey_access($cm, $context, $journey);
$visible = attendancejourneys_get_visible_participants($context, $cm);
$assigned = attendancejourneys_get_journey_participants($context, $journey);
if (!isset($visible[$userid], $assigned[$userid])) {
    throw new moodle_exception('invaliduser');
}
if (empty($journey->active) || attendancejourneys_get_active_closure((int) $activity->id, $userid, $journeyid)) {
    throw new moodle_exception('obligationblockedfinal', 'attendancejourneys');
}
$returnurl = new moodle_url(
    '/mod/attendancejourneys/individual.php',
    ['id' => $id, 'userid' => $userid, 'journeyid' => $journeyid]
);
$url = new moodle_url('/mod/attendancejourneys/obligation.php', ['id' => $id, 'userid' => $userid, 'journeyid' => $journeyid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(attendancejourneys_get_string('obligationmanage', 'attendancejourneys'));

$form = new \mod_attendancejourneys\form\individual_obligation($url);
$confirmation = new \mod_attendancejourneys\form\obligation_confirmation($url);
if ($form->is_cancelled() || $confirmation->is_cancelled()) {
    redirect($returnurl);
}
$preview = null;
if ($data = $confirmation->get_data()) {
    require_sesskey();
    if (!empty($data->editproposal)) {
        $form->set_data($data);
    } else {
        $policy = \mod_attendancejourneys\local\obligation_policy::decision(
            !empty($data->waived),
            (int) $data->starttime,
            (int) $data->endtime,
            $data->reason
        );
        \mod_attendancejourneys\local\individual_obligation::change(
            $cm,
            $journeyid,
            $userid,
            $policy,
            (string) ($data->token ?? '')
        );
        redirect(
            $returnurl,
            attendancejourneys_get_string('obligationsaved', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
} else if ($data = $form->get_data()) {
    require_sesskey();
    $policy = \mod_attendancejourneys\local\obligation_policy::decision(
        !empty($data->waived),
        empty($data->waived) ? (int) $data->starttime : 0,
        empty($data->waived) ? (int) $data->endtime : 0,
        $data->reason
    );
    $preview = \mod_attendancejourneys\local\individual_obligation::preview($cm, $journeyid, $userid, $policy);
} else if (!data_submitted()) {
    $current = \mod_attendancejourneys\local\individual_obligation::get($journeyid, $userid);
    $form->set_data((object) ['id' => $id, 'userid' => $userid, 'journeyid' => $journeyid,
        'waived' => $current->waived, 'starttime' => $current->starttime, 'endtime' => $current->endtime, 'reason' => '']);
}

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'reports');
echo $OUTPUT->heading(attendancejourneys_get_string('obligationmanage', 'attendancejourneys'));
echo $OUTPUT->heading(format_string($journey->name) . ' — ' . s(fullname($visible[$userid])), 3);
echo $OUTPUT->notification(attendancejourneys_get_string('obligationhelp', 'attendancejourneys'), 'info');
if ($preview) {
    $table = new html_table();
    $table->head = [attendancejourneys_get_string('obligationbefore', 'attendancejourneys'),
        attendancejourneys_get_string('obligationafter', 'attendancejourneys')];
    $values = [];
    foreach ([$preview->before, $preview->after] as $calculation) {
        $values[] = attendancejourneys_get_string('obligationcalculation', 'attendancejourneys', (object) [
            'present' => $calculation->presentminutes, 'possible' => $calculation->possibleminutes,
            'percent' => $calculation->percent === null ? '—' : format_float($calculation->percent, 2),
            'recorded' => $calculation->recordedsessions, 'required' => $calculation->applicablesessions,
        ]);
    }
    $table->data[] = $values;
    echo attendancejourneys_responsive_table($table, attendancejourneys_get_string('obligationpreview', 'attendancejourneys'));
    echo html_writer::div(s($preview->proposed->reason), 'mb-3');
    if (!empty($preview->proposed->waived)) {
        echo $OUTPUT->notification(attendancejourneys_get_string('closureobligationwaived', 'attendancejourneys'), 'info');
    }
    $sessionlists = ['sessions' => 'obligationretained', 'excluded' => 'obligationexcluded', 'restored' => 'obligationrestored'];
    foreach ($sessionlists as $field => $key) {
        echo $OUTPUT->heading(attendancejourneys_get_string($key, 'attendancejourneys'), 3);
        if (!$preview->$field) {
            echo html_writer::div(get_string('none'));
            continue;
        }
        $table = new html_table();
        $table->head = [attendancejourneys_get_string('sessionname', 'attendancejourneys'),
            attendancejourneys_get_string('sessiondate', 'attendancejourneys'),
                attendancejourneys_get_string('duration', 'attendancejourneys')];
        foreach ($preview->$field as $session) {
            $table->data[] = [format_string($session->name), userdate($session->sessiondate),
                attendancejourneys_get_string('sessiondurationminutes', 'attendancejourneys', (int) $session->duration)];
        }
        echo attendancejourneys_responsive_table($table, attendancejourneys_get_string($key, 'attendancejourneys'));
    }
    $blocker = attendancejourneys_closure_blocker($preview->after);
    echo $OUTPUT->notification($blocker ? attendancejourneys_get_string($blocker[0], 'attendancejourneys', $blocker[1]) :
        attendancejourneys_get_string('obligationreadytoclose', 'attendancejourneys'), 'info');
    $confirmation->set_data((object) ['id' => $id, 'userid' => $userid, 'journeyid' => $journeyid,
        'waived' => $preview->proposed->waived, 'starttime' => $preview->proposed->starttime,
        'endtime' => $preview->proposed->endtime, 'reason' => $preview->proposed->reason, 'token' => $preview->token]);
    $confirmation->display();
} else {
    $form->display();
}
echo $OUTPUT->footer();
