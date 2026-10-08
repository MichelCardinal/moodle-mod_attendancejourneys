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
require_once($CFG->libdir . '/grouplib.php');
require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');

/**
 * Form used to create and edit an attendance session.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session extends \moodleform {
    /**
     * Defines the session form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sessionid', 0);
        $mform->setType('sessionid', PARAM_INT);

        $mform->addElement('text', 'name', attendancejourneys_get_string('sessionname', 'attendancejourneys'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('date_time_selector', 'sessiondate', attendancejourneys_get_string('sessiondate', 'attendancejourneys'));
        $mform->addHelpButton('sessiondate', 'sessiondate', 'attendancejourneys');
        $mform->addElement('date_time_selector', 'enddate', attendancejourneys_get_string('sessionenddate', 'attendancejourneys'));
        $mform->addHelpButton('enddate', 'sessionenddate', 'attendancejourneys');

        $cm = $this->_customdata['cm'];
        $islight = attendancejourneys_is_light_mode($this->_customdata['attendancejourneys']);
        $journeygrading = !empty($this->_customdata['attendancejourneys']->journeygrading);
        if (!$islight || $journeygrading) {
            $journeyoptions = [0 => $journeygrading ? get_string('choose') :
                attendancejourneys_get_string('independentsession', 'attendancejourneys')];
            foreach ($this->_customdata['journeys'] ?? [] as $journey) {
                $journeyoptions[$journey->id] = format_string($journey->name);
            }
            $mform->addElement(
                'select',
                'journeyid',
                attendancejourneys_get_string(
                    'sessionjourney',
                    'attendancejourneys'
                ),
                $journeyoptions
            );
            $mform->addHelpButton('journeyid', 'sessionjourney', 'attendancejourneys');
            if ($journeygrading && count($this->_customdata['journeys'] ?? []) === 1) {
                $mform->setDefault('journeyid', array_key_first($this->_customdata['journeys']));
            }
        }
        if ($islight) {
            $hiddenfields = [
                'groupid' => [PARAM_INT, 0],
                'modality' => [PARAM_ALPHA, 'unspecified'],
                'roomid' => [PARAM_INT, 0],
                'location' => [PARAM_TEXT, ''],
                'meetingurl' => [PARAM_URL, ''],
            ];
            if (!$journeygrading) {
                $hiddenfields['journeyid'] = [PARAM_INT, 0];
            }
            foreach ($hiddenfields as $field => [$type, $default]) {
                $mform->addElement('hidden', $field, $default);
                $mform->setType($field, $type);
            }
        } else {
            $groups = groups_get_activity_allowed_groups($cm);
            $groupoptions = [0 => attendancejourneys_get_string('allactiveparticipants', 'attendancejourneys')];
            foreach ($groups as $group) {
                $groupoptions[$group->id] = format_string($group->name);
            }
            $mform->addElement(
                'select',
                'groupid',
                attendancejourneys_get_string('sessionaudience', 'attendancejourneys'),
                $groupoptions
            );
            $mform->setDefault('groupid', 0);
            $mform->addHelpButton('groupid', 'sessionaudience', 'attendancejourneys');
            $mform->disabledIf('groupid', 'journeyid', 'neq', 0);

            $mform->addElement('select', 'modality', attendancejourneys_get_string('sessionmodality', 'attendancejourneys'), [
            'unspecified' => attendancejourneys_get_string('modalityunspecified', 'attendancejourneys'),
            'inperson' => attendancejourneys_get_string('modalityinperson', 'attendancejourneys'),
            'online' => attendancejourneys_get_string('modalityonline', 'attendancejourneys'),
            'hybrid' => attendancejourneys_get_string('modalityhybrid', 'attendancejourneys'),
            ]);
            $mform->setDefault('modality', 'unspecified');
            $mform->addHelpButton('modality', 'sessionmodality', 'attendancejourneys');

            $roomoptions = [0 => attendancejourneys_get_string('roomcustomlocation', 'attendancejourneys')];
            foreach ($this->_customdata['rooms'] ?? [] as $room) {
                $label = format_string($room->name);
                if (!empty($room->code)) {
                    $label .= ' — ' . s($room->code);
                }
                $roomoptions[$room->id] = $label;
            }
            $mform->addElement(
                'select',
                'roomid',
                attendancejourneys_get_string('sessionroom', 'attendancejourneys'),
                $roomoptions
            );
            $mform->setDefault('roomid', 0);
            $mform->addHelpButton('roomid', 'sessionroom', 'attendancejourneys');
            $mform->hideIf('roomid', 'modality', 'in', ['unspecified', 'online']);

            $mform->addElement(
                'text',
                'location',
                attendancejourneys_get_string('sessionlocation', 'attendancejourneys'),
                ['size' => 60]
            );
            $mform->setType('location', PARAM_TEXT);
            $mform->addHelpButton('location', 'sessionlocation', 'attendancejourneys');
            $mform->hideIf('location', 'modality', 'in', ['unspecified', 'online']);
            $mform->hideIf('location', 'roomid', 'neq', 0);

            $mform->addElement(
                'text',
                'meetingurl',
                attendancejourneys_get_string(
                    'sessionmeetingurl',
                    'attendancejourneys'
                ),
                ['size' => 60]
            );
            $mform->setType('meetingurl', PARAM_URL);
            $mform->addHelpButton('meetingurl', 'sessionmeetingurl', 'attendancejourneys');
            $mform->hideIf('meetingurl', 'modality', 'in', ['unspecified', 'inperson']);
        }

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 5, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        if (!empty($this->_customdata['series'])) {
            $mform->addElement('header', 'seriesplanning', attendancejourneys_get_string('seriesplanning', 'attendancejourneys'));
            $mform->addElement('select', 'repeatinterval', attendancejourneys_get_string('repeatinterval', 'attendancejourneys'), [
                1 => attendancejourneys_get_string('intervaldaily', 'attendancejourneys'),
                7 => attendancejourneys_get_string('intervalweekly', 'attendancejourneys'),
                14 => attendancejourneys_get_string('intervalbiweekly', 'attendancejourneys'),
                28 => attendancejourneys_get_string('intervalfourweeks', 'attendancejourneys'),
            ]);
            $mform->setDefault('repeatinterval', 7);
            $mform->addHelpButton('repeatinterval', 'repeatinterval', 'attendancejourneys');
            $mform->addElement(
                'text',
                'repeatcount',
                attendancejourneys_get_string('repeatcount', 'attendancejourneys'),
                ['size' => 5]
            );
            $mform->setType('repeatcount', PARAM_INT);
            $mform->setDefault('repeatcount', 5);
            $mform->addHelpButton('repeatcount', 'repeatcount', 'attendancejourneys');
            $mform->addElement('select', 'seriesnamemode', attendancejourneys_get_string('seriesnamemode', 'attendancejourneys'), [
                'same' => attendancejourneys_get_string('seriesnamesame', 'attendancejourneys'),
                'numbered' => attendancejourneys_get_string('seriesnamenumbered', 'attendancejourneys'),
            ]);
            $mform->setDefault('seriesnamemode', 'same');
            $mform->addHelpButton('seriesnamemode', 'seriesnamemode', 'attendancejourneys');
        }

        if (!empty($this->_customdata['series'])) {
            $buttons = [];
            $buttons[] = $mform->createElement(
                'submit',
                'reviewseries',
                attendancejourneys_get_string('reviewseries', 'attendancejourneys'),
                ['class' => 'btn-primary']
            );
            $buttons[] = $mform->createElement(
                'submit',
                'createseriesnow',
                attendancejourneys_get_string('createseriesnow', 'attendancejourneys')
            );
            $buttons[] = $mform->createElement('cancel');
            $mform->addGroup($buttons, 'buttonar', '', [' '], false);
            $mform->closeHeaderBefore('buttonar');
        } else {
            $this->add_action_buttons(true, attendancejourneys_get_string('savesession', 'attendancejourneys'));
        }
    }

    /**
     * Validates session values.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $journeyid = (int) ($data['journeyid'] ?? 0);
        if (
            !empty($this->_customdata['attendancejourneys']->journeygrading) &&
                (!$journeyid || !isset($this->_customdata['journeys'][$journeyid]))
        ) {
            $errors['journeyid'] = get_string('required');
        }
        if ((int) $data['enddate'] <= (int) $data['sessiondate']) {
            $errors['enddate'] = attendancejourneys_get_string('enddateerror', 'attendancejourneys');
        }
        if (
            !empty($this->_customdata['series']) &&
                ((int) ($data['repeatcount'] ?? 0) < 2 || (int) ($data['repeatcount'] ?? 0) > 50)
        ) {
            $errors['repeatcount'] = attendancejourneys_get_string('repeatcounterror', 'attendancejourneys');
        }
        if (
            !empty($this->_customdata['series']) &&
                !in_array($data['seriesnamemode'] ?? '', ['same', 'numbered'], true)
        ) {
            $errors['seriesnamemode'] = attendancejourneys_get_string('invalidseriesnamemode', 'attendancejourneys');
        }
        if (
            !empty($this->_customdata['series']) &&
                !in_array((int) ($data['repeatinterval'] ?? 0), [1, 7, 14, 28], true)
        ) {
            $errors['repeatinterval'] = attendancejourneys_get_string('invalidrepeatinterval', 'attendancejourneys');
        }
        if (!in_array($data['modality'] ?? '', ['unspecified', 'inperson', 'online', 'hybrid'], true)) {
            $errors['modality'] = attendancejourneys_get_string('invalidmodality', 'attendancejourneys');
        }
        if (!empty($data['roomid'])) {
            $rooms = $this->_customdata['rooms'] ?? [];
            if (!isset($rooms[(int) $data['roomid']])) {
                $errors['roomid'] = attendancejourneys_get_string('invalidroom', 'attendancejourneys');
            }
        }
        if (!empty($data['meetingurl']) && !preg_match('#^https?://#i', trim($data['meetingurl']))) {
            $errors['meetingurl'] = attendancejourneys_get_string('invalidmeetingurl', 'attendancejourneys');
        }
        $cm = $this->_customdata['cm'];
        if (!empty($data['groupid'])) {
            $groups = groups_get_activity_allowed_groups($cm);
            if (!isset($groups[(int) $data['groupid']])) {
                $errors['groupid'] = attendancejourneys_get_string('invalidsessiongroup', 'attendancejourneys');
            }
        }
        return $errors;
    }
}
