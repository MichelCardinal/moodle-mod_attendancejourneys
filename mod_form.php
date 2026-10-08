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
 * Activity configuration form for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');

/**
 * Attendance Journeys module form.
 */
class mod_attendancejourneys_mod_form extends moodleform_mod {
    /**
     * Defines the form.
     */
    public function definition() {
        global $COURSE;
        $mform = $this->_form;
        $policies = attendancejourneys_get_admin_policies();
        $applypolicy = static function (string $field) use ($mform, $policies): void {
            if ($policies[$field]['locked']) {
                $mform->setConstant($field, $policies[$field]['default']);
                $mform->freeze($field);
                $mform->addElement(
                    'static',
                    $field . '_policy',
                    '',
                    attendancejourneys_get_string('settinglockedbysite', 'attendancejourneys')
                );
            }
        };

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', attendancejourneys_get_string('activityname', 'attendancejourneys'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'experience', attendancejourneys_get_string('experience', 'attendancejourneys'));
        $mform->addElement('select', 'experiencemode', attendancejourneys_get_string('experiencemode', 'attendancejourneys'), [
            'light' => attendancejourneys_get_string('experiencemodelight', 'attendancejourneys'),
            'professional' => attendancejourneys_get_string('experiencemodeprofessional', 'attendancejourneys'),
        ]);
        $mform->setDefault('experiencemode', $policies['experiencemode']['default']);
        $mform->addHelpButton('experiencemode', 'experiencemode', 'attendancejourneys');
        $applypolicy('experiencemode');

        $mform->addElement('header', 'calculations', attendancejourneys_get_string('calculations', 'attendancejourneys'));
        $mform->addElement(
            'advcheckbox',
            'percentageenabled',
            attendancejourneys_get_string('percentageenabled', 'attendancejourneys')
        );
        $mform->setDefault('percentageenabled', $policies['percentageenabled']['default']);
        $mform->addHelpButton('percentageenabled', 'percentageenabled', 'attendancejourneys');
        $applypolicy('percentageenabled');

        $mform->addElement(
            'text',
            'passinggrade',
            attendancejourneys_get_string('passinggrade', 'attendancejourneys'),
            ['size' => 6]
        );
        $mform->setType('passinggrade', PARAM_FLOAT);
        $mform->setDefault('passinggrade', $policies['passinggrade']['default']);
        $mform->addRule('passinggrade', null, 'numeric', null, 'client');
        $mform->addHelpButton('passinggrade', 'passinggrade', 'attendancejourneys');
        $applypolicy('passinggrade');
        $mform->hideIf('passinggrade', 'percentageenabled', 'notchecked');

        $mform->addElement('advcheckbox', 'gradeenabled', attendancejourneys_get_string('gradeenabled', 'attendancejourneys'));
        $mform->setDefault('gradeenabled', $policies['gradeenabled']['default']);
        $mform->addHelpButton('gradeenabled', 'gradeenabled', 'attendancejourneys');
        $applypolicy('gradeenabled');
        $mform->hideIf('gradeenabled', 'percentageenabled', 'notchecked');

        $mform->addElement('advcheckbox', 'excusedenabled', attendancejourneys_get_string('excusedenabled', 'attendancejourneys'));
        $mform->setDefault('excusedenabled', $policies['excusedenabled']['default']);
        $mform->addHelpButton('excusedenabled', 'excusedenabled', 'attendancejourneys');
        $applypolicy('excusedenabled');

        if (empty($this->current->instance) || !empty($this->current->journeygrading)) {
            $mform->addElement('static', 'journeyabsencepolicy', '', get_string('journeyabsencepolicy', 'attendancejourneys'));
            $mform->addElement('hidden', 'excusedmode', 'absent');
            $mform->setType('excusedmode', PARAM_ALPHA);
            $mform->setConstant('excusedmode', 'absent');
        } else {
            $excusedoptions = [
                'excluded' => attendancejourneys_get_string('excusedexcluded', 'attendancejourneys'),
                'present' => attendancejourneys_get_string('excusedpresent', 'attendancejourneys'),
                'absent' => attendancejourneys_get_string('excusedabsent', 'attendancejourneys'),
            ];
            $mform->addElement(
                'select',
                'excusedmode',
                attendancejourneys_get_string('excusedmode', 'attendancejourneys'),
                $excusedoptions
            );
            $mform->setDefault('excusedmode', $policies['excusedmode']['default']);
            $mform->addHelpButton('excusedmode', 'excusedmode', 'attendancejourneys');
            $applypolicy('excusedmode');
            $mform->hideIf('excusedmode', 'excusedenabled', 'notchecked');
        }

        $mform->addElement('header', 'permissions', attendancejourneys_get_string('attendancepermissions', 'attendancejourneys'));
        $mform->addElement(
            'advcheckbox',
            'studentselfrecord',
            attendancejourneys_get_string('studentselfrecord', 'attendancejourneys')
        );
        $mform->setDefault('studentselfrecord', $policies['studentselfrecord']['default']);
        $mform->addHelpButton('studentselfrecord', 'studentselfrecord', 'attendancejourneys');
        $applypolicy('studentselfrecord');

        $mform->addElement(
            'header',
            'calendarintegration',
            attendancejourneys_get_string('calendarintegration', 'attendancejourneys')
        );
        $mform->addElement(
            'advcheckbox',
            'calendarenabled',
            attendancejourneys_get_string('calendarenabled', 'attendancejourneys')
        );
        $mform->setDefault('calendarenabled', $policies['calendarenabled']['default']);
        $mform->addHelpButton('calendarenabled', 'calendarenabled', 'attendancejourneys');
        $applypolicy('calendarenabled');

        // Keep specialised vocabulary available without dominating the main activity form.
        $mform->addElement('header', 'terminology', attendancejourneys_get_string('terminology', 'attendancejourneys'));
        $mform->setExpanded('terminology', false);
        $termconfig = get_config('mod_attendancejourneys');
        $stringmanager = get_string_manager();
        $languages = attendancejourneys_terminology_languages();
        $currentlanguage = attendancejourneys_normalise_terminology_language(
            !empty($COURSE->lang) ? $COURSE->lang : current_language()
        );
        $orderedlanguages = [$currentlanguage => $languages[$currentlanguage]] + $languages;
        $anylocked = false;
        foreach ($orderedlanguages as $langcode => $languagename) {
            $advanced = $langcode !== $currentlanguage;
            $languageelement = 'terminologylanguage_' . $langcode;
            $mform->addElement('static', $languageelement, '', html_writer::tag(
                'strong',
                attendancejourneys_get_string(
                    $advanced ? 'terminologyadditionallanguage' : 'terminologycurrentlanguage',
                    'attendancejourneys',
                    $languagename
                )
            ));
            if ($advanced) {
                $mform->setAdvanced($languageelement);
            }
            $columnselement = 'terminologycolumns_' . $langcode;
            $mform->addElement('static', $columnselement, '', html_writer::div(
                html_writer::span(
                    attendancejourneys_get_string('termsingular', 'attendancejourneys'),
                    'w-50 font-weight-bold'
                ) .
                html_writer::span(
                    attendancejourneys_get_string('termplural', 'attendancejourneys'),
                    'w-50 font-weight-bold'
                ),
                'd-flex'
            ));
            if ($advanced) {
                $mform->setAdvanced($columnselement);
            }
            foreach (attendancejourneys_terminology_concepts() as $concept => $canonicalids) {
                $termlocked = !empty($termconfig->{'lock_' . $concept . 'terminology'});
                $anylocked = $anylocked || $termlocked;
                $singularfield = $concept . 'termsingular_' . $langcode;
                $pluralfield = $concept . 'termplural_' . $langcode;
                $singularcanonical = $stringmanager->get_string(
                    $canonicalids['singular'],
                    'attendancejourneys',
                    null,
                    $langcode
                );
                $pluralcanonical = $stringmanager->get_string(
                    $canonicalids['plural'],
                    'attendancejourneys',
                    null,
                    $langcode
                );
                $singularinstitutional = trim((string) ($termconfig->{
                    'default_' . $concept . 'termsingular_' . $langcode} ?? ''));
                $pluralinstitutional = trim((string) ($termconfig->{
                    'default_' . $concept . 'termplural_' . $langcode} ?? ''));
                $rowname = 'terminologyrow_' . $concept . '_' . $langcode;
                $mform->addGroup(
                    [
                    $mform->createElement('text', $singularfield, '', [
                        'size' => 20, 'maxlength' => 100,
                        'placeholder' => $singularinstitutional !== '' ? $singularinstitutional : $singularcanonical,
                        'aria-label' => attendancejourneys_get_string('termsingular', 'attendancejourneys'),
                    ]),
                    $mform->createElement('text', $pluralfield, '', [
                        'size' => 20, 'maxlength' => 100,
                        'placeholder' => $pluralinstitutional !== '' ? $pluralinstitutional : $pluralcanonical,
                        'aria-label' => attendancejourneys_get_string('termplural', 'attendancejourneys'),
                    ]),
                    ],
                    $rowname,
                    attendancejourneys_get_string($canonicalids['singular'], 'attendancejourneys'),
                    [' '],
                    false
                );
                $mform->setType($singularfield, PARAM_TEXT);
                $mform->setType($pluralfield, PARAM_TEXT);
                $mform->addHelpButton($rowname, 'terminologyfield', 'attendancejourneys');
                if ($advanced) {
                    $mform->setAdvanced($rowname);
                }
                if ($termlocked) {
                    $mform->setConstant($singularfield, $singularinstitutional);
                    $mform->setConstant($pluralfield, $pluralinstitutional);
                    $mform->freeze([$singularfield, $pluralfield]);
                }
            }
        }
        if ($anylocked) {
            $mform->addElement(
                'static',
                'terminology_policy',
                '',
                attendancejourneys_get_string('settinglockedbysite', 'attendancejourneys')
            );
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validates plugin-specific settings.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $effective = attendancejourneys_apply_admin_policies((object) $data);
        $effective->journeygrading = empty($this->current->instance) || !empty($this->current->journeygrading);
        if (attendancejourneys_journey_requires_percentage($effective)) {
            $errors['percentageenabled'] = get_string('journeyrequirespercentage', 'attendancejourneys');
        }
        if (!empty($effective->gradeenabled) && empty($effective->percentageenabled)) {
            $errors['percentageenabled'] = get_string('gradecalculationrequired', 'attendancejourneys');
        }
        if (
            $effective->journeygrading && (int) ($data['completion'] ?? 0) === COMPLETION_TRACKING_AUTOMATIC &&
                empty($data['completionpass' . $this->get_suffix()])
        ) {
            $errors['completionpass' . $this->get_suffix()] = get_string('completionjourneysrequired', 'attendancejourneys');
        }
        if (
            !empty($data['percentageenabled']) &&
                ($data['passinggrade'] < 0 || $data['passinggrade'] > 100)
        ) {
            $errors['passinggrade'] = attendancejourneys_get_string('passinggradeerror', 'attendancejourneys');
        }
        return $errors;
    }

    /**
     * Loads language-specific terminology rows into the standard activity form.
     *
     * @param array $defaultvalues Form defaults to update in place.
     */
    public function data_preprocessing(&$defaultvalues): void {
        global $DB;
        parent::data_preprocessing($defaultvalues);
        $instanceid = (int) ($this->current->instance ?? 0);
        if (!$instanceid) {
            return;
        }
        foreach ($DB->get_records('attendancejourneys_terms', ['attendancejourneysid' => $instanceid]) as $term) {
            if (array_key_exists($term->langcode, attendancejourneys_terminology_languages())) {
                $concept = $term->concept ?? 'journey';
                if (array_key_exists($concept, attendancejourneys_terminology_concepts())) {
                    $defaultvalues[$concept . 'termsingular_' . $term->langcode] = $term->singular;
                    $defaultvalues[$concept . 'termplural_' . $term->langcode] = $term->plural;
                }
            }
        }
    }

    /**
     * Adds attendance-specific completion rules to Moodle's standard completion section.
     *
     * @return array Form element names.
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $suffix = $this->get_suffix();
        $rules = [];
        if (empty($this->current->instance) || !empty($this->current->journeygrading)) {
            foreach (['completionrecorded', 'completionallsessions', 'completionclosed'] as $legacyrule) {
                $mform->addElement('hidden', $legacyrule . $suffix, 0);
                $mform->setType($legacyrule . $suffix, PARAM_INT);
                $mform->setConstant($legacyrule . $suffix, 0);
            }
            $element = 'completionpass' . $suffix;
            $mform->addElement('checkbox', $element, '', get_string('completionjourneys', 'attendancejourneys'));
            $mform->addHelpButton($element, 'completionjourneys', 'attendancejourneys');
            $mform->setDefault($element, 1);
            $mform->hideIf($element, 'percentageenabled', 'notchecked');
            return [$element];
        }

        foreach (['completionrecorded', 'completionallsessions', 'completionclosed', 'completionpass'] as $rule) {
            $element = $rule . $suffix;
            $label = $rule === 'completionclosed'
                ? attendancejourneys_get_string($rule, 'attendancejourneys') : attendancejourneys_get_string(
                    $rule,
                    'attendancejourneys'
                );
            $mform->addElement('checkbox', $element, '', $label);
            $mform->addHelpButton($element, $rule, 'attendancejourneys');
            $rules[] = $element;
        }
        $mform->hideIf('completionpass' . $suffix, 'percentageenabled', 'notchecked');
        return $rules;
    }

    /**
     * Reports whether at least one custom completion rule is enabled.
     *
     * @param array $data Submitted form data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        $suffix = $this->get_suffix();
        return !empty($data['completionrecorded' . $suffix]) ||
            !empty($data['completionallsessions' . $suffix]) ||
            !empty($data['completionclosed' . $suffix]) ||
            !empty($data['completionpass' . $suffix]);
    }
}
