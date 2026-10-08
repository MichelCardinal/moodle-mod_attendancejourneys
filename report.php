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
 * Collective report for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$sort = optional_param('sort', 'lastname', PARAM_ALPHA);
$direction = optional_param('direction', 'asc', PARAM_ALPHA);
$groupfilter = optional_param('groupfilter', -1, PARAM_INT);
$journeyid = optional_param('journeyid', 0, PARAM_INT);
$bulkaction = optional_param('bulkaction', '', PARAM_ALPHA);
$selectedusers = optional_param_array('selectedusers', [], PARAM_INT);
$confirmbulk = optional_param('confirmbulk', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:viewreports', $context);
// Coordinate administrative changes with attendance submissions before reading mutable state.
if (
    data_submitted() || (($bulkaction !== '')
        && optional_param('sesskey', '', PARAM_RAW) !== '' && confirm_sesskey())
) {
    require_sesskey();
    $writelock = new \mod_attendancejourneys\local\write_lock((int) $attendancejourneys->id);
    $attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
}

$islight = attendancejourneys_is_light_mode($attendancejourneys);
$canclosejourneys = !$islight && attendancejourneys_can_decide($context, 'closejourneys');
$canreopenjourneys = !$islight && attendancejourneys_can_decide($context, 'reopenjourneys');
$canmanagejourneys = $canclosejourneys || $canreopenjourneys;
if ($islight) {
    $groupfilter = -1;
    if ($bulkaction !== '') {
        redirect(
            new moodle_url('/mod/attendancejourneys/report.php', ['id' => $cm->id]),
            attendancejourneys_get_string('professionalfeaturehidden', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
}

$groupoptions = attendancejourneys_report_group_filter_options($cm);
if (!array_key_exists($groupfilter, $groupoptions)) {
    $groupfilter = -1;
}
$urlparams = ['id' => $cm->id];
if ($groupfilter >= 0) {
    $urlparams['groupfilter'] = $groupfilter;
}
$url = new moodle_url('/mod/attendancejourneys/report.php', $urlparams);
$PAGE->set_url($url);
$PAGE->set_title(attendancejourneys_get_string('attendancereport', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$reportjourney = null;
if (!empty($attendancejourneys->journeygrading)) {
    $availablejourneys = attendancejourneys_visible_journeys($context, (int) $attendancejourneys->id);
    if ($journeyid && !isset($availablejourneys[$journeyid])) {
        throw new moodle_exception('invalidparameter');
    }
    if (!$journeyid && count($availablejourneys) === 1) {
        $journeyid = (int) array_key_first($availablejourneys);
    }
    if (!$journeyid) {
        echo $OUTPUT->header();
        attendancejourneys_print_navigation($cm, $context, 'reports');
        echo $OUTPUT->heading(get_string('selectreportjourney', 'attendancejourneys'));
        if (!$availablejourneys) {
            echo $OUTPUT->notification(get_string('noreportjourney', 'attendancejourneys'), 'info');
        }
        foreach ($availablejourneys as $available) {
            echo html_writer::div(html_writer::link(
                new moodle_url($url, ['journeyid' => $available->id]),
                format_string($available->name),
                ['class' => 'btn btn-outline-primary mb-2']
            ));
        }
        echo $OUTPUT->footer();
        exit;
    }
    $reportjourney = $availablejourneys[$journeyid];
    $urlparams['journeyid'] = $journeyid;
    $url->param('journeyid', $journeyid);
    $PAGE->set_url($url);
} else {
    $journeyid = 0;
}

$participants = attendancejourneys_get_visible_participants(
    $context,
    $cm,
    'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename',
    'u.lastname ASC, u.firstname ASC'
);
$sessions = $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $attendancejourneys->id], 'sessiondate ASC');
if ($groupfilter >= 0) {
    $groupmembers = groups_get_members($groupfilter, 'u.id');
    $participants = array_intersect_key($participants, $groupmembers);
    $sessions = array_filter($sessions, static fn($session) =>
        empty($session->groupid) || (int) $session->groupid === $groupfilter);
}
if ($reportjourney) {
    $participants = array_intersect_key($participants, attendancejourneys_get_journey_participants($context, $reportjourney));
    $sessions = array_filter($sessions, static fn($session) => (int) $session->journeyid === $journeyid);
}
$recordsbyuser = [];
$journeysbyid = $DB->get_records('attendancejourneys_journeys', ['attendancejourneysid' => $attendancejourneys->id]);
if ($sessions) {
    [$insql, $params] = $DB->get_in_or_equal(array_keys($sessions), SQL_PARAMS_NAMED, 'session');
    foreach (
        $DB->get_records_select(
            'attendancejourneys_records',
            "sessionid $insql",
            $params,
            '',
            'id,sessionid,userid,status,minutesabsent,takenby,approved'
        ) as $record
    ) {
        $recordsbyuser[$record->userid][$record->sessionid] = $record;
    }
}

// Preload journey audiences, active assignments, closures and group audiences once.
// This prevents a database query for every participant/session cell in large reports.
$membersbyjourney = [];
$activejourneybyuser = [];
$members = $DB->get_records(
    'attendancejourneys_members',
    ['attendancejourneysid' => $attendancejourneys->id],
    'timeassigned DESC, id DESC'
);
foreach ($members as $member) {
    $membersbyjourney[(int) $member->journeyid][(int) $member->userid] = true;
    if (
        !empty($member->active) && !isset($activejourneybyuser[$member->userid]) &&
            isset($journeysbyid[$member->journeyid]) && !empty($journeysbyid[$member->journeyid]->active)
    ) {
        $activejourneybyuser[(int) $member->userid] = $journeysbyid[$member->journeyid];
    }
}
// Keep preloaded audience decisions consistent with the explicit journey mode.
foreach ($journeysbyid as $audiencejourney) {
    if ((int) $audiencejourney->audiencemode === 1) {
        unset($membersbyjourney[(int) $audiencejourney->id]);
    } else if ((int) $audiencejourney->audiencemode === 2) {
        $membersbyjourney[(int) $audiencejourney->id] = $membersbyjourney[(int) $audiencejourney->id] ?? [];
    }
}
$closuresbyuserjourney = [];
$latestclosurebyuser = [];
foreach (
    $DB->get_records('attendancejourneys_closures', [
        'attendancejourneysid' => $attendancejourneys->id, 'active' => 1,
    ], 'timeclosed DESC, id DESC') as $loadedclosure
) {
    $loadeduserid = (int) $loadedclosure->userid;
    $loadedjourneyid = (int) $loadedclosure->journeyid;
    $latestclosurebyuser[$loadeduserid] ??= $loadedclosure;
    $closuresbyuserjourney[$loadeduserid][$loadedjourneyid] ??= $loadedclosure;
}
$groupmembers = [];
foreach (array_unique(array_filter(array_map(static fn($session) => (int) $session->groupid, $sessions))) as $groupid) {
    $groupmembers[$groupid] = array_fill_keys(array_map(
        'intval',
        array_keys(groups_get_members($groupid, 'u.id'))
    ), true);
}

// Only users with approved equivalences need the calculator's additional lookups.
$equivalenceusers = array_fill_keys($DB->get_fieldset_sql(
    "SELECT DISTINCT userid FROM {attendancejourneys_equivalences}
      WHERE attendancejourneysid = :activityid AND status = :status",
    ['activityid' => $attendancejourneys->id, 'status' => 'approved']
), true);

$obligationpoliciesbyuser = [];
if (!empty($attendancejourneys->journeygrading)) {
    foreach ($DB->get_records('attendancejourneys_obligations', ['attendancejourneysid' => $attendancejourneys->id]) as $policy) {
        $obligationpoliciesbyuser[(int) $policy->userid][] = $policy;
    }
}
$attemptfamilies = [];
$rows = [];
foreach ($participants as $participant) {
    $participantid = (int) $participant->id;
    $activejourney = $reportjourney ?: ($activejourneybyuser[$participantid] ?? null);
    // Legacy activities without frozen memberships retain their original group-based fallback.
    if (!$members && !$reportjourney) {
        $activejourney = attendancejourneys_get_user_active_journey(
            $context,
            (int) $attendancejourneys->id,
            $participantid
        );
    }
    $closure = $activejourney ? ($closuresbyuserjourney[$participantid][(int) $activejourney->id] ?? null) :
        ($latestclosurebyuser[$participantid] ?? null);
    $targetjourneyid = $closure && !empty($closure->journeyid) ? (int) $closure->journeyid :
        ($activejourney ? (int) $activejourney->id : 0);
    $participantsessions = [];
    foreach ($sessions as $session) {
        if ($targetjourneyid && (int) $session->journeyid !== $targetjourneyid) {
            continue;
        }
        $applies = !empty($session->journeyid) && isset($membersbyjourney[(int) $session->journeyid]) ?
            isset($membersbyjourney[(int) $session->journeyid][$participantid]) :
            (empty($session->groupid) || isset($groupmembers[(int) $session->groupid][$participantid]));
        if (!$applies) {
            continue;
        }
        $participantsessions[$session->id] = $session;
    }
    $participantsessions = \mod_attendancejourneys\local\individual_obligation::required_sessions(
        $attendancejourneys,
        $participantid,
        $participantsessions,
        $obligationpoliciesbyuser[$participantid] ?? []
    );
    $calculation = \mod_attendancejourneys\local\calculator::aggregate(
        $attendancejourneys,
        $participantsessions,
        $recordsbyuser[$participantid] ?? [],
        isset($equivalenceusers[$participantid]) ? $participantid : 0
    );
    $presentminutes = $calculation->presentminutes;
    $possibleminutes = $calculation->possibleminutes;
    $markedsessions = $calculation->recordedsessions;
    $applicablesessions = $calculation->applicablesessions;
    if ($closure) {
        $presentminutes = (int) $closure->presentminutes;
        $possibleminutes = (int) $closure->possibleminutes;
        $markedsessions = (int) $closure->recordedsessions;
        $applicablesessions = (int) $closure->applicablesessions;
    }
    $waived = false;
    foreach ($obligationpoliciesbyuser[$participantid] ?? [] as $policy) {
        if ((int) $policy->journeyid === $targetjourneyid) {
            $waived = !empty($policy->waived);
        }
    }
    $physicalstatus = '';
    if (!empty($attendancejourneys->journeygrading) && $targetjourneyid) {
        $physical = $journeysbyid[$targetjourneyid];
        $rootid = (int) ($physical->rootjourneyid ?: $physical->id);
        $attemptfamilies[$rootid] ??= \mod_attendancejourneys\local\attempt_family::load($attendancejourneys, $rootid);
        $physicalstatus = \mod_attendancejourneys\local\attempt_family::result_status(
            $attemptfamilies[$rootid],
            $participantid,
            $targetjourneyid,
            $closure,
            $waived
        );
    }
    $rows[] = (object) [
        'attemptstatus' => $physicalstatus,
        'waived' => $waived,
        'participant' => $participant,
        'journeyname' => $targetjourneyid && isset($journeysbyid[$targetjourneyid]) ?
            format_string($journeysbyid[$targetjourneyid]->name) : attendancejourneys_get_string(
                'independent',
                'attendancejourneys'
            ),
        'markedsessions' => $markedsessions,
        'applicablesessions' => $applicablesessions,
        'presentminutes' => $presentminutes,
        'possibleminutes' => $possibleminutes,
        'percent' => $closure ? ($closure->percentage === null ? null : (float) $closure->percentage) :
            ($possibleminutes > 0 ? ($presentminutes / $possibleminutes) * 100 : null),
        'pendingapprovals' => $closure ? 0 : (int) $calculation->pendingapprovals,
        'closure' => $closure,
    ];
}

if (in_array($bulkaction, ['close', 'reopen'], true)) {
    require_capability('mod/attendancejourneys:managejourneys', $context);
    require_capability('mod/attendancejourneys:' . ($bulkaction === 'close' ? 'closejourneys' : 'reopenjourneys'), $context);
    require_sesskey();
    $selectedusers = array_values(array_intersect(
        array_map('intval', $selectedusers),
        array_map('intval', array_keys($participants))
    ));
    if (!$selectedusers) {
        redirect(
            $url,
            attendancejourneys_get_string('bulkselectparticipants', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    $eligibleusers = [];
    $pendingblocked = 0;
    foreach ($selectedusers as $selecteduserid) {
        $selectedjourney = $reportjourney ?: attendancejourneys_get_user_active_journey(
            $context,
            (int) $attendancejourneys->id,
            $selecteduserid
        );
        $hasclosure = ($selectedjourney ? attendancejourneys_get_active_closure(
            (int) $attendancejourneys->id,
            $selecteduserid,
            (int) $selectedjourney->id
        ) :
            attendancejourneys_get_active_closure((int) $attendancejourneys->id, $selecteduserid)) !== null;
        if ($bulkaction === 'close' && !$hasclosure) {
            $calculation = attendancejourneys_calculate_user_attendance($attendancejourneys, $selecteduserid, $journeyid);
            if (attendancejourneys_closure_blocker($calculation)) {
                $pendingblocked++;
                continue;
            }
            $eligibleusers[] = $selecteduserid;
        } else if ($bulkaction === 'reopen' && $hasclosure) {
            $eligibleusers[] = $selecteduserid;
        }
    }
    if ($pendingblocked) {
        redirect(
            $url,
            attendancejourneys_get_string('bulkclosureincomplete', 'attendancejourneys', $pendingblocked),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    if (!$eligibleusers) {
        redirect(
            $url,
            attendancejourneys_get_string('bulknothingtodo', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }

    if (!$confirmbulk) {
        echo $OUTPUT->header();
        attendancejourneys_print_navigation($cm, $context, 'reports');
        echo $OUTPUT->heading($bulkaction === 'close' ? attendancejourneys_get_string('bulkclosejourneys', 'attendancejourneys') :
            attendancejourneys_get_string('bulkreopenjourneys', 'attendancejourneys'));
        echo $OUTPUT->notification(attendancejourneys_get_string('bulkclosureconfirmhelp', 'attendancejourneys'), 'warning');
        $preview = new html_table();
        $preview->head = [attendancejourneys_get_string(
            'participant',
            'attendancejourneys'
        ),
        attendancejourneys_get_string(
            'markedsessions',
            'attendancejourneys'
        ),

            attendancejourneys_get_string(
                'percentage',
                'attendancejourneys'
            ),
        attendancejourneys_get_string(
            'journeystatus',
            'attendancejourneys'
        )];
        foreach ($eligibleusers as $selecteduserid) {
            $calculation = attendancejourneys_calculate_user_attendance($attendancejourneys, $selecteduserid, $journeyid);
            $preview->data[] = [
                fullname($participants[$selecteduserid]),
                $calculation->recordedsessions . ' / ' . $calculation->applicablesessions,
                $calculation->percent === null ? '—' : format_float($calculation->percent, 1) . ' %',
                $bulkaction === 'close' ? attendancejourneys_get_string('journeystatusopen', 'attendancejourneys') :
                    attendancejourneys_get_string('journeystatusclosed', 'attendancejourneys'),
            ];
        }
        echo attendancejourneys_responsive_table($preview, attendancejourneys_get_string('attendancereport', 'attendancejourneys'));
        echo html_writer::tag(
            'p',
            attendancejourneys_get_string('bulkconfirmcount', 'attendancejourneys', count($eligibleusers)),
            ['class' => 'font-weight-bold']
        );
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bulkaction', 'value' => $bulkaction]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirmbulk', 'value' => 1]);
        foreach ($eligibleusers as $selecteduserid) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectedusers[]',
                'value' => $selecteduserid]);
        }
        echo html_writer::tag('button', get_string('confirm'), ['type' => 'submit', 'class' => 'btn btn-primary mr-2 me-2']);
        echo html_writer::link($url, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
        echo $OUTPUT->footer();
        exit;
    }

    $transaction = $DB->start_delegated_transaction();
    foreach ($eligibleusers as $selecteduserid) {
        if ($bulkaction === 'close') {
            attendancejourneys_close_user_journey($attendancejourneys, $selecteduserid, (int) $USER->id, $journeyid);
        } else {
            attendancejourneys_reopen_user_journey(
                $attendancejourneys,
                $selecteduserid,
                (int) $USER->id,
                $reportjourney ? $journeyid : null
            );
        }
    }
    $transaction->allow_commit();
    attendancejourneys_update_completion($course, $cm, $eligibleusers);
    foreach ($eligibleusers as $selecteduserid) {
        attendancejourneys_update_grades($attendancejourneys, $selecteduserid);
    }
    redirect($url, attendancejourneys_get_string(
        $bulkaction === 'close' ? 'bulkjourneysclosed' : 'bulkjourneysreopened',
        'attendancejourneys',
        count($eligibleusers)
    ), null, \core\output\notification::NOTIFY_SUCCESS);
}

$direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
$sorters = [
    'lastname' => fn($row) => core_text::strtolower($row->participant->lastname . ' ' . $row->participant->firstname),
    'sessions' => fn($row) => $row->markedsessions,
    'minutes' => fn($row) => $row->presentminutes,
    'percent' => fn($row) => $row->percent ?? -1,
];
if (!isset($sorters[$sort])) {
    $sort = 'lastname';
}
usort($rows, function ($first, $second) use ($sorters, $sort, $direction) {
    $result = $sorters[$sort]($first) <=> $sorters[$sort]($second);
    return $direction === 'desc' ? -$result : $result;
});

$gradeprotections = !empty($attendancejourneys->journeygrading) ?
    \mod_attendancejourneys\local\journey_grades::protections(
        $attendancejourneys,
        $journeyid,
        array_map(static fn($row) => (int) $row->participant->id, $rows)
    ) : [];

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'reports');
echo html_writer::start_div('attendancejourneys-report-page');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(attendancejourneys_get_string('reporting', 'attendancejourneys'), 'attendancejourneys-section-eyebrow');
echo $OUTPUT->heading(attendancejourneys_get_string('attendancereport', 'attendancejourneys'));
if ($reportjourney) {
    echo $OUTPUT->heading(format_string($reportjourney->name), 3);
    if (!empty($attendancejourneys->percentageenabled)) {
        echo html_writer::div(get_string(
            'thresholdvalue',
            'attendancejourneys',
            format_float(attendancejourneys_get_passinggrade($attendancejourneys, $journeyid), 2) . ' %'
        ));
    }
    echo html_writer::div(html_writer::link(
        new moodle_url('/mod/attendancejourneys/report.php', ['id' => $cm->id]),
        get_string('selectreportjourney', 'attendancejourneys')
    ), 'mb-3');
}
echo html_writer::div(attendancejourneys_get_string(
    $islight ? 'attendancereportintrolight' : 'attendancereportintro',
    'attendancejourneys'
), 'text-muted');
echo html_writer::end_div();
if (!$islight) {
    echo html_writer::start_div('attendancejourneys-report-toolbar');
    attendancejourneys_print_report_group_filter(
        new moodle_url('/mod/attendancejourneys/report.php', ['id' => $cm->id, 'journeyid' => $journeyid]),
        $cm,
        $groupfilter
    );
    echo html_writer::end_div();
}

$table = new html_table();
$table->attributes['class'] = 'generaltable attendancejourneys-report-table';
if ($canmanagejourneys) {
    $table->head[] = html_writer::checkbox('selectall', 1, false, '', [
        'id' => 'attendancejourneys-select-all', 'title'
            => attendancejourneys_get_string('selectallparticipants', 'attendancejourneys'),
        'aria-label' => attendancejourneys_get_string('selectallparticipants', 'attendancejourneys'),
    ]);
}
$headers = [
    'lastname' => attendancejourneys_get_string('participant', 'attendancejourneys'),
    'sessions' => attendancejourneys_get_string('markedsessions', 'attendancejourneys'),
    'minutes' => attendancejourneys_get_string('presentminutes', 'attendancejourneys'),
    'percent' => attendancejourneys_get_string('percentage', 'attendancejourneys'),
];
foreach ($headers as $key => $label) {
    $nextdirection = ($sort === $key && $direction === 'asc') ? 'desc' : 'asc';
    $sorturl = new moodle_url($url, ['sort' => $key, 'direction' => $nextdirection]);
    $indicator = $sort === $key ? ($direction === 'asc' ? ' ↑' : ' ↓') : '';
    $table->head[] = html_writer::link($sorturl, $label . $indicator);
}
$table->head[] = attendancejourneys_get_string('result', 'attendancejourneys');
if (!$islight) {
    $journeyoffset = $canmanagejourneys ? 2 : 1;
    $table->head = array_merge(
        array_slice($table->head, 0, $journeyoffset),
        [attendancejourneys_get_string('journey', 'attendancejourneys')],
        array_slice($table->head, $journeyoffset)
    );
}

foreach ($rows as $row) {
    if (!$row->closure) {
        $percentlabel = $row->percent === null || !$attendancejourneys->percentageenabled
            ? '—' : format_float($row->percent, 1) . ' %';
        $resultlabel = html_writer::span(
            attendancejourneys_get_string(
                !empty($row->waived) ? 'obligationwaived' : 'provisional',
                'attendancejourneys'
            ),
            'badge badge-secondary text-bg-secondary'
        );
        if (!empty($row->pendingapprovals)) {
            $resultlabel .= html_writer::span(attendancejourneys_get_string(
                'pendingapprovalcount',
                'attendancejourneys',
                $row->pendingapprovals
            ), 'd-block small text-warning mt-1');
        }
    } else if ($row->percent === null || !$attendancejourneys->percentageenabled) {
        $percentlabel = '—';
        $resultlabel = '—';
    } else {
        $percentlabel = format_float($row->percent, 1) . ' %';
        $passed = $row->closure->result === 'passed';
        $resultlabel = html_writer::span(
            $passed ? attendancejourneys_get_string(
                'passed',
                'attendancejourneys'
            ) : attendancejourneys_get_string('failed', 'attendancejourneys'),
            $passed ? 'badge badge-success text-bg-success' : 'badge badge-danger text-bg-danger'
        );
    }
    if (in_array($row->attemptstatus, ['journeypreviousresult', 'attemptprevious'], true)) {
        $resultlabel .= html_writer::div(
            attendancejourneys_get_string($row->attemptstatus, 'attendancejourneys'),
            'small text-muted mt-1'
        );
    }
    if (isset($gradeprotections[$row->participant->id])) {
        $resultlabel .= html_writer::div(
            get_string($gradeprotections[$row->participant->id], 'attendancejourneys'),
            'small text-warning mt-1'
        );
    }
    $individualurl = new moodle_url('/mod/attendancejourneys/individual.php', [
        'id' => $cm->id,
        'userid' => $row->participant->id, 'journeyid' => $journeyid,
        'groupfilter' => $groupfilter,
    ]);
    $participantname = html_writer::tag('strong', s($row->participant->lastname)) .
        html_writer::tag('span', s($row->participant->firstname), ['class' => 'd-block']);
    $rowdata = [];
    if ($canmanagejourneys) {
        $rowdata[] = html_writer::checkbox('selectedusers[]', $row->participant->id, false, '', [
            'class' => 'attendancejourneys-user-select',
            'aria-label' => attendancejourneys_get_string('selectparticipant', 'attendancejourneys', fullname($row->participant)),
        ] + (!empty($row->waived) ? ['disabled' => 'disabled'] : []));
    }
    $rowdata[] = html_writer::link($individualurl, $participantname, [
            'class' => 'attendancejourneys-participant-link',
            'title' => attendancejourneys_get_string('viewindividualreport', 'attendancejourneys'),
        ]);
    if (!$islight) {
        $rowdata[] = $row->journeyname;
    }
    $rowdata = array_merge($rowdata, [
        $row->markedsessions . ' / ' . $row->applicablesessions,
        attendancejourneys_get_string('minutesfraction', 'attendancejourneys', (object) [
            'present' => $row->presentminutes,
            'possible' => $row->possibleminutes,
        ]),
        $percentlabel,
        $resultlabel,
    ]);
    $table->data[] = $rowdata;
}

if ($rows) {
    if ($canmanagejourneys) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
            'class' => 'attendancejourneys-bulk-form']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    }
    echo attendancejourneys_responsive_table($table, attendancejourneys_get_string('attendancereport', 'attendancejourneys'));
    if ($canmanagejourneys) {
        echo html_writer::start_div('attendancejourneys-report-bulk-actions mt-3');
        echo html_writer::tag(
            'span',
            attendancejourneys_get_string(
                'withselectedparticipants',
                'attendancejourneys'
            ),
            ['class' => 'mr-2 me-2']
        );
        if ($canclosejourneys) {
            echo html_writer::tag('button', attendancejourneys_get_string('bulkclosejourneys', 'attendancejourneys'), [
                'type' => 'submit', 'name' => 'bulkaction', 'value' => 'close',
                'class' => 'btn btn-primary attendancejourneys-report-bulk-action',
                'id' => 'attendancejourneys-close-selected-participants',
            ]);
        }
        if ($canreopenjourneys) {
            echo html_writer::tag('button', attendancejourneys_get_string('bulkreopenjourneys', 'attendancejourneys'), [
                'type' => 'submit', 'name' => 'bulkaction', 'value' => 'reopen',
                'class' => 'btn btn-outline-secondary attendancejourneys-report-bulk-action',
                'id' => 'attendancejourneys-reopen-selected-participants',
            ]);
        }
        echo html_writer::end_div();
        echo html_writer::end_tag('form');
        $PAGE->requires->js_call_amd('mod_attendancejourneys/selection', 'init', [
            'attendancejourneys-select-all',
            '.attendancejourneys-user-select',
            ['attendancejourneys-close-selected-participants', 'attendancejourneys-reopen-selected-participants'],
        ]);
    }
    $exporturl = new moodle_url('/mod/attendancejourneys/export.php');
    echo html_writer::div($OUTPUT->download_dataformat_selector(
        attendancejourneys_get_string('exportcollectivereport', 'attendancejourneys'),
        $exporturl,
        'dataformat',
        ['id' => $cm->id, 'scope' => 'collective', 'groupfilter' => $groupfilter, 'journeyid' => $journeyid]
    ), 'attendancejourneys-export mt-3');
} else {
    echo $OUTPUT->notification(attendancejourneys_get_string('noparticipants', 'attendancejourneys'), 'info');
}

$returnurl = new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]);
echo html_writer::div(html_writer::link($returnurl, attendancejourneys_get_string('backtoactivity', 'attendancejourneys')), 'mt-4');
echo html_writer::end_div();
echo $OUTPUT->footer();
