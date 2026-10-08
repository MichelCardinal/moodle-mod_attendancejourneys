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
 * Propose a distinct personal attendance attempt before any grade is withdrawn.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_proposal extends \moodleform {
    /**
     * Define the proposed name and required justification.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        foreach (['id', 'userid', 'rootjourneyid'] as $field) {
            $mform->addElement('hidden', $field);
            $mform->setType($field, PARAM_INT);
        }
        $mform->addElement(
            'text',
            'name',
            attendancejourneys_get_string('attemptname', 'attendancejourneys'),
            ['maxlength' => 255]
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addElement(
            'textarea',
            'reason',
            attendancejourneys_get_string('obligationreason', 'attendancejourneys'),
            ['rows' => 4, 'class' => 'w-100']
        );
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', get_string('required'), 'required', null, 'client');
        $this->add_action_buttons(true, attendancejourneys_get_string('obligationpreview', 'attendancejourneys'));
    }

    /**
     * Validate the name and explanation before displaying a proposal.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Field errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (['name', 'reason'] as $field) {
            if (trim($data[$field] ?? '') === '') {
                $errors[$field] = get_string('required');
            }
        }
        if (\core_text::strlen(trim($data['name'] ?? '')) > 255) {
            $errors['name'] = get_string('attemptdetailsrequired', 'attendancejourneys');
        }
        return $errors;
    }
}
