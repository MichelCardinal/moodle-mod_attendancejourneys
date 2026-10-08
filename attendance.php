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
 * Session selector for taking attendance.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$groupfilter = optional_param('groupfilter', -1, PARAM_INT);
$journeyfilter = optional_param('journeyfilter', -1, PARAM_INT);
$showcompleted = optional_param('showcompleted', 0, PARAM_BOOL);
$workfilter = optional_param('workfilter', 'all', PARAM_ALPHA);
$query = trim(optional_param('q', '', PARAM_TEXT));
$period = optional_param('period', 'all', PARAM_ALPHA);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 25;
if (!in_array($period, ['all', 'upcoming', 'past'], true)) {
    $period = 'all';
}
if (!in_array($workfilter, ['all', 'incomplete', 'approval', 'correction'], true)) {
    $workfilter = 'all';
}
$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:takeattendance', $context);
$islight = attendancejourneys_is_light_mode($attendancejourneys);
if ($islight) {
    // Light mode deliberately presents one unified session catalogue.
    $groupfilter = -1;
    $journeyfilter = -1;
    $showcompleted = 1;
}

$url = new moodle_url('/mod/attendancejourneys/attendance.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(attendancejourneys_get_string('navigationattendance', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$sessions = $DB->get_records(
    'attendancejourneys_sessions',
    ['attendancejourneysid' => $attendancejourneys->id],
    'sessiondate DESC'
);
$journeys = $DB->get_records('attendancejourneys_journeys', ['attendancejourneysid' => $attendancejourneys->id], 'name ASC');
$sessions = array_filter(
    $sessions,
    fn($session) => attendancejourneys_session_is_visible($cm, $context, $session)
);
$journeys = array_filter(
    $journeys,
    fn($journey) => attendancejourneys_session_is_visible($cm, $context, $journey)
);
$journeyoptions = [-1 => attendancejourneys_get_string('allcurrentjourneys', 'attendancejourneys'),
    0 => attendancejourneys_get_string('independentsessions', 'attendancejourneys')];
