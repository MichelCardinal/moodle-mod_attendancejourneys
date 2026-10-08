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
 * Adds one enrolled participant to a journey waiting list.
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class waitlist_member extends \moodleform {
    /**
     * Define the form controls, defaults and parameter types.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'journeyid');
        $mform->setType('journeyid', PARAM_INT);
        $options = [];
        foreach ($this->_customdata['participants'] as $participant) {
            $options[$participant->id] = fullname($participant);
        }
        $mform->addElement('autocomplete', 'userid', attendancejourneys_get_string('participant', 'attendancejourneys'), $options);
        $mform->setType('userid', PARAM_INT);
        $mform->addRule('userid', null, 'required', null, 'client');
        $this->add_action_buttons(true, attendancejourneys_get_string('addtowaitlist', 'attendancejourneys'));
    }
}
