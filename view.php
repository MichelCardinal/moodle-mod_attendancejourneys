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
 * Main page for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = optional_param('id', 0, PARAM_INT);
$a = optional_param('a', 0, PARAM_INT);
$requestedjourneyid = optional_param('journeyid', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    $attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $a], '*', MUST_EXIST);
    $course = get_course($attendancejourneys->course);
    $cm = get_coursemodule_from_instance('attendancejourneys', $attendancejourneys->id, $course->id, false, MUST_EXIST);
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:view', $context);

$PAGE->set_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($attendancejourneys->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$event = \mod_attendancejourneys\event\course_module_viewed::create([
    'objectid' => $attendancejourneys->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('attendancejourneys', $attendancejourneys);
$event->trigger();

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'home');

$canmanagesessions = has_capability('mod/attendancejourneys:managesessions', $context);
$canmanagejourneys = has_capability('mod/attendancejourneys:managejourneys', $context);
$cantakeattendance = has_capability('mod/attendancejourneys:takeattendance', $context);
$canviewreports = has_capability('mod/attendancejourneys:viewreports', $context);
$islight = attendancejourneys_is_light_mode($attendancejourneys);
$hasstaffaccess = $canmanagesessions || $canmanagejourneys || $cantakeattendance || $canviewreports;
$sessions = $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $attendancejourneys->id], 'sessiondate ASC');
if ($hasstaffaccess) {
    $sessions = array_filter(
        $sessions,
        fn($session) => attendancejourneys_session_is_visible($cm, $context, $session)
    );
    $participants = attendancejourneys_get_visible_participants(
        $context,
        $cm,
        'u.id,u.firstname,u.lastname,u.email,u.picture,u.imagealt',
        'u.lastname ASC, u.firstname ASC'
    );
} else {
    $sessions = array_filter(
        $sessions,
        fn($session) => attendancejourneys_session_applies_to_user($session, (int) $USER->id)
    );
    $participants = has_capability('mod/attendancejourneys:canbelisted', $context) ? [$USER->id => $USER] : [];
}
$pendingapprovalbysession = [];
$correctionbysession = [];
if ($hasstaffaccess && $sessions) {
    [$sessionidsql, $sessionidparams] = $DB->get_in_or_equal(array_keys($sessions), SQL_PARAMS_NAMED, 'approvalsession');
    $pendingapprovalrecords = $DB->get_records_sql(
        "SELECT r.sessionid, COUNT(r.id) AS pendingcount
           FROM {attendancejourneys_records} r
          WHERE r.sessionid $sessionidsql
            AND r.takenby = r.userid
            AND r.approved = 0 AND r.changesrequested = 0
       GROUP BY r.sessionid",
        $sessionidparams
    );
    foreach ($pendingapprovalrecords as $pendingrecord) {
        $pendingapprovalbysession[(int) $pendingrecord->sessionid] = (int) $pendingrecord->pendingcount;
    }
    $correctionrecords = $DB->get_records_sql(
        "SELECT r.sessionid, COUNT(r.id) AS correctioncount FROM {attendancejourneys_records} r
          WHERE r.sessionid $sessionidsql AND r.takenby = r.userid AND r.changesrequested = 1
       GROUP BY r.sessionid",
        $sessionidparams
    );
    foreach ($correctionrecords as $correctionrecord) {
        $correctionbysession[(int) $correctionrecord->sessionid] = (int) $correctionrecord->correctioncount;
    }
}
$pendingapprovalcount = array_sum($pendingapprovalbysession);
$correctioncount = array_sum($correctionbysession);
$completedsessions = 0;
$sessionprogress = [];
$sessionsrequiringattention = [];
$upcomingsessions = [];
$now = time();
$todaystart = usergetmidnight($now);
$tomorrowstart = $todaystart + DAYSECS;
foreach ($sessions as $session) {
    if (!empty($session->cancelled)) {
        continue;
    }
    $progress = attendancejourneys_session_progress($context, $session);
    $sessionprogress[$session->id] = $progress;
    if ($progress['total'] > 0 && $progress['recorded'] >= $progress['total']) {
        $completedsessions++;
    }
    if (
        ($progress['total'] === 0 || $progress['recorded'] < $progress['total'] ||
            !empty($pendingapprovalbysession[$session->id]) || !empty($correctionbysession[$session->id])) &&
            $session->sessiondate < $tomorrowstart
    ) {
        $sessionsrequiringattention[] = $session;
    }
    if ($session->sessiondate >= $todaystart) {
        $upcomingsessions[] = $session;
    }
}

