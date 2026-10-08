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
 * Confirm only the previously displayed proposal, using its consistency token.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class obligation_confirmation extends \moodleform {
    /**
     * Define hidden proposal values and explicit confirmation/correction buttons.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        foreach (['id', 'userid', 'journeyid', 'waived', 'starttime', 'endtime'] as $field) {
            $mform->addElement('hidden', $field);
            $mform->setType($field, PARAM_INT);
        }
        foreach (['token' => PARAM_ALPHANUM, 'reason' => PARAM_TEXT] as $field => $type) {
            $mform->addElement('hidden', $field);
            $mform->setType($field, $type);
        }
        $mform->addElement('submit', 'editproposal', get_string('edit'));
        $this->add_action_buttons(true, attendancejourneys_get_string('obligationconfirm', 'attendancejourneys'));
    }
}
