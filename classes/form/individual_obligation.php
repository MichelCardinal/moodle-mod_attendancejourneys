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
 * Propose an explicit individual period or whole-journey waiver.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class individual_obligation extends \moodleform {
    /**
     * Define the proposal controls.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        foreach (['id', 'userid', 'journeyid'] as $field) {
            $mform->addElement('hidden', $field);
            $mform->setType($field, PARAM_INT);
        }
        $mform->addElement('advcheckbox', 'waived', attendancejourneys_get_string('obligationwaive', 'attendancejourneys'));
        $mform->addElement(
            'date_time_selector',
            'starttime',
            attendancejourneys_get_string('obligationstart', 'attendancejourneys'),
            ['optional' => true]
        );
        $mform->addElement(
            'date_time_selector',
            'endtime',
            attendancejourneys_get_string('obligationend', 'attendancejourneys'),
            ['optional' => true]
        );
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
     * Validate dates and require a new justification, including for restoration.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Field errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim($data['reason'] ?? '') === '') {
            $errors['reason'] = get_string('obligationreasonrequired', 'attendancejourneys');
        }
        if (empty($data['waived']) && !empty($data['endtime']) && (int) $data['endtime'] <= (int) ($data['starttime'] ?? 0)) {
            $errors['endtime'] = get_string('obligationinvalidperiod', 'attendancejourneys');
        }
        return $errors;
    }
}
