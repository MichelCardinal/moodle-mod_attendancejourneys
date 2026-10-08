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
 * Native Moodle course-group management inside Attendance Journeys.
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->dirroot . '/group/lib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$groupid = optional_param('groupid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
$coursecontext = context_course::instance($course->id);
require_capability('moodle/course:managegroups', $coursecontext);
attendancejourneys_require_professional_mode($attendancejourneys, (int) $cm->id);

$baseurl = new moodle_url('/mod/attendancejourneys/groups.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(attendancejourneys_get_string('managecoursegroups', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'add' || ($action === 'edit' && $groupid)) {
    $group = null;
    $currentmembers = [];
    if ($action === 'edit') {
        $group = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $course->id], '*', MUST_EXIST);
        $currentmembers = groups_get_members($group->id, 'u.id, u.firstname, u.lastname, u.email');
    }
    $eligible = get_enrolled_users(
        $coursecontext,
        '',
        0,
        'u.id, u.firstname, u.lastname, u.email',
        'u.lastname, u.firstname',
        0,
        0,
        true
    );
    foreach ($currentmembers as $member) {
        $eligible[$member->id] = $member;
    }
    $memberoptions = [];
    foreach ($eligible as $user) {
        $memberoptions[$user->id] = fullname($user);
    }
    $formurl = new moodle_url($baseurl, ['action' => $action] + ($groupid ? ['groupid' => $groupid] : []));
    $form = new \mod_attendancejourneys\form\course_group(
        $formurl,
        ['courseid' => $course->id, 'memberoptions' => $memberoptions]
    );
    if ($form->is_cancelled()) {
        redirect($baseurl);
    }
    if ($group) {
        $form->set_data((object) [
            'id' => $cm->id,
            'groupid' => $group->id,
            'name' => $group->name,
            'idnumber' => $group->idnumber,
            'description' => $group->description,
            'members' => array_keys($currentmembers),
        ]);
    } else {
        $form->set_data((object) ['id' => $cm->id, 'members' => []]);
    }
    if ($data = $form->get_data()) {
        $record = (object) [
            'courseid' => $course->id,
            'name' => trim($data->name),
            'idnumber' => trim($data->idnumber ?? ''),
            'description' => trim($data->description ?? ''),
            'descriptionformat' => FORMAT_PLAIN,
        ];
        if (!empty($data->groupid)) {
            $record->id = $group->id;
            groups_update_group($record, false, false);
            $savedgroupid = $group->id;
            $message = attendancejourneys_get_string('groupupdated', 'attendancejourneys');
        } else {
            $savedgroupid = groups_create_group($record, false, false);
            $message = attendancejourneys_get_string('groupcreated', 'attendancejourneys');
        }
        $selected = array_map('intval', $data->members ?? []);
        $existingids = array_map('intval', array_keys(groups_get_members($savedgroupid, 'u.id')));
        foreach (array_diff($selected, $existingids) as $userid) {
            groups_add_member($savedgroupid, $userid);
        }
        foreach (array_diff($existingids, $selected) as $userid) {
            groups_remove_member($savedgroupid, $userid);
        }
        redirect($baseurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    attendancejourneys_print_navigation($cm, $context, 'groups');
    echo html_writer::start_div('attendancejourneys-section-header');
    echo html_writer::div(
        attendancejourneys_get_string('courseorganisation', 'attendancejourneys'),
        'attendancejourneys-section-eyebrow'
    );
    echo $OUTPUT->heading(attendancejourneys_get_string(
        $action === 'edit' ? 'editcoursegroup' : 'addcoursegroup',
        'attendancejourneys'
    ));
    echo html_writer::tag(
        'p',
        attendancejourneys_get_string('coursegroupformintro', 'attendancejourneys'),
        ['class' => 'text-muted']
    );
    echo html_writer::end_div();
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

$groups = groups_get_all_groups($course->id, 0, 0, 'g.*');
$membercounts = $DB->get_records_sql_menu("SELECT gm.groupid, COUNT(gm.id)
    FROM {groups_members} gm JOIN {groups} g ON g.id = gm.groupid
    WHERE g.courseid = :courseid GROUP BY gm.groupid", ['courseid' => $course->id]);
$sessioncounts = $DB->get_records_sql_menu("SELECT groupid, COUNT(id) FROM {attendancejourneys_sessions}
    WHERE attendancejourneysid = :activityid AND groupid > 0 GROUP BY groupid", ['activityid' => $cm->instance]);
$journeycounts = $DB->get_records_sql_menu("SELECT groupid, COUNT(id) FROM {attendancejourneys_journeys}
    WHERE attendancejourneysid = :activityid AND groupid > 0 GROUP BY groupid", ['activityid' => $cm->instance]);

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'groups');
echo html_writer::start_div('attendancejourneys-section-header');
echo html_writer::div(
    attendancejourneys_get_string('courseorganisation', 'attendancejourneys'),
    'attendancejourneys-section-eyebrow'
);
echo $OUTPUT->heading(attendancejourneys_get_string('managecoursegroups', 'attendancejourneys'));
echo html_writer::tag(
    'p',
    attendancejourneys_get_string('managecoursegroupsintro', 'attendancejourneys'),
    ['class' => 'text-muted']
);
echo html_writer::end_div();
echo $OUTPUT->single_button(
    new moodle_url($baseurl, ['action' => 'add']),
    attendancejourneys_get_string('addcoursegroup', 'attendancejourneys'),
    'get',
    ['class' => 'mb-3', 'type' => 'primary']
);

if (!$groups) {
    echo html_writer::div(
        html_writer::tag('h3', attendancejourneys_get_string('nocoursegroups', 'attendancejourneys')) .
        html_writer::tag(
            'p',
            attendancejourneys_get_string('nocoursegroups_help', 'attendancejourneys'),
            ['class' => 'text-muted']
        ),
        'attendancejourneys-empty-state'
    );
} else {
    $table = new html_table();
    $table->head = [attendancejourneys_get_string(
        'groupname',
        'attendancejourneys'
    ),
    attendancejourneys_get_string(
        'groupmembers',
        'attendancejourneys'
    ),

        attendancejourneys_get_string(
            'sessions',
            'attendancejourneys'
        ),
    attendancejourneys_get_string(
        'journeys',
        'attendancejourneys'
    ),
    get_string('actions')];
    foreach ($groups as $group) {
        $name = html_writer::tag('strong', format_string($group->name));
        if ($group->idnumber !== '') {
            $name .= html_writer::div(s($group->idnumber), 'text-muted small');
        }
        if ($group->description !== '') {
            $name .= html_writer::div(s($group->description), 'text-muted small mt-1');
        }
        $edit = $OUTPUT->action_icon(new moodle_url(
            $baseurl,
            ['action' => 'edit', 'groupid' => $group->id]
        ), new pix_icon('t/edit', get_string('edit')));
        $table->data[] = [$name, (int) ($membercounts[$group->id] ?? 0),
            (int) ($sessioncounts[$group->id] ?? 0), (int) ($journeycounts[$group->id] ?? 0), $edit];
    }
    echo html_writer::table($table);
}
echo html_writer::tag(
    'p',
    attendancejourneys_get_string(
        'coursegroupsnativehint',
        'attendancejourneys'
    ),
    ['class' => 'text-muted small mt-3']
);
echo $OUTPUT->footer();
