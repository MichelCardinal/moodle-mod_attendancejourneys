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
 * Preview and explicitly convert a compatible unfinished historical activity.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
$preview = \mod_attendancejourneys\local\legacy_migration::preview($context);
$url = new moodle_url('/mod/attendancejourneys/migration.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('migrationtitle', 'attendancejourneys'));
$PAGE->set_heading(format_string($course->fullname));

if ($action === 'convert') {
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', 'error');
    }
    require_sesskey();
    $fingerprint = required_param('fingerprint', PARAM_ALPHANUM);
    try {
        \mod_attendancejourneys\local\legacy_migration::convert($context, $fingerprint);
    } catch (moodle_exception $exception) {
        if (in_array($exception->errorcode, ['migrationblocked', 'migrationstale'], true)) {
            redirect(
                $url,
                get_string($exception->errorcode, 'attendancejourneys'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
        throw $exception;
    }
    redirect(
        new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]),
        get_string('migrationsuccess', 'attendancejourneys'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
attendancejourneys_print_navigation($cm, $context, 'migration');
echo $OUTPUT->heading(get_string('migrationtitle', 'attendancejourneys'));
echo $OUTPUT->notification(get_string('migrationintro', 'attendancejourneys'), 'info');
$table = new html_table();
$table->head = [get_string('migrationelement', 'attendancejourneys'), get_string('migrationcount', 'attendancejourneys')];
foreach (['sessions', 'independent', 'records', 'closures', 'excused', 'published', 'protected'] as $field) {
    $table->data[] = [get_string('migrationcount' . $field, 'attendancejourneys'), (int) $preview->{$field}];
}
echo attendancejourneys_responsive_table($table, get_string('migrationtitle', 'attendancejourneys'));
if ($preview->blockers) {
    echo $OUTPUT->notification(get_string('migrationblocked', 'attendancejourneys'), 'warning');
    $reasons = array_map(static fn($key) => get_string($key, 'attendancejourneys'), $preview->blockers);
    echo html_writer::alist($reasons);
} else {
    echo $OUTPUT->notification(get_string('migrationmapping', 'attendancejourneys', (object) [
        'name' => format_string($preview->mapping->name),
        'threshold' => format_float($preview->mapping->threshold, 2),
        'audience' => get_string($preview->mapping->audiencemode === 2 ? 'migrationaudienceexplicit' :
            'migrationaudienceautomatic', 'attendancejourneys'),
    ]), 'info');
    echo $OUTPUT->notification(get_string('migrationcompletion', 'attendancejourneys'), 'warning');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'convert']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'fingerprint', 'value' => $preview->fingerprint]);
    echo html_writer::tag(
        'button',
        get_string('migrationconfirm', 'attendancejourneys'),
        ['type' => 'submit', 'class' => 'btn btn-primary']
    );
    echo html_writer::end_tag('form');
}
echo $OUTPUT->footer();
