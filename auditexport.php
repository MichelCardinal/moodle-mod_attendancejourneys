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
 * Export the activity-scoped attendance audit history.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$dataformat = required_param('dataformat', PARAM_ALPHANUMEXT);
$userid = optional_param('userid', 0, PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$groupid = optional_param('groupid', -1, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$fromdate = optional_param('fromdate', '', PARAM_RAW_TRIMMED);
$todate = optional_param('todate', '', PARAM_RAW_TRIMMED);

$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:resetuserdata', $context);
require_sesskey();

$parsedate = static function (string $value, bool $endofday): int {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return 0;
    }
    $time = strtotime($value . ($endofday ? ' 23:59:59' : ' 00:00:00'));
    return $time ?: 0;
};
$validactions = ['recorded', 'updated', 'approved', 'approvalwithdrawn', 'changesrequested', 'resubmitted'];
if (!in_array($action, $validactions, true)) {
    $action = '';
}
$filters = ['userid' => $userid, 'sessionid' => $sessionid, 'groupid' => $groupid, 'action' => $action,
    'from' => $parsedate($fromdate, false), 'to' => $parsedate($todate, true)];
[$fromsql, $params] = attendancejourneys_audit_query((int) $activity->id, $filters);
$select = "SELECT arv.*, aps.name AS sessionname, aps.sessiondate,
                  participant.firstname, participant.lastname,
                  participant.firstnamephonetic, participant.lastnamephonetic,
                  participant.middlename, participant.alternatename,
                  actor.firstname AS actorfirstname, actor.lastname AS actorlastname,
                  actor.firstnamephonetic AS actorfirstnamephonetic,
                  actor.lastnamephonetic AS actorlastnamephonetic,
                  actor.middlename AS actormiddlename, actor.alternatename AS actoralternatename";
$reviews = $DB->get_records_sql($select . $fromsql . ' ORDER BY arv.timecreated DESC, arv.id DESC', $params);
$rows = [];
foreach ($reviews as $review) {
    $actor = (object) ['firstname' => $review->actorfirstname, 'lastname' => $review->actorlastname,
        'firstnamephonetic' => $review->actorfirstnamephonetic,
        'lastnamephonetic' => $review->actorlastnamephonetic, 'middlename' => $review->actormiddlename,
        'alternatename' => $review->actoralternatename];
    $rows[] = (object) [
        'date' => userdate($review->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'participant' => fullname($review),
        'session' => format_string($review->sessionname),
        'sessiondate' => userdate($review->sessiondate, get_string('strftimedatetimeshort', 'langconfig')),
        'action' => attendancejourneys_get_string('reviewaction' . $review->action, 'attendancejourneys'),
        'performedby' => !empty($review->actorid) ? fullname($actor) : attendancejourneys_get_string(
            'deleteduser',
            'attendancejourneys'
        ),
        'note' => attendancejourneys_format_review_note($review),
    ];
}
$columns = [
    'date' => get_string('date'),
    'participant' => attendancejourneys_get_string('participant', 'attendancejourneys'),
    'session' => attendancejourneys_get_string('session', 'attendancejourneys'),
    'sessiondate' => attendancejourneys_get_string('sessiondate', 'attendancejourneys'),
    'action' => attendancejourneys_get_string('action', 'attendancejourneys'),
    'performedby' => attendancejourneys_get_string('performedby', 'attendancejourneys'),
    'note' => attendancejourneys_get_string('reviewdetails', 'attendancejourneys'),
];
$filename = clean_filename($course->shortname . '-' . $activity->name . '-journal-audit');
if ($dataformat === 'pdf') {
    \mod_attendancejourneys\local\pdf_export::download($filename, $columns, $rows);
} else {
    \core\dataformat::download_data($filename, $dataformat, $columns, new ArrayIterator($rows));
}
exit;
