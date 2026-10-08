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
 * Manage attendance journeys and their lifecycle.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$journeyid = optional_param('journeyid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:managejourneys', $context);
// Coordinate administrative changes with attendance submissions before reading mutable state.
if (
    data_submitted() || (($action !== '')
        && optional_param('sesskey', '', PARAM_RAW) !== '' && confirm_sesskey())
) {
    require_sesskey();
    $writelock = new \mod_attendancejourneys\local\write_lock((int) $activity->id);
    $activity = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
}

attendancejourneys_require_professional_mode($activity, (int) $cm->id);
if ($journeyid) {
    $requestedjourney = $DB->get_record('attendancejourneys_journeys', [
        'id' => $journeyid, 'attendancejourneysid' => $activity->id,
    ], '*', MUST_EXIST);
    attendancejourneys_require_journey_access(
        $cm,
        $context,
        $requestedjourney,
        in_array($action, ['delete', 'complete', 'reopen', 'edit'], true)
    );
}
$url = new moodle_url('/mod/attendancejourneys/journeys.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(attendancejourneys_get_string('managejourneys', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));

if ($action === 'delete' && $journeyid) {
    $journey = $DB->get_record(
        'attendancejourneys_journeys',
        ['id' => $journeyid, 'attendancejourneysid' => $activity->id],
        '*',
        MUST_EXIST
    );
    $deletioninfo = attendancejourneys_journey_deletion_info((int) $activity->id, (int) $journey->id);
    if (!$deletioninfo->candelete) {
        if ($deletioninfo->hascancellationhistory) {
            redirect(
                $url,
                attendancejourneys_get_string('journeydeleteblockedcancellation', 'attendancejourneys'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        if ($deletioninfo->equivalences) {
            redirect(
                $url,
                attendancejourneys_get_string('journeydeleteblockedequivalence', 'attendancejourneys'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        redirect($url, attendancejourneys_get_string('journeydeleteblocked', 'attendancejourneys', (object) [
            'records' => $deletioninfo->records, 'results' => $deletioninfo->closures,
        ]), null, \core\output\notification::NOTIFY_ERROR);
    }
    if ($confirm) {
        require_sesskey();
        $deletedsessions = attendancejourneys_delete_journey((int) $activity->id, (int) $journey->id);
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
        attendancejourneys_update_grades($activity);
        redirect(
            $url,
            attendancejourneys_get_string('journeydeleted', 'attendancejourneys', $deletedsessions),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'journeys');
    echo $OUTPUT->heading(attendancejourneys_get_string('deletejourney', 'attendancejourneys'));
    $confirmurl = new moodle_url($url, ['action' => 'delete', 'journeyid' => $journey->id,
        'confirm' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->confirm(attendancejourneys_get_string('deletejourneyconfirm', 'attendancejourneys', (object) [
        'name' => format_string($journey->name), 'sessions' => $deletioninfo->sessions,
    ]), $confirmurl, $url);
    echo $OUTPUT->footer();
    exit;
}

if (in_array($action, ['complete', 'reopen'], true) && $journeyid) {
    require_capability('mod/attendancejourneys:' . ($action === 'complete' ? 'closejourneys' : 'reopenjourneys'), $context);
    $journey = $DB->get_record(
        'attendancejourneys_journeys',
        ['id' => $journeyid, 'attendancejourneysid' => $activity->id],
        '*',
        MUST_EXIST
    );
    $conflicts = attendancejourneys_find_journey_conflicts(
        $context,
        (int) $activity->id,
        (int) $journey->groupid,
        (int) $journey->id
    );
    if ($conflicts) {
        redirect(
            $url,
            attendancejourneys_get_string('journeyreopenconflict', 'attendancejourneys', count($conflicts)),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    if ($action === 'complete') {
        foreach (attendancejourneys_get_journey_participants($context, $journey) as $participant) {
            if (attendancejourneys_get_active_closure((int) $activity->id, (int) $participant->id, (int) $journey->id)) {
                continue;
            }
            $calculation = attendancejourneys_calculate_user_attendance($activity, (int) $participant->id, (int) $journey->id);
            $blocker = attendancejourneys_closure_blocker($calculation);
            if ($blocker) {
                redirect(
                    $url,
                    attendancejourneys_get_string($blocker[0], 'attendancejourneys', $blocker[1]),
                    null,
                    \core\output\notification::NOTIFY_WARNING
                );
            }
        }
    }
    if ($confirm) {
        require_sesskey();
        if ($action === 'complete') {
            $userids = attendancejourneys_complete_journey($activity, $journey, $context, (int) $USER->id);
            $message = attendancejourneys_get_string('journeycompleted', 'attendancejourneys', count($userids));
        } else {
            $userids = attendancejourneys_reopen_completed_journey($journey, (int) $USER->id);
            $message = attendancejourneys_get_string('completedjourneyreopened', 'attendancejourneys', count($userids));
        }
        attendancejourneys_update_completion($course, $cm, $userids);
        foreach ($userids as $userid) {
            attendancejourneys_update_grades($activity, $userid);
        }
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $participants = attendancejourneys_get_journey_participants(
        $context,
        $journey,
        'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename'
    );
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'journeys');
    echo $OUTPUT->heading(attendancejourneys_get_string(
        $action === 'complete' ? 'completejourney' : 'reopencompletedjourney',
        'attendancejourneys'
    ));
    echo html_writer::tag('h3', format_string($journey->name), ['class' => 'h5']);
    if ($action === 'complete') {
        echo $OUTPUT->notification(attendancejourneys_get_string('completejourneywarning', 'attendancejourneys'), 'warning');
        $preview = new html_table();
        $preview->head = [attendancejourneys_get_string(
            'participant',
            'attendancejourneys'
        ),
        attendancejourneys_get_string(
            'markedsessions',
            'attendancejourneys'
        ),

            attendancejourneys_get_string('percentage', 'attendancejourneys'),
                attendancejourneys_get_string('result', 'attendancejourneys')];
        foreach ($participants as $participant) {
            $calculation = attendancejourneys_calculate_user_attendance(
                $activity,
                (int) $participant->id,
                (int) $journey->id
            );
            $percent = $calculation->percent === null ? '—' : format_float($calculation->percent, 1) . ' %';
            $result = $calculation->percent === null || empty($activity->percentageenabled) ? '—' :
                ($calculation->percent >= $calculation->threshold ? attendancejourneys_get_string('passed', 'attendancejourneys') :
                    attendancejourneys_get_string('failed', 'attendancejourneys'));
            $preview->data[] = [fullname($participant),
                $calculation->recordedsessions . ' / ' . $calculation->applicablesessions, $percent, $result];
        }
        echo attendancejourneys_responsive_table($preview, attendancejourneys_get_string('completejourney', 'attendancejourneys'));
    }
    $confirmurl = new moodle_url($url, ['action' => $action, 'journeyid' => $journey->id,
        'confirm' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->confirm(attendancejourneys_get_string($action === 'complete' ? 'completejourneyconfirm' :
        'reopencompletedjourneyconfirm', 'attendancejourneys', count($participants)), $confirmurl, $url);
    echo $OUTPUT->footer();
    exit;
}

if (in_array($action, ['add', 'edit'], true)) {
    $formurl = new moodle_url($url, ['action' => $action, 'journeyid' => $journeyid]);
    $editingjourney = $action === 'edit' ? $DB->get_record(
        'attendancejourneys_journeys',
        ['id' => $journeyid, 'attendancejourneysid' => $activity->id],
        '*',
        MUST_EXIST
    ) : null;
    $form = new \mod_attendancejourneys\form\journey($formurl, ['cm' => $cm, 'attendancejourneys' => $activity,
        'editing' => $action === 'edit', 'journey' => $editingjourney]);
    if ($form->is_cancelled()) {
        redirect($url);
    }
    if ($action === 'edit') {
        $journey = $DB->get_record(
            'attendancejourneys_journeys',
            ['id' => $journeyid, 'attendancejourneysid' => $activity->id],
            '*',
            MUST_EXIST
        );
        $journey->id = $cm->id;
        $journey->journeyid = $journeyid;
        $journey->customthreshold = $journey->passinggrade !== null;
        $journey->passinggrade = empty($journey->rootjourneyid) ? ($journey->passinggrade ?? $activity->passinggrade) :
            attendancejourneys_get_passinggrade($activity, $journeyid);
        if (!empty($journey->rootjourneyid)) {
            $journey->customthreshold = 1;
            $journey->required = \mod_attendancejourneys\local\attempt_family::root($activity, $journeyid)->required;
        }
        $journey->hasdates = !empty($journey->startdate) || !empty($journey->enddate);
        $form->set_data($journey);
    } else {
        $form->set_data((object) ['id' => $cm->id, 'startdate' => time(), 'enddate' => time()]);
    }
    if ($data = $form->get_data()) {
        $record = (object) ['attendancejourneysid' => $activity->id, 'name' => trim($data->name),
            'description' => trim($data->description ?? ''),
            'passinggrade' => empty($data->customthreshold) ? null : (float) $data->passinggrade,
            'required' => empty($activity->journeygrading) ? 1 : (int) !empty($data->required),
            'defaultmodality' => $data->defaultmodality ?? 'unspecified',
            'capacity' => max(0, (int) ($data->capacity ?? 0)),
            'waitlistenabled' => empty($data->waitlistenabled) ? 0 : 1,
            'groupid' => isset($journey) ? (int) $journey->groupid : (int) $data->groupid,
            'startdate' => empty($data->hasdates) ? 0 : (int) $data->startdate,
            'enddate' => empty($data->hasdates) ? 0 : (int) $data->enddate,
            'active' => isset($journey) ? (int) $journey->active : 1,
            'timemodified' => time()];
        if (!empty($editingjourney->rootjourneyid)) {
            $record->passinggrade = attendancejourneys_get_passinggrade($activity, $journeyid);
            $record->required = \mod_attendancejourneys\local\attempt_family::root($activity, $journeyid)->required;
        }
        $policyerror = attendancejourneys_journey_threshold_policy_error($editingjourney, $record->passinggrade);
        if ($policyerror) {
            throw new moodle_exception($policyerror, 'attendancejourneys');
        }
        attendancejourneys_require_journey_access($cm, $context, $record, true);
        if (!empty($data->journeyid)) {
            $record->id = $data->journeyid;
            $DB->update_record('attendancejourneys_journeys', $record);
            $DB->set_field(
                'attendancejourneys_sessions',
                'groupid',
                $record->groupid,
                ['attendancejourneysid' => $activity->id, 'journeyid' => $record->id]
            );
            if (!empty($activity->journeygrading)) {
                $affected = array_keys(attendancejourneys_get_journey_participants($context, $record));
                attendancejourneys_update_completion($course, $cm, $affected);
                attendancejourneys_update_grades($activity);
            }
            $message = attendancejourneys_get_string('journeyupdated', 'attendancejourneys');
        } else {
            $record->timecreated = time();
            $record->audiencemode = empty($activity->journeygrading) ? 0 : 2;
            $record->id = $DB->insert_record('attendancejourneys_journeys', $record);
            $assigneduserids = attendancejourneys_assign_journey_members($context, $record);
            attendancejourneys_update_completion($course, $cm, $assigneduserids);
            foreach ($assigneduserids as $assigneduserid) {
                attendancejourneys_update_grades($activity, (int) $assigneduserid);
            }
            $message = attendancejourneys_get_string('journeycreated', 'attendancejourneys');
        }
        if (!empty($activity->journeygrading)) {
            attendancejourneys_grade_item_update($activity);
        }
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'journeys');
    echo html_writer::start_div('attendancejourneys-section-header');
    echo html_writer::div(
        attendancejourneys_get_string('journeyframework', 'attendancejourneys'),
        'attendancejourneys-section-eyebrow'
    );
    echo $OUTPUT->heading(attendancejourneys_get_string($action === 'add' ? 'createjourney' : 'editjourney', 'attendancejourneys'));
    echo html_writer::div(attendancejourneys_get_string('journeyformintro', 'attendancejourneys'), 'text-muted');
    echo html_writer::end_div();
    echo html_writer::start_div('attendancejourneys-form-card');
    $form->display();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$journeys = attendancejourneys_visible_journeys($context, (int) $activity->id);
$visibleparticipants = attendancejourneys_get_visible_participants($context, $cm);
echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'journeys');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(
    attendancejourneys_get_string('journeyframework', 'attendancejourneys'),
    'attendancejourneys-section-eyebrow'
);
echo $OUTPUT->heading(attendancejourneys_get_string('managejourneys', 'attendancejourneys'));
echo html_writer::tag(
    'p',
    attendancejourneys_get_string('journeysfoundationhelp', 'attendancejourneys'),
    ['class' => 'text-muted']
);
echo html_writer::end_div();

$activecount = count(array_filter($journeys, static fn($journey) => !empty($journey->active)));
$completedcount = count($journeys) - $activecount;
$journeysessions = $DB->count_records_select(
    'attendancejourneys_sessions',
    'attendancejourneysid = :attendancejourneysid AND journeyid > 0',
    ['attendancejourneysid' => $activity->id]
);
echo html_writer::start_div('attendancejourneys-journey-overview');
echo html_writer::start_div('attendancejourneys-journey-metrics');
foreach (
    [[count($journeys), attendancejourneys_get_string('journeys', 'attendancejourneys')],
        [$activecount, attendancejourneys_get_string('activejourneys', 'attendancejourneys')],
        [$completedcount, attendancejourneys_get_string('completedjourneys', 'attendancejourneys')],
        [$journeysessions, attendancejourneys_get_string('journeysessions', 'attendancejourneys')]] as [$value, $label]
) {
    echo html_writer::div(
        html_writer::tag('strong', (string) $value) . html_writer::span($label),
        'attendancejourneys-journey-metric'
    );
}
echo html_writer::end_div();
echo $OUTPUT->single_button(
    new moodle_url($url, ['action' => 'add']),
    attendancejourneys_get_string('createjourney', 'attendancejourneys'),
    'get',
    ['type' => 'primary', 'class' => 'attendancejourneys-journey-create']
);
echo html_writer::end_div();
if ($journeys) {
    $memberships = [];
    foreach ($journeys as $journey) {
        if (empty($journey->active)) {
            continue;
        }
        if (attendancejourneys_journey_has_fixed_audience($journey)) {
            $activeids = $DB->get_fieldset_select(
                'attendancejourneys_members',
                'userid',
                'journeyid = :journeyid AND active = 1',
                ['journeyid' => $journey->id]
            );
        } else {
            $activeids = array_keys(attendancejourneys_get_journey_participants($context, $journey));
        }
        foreach (array_intersect($activeids, array_keys($visibleparticipants)) as $participantid) {
            $memberships[$participantid][] = $journey->id;
        }
    }
    $overlappingids = array_filter($memberships, static fn($ids) => count($ids) > 1);
    if ($overlappingids) {
        echo $OUTPUT->notification(
            attendancejourneys_get_string(
                empty($activity->journeygrading) ? 'journeyoverlapwarning' : 'journeymultipleobligations',
                'attendancejourneys',
                count($overlappingids)
            ),
            empty($activity->journeygrading) ? 'warning' : 'info'
        );
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable attendancejourneys-journeys-table';
    $table->head = [attendancejourneys_get_string(
        'journeyname',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        'journeyaudience',
        'attendancejourneys'
    ),

        attendancejourneys_get_string(
            'journeyplaces',
            'attendancejourneys'
        ),
    attendancejourneys_get_string(
        'defaultmodality',
        'attendancejourneys'
    ),

        attendancejourneys_get_string('journeyperiod', 'attendancejourneys'),
        attendancejourneys_get_string(
            'sessions',
            'attendancejourneys'
        ),
    attendancejourneys_get_string(
        'journeywaitlistshort',
        'attendancejourneys'
    ),

        attendancejourneys_get_string('journeystatus', 'attendancejourneys'), get_string('actions')];
    foreach ($journeys as $journey) {
        $audience = attendancejourneys_journey_has_fixed_audience($journey) ?
            attendancejourneys_get_string('journeyselectedaudience', 'attendancejourneys') :
            (empty($journey->groupid) ? attendancejourneys_get_string('allactiveparticipants', 'attendancejourneys') :
            (groups_get_group_name($journey->groupid) ?: attendancejourneys_get_string('unknowngroup', 'attendancejourneys')));
        $period = empty($journey->startdate) ? attendancejourneys_get_string('journeynodates', 'attendancejourneys') :
            userdate($journey->startdate, get_string('strftimedatefullshort', 'langconfig')) . ' – ' .
            userdate($journey->enddate, get_string('strftimedatefullshort', 'langconfig'));
        $count = $DB->count_records('attendancejourneys_sessions', ['journeyid' => $journey->id]);
        $waitingcount = empty($journey->waitlistenabled) ? null : $DB->count_records(
            'attendancejourneys_waitlist',
            ['journeyid' => $journey->id, 'status' => 'waiting']
        );
        $participants = array_intersect_key(
            attendancejourneys_get_journey_participants($context, $journey),
            $visibleparticipants
        );
        $participantcount = count($participants);
        if (empty($journey->capacity)) {
            $places = html_writer::span(
                attendancejourneys_get_string('journeycapacityunlimited', 'attendancejourneys'),
                'badge badge-secondary text-bg-secondary'
            );
        } else {
            $remaining = max(0, (int) $journey->capacity - $participantcount);
            $excess = max(0, $participantcount - (int) $journey->capacity);
            $placeclass = $excess > 0 ? 'badge badge-danger text-bg-danger' :
                ($remaining === 0 ? 'badge badge-warning text-bg-warning' : 'badge badge-success text-bg-success');
            $places = html_writer::span(attendancejourneys_get_string($excess > 0 ? 'journeycapacityexceeded' :
                'journeycapacitysummary', 'attendancejourneys', (object) [
                    'count' => $participantcount, 'capacity' => (int) $journey->capacity,
                    'remaining' => $remaining, 'excess' => $excess,
                ]), $placeclass);
        }
        $journeyconflicts = array_intersect(array_keys($participants), array_keys($overlappingids));
        if (empty($journey->active)) {
            $status = html_writer::span(
                attendancejourneys_get_string(
                    'journeycompletedstatus',
                    'attendancejourneys'
                ),
                'badge badge-secondary text-bg-secondary'
            );
        } else {
            $status = $journeyconflicts ? html_writer::span(
                attendancejourneys_get_string(
                    empty($activity->journeygrading) ? 'journeyconflictcount' : 'journeymultiplecount',
                    'attendancejourneys',
                    count($journeyconflicts)
                ),
                empty($activity->journeygrading) ? 'badge badge-warning text-bg-warning' : 'badge badge-info text-bg-info'
            ) :
                html_writer::span(
                    attendancejourneys_get_string(
                        'journeyinprogress',
                        'attendancejourneys'
                    ),
                    'badge badge-info text-bg-info'
                );
        }
        $editurl = new moodle_url($url, ['action' => 'edit', 'journeyid' => $journey->id]);
        $stateurl = new moodle_url($url, ['action' => empty($journey->active) ? 'reopen' : 'complete',
            'journeyid' => $journey->id]);
        $deleteurl = new moodle_url($url, ['action' => 'delete', 'journeyid' => $journey->id]);
        $actions = $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit'))) . ' ' .
            $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete'))) . ' ' .
            (attendancejourneys_can_decide($context, empty($journey->active) ? 'reopenjourneys' : 'closejourneys') ?
                html_writer::link($stateurl, attendancejourneys_get_string(empty($journey->active) ? 'reopencompletedjourney' :
                    'completejourney', 'attendancejourneys'), ['class' => 'btn btn-sm btn-outline-secondary']) : '');
        $detailurl = new moodle_url('/mod/attendancejourneys/journey.php', [
            'id' => $cm->id, 'journeyid' => $journey->id,
        ]);
        $table->data[] = [html_writer::link(
            $detailurl,
            html_writer::tag('strong', format_string($journey->name)),
            ['class' => 'attendancejourneys-journey-link']
        ),
            format_string($audience),
            $places, attendancejourneys_modality_label($journey->defaultmodality ?? 'unspecified'),
            $period, $count, $waitingcount === null ? '—' : $waitingcount,
            $status,
            $actions];
    }
    echo attendancejourneys_responsive_table($table, attendancejourneys_get_string('managejourneys', 'attendancejourneys'));
}
echo html_writer::div(html_writer::link(
    new moodle_url('/mod/attendancejourneys/sessions.php', ['id' => $cm->id]),
    '← ' . attendancejourneys_get_string('managesessions', 'attendancejourneys')
), 'mt-4');
echo $OUTPUT->footer();
