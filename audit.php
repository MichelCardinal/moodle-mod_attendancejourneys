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
 * Display the activity-scoped attendance audit history.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$groupid = optional_param('groupid', -1, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$fromdate = optional_param('fromdate', '', PARAM_RAW_TRIMMED);
$todate = optional_param('todate', '', PARAM_RAW_TRIMMED);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 50;

$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:resetuserdata', $context);
attendancejourneys_require_professional_mode($activity, (int) $cm->id);

$actions = ['' => attendancejourneys_get_string('allactions', 'attendancejourneys')];
foreach (['recorded', 'updated', 'approved', 'approvalwithdrawn', 'changesrequested', 'resubmitted'] as $key) {
    $actions[$key] = attendancejourneys_get_string('reviewaction' . $key, 'attendancejourneys');
}
if (!array_key_exists($action, $actions)) {
    $action = '';
}
$sessions = $DB->get_records(
    'attendancejourneys_sessions',
    ['attendancejourneysid' => $activity->id],
    'sessiondate, name',
    'id,name,sessiondate,groupid'
);
if ($sessionid && !isset($sessions[$sessionid])) {
    $sessionid = 0;
}
$sessionoptions = [0 => attendancejourneys_get_string('allsessions', 'attendancejourneys')];
foreach ($sessions as $session) {
    $sessionoptions[$session->id] = format_string($session->name) . ' — ' .
        userdate($session->sessiondate, get_string('strftimedateshort', 'langconfig'));
}
$participants = attendancejourneys_get_visible_participants(
    $context,
    $cm,
    'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename',
    null
);
$participantoptions = [0 => attendancejourneys_get_string('allparticipants', 'attendancejourneys')];
foreach ($participants as $participant) {
    $participantoptions[$participant->id] = fullname($participant);
}
$historicalids = $DB->get_fieldset_sql("SELECT DISTINCT arv.userid
                                          FROM {attendancejourneys_reviews} arv
                                          JOIN {attendancejourneys_sessions} aps ON aps.id = arv.sessionid
                                         WHERE aps.attendancejourneysid = :activityid", ['activityid' => $activity->id]);
if ($historicalids) {
    $historicalusers = $DB->get_records_list(
        'user',
        'id',
        $historicalids,
        '',
        'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
    );
    foreach ($historicalusers as $historicaluser) {
        $participantoptions[$historicaluser->id] = fullname($historicaluser);
    }
    core_collator::asort($participantoptions);
    $participantoptions = [0 => attendancejourneys_get_string('allparticipants', 'attendancejourneys')] + $participantoptions;
}
if ($userid && !isset($participantoptions[$userid])) {
    $historical = core_user::get_user($userid, '*', IGNORE_MISSING);
    if ($historical) {
        $participantoptions[$userid] = fullname($historical);
    } else {
        $userid = 0;
    }
}
$groupoptions = attendancejourneys_report_group_filter_options($cm);
if (!array_key_exists($groupid, $groupoptions)) {
    $groupid = -1;
}
$parsedate = static function (string $value, bool $endofday): int {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return 0;
    }
    $time = strtotime($value . ($endofday ? ' 23:59:59' : ' 00:00:00'));
    return $time ?: 0;
};
$filters = ['userid' => $userid, 'sessionid' => $sessionid, 'groupid' => $groupid, 'action' => $action,
    'from' => $parsedate($fromdate, false), 'to' => $parsedate($todate, true)];
[$fromsql, $params] = attendancejourneys_audit_query((int) $activity->id, $filters);
$total = $DB->count_records_sql('SELECT COUNT(arv.id)' . $fromsql, $params);
$select = "SELECT arv.*, aps.name AS sessionname, aps.sessiondate,
                  participant.firstname, participant.lastname,
                  participant.firstnamephonetic, participant.lastnamephonetic,
                  participant.middlename, participant.alternatename,
                  actor.firstname AS actorfirstname, actor.lastname AS actorlastname,
                  actor.firstnamephonetic AS actorfirstnamephonetic,
                  actor.lastnamephonetic AS actorlastnamephonetic,
                  actor.middlename AS actormiddlename, actor.alternatename AS actoralternatename";
$reviews = $DB->get_records_sql(
    $select . $fromsql . ' ORDER BY arv.timecreated DESC, arv.id DESC',
    $params,
    $page * $perpage,
    $perpage
);

$urlparams = ['id' => $cm->id, 'userid' => $userid, 'sessionid' => $sessionid, 'groupid' => $groupid,
    'action' => $action, 'fromdate' => $fromdate, 'todate' => $todate];
$url = new moodle_url('/mod/attendancejourneys/audit.php', $urlparams);
$PAGE->set_url($url);
$PAGE->set_title(attendancejourneys_get_string('auditlog', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'administration');
attendancejourneys_print_admin_navigation($cm, 'audit');
echo html_writer::start_div('attendancejourneys-admin-page');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(
    attendancejourneys_get_string('institutionalcontrol', 'attendancejourneys'),
    'attendancejourneys-section-eyebrow'
);
echo $OUTPUT->heading(attendancejourneys_get_string('auditlog', 'attendancejourneys'));
echo html_writer::div(attendancejourneys_get_string('auditlogintro', 'attendancejourneys'), 'text-muted');
echo html_writer::end_div();

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out_omit_querystring(),
    'class' => 'attendancejourneys-admin-filters']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
$controls = [
    ['participant', 'audit-userid', html_writer::select(
        $participantoptions,
        'userid',
        $userid,
        false,
        ['class' => 'custom-select form-select', 'id' => 'audit-userid']
    )],
    ['session', 'audit-sessionid', html_writer::select(
        $sessionoptions,
        'sessionid',
        $sessionid,
        false,
        ['class' => 'custom-select form-select', 'id' => 'audit-sessionid']
    )],
    ['group', 'audit-groupid', html_writer::select(
        $groupoptions,
        'groupid',
        $groupid,
        false,
        ['class' => 'custom-select form-select', 'id' => 'audit-groupid']
    )],
    ['action', 'audit-action', html_writer::select(
        $actions,
        'action',
        $action,
        false,
        ['class' => 'custom-select form-select', 'id' => 'audit-action']
    )],
];
foreach ($controls as [$label, $controlid, $control]) {
    echo html_writer::div(html_writer::tag(
        'label',
        attendancejourneys_get_string($label, 'attendancejourneys'),
        ['class' => 'd-block', 'for' => $controlid]
    ) .
        $control, 'mb-2');
}
foreach ([['fromdate', 'fromdate', $fromdate], ['todate', 'todate', $todate]] as [$name, $label, $value]) {
    echo html_writer::div(html_writer::tag(
        'label',
        attendancejourneys_get_string($label, 'attendancejourneys'),
        ['class' => 'd-block', 'for' => 'audit-' . $name]
    ) .
        html_writer::empty_tag('input', ['type' => 'date', 'name' => $name, 'value' => $value,
            'class' => 'form-control', 'id' => 'audit-' . $name]), 'mb-2');
}
echo html_writer::tag('button', get_string('apply'), ['type' => 'submit', 'class' => 'btn btn-secondary mb-2']);
echo html_writer::link(
    new moodle_url('/mod/attendancejourneys/audit.php', ['id' => $cm->id]),
    attendancejourneys_get_string('clearfilters', 'attendancejourneys'),
    ['class' => 'btn btn-link mb-2']
);
echo html_writer::end_tag('form');

if (!$reviews) {
    echo $OUTPUT->notification(attendancejourneys_get_string('noauditentries', 'attendancejourneys'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable attendancejourneys-admin-table';
    $table->head = [get_string('date'), attendancejourneys_get_string('participant', 'attendancejourneys'),
        attendancejourneys_get_string('session', 'attendancejourneys'),
            attendancejourneys_get_string('action', 'attendancejourneys'),
        attendancejourneys_get_string('performedby', 'attendancejourneys'),
            attendancejourneys_get_string('reviewdetails', 'attendancejourneys')];
    foreach ($reviews as $review) {
        $actor = (object) ['firstname' => $review->actorfirstname, 'lastname' => $review->actorlastname,
            'firstnamephonetic' => $review->actorfirstnamephonetic,
            'lastnamephonetic' => $review->actorlastnamephonetic, 'middlename' => $review->actormiddlename,
            'alternatename' => $review->actoralternatename];
        $table->data[] = [userdate($review->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            fullname($review), format_string($review->sessionname),
            attendancejourneys_get_string('reviewaction' . $review->action, 'attendancejourneys'),
            !empty($review->actorid) ? fullname($actor) : attendancejourneys_get_string('deleteduser', 'attendancejourneys'),
            s(attendancejourneys_format_review_note($review))];
    }
    echo attendancejourneys_responsive_table($table, attendancejourneys_get_string('auditlog', 'attendancejourneys'));
    echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
}
$exporturl = new moodle_url('/mod/attendancejourneys/auditexport.php');
echo html_writer::div($OUTPUT->download_dataformat_selector(
    attendancejourneys_get_string('exportauditlog', 'attendancejourneys'),
    $exporturl,
    'dataformat',
    $urlparams
), 'attendancejourneys-export mt-3');
echo html_writer::end_div();
echo $OUTPUT->footer();
