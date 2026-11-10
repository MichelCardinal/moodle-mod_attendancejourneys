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
 * Form for adding or removing participants to a journey before attendance begins.
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class journey_member extends \moodleform {
    /**
     * Define the form controls, defaults and parameter types.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $removing = !empty($this->_customdata['removing']);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'journeyid');
        $mform->setType('journeyid', PARAM_INT);
        $options = [];
        foreach ($this->_customdata['participants'] as $participant) {
            $options[$participant->id] = fullname($participant);
        }
        $mform->addElement(
            'autocomplete',
            'userids',
            get_string('participants', 'attendancejourneys'),
            $options,
            ['multiple' => true]
        );
        $mform->setType('userids', PARAM_INT);
        if (!empty($this->_customdata['capacity'])) {
            $mform->addElement(
                'advcheckbox',
                'allowovercapacity',
                attendancejourneys_get_string('journeyallowmemberovercapacity', 'attendancejourneys')
            );
            $mform->addHelpButton('allowovercapacity', 'journeyallowmemberovercapacity', 'attendancejourneys');
        }
        if ($removing) {
            $mform->addElement(
                'advcheckbox',
                'confirmremoval',
                get_string('confirmjourneyparticipantsremoval', 'attendancejourneys')
            );
        }
        $this->add_action_buttons(
            true,
            get_string($removing ? 'removejourneyparticipants' : 'addjourneyparticipants', 'attendancejourneys')
        );
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
        $removing = !empty($this->_customdata['removing']);
        // Reject the whole removal if Moodle has filtered any unavailable options out of the selection.
        $selected = $removing ? $this->_form->getSubmitValue('userids') : ($data['userids'] ?? []);
        $userids = array_unique(array_map('intval', (array) $selected));
        if (empty($data['userids'])) {
            $errors['userids'] = get_string('required');
        } else if (array_diff($userids, array_keys($this->_customdata['participants']))) {
            $errors['userids'] = get_string('invaliduser', 'error');
        }
        if ($removing && empty($data['confirmremoval'])) {
            $errors['confirmremoval'] = get_string('required');
        }
        $capacity = (int) $this->_customdata['capacity'];
        if (
            $capacity && $this->_customdata['occupancy'] + count($userids) > $capacity && empty($data['allowovercapacity'])
        ) {
            $errors['allowovercapacity'] = get_string('journeybulkcapacityoverrideconfirm', 'attendancejourneys');
        }
        return $errors;
    }
}
