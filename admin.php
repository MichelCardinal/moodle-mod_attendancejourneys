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
 * Exceptional administrative operations for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$groupfilter = optional_param('groupfilter', -1, PARAM_INT);
$query = trim(optional_param('q', '', PARAM_TEXT));
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$selectedusers = optional_param_array('selectedusers', [], PARAM_INT);

$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendancejourneys:resetuserdata', $context);
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

$url = new moodle_url('/mod/attendancejourneys/admin.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(attendancejourneys_get_string('navigationadministration', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$fields = 'u.id,u.firstname,u.lastname,u.email,u.suspended,u.deleted,u.picture,u.imagealt,' .
    'u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename';
$participants = get_enrolled_users(
    $context,
    'mod/attendancejourneys:canbelisted',
    0,
    $fields,
    'u.lastname ASC, u.firstname ASC',
    0,
    0,
    false
);
$sessionids = $DB->get_fieldset_select(
    'attendancejourneys_sessions',
    'id',
    'attendancejourneysid = :attendancejourneysid',
    ['attendancejourneysid' => $activity->id]
);
$recordcounts = [];
if ($sessionids) {
    [$insql, $params] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'adminsession');
    $recordcounts = $DB->get_records_sql_menu("SELECT userid, COUNT(1)
                                                 FROM {attendancejourneys_records}
                                                WHERE sessionid $insql
                                             GROUP BY userid", $params);
}
$closurecounts = $DB->get_records_sql_menu("SELECT userid, COUNT(1)
                                              FROM {attendancejourneys_closures}
                                             WHERE attendancejourneysid = :attendancejourneysid
                                          GROUP BY userid", ['attendancejourneysid' => $activity->id]);
$historicalids = array_unique(array_merge(array_keys($recordcounts), array_keys($closurecounts)));
foreach ($historicalids as $historicalid) {
    if (!isset($participants[$historicalid])) {
        $historicaluser = core_user::get_user($historicalid, '*', IGNORE_MISSING);
        if ($historicaluser && empty($historicaluser->deleted)) {
            $participants[$historicalid] = $historicaluser;
        }
    }
}
core_collator::asort_objects_by_property($participants, 'lastname', core_collator::SORT_NATURAL);

$groupoptions = attendancejourneys_report_group_filter_options($cm);
if (!array_key_exists($groupfilter, $groupoptions)) {
    $groupfilter = -1;
}
if ($groupfilter >= 0) {
    $participants = array_intersect_key($participants, groups_get_members($groupfilter, 'u.id'));
}
if ($query !== '') {
    $needle = core_text::strtolower($query);
    $participants = array_filter($participants, static function ($participant) use ($needle) {
        $haystack = core_text::strtolower(fullname($participant) . ' ' . $participant->email);
        return core_text::strpos($haystack, $needle) !== false;
    });
}

if ($action === 'reset') {
    require_sesskey();
    $selectedusers = array_values(array_intersect(
        array_unique(array_map('intval', $selectedusers)),
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
    if (!$confirm) {
        echo $OUTPUT->header();
        attendancejourneys_print_navigation($cm, $context, 'administration');
        attendancejourneys_print_admin_navigation($cm, 'participants');
        echo $OUTPUT->heading(attendancejourneys_get_string('bulkresetuserdata', 'attendancejourneys'));
        echo $OUTPUT->notification(attendancejourneys_get_string('bulkresetwarning', 'attendancejourneys'), 'warning');
        $preview = new html_table();
        $preview->head = [attendancejourneys_get_string(
            'participant',
            'attendancejourneys'
        ),
        attendancejourneys_get_string(
            'attendanceentries',
            'attendancejourneys'
        ),

            attendancejourneys_get_string('journeyresults', 'attendancejourneys')];
        foreach ($selectedusers as $userid) {
            $preview->data[] = [fullname($participants[$userid]), (int) ($recordcounts[$userid] ?? 0),
                (int) ($closurecounts[$userid] ?? 0)];
        }
        echo attendancejourneys_responsive_table(
            $preview,
            attendancejourneys_get_string('bulkresetuserdata', 'attendancejourneys')
        );
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'reset']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
        foreach ($selectedusers as $userid) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectedusers[]', 'value' => $userid]);
        }
        echo html_writer::tag(
            'button',
            attendancejourneys_get_string('confirmreset', 'attendancejourneys'),
            ['type' => 'submit', 'class' => 'btn btn-danger mr-2 me-2']
        );
        echo html_writer::link($url, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
        echo $OUTPUT->footer();
        exit;
    }

    $transaction = $DB->start_delegated_transaction();
    $totalrecords = 0;
    $totalclosures = 0;
    foreach ($selectedusers as $userid) {
        $deleted = attendancejourneys_reset_user_data($activity, $cm, $userid, (int) $USER->id);
        $totalrecords += $deleted['records'];
        $totalclosures += $deleted['closures'];
    }
    $transaction->allow_commit();
    redirect($url, attendancejourneys_get_string('bulkuserdataresetsuccess', 'attendancejourneys', (object) [
        'users' => count($selectedusers), 'records' => $totalrecords, 'closures' => $totalclosures,
    ]), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'administration');
attendancejourneys_print_admin_navigation($cm, 'participants');
echo html_writer::start_div('attendancejourneys-admin-page');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(
    attendancejourneys_get_string('advancedadministration', 'attendancejourneys'),
    'attendancejourneys-section-eyebrow'
);
echo $OUTPUT->heading(attendancejourneys_get_string('navigationadministration', 'attendancejourneys'));
echo html_writer::div(attendancejourneys_get_string('administrationintro', 'attendancejourneys'), 'text-muted');
echo html_writer::end_div();
echo $OUTPUT->notification(attendancejourneys_get_string('administrationwarning', 'attendancejourneys'), 'warning');

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false),
    'class' => 'attendancejourneys-admin-filters mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::start_div('attendancejourneys-admin-filter-field');
echo html_writer::label(
    attendancejourneys_get_string('filterreportbygroup', 'attendancejourneys'),
    'attendancejourneys-admin-group',
    false,
    ['class' => 'd-block']
);
echo html_writer::select(
    $groupoptions,
    'groupfilter',
    $groupfilter,
    false,
    ['id' => 'attendancejourneys-admin-group', 'class' => 'custom-select form-select']
);
echo html_writer::end_div();
echo html_writer::start_div('attendancejourneys-admin-filter-field');
echo html_writer::label(
    attendancejourneys_get_string('searchparticipants', 'attendancejourneys'),
    'attendancejourneys-admin-search',
    false,
    ['class' => 'd-block']
);
echo html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $query,
    'id' => 'attendancejourneys-admin-search', 'class' => 'form-control']);
echo html_writer::end_div();
echo html_writer::tag('button', get_string('apply'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

if (!$participants) {
    echo $OUTPUT->notification(attendancejourneys_get_string('noparticipants', 'attendancejourneys'), 'info');
} else {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'reset']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'groupfilter', 'value' => $groupfilter]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'q', 'value' => $query]);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable attendancejourneys-admin-table';
    $table->head = [html_writer::checkbox('selectall', 1, false, '', [
        'id' => 'attendancejourneys-admin-select-all',
        'aria-label' => attendancejourneys_get_string('selectallparticipants', 'attendancejourneys'),
    ]),
        attendancejourneys_get_string('participant', 'attendancejourneys'),
            attendancejourneys_get_string('enrolmentstatus', 'attendancejourneys'),
        attendancejourneys_get_string(
            'attendanceentries',
            'attendancejourneys'
        ),
    attendancejourneys_get_string(
        'journeyresults',
        'attendancejourneys'
    )];
    foreach ($participants as $participant) {
        $active = is_enrolled($context, $participant->id, 'mod/attendancejourneys:canbelisted', true) &&
            empty($participant->suspended);
        $table->data[] = [html_writer::checkbox(
            'selectedusers[]',
            $participant->id,
            false,
            '',
            ['class' => 'attendancejourneys-admin-user',
            'aria-label' => attendancejourneys_get_string(
                'selectparticipant',
                'attendancejourneys',
                fullname($participant)
            )]
        ),
            html_writer::tag('strong', fullname($participant)) . html_writer::span(
                s($participant->email),
                'd-block text-muted small'
            ),
            html_writer::span(
                get_string($active ? 'active' : 'inactive'),
                $active ? 'badge badge-success text-bg-success' : 'badge badge-secondary text-bg-secondary'
            ),
            (int) ($recordcounts[$participant->id] ?? 0), (int) ($closurecounts[$participant->id] ?? 0)];
    }
    echo attendancejourneys_responsive_table(
        $table,
        attendancejourneys_get_string('navigationadministration', 'attendancejourneys')
    );
    echo html_writer::div(html_writer::tag(
        'button',
        attendancejourneys_get_string('resetselectedparticipants', 'attendancejourneys'),
        ['type' => 'submit', 'class' => 'btn btn-danger',
        'id' => 'attendancejourneys-reset-selected-participants']
    ), 'attendancejourneys-admin-actions');
    echo html_writer::end_tag('form');
    $PAGE->requires->js_call_amd('mod_attendancejourneys/selection', 'init', [
        'attendancejourneys-admin-select-all',
        '.attendancejourneys-admin-user',
        ['attendancejourneys-reset-selected-participants'],
    ]);
}
echo html_writer::end_div();
echo $OUTPUT->footer();
