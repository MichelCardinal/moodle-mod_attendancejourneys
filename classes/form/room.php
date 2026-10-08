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

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');

/**
 * Form for a reusable course-local room.
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class room extends \moodleform {
    /**
     * Define the form controls, defaults and parameter types.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'roomid', 0);
        $mform->setType('roomid', PARAM_INT);

        $mform->addElement('text', 'name', attendancejourneys_get_string('roomname', 'attendancejourneys'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('text', 'code', attendancejourneys_get_string('roomcode', 'attendancejourneys'), ['size' => 25]);
        $mform->setType('code', PARAM_TEXT);
        $mform->addHelpButton('code', 'roomcode', 'attendancejourneys');
        $mform->addElement('text', 'capacity', attendancejourneys_get_string('roomcapacity', 'attendancejourneys'), ['size' => 8]);
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 0);
        $mform->addHelpButton('capacity', 'roomcapacity', 'attendancejourneys');
        $mform->addElement('text', 'location', attendancejourneys_get_string('roomlocation', 'attendancejourneys'), ['size' => 60]);
        $mform->setType('location', PARAM_TEXT);
        $mform->addHelpButton('location', 'roomlocation', 'attendancejourneys');
        $mform->addElement(
            'textarea',
            'notes',
            attendancejourneys_get_string('roomnotes', 'attendancejourneys'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('notes', PARAM_TEXT);
        $mform->addElement('advcheckbox', 'active', attendancejourneys_get_string('roomactive', 'attendancejourneys'));
        $mform->setDefault('active', 1);
        $mform->addHelpButton('active', 'roomactive', 'attendancejourneys');
        $this->add_action_buttons(true, attendancejourneys_get_string('saveroom', 'attendancejourneys'));
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
        if ((int) ($data['capacity'] ?? 0) < 0) {
            $errors['capacity'] = attendancejourneys_get_string('roomcapacityerror', 'attendancejourneys');
        }
        return $errors;
    }
}
