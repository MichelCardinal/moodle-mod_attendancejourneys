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
 * Native Moodle data-format exports for Attendance Journeys reports.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$scope = required_param('scope', PARAM_ALPHA);
$dataformat = required_param('dataformat', PARAM_ALPHANUMEXT);
$userid = optional_param('userid', 0, PARAM_INT);
$groupfilter = optional_param('groupfilter', -1, PARAM_INT);
$journeyid = optional_param('journeyid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:viewreports', $context);
require_sesskey();
$islight = attendancejourneys_is_light_mode($attendancejourneys);

$groupoptions = attendancejourneys_report_group_filter_options($cm);
if (!array_key_exists($groupfilter, $groupoptions)) {
    $groupfilter = -1;
}

if (!in_array($scope, ['collective', 'individual', 'journey', 'journeydetail'], true)) {
    throw new moodle_exception('invalidparameter');
}
if ($scope === 'journey' || $scope === 'journeydetail') {
    attendancejourneys_require_professional_mode($attendancejourneys, (int) $cm->id);
    require_capability('mod/attendancejourneys:managejourneys', $context);
}

$sessions = $DB->get_records('attendancejourneys_sessions', ['attendancejourneysid' => $attendancejourneys->id], 'sessiondate ASC');
$collectivejourney = null;
if ($scope === 'collective' && !empty($attendancejourneys->journeygrading)) {
    $availablejourneys = attendancejourneys_visible_journeys($context, (int) $attendancejourneys->id);
    if (!$journeyid && count($availablejourneys) === 1) {
        $journeyid = (int) array_key_first($availablejourneys);
    }
    if (!$journeyid) {
        throw new moodle_exception('journeyselectionrequired', 'attendancejourneys');
    }
    if (!isset($availablejourneys[$journeyid])) {
        throw new moodle_exception('invalidparameter');
    }
    $collectivejourney = $availablejourneys[$journeyid];
    $sessions = array_filter($sessions, static fn($session) => (int) $session->journeyid === $journeyid);
}
if ($scope === 'individual' && !empty($attendancejourneys->journeygrading)) {
    $reportjourneys = attendancejourneys_get_user_report_journeys($context, (int) $attendancejourneys->id, $userid);
    if (!$journeyid) {
        $roots = [];
        foreach ($reportjourneys as $candidate) {
            $roots[(int) ($candidate->rootjourneyid ?: $candidate->id)] = true;
        }
        if (count($roots) === 1) {
            $family = \mod_attendancejourneys\local\attempt_family::load(
                $attendancejourneys,
                (int) array_key_first($roots),
                $userid
            );
            $current = \mod_attendancejourneys\local\attempt_family::select($family, $userid);
            if (isset($reportjourneys[$current->journeyid])) {
                $journeyid = $current->journeyid;
            }
        }
    }
    if (!$journeyid) {
        throw new moodle_exception('journeyselectionrequired', 'attendancejourneys');
    }
    if (!isset($reportjourneys[$journeyid])) {
        throw new moodle_exception('invalidparameter');
    }
    $exportjourney = $reportjourneys[$journeyid];
    $sessions = array_filter($sessions, static fn($session) => (int) $session->journeyid === $journeyid);
}
if ($scope === 'journey' || $scope === 'journeydetail') {
    $journey = $DB->get_record('attendancejourneys_journeys', [
        'id' => $journeyid, 'attendancejourneysid' => $attendancejourneys->id,
    ], '*', MUST_EXIST);
    if (!attendancejourneys_session_is_visible($cm, $context, $journey)) {
        throw new moodle_exception('invalidparameter');
    }
    $sessions = array_filter($sessions, static fn($session) => (int) $session->journeyid === (int) $journey->id);
}
if ($scope === 'collective' && $groupfilter >= 0) {
    $sessions = array_filter($sessions, static fn($session) =>
        empty($session->groupid) || (int) $session->groupid === $groupfilter);
}
$recordsbyuser = [];
$journeysbyid = $DB->get_records('attendancejourneys_journeys', ['attendancejourneysid' => $attendancejourneys->id]);
$attemptfamilies = [];
$attemptstatus = static function (int $physicalid, int $participantid, ?stdClass $final, bool $waived = false)
 use ($attendancejourneys, $journeysbyid, &$attemptfamilies): string {
    if (empty($attendancejourneys->journeygrading) || !$physicalid) {
        return '';
    }
    $physical = $journeysbyid[$physicalid];
    $rootid = (int) ($physical->rootjourneyid ?: $physical->id);
    $attemptfamilies[$rootid] ??= \mod_attendancejourneys\local\attempt_family::load($attendancejourneys, $rootid);
    return attendancejourneys_get_string(\mod_attendancejourneys\local\attempt_family::result_status(
        $attemptfamilies[$rootid],
        $participantid,
        $physicalid,
        $final,
        $waived
    ), 'attendancejourneys');
};