foreach ($journeys as $journey) {
    $journeyoptions[(int) $journey->id] = format_string($journey->name) .
        (empty($journey->active) ? ' — ' . attendancejourneys_get_string('journeycompletedstatus', 'attendancejourneys') : '');
}
if (!array_key_exists($journeyfilter, $journeyoptions)) {
    $journeyfilter = -1;
}
$sessions = array_filter($sessions, static function ($session) use ($journeys, $journeyfilter, $showcompleted) {
    if ($journeyfilter >= 0 && (int) $session->journeyid !== $journeyfilter) {
        return false;
    }
    if (
        !$showcompleted && !empty($session->journeyid) && isset($journeys[$session->journeyid]) &&
            empty($journeys[$session->journeyid]->active)
    ) {
        return false;
    }
    return true;
});
$allowedfilters = attendancejourneys_group_filter_options($cm);
if (!array_key_exists($groupfilter, $allowedfilters)) {
    $groupfilter = -1;
}
$sessions = attendancejourneys_filter_sessions($sessions, $groupfilter);
$sessionprogress = [];
$pendingapprovalbysession = [];
$correctionbysession = [];
if ($sessions) {
    [$sessionidsql, $sessionidparams] = $DB->get_in_or_equal(array_keys($sessions), SQL_PARAMS_NAMED, 'worksession');
    foreach (
        $DB->get_records_sql(
            "SELECT r.sessionid, COUNT(r.id) AS pendingcount
               FROM {attendancejourneys_records} r
              WHERE r.sessionid $sessionidsql AND r.takenby = r.userid AND r.approved = 0
                AND r.changesrequested = 0
           GROUP BY r.sessionid",
            $sessionidparams
        ) as $pendingrecord
    ) {
        $pendingapprovalbysession[(int) $pendingrecord->sessionid] = (int) $pendingrecord->pendingcount;
    }
    foreach (
        $DB->get_records_sql(
            "SELECT r.sessionid, COUNT(r.id) AS correctioncount FROM {attendancejourneys_records} r
              WHERE r.sessionid $sessionidsql AND r.takenby = r.userid AND r.changesrequested = 1
           GROUP BY r.sessionid",
            $sessionidparams
        ) as $correctionrecord
    ) {
        $correctionbysession[(int) $correctionrecord->sessionid] = (int) $correctionrecord->correctioncount;
    }
}
$incompletecount = 0;
foreach ($sessions as $session) {
    $sessionprogress[$session->id] = attendancejourneys_session_progress($context, $session);
    $progress = $sessionprogress[$session->id];
    if (!empty($session->cancelled)) {
        unset($pendingapprovalbysession[$session->id], $correctionbysession[$session->id]);
        continue;
    }
    if ($progress['total'] > 0 && $progress['recorded'] < $progress['total']) {
        $incompletecount++;
    }
}
$pendingapprovalcount = array_sum($pendingapprovalbysession);
$correctioncount = array_sum($correctionbysession);
$scopedsessioncount = count($sessions);
$sessions = attendancejourneys_filter_session_catalog($sessions, $query, $period);
$sessions = array_filter($sessions, static function ($session) use (
    $workfilter,
    $sessionprogress,
    $pendingapprovalbysession,
    $correctionbysession
): bool {
    if (!empty($session->cancelled)) {
        return $workfilter === 'all';
    }
    if ($workfilter === 'approval') {
        return !empty($pendingapprovalbysession[$session->id]);
    }
    if ($workfilter === 'correction') {
        return !empty($correctionbysession[$session->id]);
    }
    if ($workfilter === 'incomplete') {
        $progress = $sessionprogress[$session->id];
        return $progress['total'] > 0 && $progress['recorded'] < $progress['total'];
    }
    return true;
});
$totalfiltered = count($sessions);
$lastpage = max(0, (int) ceil($totalfiltered / $perpage) - 1);
$page = min($page, $lastpage);
$sessions = array_slice(array_values($sessions), $page * $perpage, $perpage);

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'attendance');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(
    attendancejourneys_get_string('attendanceworkflow', 'attendancejourneys'),
    'attendancejourneys-section-eyebrow'
);
echo $OUTPUT->heading(attendancejourneys_get_string('navigationattendance', 'attendancejourneys'));
echo html_writer::tag('p', attendancejourneys_get_string('selectsessiontotake', 'attendancejourneys'), ['class' => 'text-muted']);
echo html_writer::end_div();
echo html_writer::start_div('attendancejourneys-session-metrics attendancejourneys-workqueue-metrics');
foreach (
    [
    [$scopedsessioncount, attendancejourneys_get_string('sessionsinscope', 'attendancejourneys')],
    [$incompletecount, attendancejourneys_get_string('incompletesessions', 'attendancejourneys')],
    [$pendingapprovalcount, attendancejourneys_get_string('declarationsawaitingapproval', 'attendancejourneys')],
    [$correctioncount, attendancejourneys_get_string('correctionsrequested', 'attendancejourneys')],
    ] as [$value, $label]
) {
    echo html_writer::div(
        html_writer::tag('strong', (string) $value) . html_writer::span($label),
        'attendancejourneys-session-metric'
    );
}
echo html_writer::end_div();
echo html_writer::start_div('attendancejourneys-attendance-filters');
if (!$islight) {
    $groupfilterurl = new moodle_url($url, ['q' => $query, 'period' => $period,
        'journeyfilter' => $journeyfilter, 'showcompleted' => $showcompleted, 'workfilter' => $workfilter]);
    attendancejourneys_print_group_filter($groupfilterurl, $cm, $groupfilter);
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false),
        'class' => 'attendancejourneys-journey-filter d-flex flex-wrap align-items-end']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'groupfilter', 'value' => $groupfilter]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'q', 'value' => $query]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'period', 'value' => $period]);
    echo html_writer::label(
        attendancejourneys_get_string('filterattendancebyjourney', 'attendancejourneys'),
        'attendancejourneys-journey-filter',
        false,
        ['class' => 'mr-2 me-2']
    );
    echo html_writer::select(
        $journeyoptions,
        'journeyfilter',
        $journeyfilter,
        false,
        ['id' => 'attendancejourneys-journey-filter', 'class' => 'custom-select form-select mr-3 me-3']
    );
    echo html_writer::checkbox(
        'showcompleted',
        1,
        $showcompleted,
        attendancejourneys_get_string('showcompletedjourneys', 'attendancejourneys'),
        ['class' => 'mr-3 me-3']
    );
    echo html_writer::label(
        attendancejourneys_get_string('filterbyworkstate', 'attendancejourneys'),
        'attendancejourneys-work-filter',
        false,
        ['class' => 'sr-only visually-hidden']
    );
    echo html_writer::select([
        'all' => attendancejourneys_get_string('allworkstates', 'attendancejourneys'),
        'incomplete' => attendancejourneys_get_string('incompletesessions', 'attendancejourneys'),
        'approval' => attendancejourneys_get_string('declarationsawaitingapproval', 'attendancejourneys'),
        'correction' => attendancejourneys_get_string('correctionsrequested', 'attendancejourneys'),
    ], 'workfilter', $workfilter, false, ['id' => 'attendancejourneys-work-filter', 'class'
        => 'custom-select form-select mr-3 me-3']);
    echo html_writer::tag('button', get_string('apply'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}
attendancejourneys_print_session_catalog_filters($url, $groupfilter, $query, $period, [
    'workfilter' => $workfilter,
] + (!$islight ? [
    'journeyfilter' => $journeyfilter, 'showcompleted' => $showcompleted,
] : [
]));
echo html_writer::end_div();

if (!$sessions) {
    echo html_writer::start_div('attendancejourneys-empty-state');
    if ($query !== '' || $period !== 'all' || $workfilter !== 'all') {
        echo html_writer::tag('h3', attendancejourneys_get_string('nosearchsessions', 'attendancejourneys'));
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string('nosearchsessions_help', 'attendancejourneys'),
            ['class' => 'text-muted']
        );
        $clearparams = ['id' => $cm->id, 'journeyfilter' => $journeyfilter,
            'showcompleted' => $showcompleted, 'workfilter' => 'all'];
        if ($groupfilter >= 0) {
            $clearparams['groupfilter'] = $groupfilter;
        }
        echo html_writer::link(
            new moodle_url($url, $clearparams),
            attendancejourneys_get_string('clearfilters', 'attendancejourneys')
        );
    } else if ($groupfilter >= 0) {
        echo html_writer::tag('h3', attendancejourneys_get_string('nofilteredsessions', 'attendancejourneys'));
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string(
                'nofilteredsessions_help',
                'attendancejourneys'
            ),
            ['class' => 'text-muted']
        );
        $allurl = new moodle_url('/mod/attendancejourneys/attendance.php', ['id' => $cm->id, 'groupfilter' => -1]);
        echo html_writer::link(
            $allurl,
            attendancejourneys_get_string(
                'showallsessions',
                'attendancejourneys'
            ),
            ['class' => 'btn btn-secondary']
        );
    } else {
        echo html_writer::tag('h3', attendancejourneys_get_string('nosessions', 'attendancejourneys'));
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string('nosessions_help', 'attendancejourneys'),
            ['class' => 'text-muted']
        );
        if (has_capability('mod/attendancejourneys:managesessions', $context)) {
            $addurl = new moodle_url('/mod/attendancejourneys/sessions.php', ['id' => $cm->id, 'action' => 'add']);
            echo $OUTPUT->single_button($addurl, attendancejourneys_get_string('addsession', 'attendancejourneys'), 'get');
        }
    }
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('attendancejourneys-session-picker');
    foreach ($sessions as $session) {
        $progress = $sessionprogress[$session->id];
        $participantcount = $progress['total'];
        $recorded = $progress['recorded'];
        $complete = $participantcount > 0 && $recorded >= $participantcount;
        $statuskey = !empty($session->cancelled) ? 'sessioncancelled' :
            ($participantcount === 0 ? 'nocurrentparticipants' : ($complete ? 'attendancecomplete' : 'attendancetodo'));
        $takeurl = new moodle_url('/mod/attendancejourneys/take.php', ['id' => $cm->id, 'sessionid' => $session->id]);
        echo html_writer::start_div('attendancejourneys-session-picker-row');
        echo html_writer::start_div('attendancejourneys-session-picker-main');
        echo html_writer::tag('strong', format_string($session->name), ['class' => 'attendancejourneys-session-picker-name']);
        echo html_writer::tag(
            'span',
            userdate($session->sessiondate, get_string('strftimedatetime', 'langconfig')),
            ['class' => 'd-block text-muted']
        );
        echo html_writer::tag(
            'span',
            attendancejourneys_get_string('minutesvalue', 'attendancejourneys', $session->duration),
            ['class' => 'd-block small']
        );
        if (!$islight) {
            echo html_writer::div(attendancejourneys_session_delivery_details($session), 'mt-1');
            echo html_writer::div(attendancejourneys_session_audience_badge($session), 'mt-1');
            $sessionjourney = !empty($session->journeyid) && isset($journeys[$session->journeyid]) ?
                $journeys[$session->journeyid] : null;
            echo html_writer::div(html_writer::span(
                $sessionjourney ? format_string($sessionjourney->name) :
                attendancejourneys_get_string(
                    'independentsession',
                    'attendancejourneys'
                ),
                $sessionjourney ? 'badge badge-info text-bg-info' :
                'badge badge-light text-bg-light'
            ), 'mt-1');
        }
        echo html_writer::end_div();
        echo html_writer::div(
            attendancejourneys_get_string('recordedparticipants', 'attendancejourneys', (object) [
                'recorded' => $recorded,
                'total' => $participantcount,
            ]),
            'attendancejourneys-session-picker-progress'
        );
        echo html_writer::span(
            attendancejourneys_get_string($statuskey, 'attendancejourneys'),
            $complete && empty($session->cancelled) ? 'badge badge-success text-bg-success' :
                'badge badge-secondary text-bg-secondary'
        );
        if (!empty($pendingapprovalbysession[$session->id])) {
            echo html_writer::span(attendancejourneys_get_string(
                'pendingapprovalcount',
                'attendancejourneys',
                $pendingapprovalbysession[$session->id]
            ), 'badge badge-info text-bg-info');
        }
        if (!empty($correctionbysession[$session->id])) {
            echo html_writer::span(attendancejourneys_get_string(
                'correctionrequestcount',
                'attendancejourneys',
                $correctionbysession[$session->id]
            ), 'badge badge-warning text-bg-warning');
        }
        if (
            empty($session->cancelled) && ($participantcount > 0 ||
                !empty($pendingapprovalbysession[$session->id]) || !empty($correctionbysession[$session->id]))
        ) {
            echo html_writer::link(
                $takeurl,
                (!empty($pendingapprovalbysession[$session->id]) || !empty($correctionbysession[$session->id])) ?
                    attendancejourneys_get_string('reviewdeclarations', 'attendancejourneys') :
                    ($recorded ? attendancejourneys_get_string(
                        'modifyattendance',
                        'attendancejourneys'
                    ) : attendancejourneys_get_string(
                        'takeattendance',
                        'attendancejourneys'
                    )),
                ['class' => 'btn btn-primary']
            );
        }
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    $pagingurl = new moodle_url($url, [
        'groupfilter' => $groupfilter, 'journeyfilter' => $journeyfilter, 'showcompleted' => $showcompleted,
        'q' => $query, 'period' => $period, 'workfilter' => $workfilter,
    ]);
    echo $OUTPUT->paging_bar($totalfiltered, $page, $perpage, $pagingurl);
}

echo $OUTPUT->footer();
