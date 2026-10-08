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
 * Display a journey, its participants and attendance results.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$journeyid = required_param('journeyid', PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$waitlistid = optional_param('waitlistid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$perpage = 25;
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
$journey = $DB->get_record(
    'attendancejourneys_journeys',
    ['id' => $journeyid, 'attendancejourneysid' => $activity->id],
    '*',
    MUST_EXIST
);
attendancejourneys_require_journey_access($cm, $context, $journey);
$visibleparticipants = attendancejourneys_get_visible_participants($context, $cm);
$restrictedgroups = groups_get_activity_groupmode($cm) == SEPARATEGROUPS &&
    !has_capability('moodle/site:accessallgroups', $context);
if ($restrictedgroups && $userid && !isset($visibleparticipants[$userid])) {
    throw new required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
}
$url = new moodle_url('/mod/attendancejourneys/journey.php', ['id' => $cm->id, 'journeyid' => $journey->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($journey->name));
$PAGE->set_heading(format_string($course->fullname));
$audienceeditable = attendancejourneys_journey_audience_is_editable($journey);
$audiencelockmessage = (int) $journey->audiencemode === 1 ? 'journeyaudienceautomaticnotice' : 'journeyaudiencelocked';

if ($action === 'addwaitlist') {
    if (empty($journey->waitlistenabled) || !$audienceeditable) {
        redirect(
            $url,
            attendancejourneys_get_string('journeywaitlistunavailable', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $eligible = get_enrolled_users(
        $context,
        'mod/attendancejourneys:canbelisted',
        0,
        'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename',
        'u.lastname,u.firstname',
        0,
        0,
        true
    );
    $eligible = array_intersect_key($eligible, $visibleparticipants);
    foreach (attendancejourneys_get_journey_participants($context, $journey) as $participant) {
        unset($eligible[$participant->id]);
    }
    $waitinguserids = $DB->get_fieldset_select(
        'attendancejourneys_waitlist',
        'userid',
        'journeyid = :journeyid AND status = :status',
        ['journeyid' => $journey->id, 'status' => 'waiting']
    );
    foreach ($waitinguserids as $waitinguserid) {
        unset($eligible[$waitinguserid]);
    }
    if (!$eligible) {
        redirect(
            $url,
            attendancejourneys_get_string('nowaitlistcandidates', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
    $formurl = new moodle_url($url, ['action' => 'addwaitlist']);
    $form = new \mod_attendancejourneys\form\waitlist_member($formurl, ['participants' => $eligible]);
    $form->set_data((object) ['id' => $cm->id, 'journeyid' => $journey->id]);
    if ($form->is_cancelled()) {
        redirect($url);
    }
    if ($data = $form->get_data()) {
        if (!isset($eligible[(int) $data->userid])) {
            throw new moodle_exception('invaliduser');
        }
        $now = time();
        $DB->insert_record('attendancejourneys_waitlist', (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => $journey->id,
            'userid' => (int) $data->userid, 'status' => 'waiting', 'createdby' => $USER->id,
            'timecreated' => $now, 'updatedby' => $USER->id, 'timeupdated' => $now,
        ]);
        redirect($url, attendancejourneys_get_string(
            'waitlistparticipantadded',
            'attendancejourneys',
            fullname($eligible[(int) $data->userid])
        ), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'journeys');
    echo $OUTPUT->heading(attendancejourneys_get_string('addtowaitlist', 'attendancejourneys'));
    echo $OUTPUT->notification(attendancejourneys_get_string('waitlistaddhelp', 'attendancejourneys'), 'info');
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

if (in_array($action, ['promotewaitlist', 'removewaitlist'], true) && $waitlistid) {
    $entry = $DB->get_record('attendancejourneys_waitlist', [
        'id' => $waitlistid, 'journeyid' => $journey->id, 'status' => 'waiting',
    ], '*', MUST_EXIST);
    if ($restrictedgroups && !isset($visibleparticipants[$entry->userid])) {
        throw new required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
    }
    if (!$audienceeditable) {
        redirect(
            $url,
            attendancejourneys_get_string($audiencelockmessage, 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $participant = core_user::get_user($entry->userid, '*', MUST_EXIST);
    $occupancy = count(attendancejourneys_get_journey_participants($context, $journey));
    $capacityexceeded = !empty($journey->capacity) && $occupancy >= (int) $journey->capacity;
    if ($confirm) {
        require_sesskey();
        if ($action === 'promotewaitlist') {
            $membershipid = attendancejourneys_assign_journey_member($context, $journey, (int) $entry->userid);
            \mod_attendancejourneys\event\journey_member_added::create([
                'objectid' => $membershipid, 'context' => $context, 'relateduserid' => (int) $entry->userid,
                'other' => ['attendancejourneysid' => (int) $activity->id, 'journeyid' => (int) $journey->id],
            ])->trigger();
            $entry->status = 'promoted';
            $message = attendancejourneys_get_string('waitlistparticipantpromoted', 'attendancejourneys', fullname($participant));
            attendancejourneys_update_completion($course, $cm, [(int) $entry->userid]);
            attendancejourneys_update_grades($activity, (int) $entry->userid);
        } else {
            $entry->status = 'removed';
            $message = attendancejourneys_get_string('waitlistparticipantremoved', 'attendancejourneys', fullname($participant));
        }
        $entry->updatedby = $USER->id;
        $entry->timeupdated = time();
        $DB->update_record('attendancejourneys_waitlist', $entry);
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'journeys');
    echo $OUTPUT->heading(attendancejourneys_get_string($action === 'promotewaitlist' ? 'promotewaitlist' :
        'removewaitlist', 'attendancejourneys'));
    if ($action === 'promotewaitlist' && $capacityexceeded) {
        echo $OUTPUT->notification(attendancejourneys_get_string(
            'waitlistpromotioncapacitywarning',
            'attendancejourneys',
            (object) [
            'count' => $occupancy, 'capacity' => (int) $journey->capacity,
            ]
        ), 'warning');
    }
    $confirmurl = new moodle_url($url, ['action' => $action, 'waitlistid' => $entry->id,
        'confirm' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->confirm(attendancejourneys_get_string($action === 'promotewaitlist' ? 'promotewaitlistconfirm' :
        'removewaitlistconfirm', 'attendancejourneys', fullname($participant)), $confirmurl, $url);
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'addmember') {
    if (!$audienceeditable) {
        redirect(
            $url,
            attendancejourneys_get_string($audiencelockmessage, 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $addable = attendancejourneys_get_addable_journey_participants($context, $journey);
    if (!$addable) {
        redirect(
            $url,
            attendancejourneys_get_string('noaddablejourneyparticipants', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
    $currentoccupancy = count(attendancejourneys_get_journey_participants($context, $journey));
    $overcapacity = !empty($journey->capacity) && $currentoccupancy >= (int) $journey->capacity;
    $formurl = new moodle_url($url, ['action' => 'addmember']);
    $form = new \mod_attendancejourneys\form\journey_member(
        $formurl,
        ['participants' => $addable, 'overcapacity' => $overcapacity]
    );
    $form->set_data((object) ['id' => $cm->id, 'journeyid' => $journey->id]);
    if ($form->is_cancelled()) {
        redirect($url);
    }
    if ($data = $form->get_data()) {
        if (!isset($addable[(int) $data->userid])) {
            throw new moodle_exception('invaliduser');
        }
        $membershipid = attendancejourneys_assign_journey_member($context, $journey, (int) $data->userid);
        \mod_attendancejourneys\event\journey_member_added::create([
            'objectid' => $membershipid, 'context' => $context, 'relateduserid' => (int) $data->userid,
            'other' => ['attendancejourneysid' => (int) $activity->id, 'journeyid' => (int) $journey->id],
        ])->trigger();
        attendancejourneys_update_completion($course, $cm, [(int) $data->userid]);
        attendancejourneys_update_grades($activity, (int) $data->userid);
        $message = attendancejourneys_get_string(
            'journeyparticipantadded',
            'attendancejourneys',
            fullname($addable[(int) $data->userid])
        );
        $othernames = attendancejourneys_other_journey_names(
            $context,
            (int) $activity->id,
            (int) $data->userid,
            (int) $journey->id
        );
        if ($othernames) {
            $message .= ' ' . get_string('otherjourneysnotice', 'attendancejourneys', implode(', ', $othernames));
        }
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'journeys');
    echo $OUTPUT->heading(attendancejourneys_get_string('addjourneyparticipant', 'attendancejourneys'));
    echo $OUTPUT->notification(attendancejourneys_get_string('journeyaudienceedithelp', 'attendancejourneys'), 'info');
    if ($overcapacity) {
        echo $OUTPUT->notification(attendancejourneys_get_string('journeycapacityreachedwarning', 'attendancejourneys', (object) [
            'count' => $currentoccupancy, 'capacity' => (int) $journey->capacity,
        ]), 'warning');
    }
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'removemember' && $userid) {
    if (!$audienceeditable) {
        redirect(
            $url,
            attendancejourneys_get_string($audiencelockmessage, 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $membership = $DB->get_record('attendancejourneys_members', [
        'journeyid' => $journey->id, 'userid' => $userid,
    ], '*', MUST_EXIST);
    $member = core_user::get_user($userid, '*', MUST_EXIST);
    if ($confirm) {
        require_sesskey();
        $event = \mod_attendancejourneys\event\journey_member_removed::create([
            'objectid' => $membership->id, 'context' => $context, 'relateduserid' => $userid,
            'other' => ['attendancejourneysid' => (int) $activity->id, 'journeyid' => (int) $journey->id],
        ]);
        $event->add_record_snapshot('attendancejourneys_members', $membership);
        $DB->delete_records('attendancejourneys_members', ['id' => $membership->id]);
        $event->trigger();
        attendancejourneys_update_completion($course, $cm, [$userid]);
        attendancejourneys_update_grades($activity, $userid);
        redirect(
            $url,
            attendancejourneys_get_string('journeyparticipantremoved', 'attendancejourneys', fullname($member)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'journeys');
    echo $OUTPUT->heading(attendancejourneys_get_string('removejourneyparticipant', 'attendancejourneys'));
    $confirmurl = new moodle_url($url, ['action' => 'removemember', 'userid' => $userid,
        'confirm' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->confirm(
        attendancejourneys_get_string('removejourneyparticipantconfirm', 'attendancejourneys', fullname($member)),
        $confirmurl,
        $url
    );
    echo $OUTPUT->footer();
    exit;
}

$sessions = $DB->get_records('attendancejourneys_sessions', [
    'attendancejourneysid' => $activity->id, 'journeyid' => $journey->id,
], 'sessiondate ASC');
$participants = attendancejourneys_get_journey_participants(
    $context,
    $journey,
    'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename'
);
$participants = array_intersect_key($participants, $visibleparticipants);
$sessions = array_filter($sessions, static fn($session) => attendancejourneys_session_is_visible($cm, $context, $session));
$membershipsbyuser = [];
foreach ($DB->get_records('attendancejourneys_members', ['journeyid' => $journey->id]) as $membership) {
    $membershipsbyuser[(int) $membership->userid] = $membership;
}
$audience = attendancejourneys_journey_has_fixed_audience($journey) ?
    attendancejourneys_get_string('journeyselectedaudience', 'attendancejourneys') :
    (empty($journey->groupid) ? attendancejourneys_get_string('allactiveparticipants', 'attendancejourneys') :
    (groups_get_group_name($journey->groupid) ?: attendancejourneys_get_string('unknowngroup', 'attendancejourneys')));

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'journeys');
echo html_writer::div(html_writer::link(
    new moodle_url('/mod/attendancejourneys/journeys.php', ['id' => $cm->id]),
    '← ' . attendancejourneys_get_string('managejourneys', 'attendancejourneys')
), 'mb-3');
if ((int) $journey->audiencemode === 1) {
    echo $OUTPUT->notification(get_string('journeyaudienceautomaticnotice', 'attendancejourneys'), 'info');
}
echo html_writer::start_div('attendancejourneys-journey-hero');
echo html_writer::start_div('attendancejourneys-journey-hero-main');
echo html_writer::div(
    attendancejourneys_get_string('journeyframework', 'attendancejourneys'),
    'attendancejourneys-section-eyebrow'
);
echo $OUTPUT->heading(format_string($journey->name));
if (!empty($journey->description)) {
    echo html_writer::div(format_text($journey->description, FORMAT_PLAIN), 'text-muted mb-3');
}
echo html_writer::end_div();
echo html_writer::div(html_writer::span(
    attendancejourneys_get_string(empty($journey->active) ? 'journeycompletedstatus' : 'journeyinprogress', 'attendancejourneys'),
    empty($journey->active) ? 'badge badge-secondary text-bg-secondary' : 'badge badge-info text-bg-info'
), 'attendancejourneys-journey-hero-status');
echo html_writer::end_div();

$summary = [];
$summary[] = html_writer::div(
    html_writer::tag(
        'strong',
        count($participants),
        ['class' => 'attendancejourneys-stat-value']
    ) . html_writer::span(attendancejourneys_get_string('participants', 'attendancejourneys')),
    'attendancejourneys-stat'
);
$capacitylabel = empty($journey->capacity) ? attendancejourneys_get_string('journeycapacityunlimited', 'attendancejourneys') :
    attendancejourneys_get_string('journeycapacityusage', 'attendancejourneys', (object) [
        'count' => count($participants), 'capacity' => (int) $journey->capacity,
    ]);
$summary[] = html_writer::div(html_writer::tag(
    'strong',
    $capacitylabel,
    ['class' => 'attendancejourneys-stat-value attendancejourneys-stat-text']
) .
    html_writer::span(attendancejourneys_get_string('journeyplaces', 'attendancejourneys')), 'attendancejourneys-stat');
$summary[] = html_writer::div(
    html_writer::tag(
        'strong',
        count($sessions),
        ['class' => 'attendancejourneys-stat-value']
    ) . html_writer::span(attendancejourneys_get_string('sessions', 'attendancejourneys')),
    'attendancejourneys-stat'
);
$summary[] = html_writer::div(html_writer::tag(
    'strong',
    format_string($audience),
    ['class' => 'attendancejourneys-stat-value attendancejourneys-stat-text']
) .
    html_writer::span(attendancejourneys_get_string('journeyaudience', 'attendancejourneys')), 'attendancejourneys-stat');
$summary[] = html_writer::div(html_writer::div(html_writer::span(
    attendancejourneys_get_string(empty($journey->active) ? 'journeycompletedstatus' : 'journeyinprogress', 'attendancejourneys'),
    empty($journey->active) ? 'badge badge-secondary text-bg-secondary' : 'badge badge-info text-bg-info'
), 'attendancejourneys-stat-value') .
    html_writer::span(attendancejourneys_get_string('journeystatus', 'attendancejourneys')), 'attendancejourneys-stat');
echo html_writer::div(implode('', $summary), 'attendancejourneys-summary-grid');

echo html_writer::start_div('attendancejourneys-actions mb-4');
if (!empty($journey->active)) {
    echo html_writer::link(new moodle_url('/mod/attendancejourneys/sessions.php', [
        'id' => $cm->id, 'action' => 'add', 'journeyid' => $journey->id,
    ]), attendancejourneys_get_string('addsession', 'attendancejourneys'), ['class' => 'btn btn-primary']);
    echo html_writer::link(new moodle_url('/mod/attendancejourneys/sessions.php', [
        'id' => $cm->id, 'action' => 'series', 'journeyid' => $journey->id,
    ]), attendancejourneys_get_string('addsessionseries', 'attendancejourneys'), ['class' => 'btn btn-secondary']);
    if (attendancejourneys_can_decide($context, 'closejourneys')) {
        echo html_writer::link(new moodle_url('/mod/attendancejourneys/journeys.php', [
            'id' => $cm->id, 'action' => 'complete', 'journeyid' => $journey->id,
        ]), attendancejourneys_get_string('completejourney', 'attendancejourneys'), ['class' => 'btn btn-outline-secondary']);
    }
    if ($audienceeditable) {
        echo html_writer::link(
            new moodle_url($url, ['action' => 'addmember']),
            attendancejourneys_get_string('addjourneyparticipant', 'attendancejourneys'),
            ['class' => 'btn btn-outline-primary']
        );
        if (!empty($journey->waitlistenabled)) {
            echo html_writer::link(
                new moodle_url($url, ['action' => 'addwaitlist']),
                attendancejourneys_get_string('addtowaitlist', 'attendancejourneys'),
                ['class' => 'btn btn-outline-secondary']
            );
        }
    }
}
echo html_writer::end_div();

echo $OUTPUT->notification(attendancejourneys_get_string(
    $audienceeditable ? 'journeyaudienceedithelp' : 'journeyaudiencelocked',
    'attendancejourneys'
), $audienceeditable ? 'info' : 'warning');

echo html_writer::start_div('attendancejourneys-journey-section');
echo html_writer::tag('h3', attendancejourneys_get_string('journeysessions', 'attendancejourneys'), ['class' => 'h5']);
if (!$sessions) {
    echo $OUTPUT->notification(attendancejourneys_get_string('journeynosessions', 'attendancejourneys'), 'info');
} else {
    $sessiontable = new html_table();
    $sessiontable->head = [attendancejourneys_get_string(
        'sessiondate',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        'sessionname',
        'attendancejourneys'
    ),

        attendancejourneys_get_string('duration', 'attendancejourneys'),
            attendancejourneys_get_string('sessionmodality', 'attendancejourneys'),
        attendancejourneys_get_string('courseprogress', 'attendancejourneys')];
    foreach ($sessions as $session) {
        $progress = attendancejourneys_session_progress($context, $session);
        $takeurl = new moodle_url('/mod/attendancejourneys/take.php', ['id' => $cm->id, 'sessionid' => $session->id]);
        $sessiontable->data[] = [userdate(
            $session->sessiondate,
            get_string('strftimedatetimeshort', 'langconfig')
        ), html_writer::link(
            $takeurl,
            format_string($session->name)
        ), attendancejourneys_get_string('minutesvalue', 'attendancejourneys', $session->duration),
            attendancejourneys_session_delivery_details($session),
            attendancejourneys_get_string('recordedparticipants', 'attendancejourneys', (object) $progress)];
    }
    echo attendancejourneys_responsive_table($sessiontable, attendancejourneys_get_string('journeysessions', 'attendancejourneys'));
}
echo html_writer::end_div();

if (!empty($journey->waitlistenabled)) {
    $waitlist = $DB->get_records('attendancejourneys_waitlist', [
        'journeyid' => $journey->id, 'status' => 'waiting',
    ], 'timecreated ASC, id ASC');
    if ($restrictedgroups) {
        $waitlist = array_filter($waitlist, static fn($entry) => isset($visibleparticipants[$entry->userid]));
    }
    echo html_writer::start_div('attendancejourneys-journey-section');
    echo html_writer::tag('h3', attendancejourneys_get_string('journeywaitlist', 'attendancejourneys'), ['class' => 'h5']);
    if (!$waitlist) {
        echo $OUTPUT->notification(attendancejourneys_get_string('emptywaitlist', 'attendancejourneys'), 'info');
    } else {
        $waittable = new html_table();
        $waittable->head = [attendancejourneys_get_string(
            'waitlistposition',
            'attendancejourneys'
        ),
        attendancejourneys_get_string(
            'participant',
            'attendancejourneys'
        ),

            attendancejourneys_get_string('waitlistsince', 'attendancejourneys'), get_string('actions')];
        $position = 0;
        foreach ($waitlist as $entry) {
            $position++;
            $waitinguser = core_user::get_user($entry->userid);
            $actions = '';
            if ($audienceeditable && $waitinguser) {
                $actions = html_writer::link(
                    new moodle_url(
                        $url,
                        ['action' => 'promotewaitlist', 'waitlistid' => $entry->id]
                    ),
                    attendancejourneys_get_string('promotewaitlist', 'attendancejourneys'),
                    ['class' => 'btn btn-sm btn-primary mr-1 me-1']
                );
                $actions .= html_writer::link(
                    new moodle_url(
                        $url,
                        ['action' => 'removewaitlist', 'waitlistid' => $entry->id]
                    ),
                    attendancejourneys_get_string('removewaitlist', 'attendancejourneys'),
                    ['class' => 'btn btn-sm btn-outline-secondary']
                );
            }
            $waittable->data[] = [$position, $waitinguser ? fullname($waitinguser) : attendancejourneys_get_string(
                'deleteduser',
                'attendancejourneys'
            ), userdate($entry->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                $actions];
        }
        echo attendancejourneys_responsive_table(
            $waittable,
            attendancejourneys_get_string('journeywaitlist', 'attendancejourneys')
        );
    }
    echo html_writer::end_div();

    $waitlisthistory = $DB->get_records_select(
        'attendancejourneys_waitlist',
        'journeyid = :journeyid AND status <> :status',
        ['journeyid' => $journey->id, 'status' => 'waiting'],
        'timeupdated DESC, id DESC'
    );
    if ($restrictedgroups) {
        $waitlisthistory = array_filter($waitlisthistory, static fn($entry) => isset($visibleparticipants[$entry->userid]));
    }
    if ($waitlisthistory) {
        $historyuserids = [];
        foreach ($waitlisthistory as $entry) {
            $historyuserids[] = (int) $entry->userid;
            if (!empty($entry->updatedby)) {
                $historyuserids[] = (int) $entry->updatedby;
            }
        }
        $historyusers = $DB->get_records_list(
            'user',
            'id',
            array_values(array_unique($historyuserids)),
            '',
            'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename,deleted'
        );
        $historytable = new html_table();
        $historytable->head = [attendancejourneys_get_string('participant', 'attendancejourneys'),
            attendancejourneys_get_string(
                'waitlisthistorystatus',
                'attendancejourneys'
            ),
        attendancejourneys_get_string(
            'waitlistdecisiondate',
            'attendancejourneys'
        ),

            attendancejourneys_get_string('waitlistdecisionby', 'attendancejourneys')];
        foreach ($waitlisthistory as $entry) {
            $historyparticipant = $historyusers[$entry->userid] ?? null;
            $decisionmaker = $historyusers[$entry->updatedby] ?? null;
            $statuskey = $entry->status === 'promoted' ? 'waitliststatuspromoted' : 'waitliststatusremoved';
            $historytable->data[] = [
                $historyparticipant && empty($historyparticipant->deleted) ? fullname($historyparticipant) :
                    attendancejourneys_get_string('deleteduser', 'attendancejourneys'),
                attendancejourneys_get_string($statuskey, 'attendancejourneys'),
                userdate($entry->timeupdated, get_string('strftimedatetimeshort', 'langconfig')),
                $decisionmaker && empty($decisionmaker->deleted) ? fullname($decisionmaker) :
                    attendancejourneys_get_string('deleteduser', 'attendancejourneys'),
            ];
        }
        echo html_writer::start_div('attendancejourneys-waitlist-history mt-4');
        echo html_writer::tag('h4', attendancejourneys_get_string('waitlisthistory', 'attendancejourneys'), ['class' => 'h6']);
        echo html_writer::div(attendancejourneys_get_string('waitlisthistoryhelp', 'attendancejourneys'), 'text-muted mb-2');
        echo attendancejourneys_responsive_table(
            $historytable,
            attendancejourneys_get_string('waitlisthistory', 'attendancejourneys')
        );
        echo html_writer::end_div();
    }
}

echo html_writer::start_div('attendancejourneys-journey-section');
echo html_writer::tag('h3', attendancejourneys_get_string('journeyparticipantprogress', 'attendancejourneys'), ['class' => 'h5']);
if (!$participants) {
    echo $OUTPUT->notification(attendancejourneys_get_string('noparticipants', 'attendancejourneys'), 'info');
} else {
    $participanttable = new html_table();
    $participanttable->head = [attendancejourneys_get_string(
        'participant',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        !empty($activity->journeygrading) ? 'journeyassignment' : 'journeyattempt',
        'attendancejourneys'
    ),

        attendancejourneys_get_string('markedsessions', 'attendancejourneys'),
        attendancejourneys_get_string('percentage', 'attendancejourneys'),
            attendancejourneys_get_string('result', 'attendancejourneys'),
        attendancejourneys_get_string('status', 'attendancejourneys')];
    if ($audienceeditable) {
        $participanttable->head[] = get_string('actions');
    }
    $pageparticipants = array_slice(array_values($participants), $page * $perpage, $perpage);
    foreach ($pageparticipants as $participant) {
        $closure = attendancejourneys_get_active_closure(
            (int) $activity->id,
            (int) $participant->id,
            (int) $journey->id
        );
        $calculation = $closure ?: attendancejourneys_calculate_user_attendance(
            $activity,
            (int) $participant->id,
            (int) $journey->id
        );
        $recorded = $closure ? $closure->recordedsessions : $calculation->recordedsessions;
        $applicable = $closure ? $closure->applicablesessions : $calculation->applicablesessions;
        $percent = $closure ? $closure->percentage : $calculation->percent;
        $passed = $closure ? $closure->result === 'passed' :
            ($percent !== null && $percent >= $calculation->threshold);
        if (!empty($activity->journeygrading) && !$closure) {
            $result = attendancejourneys_get_string(
                !empty($calculation->waived) ? 'obligationwaived' : 'provisional',
                'attendancejourneys'
            );
        } else {
            $result = $percent === null || empty($activity->percentageenabled) ? '—' :
                ($passed ? attendancejourneys_get_string('passed', 'attendancejourneys') :
                    attendancejourneys_get_string('failed', 'attendancejourneys'));
        }
        $status = $closure ? attendancejourneys_get_string('journeyresultpublished', 'attendancejourneys') :
            attendancejourneys_get_string(!empty($calculation->waived) ? 'obligationwaived' : 'provisional', 'attendancejourneys');
        $individualurl = new moodle_url('/mod/attendancejourneys/individual.php', [
            'id' => $cm->id, 'userid' => $participant->id, 'journeyid' => $journey->id,
        ]);
        $attempt = isset($membershipsbyuser[$participant->id]) ?
            (int) $membershipsbyuser[$participant->id]->attemptnumber : 1;
        $row = [html_writer::link($individualurl, fullname($participant)),
            attendancejourneys_get_string(
                !empty($activity->journeygrading) ? 'journeyassignmentnumber' : 'journeyattemptnumber',
                'attendancejourneys',
                $attempt
            ),
            $recorded . ' / ' . $applicable, $percent === null ? '—' : format_float($percent, 1) . ' %',
            $result,
        html_writer::span(
            $status,
            $closure ? 'badge badge-success text-bg-success' : 'badge badge-secondary text-bg-secondary'
        )];
        if ($audienceeditable) {
            $removeurl = new moodle_url($url, ['action' => 'removemember', 'userid' => $participant->id]);
            $row[] = isset($membershipsbyuser[$participant->id]) ? $OUTPUT->action_icon(
                $removeurl,
                new pix_icon('t/delete', attendancejourneys_get_string('removejourneyparticipant', 'attendancejourneys'))
            ) : '—';
        }
        $participanttable->data[] = $row;
    }
    echo attendancejourneys_responsive_table(
        $participanttable,
        attendancejourneys_get_string('journeyparticipantprogress', 'attendancejourneys')
    );
    echo $OUTPUT->paging_bar(count($participants), $page, $perpage, $url);
    echo html_writer::div($OUTPUT->download_dataformat_selector(
        attendancejourneys_get_string('exportjourneyreport', 'attendancejourneys'),
        new moodle_url('/mod/attendancejourneys/export.php'),
        'dataformat',
        ['id' => $cm->id, 'scope' => 'journey', 'journeyid' => $journey->id]
    ), 'attendancejourneys-export mt-3');
    if ($sessions) {
        echo html_writer::div($OUTPUT->download_dataformat_selector(
            attendancejourneys_get_string('exportjourneysessiondetail', 'attendancejourneys'),
            new moodle_url('/mod/attendancejourneys/export.php'),
            'dataformat',
            ['id' => $cm->id, 'scope' => 'journeydetail', 'journeyid' => $journey->id]
        ), 'attendancejourneys-export mt-2');
    }
}
echo html_writer::end_div();
echo $OUTPUT->footer();