$activejourneys = $hasstaffaccess ? $DB->count_records('attendancejourneys_journeys', [
    'attendancejourneysid' => $attendancejourneys->id, 'active' => 1,
]) : 0;
$calculationlabel = $attendancejourneys->percentageenabled
    ? attendancejourneys_get_string(
        'thresholdvalue',
        'attendancejourneys',
        format_float($attendancejourneys->passinggrade, 0) . '%'
    )
    : attendancejourneys_get_string('disabled', 'attendancejourneys');
if (!empty($attendancejourneys->journeygrading) && !empty($attendancejourneys->percentageenabled)) {
    $calculationlabel = get_string('journeythresholdpolicy', 'attendancejourneys');
}

echo html_writer::start_div('attendancejourneys-home-hero');
echo html_writer::start_div('attendancejourneys-home-hero-main');
echo html_writer::tag(
    'div',
    attendancejourneys_get_string('courseidentity', 'attendancejourneys'),
    ['class' => 'attendancejourneys-home-eyebrow']
);
echo html_writer::tag('h2', format_string($course->fullname), ['class' => 'attendancejourneys-home-title']);
echo html_writer::tag('div', format_string($course->shortname), ['class' => 'attendancejourneys-home-shortname']);
echo html_writer::end_div();
echo html_writer::start_div('attendancejourneys-home-policy');
echo html_writer::tag('span', attendancejourneys_get_string(
    $islight ? 'experiencemodelight' : 'experiencemodeprofessional',
    'attendancejourneys'
), ['class' => 'badge badge-secondary text-bg-secondary mb-2']);
echo html_writer::tag(
    'span',
    attendancejourneys_get_string(
        'calculation',
        'attendancejourneys'
    ),
    ['class' => 'attendancejourneys-home-policy-label']
);
echo html_writer::tag('strong', $calculationlabel, ['class' => 'attendancejourneys-home-policy-value']);
echo html_writer::end_div();
echo html_writer::end_div();

