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
 * Creates or edits a native Moodle course group from the Attendance Journeys workspace.
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_group extends \moodleform {
    /**
     * Define the form controls, defaults and parameter types.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'groupid', 0);
        $mform->setType('groupid', PARAM_INT);

        $mform->addElement('text', 'name', attendancejourneys_get_string('groupname', 'attendancejourneys'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement(
            'text',
            'idnumber',
            attendancejourneys_get_string('groupidnumber', 'attendancejourneys'),
            ['size' => 30]
        );
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addHelpButton('idnumber', 'groupidnumber', 'attendancejourneys');
        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement(
            'autocomplete',
            'members',
            attendancejourneys_get_string('groupmembers', 'attendancejourneys'),
            $this->_customdata['memberoptions'] ?? [],
            ['multiple' => true]
        );
        $mform->setType('members', PARAM_INT);
        $mform->addHelpButton('members', 'groupmembers', 'attendancejourneys');

        $this->add_action_buttons(true, attendancejourneys_get_string('savegroup', 'attendancejourneys'));
    }

    /**
     * Validate the submitted values against the activity rules.
     *
     * @param array $data Submitted form values.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        $courseid = (int) $this->_customdata['courseid'];
        $groupid = (int) ($data['groupid'] ?? 0);
        $name = trim($data['name'] ?? '');
        $existing = $DB->get_record('groups', ['courseid' => $courseid, 'name' => $name]);
        if ($existing && (int) $existing->id !== $groupid) {
            $errors['name'] = attendancejourneys_get_string('groupnameduplicate', 'attendancejourneys');
        }
        $idnumber = trim($data['idnumber'] ?? '');
        if ($idnumber !== '') {
            $existing = $DB->get_record('groups', ['courseid' => $courseid, 'idnumber' => $idnumber]);
            if ($existing && (int) $existing->id !== $groupid) {
                $errors['idnumber'] = attendancejourneys_get_string('groupidnumberduplicate', 'attendancejourneys');
            }
        }
        $allowedmembers = $this->_customdata['memberoptions'] ?? [];
        foreach ($data['members'] ?? [] as $userid) {
            if (!isset($allowedmembers[(int) $userid])) {
                $errors['members'] = attendancejourneys_get_string('invalidgroupmember', 'attendancejourneys');
                break;
            }
        }
        return $errors;
    }
}
