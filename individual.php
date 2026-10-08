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
 * Individual participant report for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$requestedjourneyid = optional_param('journeyid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$query = trim(optional_param('q', '', PARAM_TEXT));
$statusfilter = optional_param('statusfilter', 'all', PARAM_ALPHA);
$groupfilter = optional_param('groupfilter', -1, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$equivalenceid = optional_param('equivalenceid', 0, PARAM_INT);
$perpage = 15;
$allowedstatusfilters = ['all', 'unrecorded', 'present', 'absent', 'partial', 'excused', 'exempt', 'cancelled'];
if (!in_array($statusfilter, $allowedstatusfilters, true)) {
    $statusfilter = 'all';
}

$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
if ($statusfilter === 'exempt' && empty($attendancejourneys->journeygrading)) {
    $statusfilter = 'all';
}
if ($statusfilter === 'excused' && empty($attendancejourneys->excusedenabled)) {
    $statusfilter = 'all';
}
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
$canviewreports = has_capability('mod/attendancejourneys:viewreports', $context);
if ((int) $userid === (int) $USER->id) {
    require_capability('mod/attendancejourneys:view', $context);
} else {
    require_capability('mod/attendancejourneys:viewreports', $context);
}

// Coordinate administrative changes with attendance submissions before reading mutable state.
if (
    data_submitted() || (($action !== '')
        && optional_param('sesskey', '', PARAM_RAW) !== '' && confirm_sesskey())
) {
    require_sesskey();
    $writelock = new \mod_attendancejourneys\local\write_lock((int) $attendancejourneys->id);
    $attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
}
$islight = attendancejourneys_is_light_mode($attendancejourneys);
$showjourneyclosure = !$islight || !empty($attendancejourneys->journeygrading);
$visiblejourneys = attendancejourneys_visible_journeys($context, (int) $attendancejourneys->id);
if (
    $islight && ($action === 'reset' ||
        (!$showjourneyclosure && in_array($action, ['close', 'reopen'], true)) ||
        str_contains($action, 'equivalence'))
) {
    redirect(
        new moodle_url('/mod/attendancejourneys/individual.php', ['id' => $cm->id, 'userid' => $userid]),
        attendancejourneys_get_string('professionalfeaturehidden', 'attendancejourneys'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

$participants = attendancejourneys_get_visible_participants(
    $context,
    $cm,
    'u.id,u.firstname,u.lastname,u.email,u.picture,u.imagealt,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename',
    null
);
if (!isset($participants[$userid])) {
    $hasclosures = $DB->record_exists('attendancejourneys_closures', [
        'attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid,
    ]);
    $hasrecords = $DB->record_exists_sql(
        "SELECT 1
                                           FROM {attendancejourneys_records} r
                                           JOIN {attendancejourneys_sessions} s ON s.id = r.sessionid
                                          WHERE s.attendancejourneysid = :attendancejourneysid
                                                AND r.userid = :userid",
        ['attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid]
    );
    $canaccessarchiveduser = has_capability('moodle/site:accessallgroups', $context) ||
        has_capability('mod/attendancejourneys:resetuserdata', $context);
    if (!$canaccessarchiveduser || (!is_enrolled($context, $userid, '', false) && !$hasclosures && !$hasrecords)) {
        throw new moodle_exception('invaliduser');
    }
    $participants[$userid] = core_user::get_user($userid, '*', MUST_EXIST);
}
$participant = $participants[$userid];

$url = new moodle_url('/mod/attendancejourneys/individual.php', ['id' => $cm->id, 'userid' => $userid]);
$PAGE->set_url($url);
$PAGE->set_title(attendancejourneys_get_string('individualreport', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'reset') {
    require_capability('mod/attendancejourneys:resetuserdata', $context);
    $sessionids = $DB->get_fieldset_select(
        'attendancejourneys_sessions',
        'id',
        'attendancejourneysid = :attendancejourneysid',
        ['attendancejourneysid' => $attendancejourneys->id]
    );
    $recordcount = 0;
    if ($sessionids) {
        [$insql, $params] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'previewsession');
        $params['userid'] = $userid;
        $recordcount = $DB->count_records_select(
            'attendancejourneys_records',
            "userid = :userid AND sessionid $insql",
            $params
        );
    }
    $closurecount = $DB->count_records('attendancejourneys_closures', [
        'attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid,
    ]);
    if ($confirm) {
        require_sesskey();
        $deleted = attendancejourneys_reset_user_data($attendancejourneys, $cm, $userid, (int) $USER->id);
        redirect($url, attendancejourneys_get_string('userdataresetsuccess', 'attendancejourneys', (object) [
            'name' => fullname($participant), 'records' => $deleted['records'], 'closures' => $deleted['closures'],
        ]), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'reports');
    echo $OUTPUT->heading(attendancejourneys_get_string('resetuserdata', 'attendancejourneys'));
    $confirmurl = new moodle_url($url, [
        'action' => 'reset', 'confirm' => 1, 'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->confirm(attendancejourneys_get_string('resetuserdataconfirm', 'attendancejourneys', (object) [
        'name' => fullname($participant), 'records' => $recordcount, 'closures' => $closurecount,
    ]), $confirmurl, $url);
    echo $OUTPUT->footer();
    exit;
}

if (!empty($attendancejourneys->journeygrading)) {
    $reportjourneys = attendancejourneys_get_user_report_journeys($context, (int) $attendancejourneys->id, $userid);
    if ($requestedjourneyid && !isset($reportjourneys[$requestedjourneyid])) {
        throw new moodle_exception('invalidparameter');
    }
    $reportfamilies = [];
    foreach ($reportjourneys as $reportjourney) {
        $reportrootid = (int) ($reportjourney->rootjourneyid ?: $reportjourney->id);
        if (!isset($reportfamilies[$reportrootid])) {
            $reportfamilies[$reportrootid] = \mod_attendancejourneys\local\attempt_family::load(
                $attendancejourneys,
                $reportrootid,
                $userid
            );
        }
    }
    if (!$requestedjourneyid && count($reportfamilies) === 1) {
        $singlefamily = reset($reportfamilies);
        $singlecurrent = \mod_attendancejourneys\local\attempt_family::select($singlefamily, $userid);
        if (isset($reportjourneys[$singlecurrent->journeyid])) {
            $requestedjourneyid = $singlecurrent->journeyid;
        }
    }
    if (!$requestedjourneyid) {
        echo $OUTPUT->header();
        attendancejourneys_print_navigation($cm, $context, 'reports');
        echo $OUTPUT->heading(get_string('selectreportjourney', 'attendancejourneys'));
        echo html_writer::tag('p', s(fullname($participant)));
        if (!$reportjourneys) {
            echo $OUTPUT->notification(get_string('noreportjourney', 'attendancejourneys'), 'info');
        }
        foreach ($reportfamilies as $reportfamily) {
            $reportcurrent = \mod_attendancejourneys\local\attempt_family::select($reportfamily, $userid);
            echo $OUTPUT->heading(format_string($reportfamily->root->name), 3);
            if (isset($reportjourneys[$reportcurrent->journeyid])) {
                echo html_writer::div(html_writer::link(
                    new moodle_url($url, ['journeyid' => $reportcurrent->journeyid]),
                    format_string($reportjourneys[$reportcurrent->journeyid]->name),
                    ['class' => 'btn btn-outline-primary mb-2']
                ));
            }
            foreach ($reportjourneys as $reportjourney) {
                if (
                    (int) ($reportjourney->rootjourneyid ?: $reportjourney->id) !== (int) $reportfamily->root->id ||
                        (int) $reportjourney->id === $reportcurrent->journeyid
                ) {
                    continue;
                }
                echo html_writer::div(html_writer::link(
                    new moodle_url($url, ['journeyid' => $reportjourney->id]),
                    get_string('attempthistorylink', 'attendancejourneys', format_string($reportjourney->name)),
                    ['class' => 'btn btn-link mb-2']
                ));
            }
        }
        echo $OUTPUT->footer();
        exit;
    }
    $url->param('journeyid', $requestedjourneyid);
    $PAGE->set_url($url);
    $activejourney = $reportjourneys[$requestedjourneyid];
    $closure = attendancejourneys_get_active_closure((int) $attendancejourneys->id, $userid, $requestedjourneyid);
} else {
    $requestedjourneyid = 0;
    $activejourney = attendancejourneys_get_user_active_journey($context, (int) $attendancejourneys->id, $userid);
    $closure = $activejourney ? attendancejourneys_get_active_closure(
        (int) $attendancejourneys->id,
        $userid,
        (int) $activejourney->id
    ) : attendancejourneys_get_active_closure((int) $attendancejourneys->id, $userid);
}
if ($action === 'addequivalence') {
    require_capability('mod/attendancejourneys:managejourneys', $context);
    if (!$activejourney || $closure) {
        redirect(
            $url,
            attendancejourneys_get_string('equivalenceunavailable', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    $allsessions = $DB->get_records(
        'attendancejourneys_sessions',
        ['attendancejourneysid' => $attendancejourneys->id],
        'sessiondate ASC'
    );
    $requiredsessions = \mod_attendancejourneys\local\individual_obligation::required_sessions(
        $attendancejourneys,
        $userid,
        $allsessions
    );
    $evidencesessions = \mod_attendancejourneys\local\individual_obligation::evidence_sessions(
        $attendancejourneys,
        $userid,
        $allsessions
    );
    $targets = [];
    $sources = [];
    foreach ($allsessions as $session) {
        if (!empty($session->cancelled) || !attendancejourneys_session_is_visible($cm, $context, $session)) {
            continue;
        }
        $label = format_string($session->name) . ' — ' . userdate(
            $session->sessiondate,
            get_string('strftimedatetimeshort', 'langconfig')
        );
        if (
            isset($requiredsessions[$session->id]) && (int) $session->journeyid === (int) $activejourney->id &&
                attendancejourneys_session_applies_to_user($session, $userid) &&
                !$DB->record_exists('attendancejourneys_records', ['sessionid' => $session->id, 'userid' => $userid])
        ) {
            $targets[$session->id] = $label;
        } else if (
            isset($evidencesessions[$session->id]) && !empty($session->journeyid) &&
                isset($visiblejourneys[$session->journeyid]) &&
                (int) $session->journeyid !== (int) $activejourney->id &&
                \mod_attendancejourneys\local\calculator::session_is_due($session) && $DB->record_exists_select(
                    'attendancejourneys_records',
                    'sessionid = :sessionid AND userid = :userid AND status IN (:present, :partial) ' .
                        'AND (approved = 1 OR takenby <> userid)',
                    [
                    'sessionid' => $session->id, 'userid' => $userid,
                    'present' => 'present', 'partial' => 'partial',
                    ]
                )
        ) {
            $sourcejourney = $DB->get_field('attendancejourneys_journeys', 'name', ['id' => $session->journeyid]);
            if (
                !$DB->record_exists_select(
                    'attendancejourneys_equivalences',
                    'userid = :userid AND sourcesessionid = :source AND status IN (:pending, :approved)',
                    [
                        'userid' => $userid, 'source' => $session->id,
                        'pending' => 'pending', 'approved' => 'approved',
                    ]
                )
            ) {
                $sources[$session->id] = format_string((string) $sourcejourney) . ' — ' . $label;
            }
        }
    }
    if (!$targets || !$sources) {
        redirect(
            $url,
            attendancejourneys_get_string('noequivalencecandidates', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
    $form = new \mod_attendancejourneys\form\equivalence($url, ['targets' => $targets, 'sources' => $sources]);
    $form->set_data((object) ['id' => $cm->id, 'userid' => $userid, 'action' => 'addequivalence']);
    if ($form->is_cancelled()) {
        redirect($url);
    }
    if ($data = $form->get_data()) {
        if (!isset($targets[$data->targetsessionid]) || !isset($sources[$data->sourcesessionid])) {
            throw new moodle_exception('invaliddata');
        }
        if (
            $DB->record_exists_select(
                'attendancejourneys_equivalences',
                'userid = :userid AND targetsessionid = :target AND status IN (:pending, :approved)',
                [
                    'userid' => $userid, 'target' => $data->targetsessionid,
                    'pending' => 'pending', 'approved' => 'approved',
                ]
            )
        ) {
            redirect(
                $url,
                attendancejourneys_get_string('equivalencealreadyexists', 'attendancejourneys'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
        $now = time();
        $DB->insert_record('attendancejourneys_equivalences', (object) [
            'attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid,
            'targetjourneyid' => $activejourney->id, 'targetsessionid' => $data->targetsessionid,
            'sourcesessionid' => $data->sourcesessionid, 'status' => 'pending', 'reason' => $data->reason,
            'createdby' => $USER->id, 'timecreated' => $now, 'decidedby' => 0, 'timedecided' => 0,
            'decisionnote' => '', 'timemodified' => $now,
        ]);
        redirect(
            $url,
            attendancejourneys_get_string('equivalencesubmitted', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'reports');
    echo $OUTPUT->heading(attendancejourneys_get_string('addequivalence', 'attendancejourneys'));
    echo $OUTPUT->notification(attendancejourneys_get_string('equivalenceformhelp', 'attendancejourneys'), 'info');
    $form->display();
    echo $OUTPUT->footer();
    exit;
}
if (in_array($action, ['close', 'reopen'], true)) {
    require_capability('mod/attendancejourneys:managejourneys', $context);
    require_capability('mod/attendancejourneys:' . ($action === 'close' ? 'closejourneys' : 'reopenjourneys'), $context);
    $actioncalculation = attendancejourneys_calculate_user_attendance($attendancejourneys, $userid, $requestedjourneyid);
    $blocker = $action === 'close' ? attendancejourneys_closure_blocker($actioncalculation) : null;
    if ($blocker) {
        redirect(
            $url,
            attendancejourneys_get_string($blocker[0], 'attendancejourneys', $blocker[1]),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    if ($confirm) {
        require_sesskey();
        if ($action === 'close') {
            attendancejourneys_close_user_journey($attendancejourneys, $userid, (int) $USER->id, $requestedjourneyid);
            $message = attendancejourneys_get_string('journeyclosed', 'attendancejourneys');
        } else {
            attendancejourneys_reopen_user_journey(
                $attendancejourneys,
                $userid,
                (int) $USER->id,
                !empty($attendancejourneys->journeygrading) ? $requestedjourneyid : null
            );
            $message = attendancejourneys_get_string('journeyreopened', 'attendancejourneys');
        }
        attendancejourneys_update_completion($course, $cm, [$userid]);
        attendancejourneys_update_grades($attendancejourneys, $userid);
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $calculation = $actioncalculation;
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'reports');
    echo $OUTPUT->heading($action === 'close' ? attendancejourneys_get_string('closejourney', 'attendancejourneys') :
        attendancejourneys_get_string('reopenjourney', 'attendancejourneys'));
    $confirmurl = new moodle_url($url, [
        'action' => $action, 'confirm' => 1, 'sesskey' => sesskey(),
    ]);
    $warning = $action === 'close'
        ? attendancejourneys_get_string('closejourneyconfirm', 'attendancejourneys', (object) [
            'name' => fullname($participant),
            'recorded' => $calculation->recordedsessions,
            'applicable' => $calculation->applicablesessions,
            'missing' => $calculation->applicablesessions - $calculation->recordedsessions,
            'percent' => $calculation->percent === null ? '—' : format_float($calculation->percent, 1) . ' %',
            'threshold' => format_float($calculation->threshold, 1) . ' %',
        ])
        : attendancejourneys_get_string('reopenjourneyconfirm', 'attendancejourneys', fullname($participant));
    echo $OUTPUT->confirm($warning, $confirmurl, $url);
    echo $OUTPUT->footer();
    exit;
}

if (in_array($action, ['approveequivalence', 'rejectequivalence', 'revokeequivalence'], true) && $equivalenceid) {
    require_capability('mod/attendancejourneys:managejourneys', $context);
    require_capability('mod/attendancejourneys:approveequivalences', $context);
    if ($closure) {
        redirect(
            $url,
            attendancejourneys_get_string('equivalencedecisionlocked', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    $expectedstatus = $action === 'revokeequivalence' ? 'approved' : 'pending';
    $equivalence = $DB->get_record('attendancejourneys_equivalences', [
        'id' => $equivalenceid, 'attendancejourneysid' => $attendancejourneys->id,
        'userid' => $userid, 'status' => $expectedstatus,
    ], '*', MUST_EXIST);
    if (
        !$activejourney || empty($activejourney->active) ||
            (int) $equivalence->targetjourneyid !== (int) $activejourney->id
    ) {
        throw new moodle_exception('equivalencevalidationfailed', 'attendancejourneys');
    }
    $targetsession = $DB->get_record('attendancejourneys_sessions', [
        'id' => $equivalence->targetsessionid, 'attendancejourneysid' => $attendancejourneys->id,
    ], '*', MUST_EXIST);
    $sourcesession = $DB->get_record('attendancejourneys_sessions', [
        'id' => $equivalence->sourcesessionid, 'attendancejourneysid' => $attendancejourneys->id,
    ], '*', MUST_EXIST);
    if (
        !attendancejourneys_session_is_visible($cm, $context, $targetsession) ||
            !isset($visiblejourneys[$targetsession->journeyid]) ||
            (!empty($sourcesession->journeyid) && !isset($visiblejourneys[$sourcesession->journeyid])) ||
            !attendancejourneys_session_is_visible($cm, $context, $sourcesession)
    ) {
        throw new required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
    }
    $decisionlabels = [
        'approveequivalence' => attendancejourneys_get_string('approveequivalence', 'attendancejourneys'),
        'rejectequivalence' => attendancejourneys_get_string('rejectequivalence', 'attendancejourneys'),
        'revokeequivalence' => attendancejourneys_get_string('revokeequivalence', 'attendancejourneys'),
    ];
    $formurl = new moodle_url($url, ['action' => $action, 'equivalenceid' => $equivalence->id]);
    $form = new \mod_attendancejourneys\form\equivalence_decision($formurl, [
        'noterequired' => $action !== 'approveequivalence', 'submitlabel' => $decisionlabels[$action],
    ]);
    $form->set_data((object) ['id' => $cm->id, 'userid' => $userid, 'action' => $action,
        'equivalenceid' => $equivalence->id]);
    if ($form->is_cancelled()) {
        redirect($url);
    }
    if ($data = $form->get_data()) {
        $requiredcreditsessions = \mod_attendancejourneys\local\individual_obligation::required_sessions(
            $attendancejourneys,
            $userid,
            [$targetsession->id => $targetsession, $sourcesession->id => $sourcesession]
        );
        $evidencecreditsessions = \mod_attendancejourneys\local\individual_obligation::evidence_sessions(
            $attendancejourneys,
            $userid,
            [$sourcesession->id => $sourcesession]
        );
        if (
            $action === 'approveequivalence' &&
                (!empty($targetsession->cancelled) || !empty($sourcesession->cancelled) ||
                    !isset($requiredcreditsessions[$targetsession->id], $evidencecreditsessions[$sourcesession->id]))
        ) {
            throw new moodle_exception('equivalencevalidationfailed', 'attendancejourneys');
        }
        if ($action === 'approveequivalence') {
            $sourcerecord = $DB->get_record_select(
                'attendancejourneys_records',
                'sessionid = :sessionid AND userid = :userid AND status IN (:present, :partial)',
                [
                    'sessionid' => $sourcesession->id, 'userid' => $userid,
                    'present' => 'present', 'partial' => 'partial',
                ]
            );
            $sourcealreadyused = $DB->record_exists_select(
                'attendancejourneys_equivalences',
                'userid = :userid AND sourcesessionid = :source AND status = :approved AND id <> :currentid',
                [
                    'userid' => $userid, 'source' => $sourcesession->id,
                    'approved' => 'approved', 'currentid' => $equivalence->id,
                ]
            );
            if (
                !$sourcerecord || !\mod_attendancejourneys\local\calculator::record_is_official($sourcerecord) ||
                    $sourcealreadyused || !\mod_attendancejourneys\local\calculator::session_is_due($sourcesession) ||
                    (int) $targetsession->journeyid !== (int) $equivalence->targetjourneyid ||
                    (int) $sourcesession->journeyid === (int) $equivalence->targetjourneyid
            ) {
                redirect(
                    $url,
                    attendancejourneys_get_string('equivalencevalidationfailed', 'attendancejourneys'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            $decisionstatus = 'approved';
        } else {
            $decisionstatus = $action === 'rejectequivalence' ? 'rejected' : 'revoked';
        }
        \mod_attendancejourneys\local\equivalence_history::record_decision(
            $equivalence,
            $decisionstatus,
            $data->decisionnote
        );
        attendancejourneys_update_completion($course, $cm, [$userid]);
        attendancejourneys_update_grades($attendancejourneys, $userid);
        redirect(
            $url,
            attendancejourneys_get_string('equivalencedecisionsaved', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'reports');
    echo $OUTPUT->heading($decisionlabels[$action]);
    echo $OUTPUT->notification(attendancejourneys_get_string('equivalencedecisionsummary', 'attendancejourneys', (object) [
        'target' => format_string($targetsession->name), 'source' => format_string($sourcesession->name),
    ]), 'info');
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

$sessions = $DB->get_records(
    'attendancejourneys_sessions',
    ['attendancejourneysid' => $attendancejourneys->id],
    'sessiondate DESC'
);
$targetjourneyid = $closure && !empty($closure->journeyid) ? (int) $closure->journeyid :
    ($activejourney ? (int) $activejourney->id : 0);
$sessions = array_filter($sessions, static fn($session) => !$targetjourneyid ||
    (int) $session->journeyid === $targetjourneyid);
$sessions = array_filter($sessions, fn($session) => attendancejourneys_session_applies_to_user($session, $userid));
$records = [];
if ($sessions) {
    [$insql, $params] = $DB->get_in_or_equal(array_keys($sessions), SQL_PARAMS_NAMED, 'session');
    $params['userid'] = $userid;
    foreach ($DB->get_records_select('attendancejourneys_records', "userid = :userid AND sessionid $insql", $params) as $record) {
        $records[$record->sessionid] = $record;
    }
}
$records = \mod_attendancejourneys\local\calculator::apply_approved_equivalences(
    $attendancejourneys,
    $userid,
    $sessions,
    $records
);
$recorderids = array_values(array_unique(array_filter(array_map(
    static fn($record): int => (int) $record->takenby,
    $records
))));
$recorders = $recorderids ? $DB->get_records_list(
    'user',
    'id',
    $recorderids,
    '',
    'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
) : [];

$individualpolicy = !empty($attendancejourneys->journeygrading) && $targetjourneyid > 0 ?
    \mod_attendancejourneys\local\individual_obligation::get($targetjourneyid, $userid) :
    \mod_attendancejourneys\local\obligation_policy::unrestricted();
$requiredsessions = \mod_attendancejourneys\local\individual_obligation::required_sessions($attendancejourneys, $userid, $sessions);
$calculation = \mod_attendancejourneys\local\calculator::aggregate($attendancejourneys, $requiredsessions, $records);
$presentminutes = $calculation->presentminutes;
$possibleminutes = $calculation->possibleminutes;
$markedsessions = $calculation->recordedsessions;
$percent = $calculation->percent;

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'reports');
echo html_writer::start_div('attendancejourneys-individual-page');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(
    attendancejourneys_get_string('participantrecord', 'attendancejourneys'),
    'attendancejourneys-section-eyebrow'
);
echo $OUTPUT->heading(attendancejourneys_get_string('individualreport', 'attendancejourneys'));
if (!empty($attendancejourneys->journeygrading)) {
    echo $OUTPUT->heading(format_string($reportjourneys[$requestedjourneyid]->name), 3);
    if (!empty($attendancejourneys->percentageenabled)) {
        $threshold = $closure ? (float) $closure->threshold :
            attendancejourneys_get_passinggrade($attendancejourneys, $requestedjourneyid);
        echo html_writer::div(get_string('thresholdvalue', 'attendancejourneys', format_float($threshold, 2) . ' %'));
    }
    if ($canviewreports) {
        $protections = \mod_attendancejourneys\local\journey_grades::protections(
            $attendancejourneys,
            $requestedjourneyid,
            [$userid]
        );
        if (isset($protections[$userid])) {
            echo $OUTPUT->notification(get_string($protections[$userid], 'attendancejourneys'), 'warning');
        }
    }
    $canmanageobligation = $canviewreports && has_capability('mod/attendancejourneys:managejourneys', $context) &&
        has_capability('moodle/course:manageactivities', $context);
    if (!empty($individualpolicy->waived)) {
        echo $OUTPUT->notification(attendancejourneys_get_string('obligationwaivednotice', 'attendancejourneys'), 'info');
    } else if (!empty($individualpolicy->starttime) || !empty($individualpolicy->endtime)) {
        echo $OUTPUT->notification(attendancejourneys_get_string('obligationperiodnotice', 'attendancejourneys'), 'info');
    }
    if (!empty($individualpolicy->starttime) || !empty($individualpolicy->endtime)) {
        foreach (['starttime' => 'obligationstart', 'endtime' => 'obligationend'] as $field => $key) {
            echo html_writer::div(attendancejourneys_get_string($key, 'attendancejourneys') . ': ' .
                (!empty($individualpolicy->$field) ? userdate($individualpolicy->$field) : get_string('none')));
        }
    }
    if ($canmanageobligation && !$closure && !empty($activejourney->active)) {
        echo html_writer::link(new moodle_url('/mod/attendancejourneys/obligation.php', [
            'id' => $cm->id, 'userid' => $userid, 'journeyid' => $requestedjourneyid,
        ]), attendancejourneys_get_string('obligationmanage', 'attendancejourneys'), ['class' => 'btn btn-outline-secondary my-3']);
    }
    $attemptrootid = (int) ($activejourney->rootjourneyid ?: $activejourney->id);
    $attemptfamily = $reportfamilies[$attemptrootid];
    $attemptroot = $attemptfamily->root;
    $currentattempt = \mod_attendancejourneys\local\attempt_family::select($attemptfamily, $userid);
    if (!empty($attemptfamily->links[$userid])) {
        $personalnumber = 1;
        foreach ($attemptfamily->links[$userid] as $opening) {
            if ((int) $opening->journeyid === $requestedjourneyid) {
                $personalnumber = (int) $opening->attemptnumber;
            }
        }
        echo html_writer::div(attendancejourneys_get_string('attemptpersonalnumber', 'attendancejourneys', $personalnumber));
        echo $OUTPUT->notification(attendancejourneys_get_string(
            $currentattempt->journeyid === $requestedjourneyid ? 'attemptcurrentnotice' : 'attempthistoricalnotice',
            'attendancejourneys'
        ), 'info');
        if ($currentattempt->journeyid !== $requestedjourneyid && isset($reportjourneys[$currentattempt->journeyid])) {
            echo html_writer::link(
                new moodle_url('/mod/attendancejourneys/individual.php', [
                'id' => $cm->id, 'userid' => $userid, 'journeyid' => $currentattempt->journeyid,
                ]),
                attendancejourneys_get_string('attemptviewcurrent', 'attendancejourneys'),
                ['class' => 'btn btn-outline-primary my-3']
            );
        }
    }
    if (
        $canmanageobligation && attendancejourneys_can_decide($context, 'manageattempts') &&
            $currentattempt->journeyid === $requestedjourneyid && $closure &&
            empty($individualpolicy->waived) && !isset($protections[$userid])
    ) {
        echo html_writer::link(new moodle_url('/mod/attendancejourneys/attempt.php', [
            'id' => $cm->id, 'userid' => $userid, 'rootjourneyid' => $attemptroot->id,
        ]), attendancejourneys_get_string('attemptopen', 'attendancejourneys'), ['class' => 'btn btn-outline-secondary my-3']);
    }
    if (count($reportfamilies) > 1) {
        $selectionurl = new moodle_url('/mod/attendancejourneys/individual.php', ['id' => $cm->id, 'userid' => $userid]);
        echo html_writer::div(html_writer::link($selectionurl, get_string('selectreportjourney', 'attendancejourneys')), 'mb-3');
    }
}
echo html_writer::div(attendancejourneys_get_string(
    $islight ? 'individualreportintrolight' : 'individualreportintro',
    'attendancejourneys'
), 'text-muted');
echo html_writer::end_div();
if (!empty($calculation->pendingapprovals)) {
    echo html_writer::div(attendancejourneys_get_string(
        'officialcalculationnotice',
        'attendancejourneys',
        $calculation->pendingapprovals
    ), 'alert alert-info');
}

$profile = $OUTPUT->user_picture($participant, ['courseid' => $course->id, 'size' => 64]);
$profile .= html_writer::div(
    html_writer::tag('h3', fullname($participant), ['class' => 'h5 mb-1']) .
    html_writer::div(s($participant->email), 'text-muted'),
    'attendancejourneys-profile-identity'
);
echo html_writer::div($profile, 'attendancejourneys-profile-header');

$equivalenceconditions = ['attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid];
if ($activejourney) {
    $equivalenceconditions['targetjourneyid'] = $activejourney->id;
}
$equivalences = $DB->get_records('attendancejourneys_equivalences', $equivalenceconditions, 'timecreated DESC, id DESC');
$equivalences = array_filter($equivalences, static function ($equivalence) use ($DB, $cm, $context, $visiblejourneys) {
    foreach ([$equivalence->sourcesessionid, $equivalence->targetsessionid] as $sessionid) {
        $session = $DB->get_record('attendancejourneys_sessions', ['id' => $sessionid]);
        if (
            !$session || !attendancejourneys_session_is_visible($cm, $context, $session) ||
                (!empty($session->journeyid) && !isset($visiblejourneys[$session->journeyid]))
        ) {
            return false;
        }
    }
    return true;
});
if (
    !$islight && ($equivalences ||
        (empty($individualpolicy->waived) &&
            has_capability('mod/attendancejourneys:managejourneys', $context) && $activejourney && !$closure))
) {
    echo html_writer::start_div('attendancejourneys-content-card');
    echo html_writer::tag('h3', attendancejourneys_get_string('sessionequivalences', 'attendancejourneys'), ['class' => 'h5']);
    echo html_writer::div(attendancejourneys_get_string('sessionequivalenceshelp', 'attendancejourneys'), 'text-muted mb-3');
    if (
        empty($individualpolicy->waived) &&
            has_capability('mod/attendancejourneys:managejourneys', $context) && $activejourney && !$closure
    ) {
        echo html_writer::link(
            new moodle_url($url, ['action' => 'addequivalence']),
            attendancejourneys_get_string('addequivalence', 'attendancejourneys'),
            ['class' => 'btn btn-outline-primary mb-3']
        );
    }
    if ($equivalences) {
        $equivtable = new html_table();
        $equivtable->head = [attendancejourneys_get_string('equivalencetargetsession', 'attendancejourneys'),
            attendancejourneys_get_string(
                'equivalencesourcesession',
                'attendancejourneys'
            ),
        attendancejourneys_get_string(
            'status',
            'attendancejourneys'
        ),

            attendancejourneys_get_string(
                'equivalencereason',
                'attendancejourneys'
            ),
        attendancejourneys_get_string(
            'equivalencedecision',
            'attendancejourneys'
        ),

            get_string('actions')];
        foreach ($equivalences as $equivalence) {
            $target = $DB->get_record('attendancejourneys_sessions', ['id' => $equivalence->targetsessionid]);
            $source = $DB->get_record('attendancejourneys_sessions', ['id' => $equivalence->sourcesessionid]);
            $decision = '—';
            if (!empty($equivalence->decidedby)) {
                $decisionuser = core_user::get_user($equivalence->decidedby);
                $decision = attendancejourneys_get_string('equivalencedecisionmeta', 'attendancejourneys', (object) [
                    'user' => $decisionuser ? fullname($decisionuser) : get_string('unknownuser'),
                    'date' => userdate(
                        $equivalence->timedecided,
                        get_string('strftimedatetimeshort', 'langconfig')
                    ),
                ]);
                if (!empty($equivalence->decisionnote)) {
                    $decision .= html_writer::div(
                        format_text($equivalence->decisionnote, FORMAT_PLAIN),
                        'small text-muted'
                    );
                }
            }
            $history = $DB->get_records('attendancejourneys_equivlog', ['equivalenceid' => $equivalence->id], 'id');
            if ($history) {
                $items = '';
                foreach ($history as $entry) {
                    $actor = $entry->actorid ? core_user::get_user($entry->actorid) : false;
                    $date = $entry->timecreated ? userdate($entry->timecreated) :
                        attendancejourneys_get_string('equivalencehistoryunknowndate', 'attendancejourneys');
                    $label = attendancejourneys_get_string('equivalencestatus' . $entry->status, 'attendancejourneys');
                    $label .= ' — ' . ($actor ? fullname($actor) : get_string('unknownuser')) . ' — ' . $date;
                    $item = s($label);
                    if ($entry->action === 'baseline') {
                        $item .= html_writer::div(
                            attendancejourneys_get_string('equivalencehistorybaseline', 'attendancejourneys'),
                            'small text-muted'
                        );
                    }
                    if (!empty($entry->note)) {
                        $item .= html_writer::div(format_text($entry->note, FORMAT_PLAIN), 'small');
                    }
                    $items .= html_writer::tag('li', $item);
                }
                $decision .= html_writer::tag(
                    'details',
                    html_writer::tag('summary', attendancejourneys_get_string('equivalencedecisionhistory', 'attendancejourneys')) .
                    html_writer::tag('ol', $items),
                    ['class' => 'mt-2']
                );
            }
            $actions = '';
            if (!$closure && attendancejourneys_can_decide($context, 'approveequivalences')) {
                if ($equivalence->status === 'pending') {
                    $actions = html_writer::link(
                        new moodle_url($url, [
                        'action' => 'approveequivalence', 'equivalenceid' => $equivalence->id,
                        ]),
                        attendancejourneys_get_string(
                            'approveequivalence',
                            'attendancejourneys'
                        ),
                        ['class' => 'btn btn-sm btn-primary mr-1 me-1']
                    );
                    $actions .= html_writer::link(
                        new moodle_url($url, [
                        'action' => 'rejectequivalence', 'equivalenceid' => $equivalence->id,
                        ]),
                        attendancejourneys_get_string('rejectequivalence', 'attendancejourneys'),
                        ['class' => 'btn btn-sm btn-outline-secondary']
                    );
                } else if ($equivalence->status === 'approved') {
                    $actions = html_writer::link(
                        new moodle_url($url, [
                        'action' => 'revokeequivalence', 'equivalenceid' => $equivalence->id,
                        ]),
                        attendancejourneys_get_string('revokeequivalence', 'attendancejourneys'),
                        ['class' => 'btn btn-sm btn-outline-danger']
                    );
                }
            }
            $equivtable->data[] = [$target ? format_string($target->name) : '—',
                $source ? format_string($source->name) : '—',
                attendancejourneys_get_string('equivalencestatus' . $equivalence->status, 'attendancejourneys'),
                format_text($equivalence->reason, FORMAT_PLAIN), $decision, $actions];
        }
        echo attendancejourneys_responsive_table(
            $equivtable,
            attendancejourneys_get_string('sessionequivalences', 'attendancejourneys')
        );
    }
    echo html_writer::end_div();
}

$historyconditions = ['attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid];
if (!empty($attendancejourneys->journeygrading)) {
    $historyconditions['journeyid'] = $requestedjourneyid;
}
$haspersonalfamily = !empty($attendancejourneys->journeygrading) && !empty($attemptfamily->links[$userid]);
if ($haspersonalfamily) {
    $allclosures = array_reverse(array_filter(
        $attemptfamily->closures[$userid] ?? [],
        static fn($historyclosure) => isset($reportjourneys[$historyclosure->journeyid])
    ));
} else {
    $allclosures = $DB->get_records('attendancejourneys_closures', $historyconditions, 'timeclosed DESC, id DESC');
}
$attemptsbyjourney = [];
foreach (
    $DB->get_records('attendancejourneys_members', [
        'attendancejourneysid' => $attendancejourneys->id, 'userid' => $userid,
    ]) as $membership
) {
    $attemptsbyjourney[(int) $membership->journeyid] = (int) $membership->attemptnumber;
}
if ($haspersonalfamily) {
    $attemptsbyjourney = [(int) $attemptroot->id => 1];
    foreach ($attemptfamily->links[$userid] as $opening) {
        $attemptsbyjourney[(int) $opening->journeyid] = (int) $opening->attemptnumber;
    }
}
$historycurrentjourney = $haspersonalfamily ? ($reportjourneys[$currentattempt->journeyid] ?? null) : $activejourney;
$historycurrentclosure = $haspersonalfamily ? $currentattempt->closure : $closure;
if ((!$islight || $haspersonalfamily) && ($allclosures || ($historycurrentjourney && !$historycurrentclosure))) {
    echo html_writer::start_div('attendancejourneys-content-card');
    echo html_writer::tag('h3', attendancejourneys_get_string('journeyhistory', 'attendancejourneys'), ['class' => 'h5']);
    $historytable = new html_table();
    $historytable->attributes['class'] = 'generaltable attendancejourneys-journey-history';
    $historytable->head = [attendancejourneys_get_string(
        'journey',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        $haspersonalfamily ? 'attemptcolumn' :
            (!empty($attendancejourneys->journeygrading) ? 'journeyassignment' : 'journeyattempt'),
        'attendancejourneys'
    ),

        attendancejourneys_get_string('journeyperiod', 'attendancejourneys'),
        attendancejourneys_get_string('markedsessions', 'attendancejourneys'),
            attendancejourneys_get_string('percentage', 'attendancejourneys'),
        attendancejourneys_get_string('result', 'attendancejourneys'),
            attendancejourneys_get_string('status', 'attendancejourneys')];
    if ($historycurrentjourney && !$historycurrentclosure) {
        $currentcalculation = attendancejourneys_calculate_user_attendance(
            $attendancejourneys,
            $userid,
            (int) $historycurrentjourney->id
        );
        $attemptnumber = $attemptsbyjourney[(int) $historycurrentjourney->id] ?? 1;
        $historytable->data[] = [format_string($historycurrentjourney->name),
            attendancejourneys_get_string(
                $haspersonalfamily ? 'attemptpersonalnumber' :
                    (!empty($attendancejourneys->journeygrading) ? 'journeyassignmentnumber' : 'journeyattemptnumber'),
                'attendancejourneys',
                $attemptnumber
            ),
            attendancejourneys_get_string(
                !empty($attendancejourneys->journeygrading) ? 'journeycurrentassignment' : 'journeycurrentattempt',
                'attendancejourneys'
            ),
            $currentcalculation->recordedsessions . ' / ' . $currentcalculation->applicablesessions,
            $currentcalculation->percent === null ? '—' : format_float($currentcalculation->percent, 1) . ' %',
            attendancejourneys_get_string(
                !empty($currentcalculation->waived) ? 'obligationwaived' : 'provisional',
                'attendancejourneys'
            ),
            html_writer::span(attendancejourneys_get_string(
                !empty($currentcalculation->waived) ? 'obligationwaived' : 'journeyinprogress',
                'attendancejourneys'
            ), 'badge badge-info text-bg-info')];
    }
    $publishedclosure = $haspersonalfamily ? $currentattempt->closure : attendancejourneys_get_active_closure(
        (int) $attendancejourneys->id,
        $userid,
        !empty($attendancejourneys->journeygrading) ? $requestedjourneyid : null
    );
    $journeycache = [];
    foreach ($allclosures as $historyclosure) {
        $historyjourney = null;
        if (!empty($historyclosure->journeyid)) {
            if (!array_key_exists($historyclosure->journeyid, $journeycache)) {
                $journeycache[$historyclosure->journeyid] = $DB->get_record(
                    'attendancejourneys_journeys',
                    ['id' => $historyclosure->journeyid]
                );
            }
            $historyjourney = $journeycache[$historyclosure->journeyid];
        }
        $historyname = $historyjourney ? format_string($historyjourney->name) :
            attendancejourneys_get_string('activitywidejourney', 'attendancejourneys');
        $historyresult = $historyclosure->result === 'passed' ? attendancejourneys_get_string('passed', 'attendancejourneys') :
            ($historyclosure->result === 'failed' ? attendancejourneys_get_string('failed', 'attendancejourneys') : '—');
        if (!empty($historyclosure->active) && $publishedclosure && $publishedclosure->id == $historyclosure->id) {
            $historystatus = html_writer::span(
                attendancejourneys_get_string('journeyresultpublished', 'attendancejourneys'),
                'badge badge-success text-bg-success'
            );
        } else if (!empty($historyclosure->active)) {
            $historystatus = html_writer::span(
                attendancejourneys_get_string('journeypreviousresult', 'attendancejourneys'),
                'badge badge-secondary text-bg-secondary'
            );
        } else {
            $historystatus = html_writer::span(
                attendancejourneys_get_string('journeyresultreopened', 'attendancejourneys'),
                'badge badge-light text-bg-light'
            );
        }
        $attemptlabel = !empty($historyclosure->journeyid) && isset($attemptsbyjourney[$historyclosure->journeyid]) ?
            attendancejourneys_get_string(
                $haspersonalfamily ? 'attemptpersonalnumber' :
                    (!empty($attendancejourneys->journeygrading) ? 'journeyassignmentnumber' : 'journeyattemptnumber'),
                'attendancejourneys',
                $attemptsbyjourney[$historyclosure->journeyid]
            ) : '—';
        $historytable->data[] = [$historyname, $attemptlabel,
            userdate($historyclosure->timeclosed, get_string('strftimedatetime', 'langconfig')),
            $historyclosure->recordedsessions . ' / ' . $historyclosure->applicablesessions,
            $historyclosure->percentage === null ? '—' : format_float($historyclosure->percentage, 1) . ' %',
            $historyresult, $historystatus];
    }
    echo attendancejourneys_responsive_table($historytable, attendancejourneys_get_string('journeyhistory', 'attendancejourneys'));
    echo html_writer::end_div();
}

if ($closure) {
    if ($showjourneyclosure) {
        $closer = $DB->get_record('user', ['id' => $closure->closedby]);
        $closedmeta = attendancejourneys_get_string('journeyclosedmeta', 'attendancejourneys', (object) [
            'date' => userdate($closure->timeclosed, get_string('strftimedatetime', 'langconfig')),
            'user' => $closer ? fullname($closer) : get_string('unknownuser'),
        ]);
        echo html_writer::start_div('attendancejourneys-journey-state attendancejourneys-journey-closed');
        echo html_writer::tag('strong', attendancejourneys_get_string('journeystatusclosed', 'attendancejourneys'));
        echo html_writer::div($closedmeta, 'small text-muted mt-1');
        if (attendancejourneys_can_decide($context, 'reopenjourneys')) {
            echo html_writer::link(
                new moodle_url($url, ['action' => 'reopen']),
                attendancejourneys_get_string('reopenjourney', 'attendancejourneys'),
                ['class' => 'btn btn-outline-secondary btn-sm mt-3']
            );
        }
        echo html_writer::end_div();
    }
    $markedsessions = (int) $closure->recordedsessions;
    $presentminutes = (int) $closure->presentminutes;
    $possibleminutes = (int) $closure->possibleminutes;
    $percent = $closure->percentage === null ? null : (float) $closure->percentage;
    $sessionscountforsummary = (int) $closure->applicablesessions;
} else {
    if ($showjourneyclosure) {
        echo html_writer::start_div('attendancejourneys-journey-state attendancejourneys-journey-open');
        echo html_writer::tag('strong', attendancejourneys_get_string(
            !empty($individualpolicy->waived) ? 'obligationwaived' : 'journeystatusopen',
            'attendancejourneys'
        ));
        if (empty($individualpolicy->waived)) {
            echo html_writer::div(
                attendancejourneys_get_string('journeyprovisionalhelp', 'attendancejourneys'),
                'small text-muted mt-1'
            );
        }
        if (empty($individualpolicy->waived) && attendancejourneys_can_decide($context, 'closejourneys')) {
            echo html_writer::link(
                new moodle_url($url, ['action' => 'close']),
                attendancejourneys_get_string('closejourney', 'attendancejourneys'),
                ['class' => 'btn btn-primary btn-sm mt-3']
            );
        }
        echo html_writer::end_div();
    }
    $sessionscountforsummary = $calculation->applicablesessions;
}

echo html_writer::start_div('attendancejourneys-content-card');
echo html_writer::tag('h3', attendancejourneys_get_string('individualsummary', 'attendancejourneys'), ['class' => 'h5']);
$summary = [];
$summary[] = html_writer::div(
    html_writer::tag('strong', $markedsessions . ' / ' . $sessionscountforsummary, ['class' => 'attendancejourneys-stat-value']) .
    html_writer::span(attendancejourneys_get_string('sessionsrecorded', 'attendancejourneys')),
    'attendancejourneys-stat'
);
$summary[] = html_writer::div(
    html_writer::tag('strong', $presentminutes . ' / ' . $possibleminutes . ' min', ['class' => 'attendancejourneys-stat-value']) .
    html_writer::span(attendancejourneys_get_string('presentminutes', 'attendancejourneys')),
    'attendancejourneys-stat'
);
$summary[] = html_writer::div(
    html_writer::tag(
        'strong',
        $percent === null || !$attendancejourneys->percentageenabled ? '—' : format_float($percent, 1) . ' %',
        ['class' => 'attendancejourneys-stat-value']
    ) . html_writer::span(attendancejourneys_get_string('percentage', 'attendancejourneys')),
    'attendancejourneys-stat'
);
if (!empty($attendancejourneys->journeygrading) && !$closure) {
    $result = html_writer::span(
        attendancejourneys_get_string(!empty($individualpolicy->waived) ? 'obligationwaived' : 'provisional', 'attendancejourneys'),
        'badge badge-secondary text-bg-secondary'
    );
} else if ($percent === null || !$attendancejourneys->percentageenabled) {
    $result = '—';
} else {
    $passed = $closure ? $closure->result === 'passed' :
        $percent >= attendancejourneys_get_passinggrade($attendancejourneys, $targetjourneyid);
    $result = html_writer::span(
        $passed ? attendancejourneys_get_string(
            'passed',
            'attendancejourneys'
        ) : attendancejourneys_get_string('failed', 'attendancejourneys'),
        $passed ? 'badge badge-success text-bg-success' : 'badge badge-danger text-bg-danger'
    );
}
$summary[] = html_writer::div(
    html_writer::div(
        $result,
        'attendancejourneys-stat-value'
    ) .
    html_writer::span(attendancejourneys_get_string(
        'result',
        'attendancejourneys'
    )),
    'attendancejourneys-stat'
);
echo html_writer::div(implode('', $summary), 'attendancejourneys-summary-grid');
echo html_writer::end_div();

if (!empty($attendancejourneys->journeygrading) && ($canmanageobligation || (int) $USER->id === $userid)) {
    $historyconditions = ['attendancejourneysid' => $attendancejourneys->id, 'journeyid' => $targetjourneyid, 'userid' => $userid];
    $historycount = $DB->count_records('attendancejourneys_obligationlog', $historyconditions);
    if ($historycount) {
        $historypage = min(max(0, optional_param('obligationpage', 0, PARAM_INT)), (int) floor(($historycount - 1) / 20));
        $history = $DB->get_records(
            'attendancejourneys_obligationlog',
            $historyconditions,
            'revision DESC, id DESC',
            '*',
            $historypage * 20,
            20
        );
        echo $OUTPUT->heading(attendancejourneys_get_string('obligationhistory', 'attendancejourneys'), 3);
        $historytable = new html_table();
        $historytable->head = [attendancejourneys_get_string('sessiondate', 'attendancejourneys'),
            attendancejourneys_get_string('obligationwaive', 'attendancejourneys'),
            attendancejourneys_get_string('obligationstart', 'attendancejourneys'),
            attendancejourneys_get_string('obligationend', 'attendancejourneys'),
            attendancejourneys_get_string('obligationreason', 'attendancejourneys')];
        foreach ($history as $decision) {
            $historytable->data[] = [userdate($decision->timecreated), $decision->waived ? get_string('yes') : get_string('no'),
                $decision->starttime ? userdate($decision->starttime) : get_string('none'),
                $decision->endtime ? userdate($decision->endtime) : get_string('none'), s((string) $decision->reason)];
        }
        echo attendancejourneys_responsive_table(
            $historytable,
            attendancejourneys_get_string('obligationhistory', 'attendancejourneys')
        );
        echo $OUTPUT->paging_bar($historycount, $historypage, 20, $url, 'obligationpage');
    }
}

echo html_writer::start_div('attendancejourneys-content-card');
echo html_writer::tag('h3', attendancejourneys_get_string('detailbysession', 'attendancejourneys'), ['class' => 'h5']);
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $url->out(false),
    'class' => 'attendancejourneys-individual-filters',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
echo html_writer::label(
    attendancejourneys_get_string('searchsessions', 'attendancejourneys'),
    'attendancejourneys-individual-search',
    false,
    ['class' => 'sr-only visually-hidden']
);
echo html_writer::empty_tag('input', [
    'type' => 'search', 'name' => 'q', 'value' => $query, 'id' => 'attendancejourneys-individual-search',
    'class' => 'form-control', 'placeholder' => attendancejourneys_get_string('searchsessions', 'attendancejourneys'),
]);
$detailstatusoptions = [
    'all' => attendancejourneys_get_string('allstatuses', 'attendancejourneys'),
    'unrecorded' => attendancejourneys_get_string('statusunrecorded', 'attendancejourneys'),
    'cancelled' => attendancejourneys_get_string('sessioncancelled', 'attendancejourneys'),
    'present' => attendancejourneys_get_string('statuspresent', 'attendancejourneys'),
    'absent' => attendancejourneys_get_string('statusabsent', 'attendancejourneys'),
    'partial' => attendancejourneys_get_string('statuspartial', 'attendancejourneys'),
];
if (!empty($attendancejourneys->journeygrading)) {
    $detailstatusoptions['exempt'] = get_string('statusexempt', 'attendancejourneys');
}
if ($attendancejourneys->excusedenabled) {
    $detailstatusoptions['excused'] = attendancejourneys_get_string('statusexcused', 'attendancejourneys');
}
echo html_writer::label(
    attendancejourneys_get_string('filtersessionstatus', 'attendancejourneys'),
    'attendancejourneys-individual-status',
    false,
    ['class' => 'sr-only visually-hidden']
);
echo html_writer::select($detailstatusoptions, 'statusfilter', $statusfilter, false, [
    'id' => 'attendancejourneys-individual-status', 'class' => 'custom-select form-select',
]);
echo html_writer::tag('button', get_string('apply'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
if ($query !== '' || $statusfilter !== 'all') {
    echo html_writer::link($url, attendancejourneys_get_string('clearfilters', 'attendancejourneys'), ['class' => 'btn btn-link']);
}
echo html_writer::end_tag('form');
$detailsessions = array_filter($sessions, static function ($session) use ($records, $query, $statusfilter): bool {
    if (
        $query !== '' && core_text::strpos(
            core_text::strtolower(format_string($session->name)),
            core_text::strtolower($query)
        ) === false
    ) {
        return false;
    }
    $recordstatus = !empty($session->cancelled) ? 'cancelled' :
        (isset($records[$session->id]) ? $records[$session->id]->status : 'unrecorded');
    return $statusfilter === 'all' || $recordstatus === $statusfilter;
});
$detailcount = count($detailsessions);
$detailpagecount = max(1, (int) ceil($detailcount / $perpage));
$page = min(max(0, $page), $detailpagecount - 1);
echo html_writer::div(attendancejourneys_get_string('filteredsessioncount', 'attendancejourneys', (object) [
    'visible' => $detailcount,
    'total' => count($sessions),
]), 'small text-muted mb-2');
if ($detailcount === 0) {
    echo $OUTPUT->notification(attendancejourneys_get_string('noindividualsessionsmatch', 'attendancejourneys'), 'info');
}
$table = new html_table();
$table->attributes['class'] = 'generaltable attendancejourneys-individual-table';
$table->head = [
    attendancejourneys_get_string('sessions', 'attendancejourneys'),
    attendancejourneys_get_string('duration', 'attendancejourneys'),
    attendancejourneys_get_string('status', 'attendancejourneys'),
    attendancejourneys_get_string('presentminutes', 'attendancejourneys'),
    attendancejourneys_get_string('remarks', 'attendancejourneys'),
    attendancejourneys_get_string('attendanceenteredby', 'attendancejourneys'),
    attendancejourneys_get_string('attendancemodifiedon', 'attendancejourneys'),
];
$pagesessions = array_slice(array_values($detailsessions), $page * $perpage, $perpage);
foreach ($pagesessions as $session) {
    $record = $records[$session->id] ?? null;
    $endtime = $session->sessiondate + ((int) $session->duration * MINSECS);
    $period = attendancejourneys_get_string('sessionperiod', 'attendancejourneys', (object) [
        'start' => userdate($session->sessiondate, get_string('strftimedatetimeshort', 'langconfig')),
        'end' => userdate($endtime, get_string('strftimedatetimeshort', 'langconfig')),
    ]);
    if (!$record) {
        $status = html_writer::span(
            attendancejourneys_get_string(
                'notrecorded',
                'attendancejourneys'
            ),
            'badge badge-secondary text-bg-secondary'
        );
        $minutes = '—';
        $remarks = '—';
        $recordedby = '—';
        $recordsource = '';
        $modifiedon = '—';
    } else {
        $statusclasses = [
            'present' => 'badge badge-success text-bg-success',
            'absent' => 'badge badge-danger text-bg-danger',
            'partial' => 'badge badge-warning text-bg-warning',
            'excused' => 'badge badge-info text-bg-info',
            'exempt' => 'badge badge-secondary text-bg-secondary',
        ];
        $status = html_writer::span(
            attendancejourneys_get_string('status' . $record->status, 'attendancejourneys'),
            $statusclasses[$record->status] ?? 'badge badge-secondary text-bg-secondary'
        );
        [$present, $possible] = \mod_attendancejourneys\local\calculator::record_minutes(
            $attendancejourneys,
            $session,
            $record
        );
        $minutes = attendancejourneys_get_string(
            'minutesfraction',
            'attendancejourneys',
            (object) ['present' => $present,
            'possible' => $possible]
        );
        $remarks = trim((string) $record->remarks) !== '' ? format_string($record->remarks) : '—';
        $recordedby = !empty($record->takenby) && isset($recorders[$record->takenby])
            ? fullname($recorders[$record->takenby]) : attendancejourneys_get_string(
                'attendanceunknownrecorder',
                'attendancejourneys'
            );
        $isselfrecorded = (int) $record->takenby === $userid;
        $recordsource = html_writer::span(
            attendancejourneys_get_string($isselfrecorded ? 'attendancesourceself' : 'attendancesourcestaff', 'attendancejourneys'),
            'attendancejourneys-source-badge ' . ($isselfrecorded ? 'attendancejourneys-source-self' :
                'attendancejourneys-source-staff')
        );
        if (!empty($record->approved)) {
            $recordsource .= html_writer::span(
                attendancejourneys_get_string('attendanceapproved', 'attendancejourneys'),
                'attendancejourneys-source-badge attendancejourneys-source-approved ml-1 ms-1'
            );
        }
        if (!empty($record->equivalenceid)) {
            $sourcesession = $DB->get_record('attendancejourneys_sessions', ['id' => $record->sourcesessionid]);
            $recordsource .= html_writer::span(
                attendancejourneys_get_string(
                    'attendancesourceequivalence',
                    'attendancejourneys',
                    $sourcesession && attendancejourneys_session_is_visible($cm, $context, $sourcesession) &&
                        (empty($sourcesession->journeyid) || isset($visiblejourneys[$sourcesession->journeyid]))
                        ? format_string($sourcesession->name) : get_string('hidden')
                ),
                'attendancejourneys-source-badge attendancejourneys-source-approved ml-1 ms-1'
            );
        }
        $modifiedon = !empty($record->timemodified)
            ? userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig')) : '—';
    }
    if (!empty($session->cancelled)) {
        $status = html_writer::span(
            attendancejourneys_get_string('sessioncancelled', 'attendancejourneys'),
            'badge badge-secondary text-bg-secondary'
        );
        $minutes = '—';
    }
    if (empty($session->cancelled) && !isset($requiredsessions[$session->id])) {
        $status .= html_writer::span(
            attendancejourneys_get_string('obligationoutside', 'attendancejourneys'),
            'badge badge-secondary text-bg-secondary ml-1 ms-1'
        );
    }
    $table->data[] = [
        html_writer::tag('strong', format_string($session->name)) . html_writer::span($period, 'd-block text-muted small'),
        attendancejourneys_get_string('minutesvalue', 'attendancejourneys', $session->duration),
        html_writer::span($status, 'attendancejourneys-detail-status'),
        $minutes,
        html_writer::span($remarks, 'attendancejourneys-session-remarks'),
        s($recordedby) . ($recordsource ? html_writer::div($recordsource, 'mt-1') : ''),
        $modifiedon,
    ];
}
echo attendancejourneys_responsive_table($table, attendancejourneys_get_string('individualreport', 'attendancejourneys'));
$pagingurl = new moodle_url($url, ['q' => $query, 'statusfilter' => $statusfilter]);
echo $OUTPUT->paging_bar($detailcount, $page, $perpage, $pagingurl);
[$historysql, $historyparams] = attendancejourneys_audit_query((int) $attendancejourneys->id, ['userid' => $userid]);
$reviewhistory = $DB->get_records_sql('SELECT arv.*, aps.name AS sessionname' . $historysql .
    ' ORDER BY arv.timecreated DESC, arv.id DESC', $historyparams, 0, 50);
if (!$islight && $reviewhistory) {
    $historyactorids = array_values(array_unique(array_filter(array_map(
        static fn($review): int => (int) $review->actorid,
        $reviewhistory
    ))));
    $historyactors = $historyactorids ? $DB->get_records_list(
        'user',
        'id',
        $historyactorids,
        '',
        'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
    ) : [];
    echo $OUTPUT->heading(attendancejourneys_get_string('reviewhistory', 'attendancejourneys'), 3, 'mt-4');
    echo html_writer::tag('p', attendancejourneys_get_string('reviewhistoryhelp', 'attendancejourneys'), ['class' => 'text-muted']);
    $historytable = new html_table();
    $historytable->head = [get_string('date'), attendancejourneys_get_string('sessionname', 'attendancejourneys'),
        get_string('action'),
    attendancejourneys_get_string(
        'performedby',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        'reviewdetails',
        'attendancejourneys'
    )];
    foreach ($reviewhistory as $review) {
        $historyactor = isset($historyactors[$review->actorid]) ? fullname($historyactors[$review->actorid]) :
            attendancejourneys_get_string('attendanceunknownrecorder', 'attendancejourneys');
        $historytable->data[] = [
            userdate($review->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            format_string($review->sessionname),
            attendancejourneys_get_string('reviewaction' . $review->action, 'attendancejourneys'),
            $historyactor,
            trim((string) $review->note) !== '' ? s(attendancejourneys_format_review_note($review)) : '—',
        ];
    }
    echo attendancejourneys_responsive_table($historytable, attendancejourneys_get_string('reviewhistory', 'attendancejourneys'));
}
if ($canviewreports) {
    $exporturl = new moodle_url('/mod/attendancejourneys/export.php');
    echo html_writer::div($OUTPUT->download_dataformat_selector(
        attendancejourneys_get_string('exportindividualreport', 'attendancejourneys'),
        $exporturl,
        'dataformat',
        ['id' => $cm->id, 'scope' => 'individual', 'userid' => $userid,
            'journeyid' => !empty($attendancejourneys->journeygrading) ? $requestedjourneyid : 0]
    ), 'attendancejourneys-export mt-3');
}
echo html_writer::end_div();

if (!$islight && has_capability('mod/attendancejourneys:resetuserdata', $context)) {
    echo html_writer::start_div('alert alert-danger mt-4');
    echo html_writer::tag('h3', attendancejourneys_get_string('advancedadministration', 'attendancejourneys'), ['class' => 'h5']);
    echo html_writer::div(attendancejourneys_get_string('resetuserdatahelp', 'attendancejourneys'), 'mb-3');
    echo html_writer::link(
        new moodle_url($url, ['action' => 'reset']),
        attendancejourneys_get_string('resetuserdata', 'attendancejourneys'),
        ['class' => 'btn btn-danger']
    );
    echo html_writer::end_div();
}

$returnparams = ['id' => $cm->id];
if ($groupfilter >= 0) {
    $returnparams['groupfilter'] = $groupfilter;
}
$returnurl = new moodle_url(
    $canviewreports ? '/mod/attendancejourneys/report.php' : '/mod/attendancejourneys/view.php',
    $returnparams
);
$returnlabel = attendancejourneys_get_string($canviewreports ? 'attendancereport' : 'navigationhome', 'attendancejourneys');
echo html_writer::div(html_writer::link($returnurl, $returnlabel), 'mt-4');
echo html_writer::end_div();
echo $OUTPUT->footer();