if ($sessions) {
    [$insql, $params] = $DB->get_in_or_equal(array_keys($sessions), SQL_PARAMS_NAMED, 'session');
    foreach ($DB->get_records_select('attendancejourneys_records', "sessionid $insql", $params) as $record) {
        $recordsbyuser[$record->userid][$record->sessionid] = $record;
    }
}
$recorderids = [];
foreach ($recordsbyuser as $userrecords) {
    foreach ($userrecords as $record) {
        if (!empty($record->takenby)) {
            $recorderids[(int) $record->takenby] = (int) $record->takenby;
        }
        if (!empty($record->approvedby)) {
            $recorderids[(int) $record->approvedby] = (int) $record->approvedby;
        }
        if (!empty($record->reviewedby)) {
            $recorderids[(int) $record->reviewedby] = (int) $record->reviewedby;
        }
    }
}
$recorders = $recorderids ? $DB->get_records_list(
    'user',
    'id',
    array_values($recorderids),
    '',
    'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
) : [];
$recorderaudit = static function (?stdClass $record) use ($recorders): array {
    if (!$record) {
        return ['', '', '', '', '', '', '', '', ''];
    }
    $name = !empty($record->takenby) && isset($recorders[$record->takenby])
        ? fullname($recorders[$record->takenby]) : attendancejourneys_get_string('attendanceunknownrecorder', 'attendancejourneys');
    $isselfrecorded = (int) $record->takenby === (int) $record->userid;
    $source = attendancejourneys_get_string(
        $isselfrecorded ? 'attendancesourceself' : 'attendancesourcestaff',
        'attendancejourneys'
    );
    $approval = $isselfrecorded ? attendancejourneys_get_string(
        !empty($record->approved) ? 'attendanceapproved' :
        'attendancependingapproval',
        'attendancejourneys'
    ) : attendancejourneys_get_string(
        'attendanceinstitutionalentry',
        'attendancejourneys'
    );
    $approvedby = !empty($record->approvedby) && isset($recorders[$record->approvedby])
        ? fullname($recorders[$record->approvedby]) : '';
    $approvedon = !empty($record->timeapproved)
        ? userdate($record->timeapproved, get_string('strftimedatetimeshort', 'langconfig')) : '';
    $reviewer = !empty($record->reviewedby) && isset($recorders[$record->reviewedby])
        ? fullname($recorders[$record->reviewedby]) : '';
    $reviewedon = !empty($record->timereviewed)
        ? userdate($record->timereviewed, get_string('strftimedatetimeshort', 'langconfig')) : '';
    return [$name, !empty($record->timemodified) ?
        userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig')) : '',
        $source, $approval, $approvedby, $approvedon, trim((string) ($record->reviewnote ?? '')), $reviewer,
        $reviewedon];
};

$calculate = static function (stdClass $record, stdClass $session) use ($attendancejourneys): array {
    return \mod_attendancejourneys\local\calculator::record_minutes($attendancejourneys, $session, $record);
};

