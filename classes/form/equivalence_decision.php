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
 * Records an auditable decision on a session equivalence request.
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class equivalence_decision extends \moodleform {
    /**
     * Define the form controls, defaults and parameter types.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        foreach (['id', 'userid', 'equivalenceid'] as $field) {
            $mform->addElement('hidden', $field);
            $mform->setType($field, PARAM_INT);
        }
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement(
            'textarea',
            'decisionnote',
            attendancejourneys_get_string('equivalencedecisionnote', 'attendancejourneys'),
            ['rows' => 4, 'class' => 'w-100']
        );
        $mform->setType('decisionnote', PARAM_TEXT);
        if (!empty($this->_customdata['noterequired'])) {
            $mform->addRule('decisionnote', get_string('required'), 'required', null, 'client');
        }
        $this->add_action_buttons(true, $this->_customdata['submitlabel']);
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
        if (!empty($this->_customdata['noterequired']) && trim($data['decisionnote'] ?? '') === '') {
            $errors['decisionnote'] = get_string('required');
        }
        return $errors;
    }
}
