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
 * Integrated, role-aware documentation for Attendance Journeys.
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = optional_param('id', 0, PARAM_INT);
$siteguide = $id === 0;
$selected = optional_param('doc', 'user', PARAM_ALPHA);

if ($siteguide) {
    require_once($CFG->libdir . '/adminlib.php');
    admin_externalpage_setup('modattendancejourneys_siteguide');
    $context = context_system::instance();
    require_capability('moodle/site:config', $context);
    $attendancejourneys = null;
} else {
    $cm = get_coursemodule_from_id('attendancejourneys', $id, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    $attendancejourneys = $DB->get_record('attendancejourneys', ['id' => $cm->instance], '*', MUST_EXIST);
    require_course_login($course, true, $cm);
    $context = context_module::instance($cm->id);
    require_capability('mod/attendancejourneys:view', $context);
    if (!attendancejourneys_documentation_enabled()) {
        redirect(
            new moodle_url('/mod/attendancejourneys/view.php', ['id' => $cm->id]),
            attendancejourneys_get_string('documentationdisablednotice', 'attendancejourneys'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }

    $PAGE->set_url('/mod/attendancejourneys/documentation.php', ['id' => $cm->id, 'doc' => $selected]);
    $PAGE->set_title(attendancejourneys_get_string('documentationtitle', 'attendancejourneys'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($context);
}

$language = attendancejourneys_normalise_terminology_language(current_language());
$files = [
    'fr' => [
        'user' => 'GUIDE_UTILISATEUR_FR.md', 'admin' => 'GUIDE_ADMINISTRATEUR_FR.md',
        'upgrade' => 'MISE_A_NIVEAU_FR.md', 'roles' => 'MATRICE_ROLES_FR.md',
        'modes' => 'MATRICE_MODES_FR.md', 'accessibility' => 'ACCESSIBILITE_FR.md',
        'testing' => 'TESTS_FR.md', 'acceptance' => 'RECETTE_FONCTIONNELLE_FR.md',
    ],
    'en' => [
        'user' => 'USER_GUIDE_EN.md', 'admin' => 'ADMINISTRATOR_GUIDE_EN.md',
        'upgrade' => 'UPGRADE_GUIDE_EN.md', 'roles' => 'ROLE_MATRIX_EN.md',
        'modes' => 'MODE_MATRIX_EN.md', 'accessibility' => 'ACCESSIBILITY_EN.md',
        'testing' => 'TESTING_EN.md', 'acceptance' => 'ACCEPTANCE_TEST_EN.md',
    ],
    'es' => [
        'user' => 'GUIA_USUARIO_ES.md', 'admin' => 'GUIA_ADMINISTRADOR_ES.md',
        'upgrade' => 'GUIA_ACTUALIZACION_ES.md', 'roles' => 'MATRIZ_ROLES_ES.md',
        'modes' => 'MATRIZ_MODOS_ES.md', 'accessibility' => 'ACCESIBILIDAD_ES.md',
        'testing' => 'PRUEBAS_ES.md', 'acceptance' => 'PRUEBA_ACEPTACION_ES.md',
    ],
];
$canmanage = $siteguide || has_capability('moodle/course:manageactivities', context_course::instance($course->id));
$documents = [
    'user' => attendancejourneys_get_string('documentationuser', 'attendancejourneys'),
    'accessibility' => attendancejourneys_get_string('documentationaccessibility', 'attendancejourneys'),
];
if ($canmanage) {
    $documents += [
        'admin' => attendancejourneys_get_string('documentationadmin', 'attendancejourneys'),
        'roles' => attendancejourneys_get_string('documentationroles', 'attendancejourneys'),
        'modes' => attendancejourneys_get_string('documentationmodes', 'attendancejourneys'),
        'testing' => attendancejourneys_get_string('documentationtesting', 'attendancejourneys'),
        'acceptance' => attendancejourneys_get_string('documentationacceptance', 'attendancejourneys'),
    ];
}
if (has_capability('moodle/site:config', context_system::instance())) {
    $documents['upgrade'] = attendancejourneys_get_string('documentationupgrade', 'attendancejourneys');
}
if (!isset($documents[$selected])) {
    $selected = 'user';
}

$filepath = __DIR__ . '/docs/' . $files[$language][$selected];
$markdown = is_readable($filepath) ? file_get_contents($filepath)
    : attendancejourneys_get_string('documentationmissing', 'attendancejourneys');
$documentationassets = (new moodle_url('/mod/attendancejourneys/docs/images'))->out(false);
$markdown = str_replace('](images/', '](' . $documentationassets . '/', $markdown);
foreach ($files[$language] as $documentkey => $documentfile) {
    if (isset($documents[$documentkey])) {
        $documenturl = new moodle_url('/mod/attendancejourneys/documentation.php', [
            'id' => $id, 'doc' => $documentkey,
        ]);
        $markdown = str_replace('](' . $documentfile . ')', '](' . $documenturl->out(false) . ')', $markdown);
    } else {
        // Preserve explanatory text without linking to a document unavailable to this role.
        $markdown = preg_replace('/\[([^\]]+)\]\(' . preg_quote($documentfile, '/') . '\)/', '$1', $markdown);
    }
}
$content = format_text($markdown, FORMAT_MARKDOWN, [
    'context' => $context,
    'noclean' => true,
    'overflowdiv' => true,
]);
$content = attendancejourneys_apply_terminology_to_html($content, $attendancejourneys);

echo $OUTPUT->header();
if (!$siteguide) {
    attendancejourneys_print_navigation($cm, $context, 'documentation');
}
echo html_writer::start_div('attendancejourneys-workspace');
echo html_writer::tag('div', core_text::strtoupper(
    attendancejourneys_get_string('documentationeyebrow', 'attendancejourneys')
), ['class' => 'attendancejourneys-eyebrow']);
echo $OUTPUT->heading(attendancejourneys_get_string('documentationtitle', 'attendancejourneys'));
echo html_writer::tag(
    'p',
    attendancejourneys_get_string('documentationintro', 'attendancejourneys'),
    ['class' => 'text-muted']
);
echo html_writer::start_div('row mt-4 mx-0');
echo html_writer::start_div('col-12 col-lg-3 mb-3');
echo html_writer::start_tag('nav', ['class' => 'list-group',
    'aria-label' => attendancejourneys_get_string('documentationcontents', 'attendancejourneys')]);
foreach ($documents as $key => $label) {
    $classes = 'list-group-item list-group-item-action';
    if ($key === $selected) {
        $classes .= ' active';
    }
    echo html_writer::link(new moodle_url('/mod/attendancejourneys/documentation.php', [
        'id' => $id, 'doc' => $key,
    ]), $label, ['class' => $classes, 'aria-current' => $key === $selected ? 'page' : null]);
}
echo html_writer::end_tag('nav');
echo html_writer::end_div();
echo html_writer::start_div('col-12 col-lg-9');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body attendancejourneys-documentation-content');
echo $content;
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
