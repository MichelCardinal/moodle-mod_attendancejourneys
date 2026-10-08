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
 * Site administration settings for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/lib.php');

if ($hassiteconfig) {
    $terminologysettings = new admin_settingpage(
        'modattendancejourneys_terminology',
        get_string('pluginname', 'attendancejourneys') . ' — ' . get_string('adminterminology', 'attendancejourneys')
    );
    $legacysettings = new admin_settingpage(
        'modattendancejourneys_legacy',
        get_string('pluginname', 'attendancejourneys') . ' — ' . get_string('adminlegacy', 'attendancejourneys')
    );
    $ADMIN->add('modsettings', $terminologysettings);
    $ADMIN->add('modsettings', $legacysettings);
    $ADMIN->add('modsettings', new admin_externalpage(
        'modattendancejourneys_siteguide',
        get_string('pluginname', 'attendancejourneys') . ' — ' . get_string('adminsiteguide', 'attendancejourneys'),
        new moodle_url('/mod/attendancejourneys/documentation.php', ['doc' => 'admin'])
    ));
}

if ($hassiteconfig && $ADMIN->fulltree) {
    $links = [];
    foreach (['terminology' => 'adminterminology', 'legacy' => 'adminlegacy', 'siteguide' => 'adminsiteguide'] as $page => $key) {
        $url = $page === 'siteguide' ? new moodle_url('/mod/attendancejourneys/documentation.php', ['doc' => 'admin']) :
            new moodle_url('/admin/settings.php', ['section' => 'modattendancejourneys_' . $page]);
        $links[] = html_writer::link($url, get_string($key, 'attendancejourneys'));
    }
    $settings->add(new admin_setting_heading(
        'mod_attendancejourneys/adminoverview',
        get_string('adminoverview', 'attendancejourneys'),
        get_string('adminoverview_desc', 'attendancejourneys') . '<br><br>' .
        get_string('adminlinks_desc', 'attendancejourneys') . '<br>' . implode(' · ', $links)
    ));
    $legacysettings->add(new admin_setting_heading(
        'mod_attendancejourneys/legacyabsencepolicy',
        get_string('adminlegacy', 'attendancejourneys'),
        get_string('adminlegacy_desc', 'attendancejourneys')
    ));

    $settings->add(new admin_setting_heading(
        'mod_attendancejourneys/integratedhelpcentre',
        get_string('integratedhelpcentre', 'attendancejourneys'),
        get_string('integratedhelpcentre_desc', 'attendancejourneys')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'mod_attendancejourneys/documentationenabled',
        get_string('documentationenabled', 'attendancejourneys'),
        get_string('documentationenabled_desc', 'attendancejourneys'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'mod_attendancejourneys/institutionaldefaults',
        get_string('institutionaldefaults', 'attendancejourneys'),
        get_string('institutionaldefaults_desc', 'attendancejourneys')
    ));

    $settings->add(new \mod_attendancejourneys\admin_setting_calculation_policy());
    $settings->add(new admin_setting_configcheckbox(
        'mod_attendancejourneys/allowjourneythresholds',
        get_string('allowjourneythresholds', 'attendancejourneys'),
        get_string('allowjourneythresholds_desc', 'attendancejourneys'),
        1
    ));

    $policies = [
        'experiencemode' => new admin_setting_configselect(
            'mod_attendancejourneys/default_experiencemode',
            get_string('defaultsetting', 'attendancejourneys', get_string('experiencemode', 'attendancejourneys')),
            get_string('defaultsetting_desc', 'attendancejourneys'),
            'professional',
            [
                'light' => get_string('experiencemodelight', 'attendancejourneys'),
                'professional' => get_string('experiencemodeprofessional', 'attendancejourneys'),
            ]
        ),
        'passinggrade' => new \mod_attendancejourneys\admin_setting_percentage(
            'mod_attendancejourneys/default_passinggrade',
            get_string('defaultsetting', 'attendancejourneys', get_string('passinggrade', 'attendancejourneys')),
            get_string('defaultsetting_desc', 'attendancejourneys'),
            80,
            PARAM_FLOAT,
            6
        ),
        'excusedenabled' => new admin_setting_configcheckbox(
            'mod_attendancejourneys/default_excusedenabled',
            get_string('defaultsetting', 'attendancejourneys', get_string('excusedenabled', 'attendancejourneys')),
            get_string('defaultsetting_desc', 'attendancejourneys'),
            1
        ),
        'excusedmode' => new admin_setting_configselect(
            'mod_attendancejourneys/default_excusedmode',
            get_string('defaultsetting', 'attendancejourneys', get_string('excusedmode', 'attendancejourneys')),
            get_string('defaultsetting_desc', 'attendancejourneys'),
            'excluded',
            [
                'excluded' => get_string('excusedexcluded', 'attendancejourneys'),
                'present' => get_string('excusedpresent', 'attendancejourneys'),
                'absent' => get_string('excusedabsent', 'attendancejourneys'),
            ]
        ),
        'studentselfrecord' => new admin_setting_configcheckbox(
            'mod_attendancejourneys/default_studentselfrecord',
            get_string('defaultsetting', 'attendancejourneys', get_string('studentselfrecord', 'attendancejourneys')),
            get_string('defaultsetting_desc', 'attendancejourneys'),
            0
        ),
        'calendarenabled' => new admin_setting_configcheckbox(
            'mod_attendancejourneys/default_calendarenabled',
            get_string('defaultsetting', 'attendancejourneys', get_string('calendarenabled', 'attendancejourneys')),
            get_string('defaultsetting_desc', 'attendancejourneys'),
            0
        ),
    ];
    $policylabels = [
        'experiencemode' => get_string('experiencemode', 'attendancejourneys'),
        'percentageenabled' => get_string('percentageenabled', 'attendancejourneys'),
        'passinggrade' => get_string('passinggrade', 'attendancejourneys'),
        'gradeenabled' => get_string('gradeenabled', 'attendancejourneys'),
        'excusedenabled' => get_string('excusedenabled', 'attendancejourneys'),
        'excusedmode' => get_string('excusedmode', 'attendancejourneys'),
        'studentselfrecord' => get_string('studentselfrecord', 'attendancejourneys'),
        'calendarenabled' => get_string('calendarenabled', 'attendancejourneys'),
    ];

    foreach ($policies as $field => $setting) {
        // Reuse the field-specific Moodle help shown in the activity form so the
        // institutional default is understandable without opening a course instance.
        $setting->description = get_string('defaultsettingcontext_desc', 'attendancejourneys', (object) [
            'default' => get_string('defaultsetting_desc', 'attendancejourneys'),
            'meaning' => get_string($field . '_help', 'attendancejourneys'),
        ]);
        $targetsettings = $field === 'excusedmode' ? $legacysettings : $settings;
        $targetsettings->add($setting);
        $targetsettings->add(new admin_setting_configcheckbox(
            'mod_attendancejourneys/lock_' . $field,
            get_string('locksetting', 'attendancejourneys', $policylabels[$field]),
            get_string($field === 'passinggrade' ? 'activitythresholdlock_desc' : 'locksetting_desc', 'attendancejourneys'),
            0
        ));
    }

    $terminologysettings->add(new admin_setting_heading(
        'mod_attendancejourneys/institutionalterminology',
        get_string('institutionalterminology', 'attendancejourneys'),
        get_string('institutionalterminology_desc', 'attendancejourneys')
    ));
    foreach (attendancejourneys_terminology_concepts() as $concept => $canonicalids) {
        $terminologysettings->add(new admin_setting_heading(
            'mod_attendancejourneys/terminology_' . $concept,
            get_string(
                'terminologyconcept',
                'attendancejourneys',
                get_string($canonicalids['singular'], 'attendancejourneys')
            ),
            get_string('terminologyconcept_desc', 'attendancejourneys')
        ));
        foreach (attendancejourneys_terminology_languages() as $langcode => $languagename) {
            foreach (['singular', 'plural'] as $number) {
                $canonical = get_string($canonicalids[$number], 'attendancejourneys');
                $terminologysettings->add(new admin_setting_configtext(
                    'mod_attendancejourneys/default_' . $concept . 'term' . $number . '_' . $langcode,
                    get_string('terminologyfield', 'attendancejourneys', (object) [
                        'language' => $languagename,
                        'number' => get_string('term' . $number, 'attendancejourneys'),
                    ]),
                    get_string('terminologyfield_desc', 'attendancejourneys', $canonical),
                    '',
                    PARAM_TEXT,
                    30
                ));
            }
        }
        $terminologysettings->add(new admin_setting_configcheckbox(
            'mod_attendancejourneys/lock_' . $concept . 'terminology',
            get_string(
                'lockconceptterminology',
                'attendancejourneys',
                get_string($canonicalids['singular'], 'attendancejourneys')
            ),
            get_string('lockconceptterminology_desc', 'attendancejourneys'),
            0
        ));
    }
}