if ($scope === 'journeydetail') {
    $participants = attendancejourneys_get_journey_participants(
        $context,
        $journey,
        'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename'
    );
    $rows = [];
    foreach ($participants as $participant) {
        $final = attendancejourneys_get_active_closure((int) $attendancejourneys->id, (int) $participant->id, (int) $journey->id);
        $physicalstatus = $attemptstatus(
            (int) $journey->id,
            (int) $participant->id,
            $final,
            !empty(\mod_attendancejourneys\local\individual_obligation::get((int) $journey->id, (int) $participant->id)->waived)
        );
        $requiredsessions = \mod_attendancejourneys\local\individual_obligation::required_sessions(
            $attendancejourneys,
            (int) $participant->id,
            $sessions
        );
        foreach ($sessions as $session) {
            $record = $recordsbyuser[$participant->id][$session->id] ?? null;
            if ($record) {
                [$present, $possible] = $calculate($record, $session);
                $status = attendancejourneys_get_string('status' . $record->status, 'attendancejourneys');
            } else {
                $present = '';
                $possible = '';
                $status = attendancejourneys_get_string('notrecorded', 'attendancejourneys');
            }
            if (!empty($session->cancelled)) {
                $status = attendancejourneys_get_string('sessioncancelled', 'attendancejourneys');
                $present = 0;
                $possible = 0;
            } else if (!isset($requiredsessions[$session->id])) {
                $status .= ' — ' . attendancejourneys_get_string('obligationoutside', 'attendancejourneys');
            }
            [$recordedby, $modifiedon, $source, $approval, $approvedby, $approvedon, $reviewnote, $reviewedby,
                $reviewedon] = $recorderaudit($record);
            $rows[] = (object) [
                'attemptstatus' => $physicalstatus,
                'participant' => fullname($participant),
                'session' => format_string($session->name),
                'date' => userdate($session->sessiondate, get_string('strftimedatetimeshort', 'langconfig')),
                'duration' => (int) $session->duration,
                'status' => $status,
                'minutesabsent' => $record && $record->status === 'partial' ? (int) $record->minutesabsent : '',
                'present' => $present,
                'possible' => $possible,
                'remarks' => $record ? trim((string) $record->remarks) : '',
                'recordedby' => $recordedby,
                'modifiedon' => $modifiedon,
                'source' => $source,
                'approval' => $approval,
                'approvedby' => $approvedby,
                'approvedon' => $approvedon,
                'reviewnote' => $reviewnote,
                'reviewedby' => $reviewedby,
                'reviewedon' => $reviewedon,
            ];
        }
    }
    $columns = [
        'participant' => attendancejourneys_get_string('participant', 'attendancejourneys'),
        'session' => attendancejourneys_get_string('sessionname', 'attendancejourneys'),
        'date' => attendancejourneys_get_string('sessiondate', 'attendancejourneys'),
        'duration' => attendancejourneys_get_string('duration', 'attendancejourneys'),
        'status' => attendancejourneys_get_string('status', 'attendancejourneys'),
        'minutesabsent' => attendancejourneys_get_string('minutesabsent', 'attendancejourneys'),
        'present' => attendancejourneys_get_string('minutespresent', 'attendancejourneys'),
        'possible' => attendancejourneys_get_string('minutespossible', 'attendancejourneys'),
        'remarks' => attendancejourneys_get_string('remarks', 'attendancejourneys'),
        'recordedby' => attendancejourneys_get_string('attendanceenteredby', 'attendancejourneys'),
        'modifiedon' => attendancejourneys_get_string('attendancemodifiedon', 'attendancejourneys'),
        'source' => attendancejourneys_get_string('attendancesource', 'attendancejourneys'),
        'approval' => attendancejourneys_get_string('attendanceapprovalstatus', 'attendancejourneys'),
        'approvedby' => attendancejourneys_get_string('attendanceapprovedby', 'attendancejourneys'),
        'approvedon' => attendancejourneys_get_string('attendanceapproveddate', 'attendancejourneys'),
        'reviewnote' => attendancejourneys_get_string('reviewnote', 'attendancejourneys'),
        'reviewedby' => attendancejourneys_get_string('reviewedby', 'attendancejourneys'),
        'reviewedon' => attendancejourneys_get_string('reviewedon', 'attendancejourneys'),
    ];
    if (!empty($attendancejourneys->journeygrading)) {
        $columns['attemptstatus'] = attendancejourneys_get_string('attemptresultstatus', 'attendancejourneys');
    }
    $filename = clean_filename($course->shortname . '-' . $journey->name . '-' .
        get_string('detailbysession', 'attendancejourneys'));
} else if ($scope === 'journey') {
    $participants = attendancejourneys_get_journey_participants(
        $context,
        $journey,
        'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename'
    );
    $rows = [];
    foreach ($participants as $participant) {
        $closure = attendancejourneys_get_active_closure(
            (int) $attendancejourneys->id,
            (int) $participant->id,
            (int) $journey->id
        );
        $calculation = $closure ?: attendancejourneys_calculate_user_attendance(
            $attendancejourneys,
            (int) $participant->id,
            (int) $journey->id
        );
        $recorded = $closure ? (int) $closure->recordedsessions : (int) $calculation->recordedsessions;
        $applicable = $closure ? (int) $closure->applicablesessions : (int) $calculation->applicablesessions;
        $present = $closure ? (int) $closure->presentminutes : (int) $calculation->presentminutes;
        $possible = $closure ? (int) $closure->possibleminutes : (int) $calculation->possibleminutes;
        $percent = $closure ? $closure->percentage : $calculation->percent;
        $result = $closure ? ($closure->result === 'passed' ? attendancejourneys_get_string('passed', 'attendancejourneys') :
            ($closure->result === 'failed' ? attendancejourneys_get_string('failed', 'attendancejourneys') : '')) :
            attendancejourneys_get_string(!empty($calculation->waived) ? 'obligationwaived' : 'provisional', 'attendancejourneys');
        $rows[] = (object) [
            'journey' => format_string($journey->name),
            'participant' => fullname($participant),
            'sessions' => $recorded . ' / ' . $applicable,
            'present' => $present,
            'possible' => $possible,
            'percentage' => $percent === null || !$attendancejourneys->percentageenabled ? '' :
                ($dataformat === 'excel' ? round((float) $percent, 1) : format_float($percent, 1)),
            'result' => $result,
            'status' => !empty($attendancejourneys->journeygrading) ?
                $attemptstatus((int) $journey->id, (int) $participant->id, $closure, !empty($calculation->waived)) :
                ($closure ? attendancejourneys_get_string('journeyresultpublished', 'attendancejourneys') :
                    attendancejourneys_get_string(
                        !empty($calculation->waived) ? 'obligationwaived' : 'provisional',
                        'attendancejourneys'
                    )),
        ];
    }
    $columns = [
        'journey' => attendancejourneys_get_string('journey', 'attendancejourneys'),
        'participant' => attendancejourneys_get_string('participant', 'attendancejourneys'),
        'sessions' => attendancejourneys_get_string('markedsessions', 'attendancejourneys'),
        'present' => attendancejourneys_get_string('minutespresent', 'attendancejourneys'),
        'possible' => attendancejourneys_get_string('minutespossible', 'attendancejourneys'),
        'percentage' => attendancejourneys_get_string('percentage', 'attendancejourneys'),
        'result' => attendancejourneys_get_string('result', 'attendancejourneys'),
        'status' => attendancejourneys_get_string('status', 'attendancejourneys'),
    ];
    $filename = clean_filename($course->shortname . '-' . $journey->name . '-' .
        get_string('attendancereport', 'attendancejourneys'));
} else if ($scope === 'collective') {
    $participants = attendancejourneys_get_visible_participants(
        $context,
        $cm,
        'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename',
        'u.lastname ASC, u.firstname ASC'
    );
    if ($groupfilter >= 0) {
        $participants = array_intersect_key($participants, groups_get_members($groupfilter, 'u.id'));
    }
    if ($collectivejourney) {
        $participants = array_intersect_key(
            $participants,
            attendancejourneys_get_journey_participants($context, $collectivejourney)
        );
    }
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
    foreach (
        array_unique(array_filter(array_map(
            static fn($session) => (int) $session->groupid,
            $sessions
        ))) as $groupid
    ) {
        $groupmembers[$groupid] = array_fill_keys(array_map(
            'intval',
            array_keys(groups_get_members($groupid, 'u.id'))
        ), true);
    }
    $rows = [];
    foreach ($participants as $participant) {
        $participantid = (int) $participant->id;
        $activejourney = $collectivejourney ?: ($activejourneybyuser[$participantid] ?? null);
        if (!$members && !$collectivejourney) {
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
        $present = 0;
        $possible = 0;
        $recorded = 0;
        $applicable = 0;
        if (empty($attendancejourneys->journeygrading)) {
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
                $applicable++;
                $record = $recordsbyuser[$participantid][$session->id] ?? null;
                if (!$record || !\mod_attendancejourneys\local\calculator::record_is_official($record)) {
                    continue;
                }
                $recorded++;
                [$sessionpresent, $sessionpossible] = $calculate($record, $session);
                $present += $sessionpresent;
                $possible += $sessionpossible;
            }
        }
        if ($closure) {
            $present = (int) $closure->presentminutes;
            $possible = (int) $closure->possibleminutes;
            $recorded = (int) $closure->recordedsessions;
            $applicable = (int) $closure->applicablesessions;
            $percent = $closure->percentage === null ? null : (float) $closure->percentage;
        } else if (!empty($attendancejourneys->journeygrading)) {
            $calculation = \mod_attendancejourneys\local\calculator::calculate(
                $attendancejourneys,
                $participantid,
                $targetjourneyid
            );
            $present = (int) $calculation->presentminutes;
            $possible = (int) $calculation->possibleminutes;
            $recorded = (int) $calculation->recordedsessions;
            $applicable = (int) $calculation->applicablesessions;
            $percent = $calculation->percent;
        } else {
            $percent = $possible > 0 ? ($present / $possible) * 100 : null;
        }
        $result = $closure ? '' : attendancejourneys_get_string(
            !empty($attendancejourneys->journeygrading) && !empty($calculation->waived) ? 'obligationwaived' : 'provisional',
            'attendancejourneys'
        );
        if ($closure && $percent !== null && $attendancejourneys->percentageenabled) {
            $result = $closure->result === 'passed'
                ? attendancejourneys_get_string(
                    'passed',
                    'attendancejourneys'
                ) : attendancejourneys_get_string('failed', 'attendancejourneys');
        }
        $rows[] = (object) [
            'participant' => fullname($participant),
            'journey' => $targetjourneyid && isset($journeysbyid[$targetjourneyid]) ?
                format_string($journeysbyid[$targetjourneyid]->name) : attendancejourneys_get_string(
                    'independent',
                    'attendancejourneys'
                ),
            'sessions' => $recorded . ' / ' . $applicable,
            'present' => $present,
            'possible' => $possible,
            'percentage' => $percent === null || !$attendancejourneys->percentageenabled ? '' :
                ($dataformat === 'excel' ? round((float) $percent, 1) : format_float($percent, 1)),
            'result' => $result,
            'attemptstatus' => $attemptstatus($targetjourneyid, $participantid, $closure, !empty($calculation->waived)),
        ];
    }
    $columns = [
        'participant' => attendancejourneys_get_string('participant', 'attendancejourneys'),
        'sessions' => attendancejourneys_get_string('markedsessions', 'attendancejourneys'),
        'present' => attendancejourneys_get_string('minutespresent', 'attendancejourneys'),
        'possible' => attendancejourneys_get_string('minutespossible', 'attendancejourneys'),
        'percentage' => attendancejourneys_get_string('percentage', 'attendancejourneys'),
        'result' => attendancejourneys_get_string('result', 'attendancejourneys'),
    ];
    if (!$islight) {
        $columns = array_merge(
            array_slice($columns, 0, 1, true),
            ['journey' => attendancejourneys_get_string('journey', 'attendancejourneys')],
            array_slice($columns, 1, null, true)
        );
    }
    if (!empty($attendancejourneys->journeygrading)) {
        $columns['attemptstatus'] = attendancejourneys_get_string('attemptresultstatus', 'attendancejourneys');
    }
    $groupsuffix = $groupfilter >= 0 ? '-' . $groupoptions[$groupfilter] : '';
    $filename = clean_filename($course->shortname . '-' . $attendancejourneys->name .
        ($collectivejourney ? '-' . $collectivejourney->name : '') . $groupsuffix . '-' .
        get_string('attendancereport', 'attendancejourneys'));
} else {
    $participants = attendancejourneys_get_visible_participants(
        $context,
        $cm,
        'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename',
        null
    );
    if (!$userid || !isset($participants[$userid])) {
        throw new moodle_exception('invaliduser');
    }
    $participant = $participants[$userid];
    $activejourney = $exportjourney ?? attendancejourneys_get_user_active_journey($context, (int) $attendancejourneys->id, $userid);
    $closure = $activejourney ? attendancejourneys_get_active_closure(
        (int) $attendancejourneys->id,
        $userid,
        (int) $activejourney->id
    ) : attendancejourneys_get_active_closure((int) $attendancejourneys->id, $userid);
    $targetjourneyid = $closure && !empty($closure->journeyid) ? (int) $closure->journeyid :
        ($activejourney ? (int) $activejourney->id : 0);
    $individualattemptstatus = $attemptstatus(
        $targetjourneyid,
        $userid,
        $closure,
        !empty(\mod_attendancejourneys\local\individual_obligation::get($targetjourneyid, $userid)->waived)
    );
    $sessions = array_filter($sessions, fn($session) => (!$targetjourneyid ||
        (int) $session->journeyid === $targetjourneyid) && attendancejourneys_session_applies_to_user($session, $userid));
    $requiredsessions = \mod_attendancejourneys\local\individual_obligation::required_sessions(
        $attendancejourneys,
        $userid,
        $sessions
    );
    $rows = [];
    foreach ($sessions as $session) {
        $record = $recordsbyuser[$userid][$session->id] ?? null;
        if ($record) {
            [$present, $possible] = $calculate($record, $session);
            $status = attendancejourneys_get_string('status' . $record->status, 'attendancejourneys');
            $minutesabsent = $record->status === 'partial' ? (int) $record->minutesabsent : '';
            $remarks = trim((string) $record->remarks);
        } else {
            $present = '';
            $possible = '';
            $status = attendancejourneys_get_string('notrecorded', 'attendancejourneys');
            $minutesabsent = '';
            $remarks = '';
        }
        if (!empty($session->cancelled)) {
            $status = attendancejourneys_get_string('sessioncancelled', 'attendancejourneys');
            $present = 0;
            $possible = 0;
        } else if (!isset($requiredsessions[$session->id])) {
            $status .= ' — ' . attendancejourneys_get_string('obligationoutside', 'attendancejourneys');
        }
        [$recordedby, $modifiedon, $source, $approval, $approvedby, $approvedon, $reviewnote, $reviewedby,
            $reviewedon] = $recorderaudit($record);
        $rows[] = (object) [
            'attemptstatus' => $individualattemptstatus,
            'session' => format_string($session->name),
            'start' => userdate($session->sessiondate, get_string('strftimedatetimeshort', 'langconfig')),
            'end' => userdate(
                $session->sessiondate + ((int) $session->duration * MINSECS),
                get_string('strftimedatetimeshort', 'langconfig')
            ),
            'duration' => (int) $session->duration,
            'status' => $status,
            'minutesabsent' => $minutesabsent,
            'present' => $present,
            'possible' => $possible,
            'remarks' => $remarks,
            'recordedby' => $recordedby,
            'modifiedon' => $modifiedon,
            'source' => $source,
            'approval' => $approval,
            'approvedby' => $approvedby,
            'approvedon' => $approvedon,
            'reviewnote' => $reviewnote,
            'reviewedby' => $reviewedby,
            'reviewedon' => $reviewedon,
        ];
    }
    $columns = [
        'session' => attendancejourneys_get_string('sessionname', 'attendancejourneys'),
        'start' => attendancejourneys_get_string('sessiondate', 'attendancejourneys'),
        'end' => attendancejourneys_get_string('sessionenddate', 'attendancejourneys'),
        'duration' => attendancejourneys_get_string('duration', 'attendancejourneys'),
        'status' => attendancejourneys_get_string('status', 'attendancejourneys'),
        'minutesabsent' => attendancejourneys_get_string('minutesabsent', 'attendancejourneys'),
        'present' => attendancejourneys_get_string('minutespresent', 'attendancejourneys'),
        'possible' => attendancejourneys_get_string('minutespossible', 'attendancejourneys'),
        'remarks' => attendancejourneys_get_string('remarks', 'attendancejourneys'),
        'recordedby' => attendancejourneys_get_string('attendanceenteredby', 'attendancejourneys'),
        'modifiedon' => attendancejourneys_get_string('attendancemodifiedon', 'attendancejourneys'),
        'source' => attendancejourneys_get_string('attendancesource', 'attendancejourneys'),
        'approval' => attendancejourneys_get_string('attendanceapprovalstatus', 'attendancejourneys'),
    ];
    if (!$islight) {
        $columns += [
            'approvedby' => attendancejourneys_get_string('attendanceapprovedby', 'attendancejourneys'),
            'approvedon' => attendancejourneys_get_string('attendanceapproveddate', 'attendancejourneys'),
            'reviewnote' => attendancejourneys_get_string('reviewnote', 'attendancejourneys'),
            'reviewedby' => attendancejourneys_get_string('reviewedby', 'attendancejourneys'),
            'reviewedon' => attendancejourneys_get_string('reviewedon', 'attendancejourneys'),
        ];
    }
    if (!empty($attendancejourneys->journeygrading)) {
        $columns['attemptstatus'] = attendancejourneys_get_string('attemptresultstatus', 'attendancejourneys');
    }
    $filename = clean_filename($course->shortname . '-' . fullname($participant) .
        (!empty($exportjourney) ? '-' . $exportjourney->name : '') . '-' .
        get_string('individualreport', 'attendancejourneys'));
}

if ($dataformat === 'pdf') {
    \mod_attendancejourneys\local\pdf_export::download($filename, $columns, $rows);
} else if ($dataformat === 'excel') {
    \mod_attendancejourneys\local\excel_export::download($filename, $columns, $rows);
} else {
    \core\dataformat::download_data($filename, $dataformat, $columns, new ArrayIterator($rows));
}
exit;