if ($hasstaffaccess) {
    echo html_writer::start_div('attendancejourneys-home-metrics');
    $metrics = [
        [count($sessions), attendancejourneys_get_string('sessions', 'attendancejourneys')],
        [count($participants), attendancejourneys_get_string('activeparticipants', 'attendancejourneys')],
        [count($sessionsrequiringattention), attendancejourneys_get_string('attendancepending', 'attendancejourneys')],
        [$pendingapprovalcount, attendancejourneys_get_string('declarationsawaitingapproval', 'attendancejourneys')],
        [$correctioncount, attendancejourneys_get_string('correctionsrequested', 'attendancejourneys')],
    ];
    if (!$islight) {
        array_splice($metrics, 2, 0, [[
            $activejourneys, attendancejourneys_get_string('activejourneys', 'attendancejourneys'),
        ]]);
    }
    foreach ($metrics as [$value, $label]) {
        echo html_writer::div(
            html_writer::tag('strong', (string) $value) . html_writer::span($label),
            'attendancejourneys-home-metric'
        );
    }
    echo html_writer::end_div();

    echo html_writer::start_div('attendancejourneys-home-layout');
    echo html_writer::start_tag('div', ['class' => 'attendancejourneys-home-main']);
    echo html_writer::start_div('attendancejourneys-home-section');
    echo html_writer::tag('h3', attendancejourneys_get_string('requiresattention', 'attendancejourneys'), ['class' => 'h5 mb-1']);
    echo html_writer::tag(
        'p',
        attendancejourneys_get_string(
            'requiresattention_help',
            'attendancejourneys'
        ),
        ['class' => 'text-muted mb-3']
    );
    if (!$sessionsrequiringattention) {
        echo html_writer::div(
            attendancejourneys_get_string('nothingrequiresattention', 'attendancejourneys'),
            'attendancejourneys-home-clear'
        );
    } else {
        foreach (array_slice($sessionsrequiringattention, 0, 4) as $session) {
            $progress = $sessionprogress[$session->id];
            $takeurl = new moodle_url('/mod/attendancejourneys/take.php', ['id' => $cm->id, 'sessionid' => $session->id]);
            echo html_writer::start_div('attendancejourneys-home-attention-item');
            echo html_writer::div(html_writer::tag('strong', format_string($session->name)) .
                html_writer::span(
                    userdate($session->sessiondate, get_string('strftimedatetimeshort', 'langconfig')),
                    'd-block text-muted small'
                ), 'attendancejourneys-home-item-main');
            echo html_writer::span($progress['recorded'] . ' / ' . $progress['total'], 'badge badge-warning text-bg-warning');
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
            if ($cantakeattendance) {
                echo html_writer::link(
                    $takeurl,
                    attendancejourneys_get_string('completeattendance', 'attendancejourneys'),
                    ['class' => 'btn btn-sm btn-outline-primary']
                );
            }
            echo html_writer::end_div();
        }
    }
    echo html_writer::end_div();

    echo html_writer::start_div('attendancejourneys-home-section');
    echo html_writer::tag('h3', attendancejourneys_get_string('nextsessions', 'attendancejourneys'), ['class' => 'h5 mb-1']);
    echo html_writer::tag(
        'p',
        attendancejourneys_get_string('nextsessions_help', 'attendancejourneys'),
        ['class' => 'text-muted mb-3']
    );
    if (!$upcomingsessions) {
        echo html_writer::div(
            attendancejourneys_get_string('noupcomingsessions', 'attendancejourneys'),
            'attendancejourneys-home-clear'
        );
    } else {
        foreach (array_slice($upcomingsessions, 0, 5) as $session) {
            $progress = $sessionprogress[$session->id];
            $takeurl = new moodle_url('/mod/attendancejourneys/take.php', ['id' => $cm->id, 'sessionid' => $session->id]);
            echo html_writer::start_div('attendancejourneys-home-session-row');
            echo html_writer::div(
                html_writer::tag(
                    'span',
                    userdate($session->sessiondate, '%d'),
                    ['class' => 'attendancejourneys-home-date-day']
                ) . html_writer::tag(
                    'span',
                    userdate($session->sessiondate, '%b'),
                    ['class' => 'attendancejourneys-home-date-month']
                ),
                'attendancejourneys-home-date'
            );
            $sessiondetails = html_writer::tag('strong', format_string($session->name)) .
                html_writer::span(
                    userdate($session->sessiondate, get_string('strftimetime', 'langconfig')) .
                    ' · ' . attendancejourneys_get_string('minutesvalue', 'attendancejourneys', $session->duration),
                    'd-block text-muted small'
                );
            if (!$islight) {
                $sessiondetails .= html_writer::div(attendancejourneys_session_delivery_details($session), 'mt-1') .
                    html_writer::div(attendancejourneys_session_audience_badge($session), 'mt-1');
            }
            echo html_writer::div($sessiondetails, 'attendancejourneys-home-item-main');
            echo html_writer::span(
                attendancejourneys_get_string('recordedparticipants', 'attendancejourneys', (object) $progress),
                'text-muted small'
            );
            if ($cantakeattendance) {
                echo html_writer::link(
                    $takeurl,
                    attendancejourneys_get_string('takeattendance', 'attendancejourneys'),
                    ['class' => 'btn btn-sm btn-outline-primary']
                );
            }
            echo html_writer::end_div();
        }
    }
    echo html_writer::end_div();
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('aside', ['class' => 'attendancejourneys-home-aside']);
    echo html_writer::start_div('attendancejourneys-home-action-card');
    echo html_writer::tag('h3', attendancejourneys_get_string('quickactions', 'attendancejourneys'), ['class' => 'h5 mb-3']);
    echo html_writer::start_div('attendancejourneys-home-actions');
    if ($canmanagesessions) {
        echo html_writer::link(
            new moodle_url('/mod/attendancejourneys/sessions.php', ['id' => $cm->id, 'action' => 'add']),
            attendancejourneys_get_string('addsession', 'attendancejourneys'),
            ['class' => 'btn btn-primary btn-block']
        );
        if (!$islight) {
            echo html_writer::link(
                new moodle_url(
                    '/mod/attendancejourneys/sessions.php',
                    ['id' => $cm->id, 'action' => 'series']
                ),
                attendancejourneys_get_string('addsessionseries', 'attendancejourneys'),
                ['class' => 'btn btn-outline-secondary btn-block']
            );
        }
    }
    if (!$islight && $canmanagejourneys) {
        echo html_writer::link(
            new moodle_url('/mod/attendancejourneys/journeys.php', ['id' => $cm->id]),
            attendancejourneys_get_string('managejourneys', 'attendancejourneys'),
            ['class' => 'btn btn-outline-secondary btn-block']
        );
    }
    if ($sessions && $cantakeattendance) {
        echo html_writer::link(
            new moodle_url('/mod/attendancejourneys/attendance.php', ['id' => $cm->id]),
            attendancejourneys_get_string('takeattendance', 'attendancejourneys'),
            ['class' => 'btn btn-outline-secondary btn-block']
        );
    }
    if ($sessions && $canviewreports) {
        echo html_writer::link(
            new moodle_url('/mod/attendancejourneys/report.php', ['id' => $cm->id]),
            attendancejourneys_get_string('viewreport', 'attendancejourneys'),
            ['class' => 'btn btn-outline-secondary btn-block']
        );
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    $expectedsessioncount = count(array_filter($sessions, static fn($session) => empty($session->cancelled)));
    $courseprogress = $expectedsessioncount > 0 ? ($completedsessions / $expectedsessioncount) * 100 : 0;
    echo html_writer::start_div('attendancejourneys-home-progress-card');
    echo html_writer::tag('h3', attendancejourneys_get_string('courseprogress', 'attendancejourneys'), ['class' => 'h6 mb-2']);
    echo html_writer::div(attendancejourneys_get_string('sessionscompleted', 'attendancejourneys', (object) [
        'completed' => $completedsessions, 'total' => $expectedsessioncount,
    ]), 'small mb-2');
    echo html_writer::div(html_writer::div('', 'progress-bar', [
        'role' => 'progressbar', 'style' => 'width: ' . format_float($courseprogress, 0) . '%',
        'aria-valuenow' => format_float($courseprogress, 0), 'aria-valuemin' => '0', 'aria-valuemax' => '100',
        'aria-label' => attendancejourneys_get_string('courseprogress', 'attendancejourneys'),
    ]), 'progress');
    echo html_writer::end_div();
    echo html_writer::end_tag('aside');
    echo html_writer::end_div();
} else {
    if (!has_capability('mod/attendancejourneys:canbelisted', $context)) {
        echo html_writer::div(attendancejourneys_get_string('studentoverview', 'attendancejourneys'), 'alert alert-info mt-4');
    } else {
        if (!empty($attendancejourneys->journeygrading)) {
            $myjourneys = attendancejourneys_get_user_report_journeys($context, (int) $attendancejourneys->id, (int) $USER->id);
            if ($requestedjourneyid && !isset($myjourneys[$requestedjourneyid])) {
                throw new moodle_exception('invalidparameter');
            }
            if (!$requestedjourneyid && count($myjourneys) === 1) {
                $requestedjourneyid = (int) array_key_first($myjourneys);
            }
            if (!$requestedjourneyid) {
                echo $OUTPUT->heading(get_string('selectreportjourney', 'attendancejourneys'), 3);
                $table = new html_table();
                $table->head = [get_string('journey', 'attendancejourneys'), get_string('passinggrade', 'attendancejourneys'),
                    get_string('percentage', 'attendancejourneys'), get_string('result', 'attendancejourneys')];
                foreach ($myjourneys as $myjourney) {
                    $final = attendancejourneys_get_active_closure(
                        (int) $attendancejourneys->id,
                        (int) $USER->id,
                        (int) $myjourney->id
                    );
                    $calculation = attendancejourneys_calculate_user_attendance(
                        $attendancejourneys,
                        (int) $USER->id,
                        (int) $myjourney->id
                    );
                    $percentage = $final ? $final->percentage : $calculation->percent;
                    $threshold = $final ? $final->threshold : $calculation->threshold;
                    $result = $final ? (in_array($final->result, ['passed', 'failed'], true) ?
                        $final->result : 'journeyresultpublished') :
                        (!empty($calculation->waived) ? 'obligationwaived' : 'provisional');
                    $table->data[] = [html_writer::link(
                        new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id, 'journeyid' => $myjourney->id]),
                        format_string($myjourney->name)
                    ), format_float($threshold, 2) . ' %',
                        $percentage === null || empty($attendancejourneys->percentageenabled) ? '—' :
                            format_float($percentage, 2) . ' %',
                        get_string($result, 'attendancejourneys')];
                }
                echo $myjourneys ? attendancejourneys_responsive_table($table, get_string('myattendance', 'attendancejourneys')) :
                    $OUTPUT->notification(get_string('noreportjourney', 'attendancejourneys'), 'info');
                echo $OUTPUT->footer();
                exit;
            }
            $studentjourney = $myjourneys[$requestedjourneyid];
            $studentclosure = attendancejourneys_get_active_closure(
                (int) $attendancejourneys->id,
                (int) $USER->id,
                $requestedjourneyid
            );
            if (count($myjourneys) > 1) {
                echo html_writer::div(html_writer::link(
                    new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]),
                    get_string('selectreportjourney', 'attendancejourneys')
                ), 'my-3');
            }
        } else {
            $studentjourney = attendancejourneys_get_user_active_journey(
                $context,
                (int) $attendancejourneys->id,
                (int) $USER->id
            );
            $studentclosure = $studentjourney ? attendancejourneys_get_active_closure(
                (int) $attendancejourneys->id,
                (int) $USER->id,
                (int) $studentjourney->id
            ) :
                attendancejourneys_get_active_closure((int) $attendancejourneys->id, (int) $USER->id);
        }
        $studentjourneyid = $studentclosure && !empty($studentclosure->journeyid) ?
            (int) $studentclosure->journeyid : ($studentjourney ? (int) $studentjourney->id : 0);
        $studentwaived = !empty($attendancejourneys->journeygrading) && $studentjourneyid > 0 &&
            !empty(\mod_attendancejourneys\local\individual_obligation::get($studentjourneyid, (int) $USER->id)->waived);
        if (!$islight && $studentjourneyid) {
            $studentjourneyrecord = $studentjourney ?: $DB->get_record(
                'attendancejourneys_journeys',
                ['id' => $studentjourneyid]
            );
            if ($studentjourneyrecord) {
                echo html_writer::start_div('attendancejourneys-journey-state ' .
                    ($studentclosure ? 'attendancejourneys-journey-closed' : 'attendancejourneys-journey-open'));
                echo html_writer::tag('strong', format_string($studentjourneyrecord->name));
                if (!empty($attendancejourneys->journeygrading) && !empty($attendancejourneys->percentageenabled)) {
                    $threshold = $studentclosure ? (float) $studentclosure->threshold :
                        attendancejourneys_get_passinggrade($attendancejourneys, $studentjourneyid);
                    echo html_writer::div(get_string('thresholdvalue', 'attendancejourneys', format_float($threshold, 2) . ' %'));
                }
                echo html_writer::div(attendancejourneys_get_string(
                    $studentclosure ? 'studentjourneyfinal' : ($studentwaived ? 'obligationwaived' : 'studentjourneyprovisional'),
                    'attendancejourneys'
                ), 'small text-muted mt-1');
                echo html_writer::end_div();
            }
        }
        $sessions = array_filter(
            $sessions,
            fn($session) => (!$studentjourneyid || (int) $session->journeyid === $studentjourneyid) &&
            attendancejourneys_session_applies_to_user($session, (int) $USER->id)
        );
        $studentrecords = [];
        if ($sessions) {
            [$studentsessionsql, $studentrecordparams] = $DB->get_in_or_equal(
                array_keys($sessions),
                SQL_PARAMS_NAMED,
                'studentsession'
            );
            $studentrecordparams['studentuserid'] = $USER->id;
            foreach (
                $DB->get_records_select(
                    'attendancejourneys_records',
                    "userid = :studentuserid AND sessionid $studentsessionsql",
                    $studentrecordparams
                ) as $record
            ) {
                $studentrecords[$record->sessionid] = $record;
            }
        }
        $studentcalculation = \mod_attendancejourneys\local\calculator::aggregate(
            $attendancejourneys,
            \mod_attendancejourneys\local\individual_obligation::required_sessions($attendancejourneys, (int) $USER->id, $sessions),
            $studentrecords,
            (int) $USER->id
        );
        $presentminutes = (int) $studentcalculation->presentminutes;
        $possibleminutes = (int) $studentcalculation->possibleminutes;
        $studentpercent = $studentcalculation->percent;
        $studentsessionsrecorded = (int) $studentcalculation->recordedsessions;
        $studentsessionsapplicable = (int) $studentcalculation->applicablesessions;
        if ($studentclosure) {
            $presentminutes = (int) $studentclosure->presentminutes;
            $possibleminutes = (int) $studentclosure->possibleminutes;
            $studentpercent = $studentclosure->percentage === null ? null : (float) $studentclosure->percentage;
            $studentsessionsrecorded = (int) $studentclosure->recordedsessions;
            $studentsessionsapplicable = (int) $studentclosure->applicablesessions;
        }

        echo $OUTPUT->heading(attendancejourneys_get_string('myattendance', 'attendancejourneys'), 3, 'mt-4');
        echo html_writer::start_div('attendancejourneys-summary-grid attendancejourneys-student-summary');
        echo html_writer::div(
            html_writer::tag(
                'strong',
                $studentsessionsrecorded . ' / ' . $studentsessionsapplicable,
                ['class' => 'attendancejourneys-stat-value']
            ) .
            html_writer::span(attendancejourneys_get_string('sessionsrecorded', 'attendancejourneys')),
            'attendancejourneys-stat'
        );
        echo html_writer::div(
            html_writer::tag(
                'strong',
                $presentminutes . ' / ' . $possibleminutes . ' min',
                ['class' => 'attendancejourneys-stat-value']
            ) . html_writer::span(attendancejourneys_get_string('presentminutes', 'attendancejourneys')),
            'attendancejourneys-stat'
        );
        $studentpercentlabel = $studentpercent === null || !$attendancejourneys->percentageenabled
            ? '—' : format_float($studentpercent, 1) . ' %';
        echo html_writer::div(
            html_writer::tag('strong', $studentpercentlabel, ['class' => 'attendancejourneys-stat-value']) .
            html_writer::span(attendancejourneys_get_string('percentage', 'attendancejourneys')),
            'attendancejourneys-stat'
        );
        if (!$studentclosure) {
            $studentresult = html_writer::span(
                attendancejourneys_get_string(
                    $studentwaived ? 'obligationwaived' : 'provisional',
                    'attendancejourneys'
                ),
                'badge badge-secondary text-bg-secondary'
            );
        } else if ($studentpercent === null || !$attendancejourneys->percentageenabled) {
            $studentresult = '—';
        } else {
            $studentpassed = $studentclosure->result === 'passed';
            $studentresult = html_writer::span(
                $studentpassed ? attendancejourneys_get_string(
                    'passed',
                    'attendancejourneys'
                ) : attendancejourneys_get_string(
                    'failed',
                    'attendancejourneys'
                ),
                $studentpassed ? 'badge badge-success text-bg-success' : 'badge badge-danger text-bg-danger'
            );
        }
        echo html_writer::div(
            html_writer::div(
                $studentresult,
                'attendancejourneys-stat-value'
            ) .
            html_writer::span(attendancejourneys_get_string(
                'result',
                'attendancejourneys'
            )),
            'attendancejourneys-stat'
        );
        echo html_writer::end_div();
        if (!$studentclosure && !empty($studentcalculation->pendingapprovals)) {
            echo html_writer::div(attendancejourneys_get_string(
                'officialcalculationnotice',
                'attendancejourneys',
                $studentcalculation->pendingapprovals
            ), 'alert alert-info mt-3');
        }
        echo html_writer::link(
            new moodle_url('/mod/attendancejourneys/individual.php', [
            'id' => $cm->id, 'userid' => $USER->id,
            ]),
            attendancejourneys_get_string('viewindividualreport', 'attendancejourneys'),
            ['class' => 'btn btn-outline-secondary mt-3']
        );

        $studentclosures = $DB->get_records('attendancejourneys_closures', [
            'attendancejourneysid' => $attendancejourneys->id, 'userid' => $USER->id,
        ], 'timeclosed DESC, id DESC');
        if (!empty($attendancejourneys->journeygrading)) {
            $studentclosures = array_filter(
                $studentclosures,
                static fn($item) => (int) $item->journeyid === $requestedjourneyid
            );
        }
        if ($studentclosures) {
            echo $OUTPUT->heading(attendancejourneys_get_string('myjourneyhistory', 'attendancejourneys'), 3, 'mt-4');
            $historytable = new html_table();
            $historytable->head = [attendancejourneys_get_string(
                'journey',
                'attendancejourneys'
            ),
            attendancejourneys_get_string(
                'percentage',
                'attendancejourneys'
            ),

                attendancejourneys_get_string('result', 'attendancejourneys'),
                    attendancejourneys_get_string('status', 'attendancejourneys')];
            $published = attendancejourneys_get_active_closure((int) $attendancejourneys->id, (int) $USER->id);
            foreach ($studentclosures as $historyclosure) {
                $historyjourney = !empty($historyclosure->journeyid) ? $DB->get_record(
                    'attendancejourneys_journeys',
                    ['id' => $historyclosure->journeyid]
                ) : null;
                $historyname = $historyjourney ? format_string($historyjourney->name) :
                    attendancejourneys_get_string('activitywidejourney', 'attendancejourneys');
                $historyresult = $historyclosure->result === 'passed' ? attendancejourneys_get_string(
                    'passed',
                    'attendancejourneys'
                ) :
                    ($historyclosure->result === 'failed' ? attendancejourneys_get_string('failed', 'attendancejourneys') : '—');
                $historystatus = !empty($historyclosure->active) && $published &&
                    (int) $published->id === (int) $historyclosure->id ? attendancejourneys_get_string(
                        'journeyresultpublished',
                        'attendancejourneys'
                    ) :
                    (!empty($historyclosure->active) ? attendancejourneys_get_string(
                        'journeypreviousresult',
                        'attendancejourneys'
                    ) :
                        attendancejourneys_get_string('journeyresultreopened', 'attendancejourneys'));
                $historytable->data[] = [$historyname,
                    $historyclosure->percentage === null ? '—' : format_float($historyclosure->percentage, 1) . ' %',
                    $historyresult, $historystatus];
            }
            echo html_writer::table($historytable);
        }

        if (
            $attendancejourneys->studentselfrecord &&
                has_capability('mod/attendancejourneys:selfrecord', $context)
        ) {
            $selfeligiblesessions = array_filter(
                $sessions,
                fn($session) => attendancejourneys_user_can_self_record_session(
                    $context,
                    $attendancejourneys,
                    $session,
                    (int) $USER->id
                )
            );
            $selfpendingcount = count(array_filter(
                $selfeligiblesessions,
                fn($session) => empty($studentrecords[$session->id])
            ));
            if ($selfpendingcount > 0) {
                echo html_writer::start_div('attendancejourneys-selfbulk-callout');
                echo html_writer::start_div('attendancejourneys-selfbulk-callout-copy');
                echo html_writer::tag(
                    'h3',
                    attendancejourneys_get_string('myattendancestocomplete', 'attendancejourneys'),
                    ['class' => 'h5 mb-1']
                );
                echo html_writer::div(
                    attendancejourneys_get_string('selfbulkpending', 'attendancejourneys', $selfpendingcount),
                    'text-muted'
                );
                echo html_writer::end_div();
                echo html_writer::link(
                    new moodle_url('/mod/attendancejourneys/selfbulk.php', ['id' => $cm->id]),
                    attendancejourneys_get_string('selfbulkopen', 'attendancejourneys'),
                    ['class' => 'btn btn-primary']
                );
                echo html_writer::end_div();
            }
        }

        echo $OUTPUT->heading(attendancejourneys_get_string('myrecentattendance', 'attendancejourneys'), 3, 'mt-4');
        if (!$sessions) {
            echo html_writer::div(attendancejourneys_get_string('nosessions', 'attendancejourneys'), 'alert alert-info');
        } else {
            $table = new html_table();
            $table->attributes['class'] = 'generaltable attendancejourneys-student-table';
            $table->head = [
                attendancejourneys_get_string('sessions', 'attendancejourneys'),
                attendancejourneys_get_string('status', 'attendancejourneys'),
                attendancejourneys_get_string('presentminutes', 'attendancejourneys'),
                attendancejourneys_get_string('remarks', 'attendancejourneys'),
            ];
            foreach (array_slice(array_reverse(array_values($sessions)), 0, 10) as $session) {
                $record = $studentrecords[$session->id] ?? null;
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
                    $minutes = attendancejourneys_get_string('minutesfraction', 'attendancejourneys', (object) [
                        'present' => $present,
                        'possible' => $possible,
                    ]);
                    $remarks = trim((string) $record->remarks) !== '' ? s($record->remarks) : '—';
                }
                $sessionname = html_writer::tag('strong', format_string($session->name)) .
                    html_writer::span(
                        userdate($session->sessiondate, get_string('strftimedatetimeshort', 'langconfig')),
                        'd-block text-muted small'
                    );
                if (
                    $attendancejourneys->studentselfrecord &&
                        has_capability('mod/attendancejourneys:selfrecord', $context) &&
                        (!$record || (empty($record->approved) && (int) $record->takenby === (int) $USER->id)) &&
                        attendancejourneys_user_can_self_record_session(
                            $context,
                            $attendancejourneys,
                            $session,
                            (int) $USER->id
                        )
                ) {
                    $selfurl = new moodle_url('/mod/attendancejourneys/self.php', [
                        'id' => $cm->id,
                        'sessionid' => $session->id,
                    ]);
                    $sessionname .= html_writer::link(
                        $selfurl,
                        $record ? attendancejourneys_get_string(
                            'modifyattendance',
                            'attendancejourneys'
                        ) : attendancejourneys_get_string(
                            'savemyattendance',
                            'attendancejourneys'
                        ),
                        ['class' => 'd-block small mt-1']
                    );
                    if ($record) {
                        $sessionname .= html_writer::span(
                            attendancejourneys_get_string(!empty($record->changesrequested) ?
                            'selfrecordchangesrequestedshort' : 'selfrecordpendingapprovalshort', 'attendancejourneys'),
                            'd-block small text-warning mt-1'
                        );
                    }
                } else if ($record && (!empty($record->approved) || (int) $record->takenby !== (int) $USER->id)) {
                    $lockedlabel = !empty($record->approved) && (int) $record->takenby === (int) $USER->id
                        ? attendancejourneys_get_string('selfrecordapprovedshort', 'attendancejourneys')
                        : attendancejourneys_get_string('selfrecordlockedshort', 'attendancejourneys');
                    $sessionname .= html_writer::span(
                        $lockedlabel,
                        'd-block small text-muted mt-1'
                    );
                }
                if (!empty($session->cancelled)) {
                    $status = html_writer::span(
                        attendancejourneys_get_string('sessioncancelled', 'attendancejourneys'),
                        'badge badge-secondary text-bg-secondary'
                    );
                    $minutes = '—';
                }
                $table->data[] = [
                    $sessionname,
                    $status,
                    $minutes,
                    $remarks,
                ];
            }
            echo html_writer::table($table);
        }
    }
}

echo $OUTPUT->footer();
