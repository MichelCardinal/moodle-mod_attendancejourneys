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

namespace mod_attendancejourneys\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Creates a reviewable cross-journey session equivalence request.
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class equivalence extends \moodleform {
    /**
     * Define the form controls, defaults and parameter types.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        foreach (['id', 'userid'] as $field) {
            $mform->addElement('hidden', $field);
            $mform->setType($field, PARAM_INT);
        }
        $mform->addElement('hidden', 'action', 'addequivalence');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement(
            'select',
            'targetsessionid',
            attendancejourneys_get_string('equivalencetargetsession', 'attendancejourneys'),
            $this->_customdata['targets']
        );
        $mform->setType('targetsessionid', PARAM_INT);
        $mform->addRule('targetsessionid', null, 'required', null, 'client');
        $mform->addElement(
            'select',
            'sourcesessionid',
            attendancejourneys_get_string('equivalencesourcesession', 'attendancejourneys'),
            $this->_customdata['sources']
        );
        $mform->setType('sourcesessionid', PARAM_INT);
        $mform->addRule('sourcesessionid', null, 'required', null, 'client');
        $mform->addElement(
            'textarea',
            'reason',
            attendancejourneys_get_string('equivalencereason', 'attendancejourneys'),
            ['rows' => 4, 'class' => 'w-100']
        );
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', get_string('required'), 'required', null, 'client');
        $this->add_action_buttons(true, attendancejourneys_get_string('submitequivalence', 'attendancejourneys'));
    }

    /**
     * Validate the submitted values against the activity rules.
     *
     * @param array $data Submitted form values.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim($data['reason'] ?? '') === '') {
            $errors['reason'] = get_string('required');
        }
        return $errors;
    }
}
