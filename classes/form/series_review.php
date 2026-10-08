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
 * Review a generated session series before it is saved.
 *
 * @package    mod_attendancejourneys
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class series_review extends \moodleform {
    /**
     * Defines the review form.
     */
    public function definition() {
        $mform = $this->_form;
        $count = (int) $this->_customdata['count'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'draft');
        $mform->setType('draft', PARAM_ALPHANUM);

        $mform->addElement('static', 'reviewhelp', '', attendancejourneys_get_string('seriesreviewhelp', 'attendancejourneys'));
        $selectionbuttons = [];
        $selectionbuttons[] = $mform->createElement(
            'submit',
            'selectallbulk',
            attendancejourneys_get_string('seriesselectall', 'attendancejourneys'),
            ['class' => 'btn btn-secondary']
        );
        $selectionbuttons[] = $mform->createElement(
            'submit',
            'clearbulkselection',
            attendancejourneys_get_string('seriesselectnone', 'attendancejourneys'),
            ['class' => 'btn btn-secondary']
        );
        $selectionbuttons[] = $mform->createElement(
            'submit',
            'sortbydate',
            attendancejourneys_get_string('seriessortbydate', 'attendancejourneys'),
            ['class' => 'btn btn-secondary']
        );
        $mform->addGroup(
            $selectionbuttons,
            'selectionbuttons',
            attendancejourneys_get_string('seriesselection', 'attendancejourneys'),
            [' '],
            false
        );

        $mform->addElement('header', 'bulkactions', attendancejourneys_get_string('seriesbulkactions', 'attendancejourneys'));
        $mform->addElement('select', 'bulkoperation', attendancejourneys_get_string('seriesbulkoperation', 'attendancejourneys'), [
            'number' => attendancejourneys_get_string('seriesbulknumber', 'attendancejourneys'),
            'rename' => attendancejourneys_get_string('seriesbulkrename', 'attendancejourneys'),
            'duration' => attendancejourneys_get_string('seriesbulkduration', 'attendancejourneys'),
            'modality' => attendancejourneys_get_string('seriesbulkmodality', 'attendancejourneys'),
            'location' => attendancejourneys_get_string('seriesbulklocation', 'attendancejourneys'),
            'meetingurl' => attendancejourneys_get_string('seriesbulkmeetingurl', 'attendancejourneys'),
            'shiftdays' => attendancejourneys_get_string('seriesbulkshiftdays', 'attendancejourneys'),
            'shiftminutes' => attendancejourneys_get_string('seriesbulkshiftminutes', 'attendancejourneys'),
        ]);
        $mform->addElement(
            'text',
            'bulkvalue',
            attendancejourneys_get_string('seriesbulkvalue', 'attendancejourneys'),
            ['size' => 35]
        );
        $mform->setType('bulkvalue', PARAM_TEXT);
        $mform->addHelpButton('bulkvalue', 'seriesbulkvalue', 'attendancejourneys');
        $mform->hideIf('bulkvalue', 'bulkoperation', 'eq', 'modality');
        $mform->addElement('select', 'bulkmodality', attendancejourneys_get_string('sessionmodality', 'attendancejourneys'), [
            'unspecified' => attendancejourneys_get_string('modalityunspecified', 'attendancejourneys'),
            'inperson' => attendancejourneys_get_string('modalityinperson', 'attendancejourneys'),
            'online' => attendancejourneys_get_string('modalityonline', 'attendancejourneys'),
            'hybrid' => attendancejourneys_get_string('modalityhybrid', 'attendancejourneys'),
        ]);
        $mform->hideIf('bulkmodality', 'bulkoperation', 'neq', 'modality');
        $mform->addElement('submit', 'applybulk', attendancejourneys_get_string('seriesbulkapply', 'attendancejourneys'));

        $repeatarray = [];
        $repeatarray[] = $mform->createElement(
            'header',
            'occurrenceheader',
            attendancejourneys_get_string('seriesoccurrence', 'attendancejourneys')
        );
        $repeatarray[] = $mform->createElement(
            'advcheckbox',
            'included',
            attendancejourneys_get_string('includesession', 'attendancejourneys')
        );
        $repeatarray[] = $mform->createElement(
            'advcheckbox',
            'selected',
            attendancejourneys_get_string('selectforbulk', 'attendancejourneys')
        );
        $repeatarray[] = $mform->createElement(
            'text',
            'sessionname',
            attendancejourneys_get_string('sessionname', 'attendancejourneys'),
            ['size' => 45]
        );
        $repeatarray[] = $mform->createElement(
            'date_time_selector',
            'sessionstart',
            attendancejourneys_get_string('sessiondate', 'attendancejourneys')
        );
        $repeatarray[] = $mform->createElement(
            'date_time_selector',
            'sessionend',
            attendancejourneys_get_string('sessionenddate', 'attendancejourneys')
        );
        $repeatarray[] = $mform->createElement(
            'select',
            'sessionmodality',
            attendancejourneys_get_string('sessionmodality', 'attendancejourneys'),
            [
                'unspecified' => attendancejourneys_get_string('modalityunspecified', 'attendancejourneys'),
                'inperson' => attendancejourneys_get_string('modalityinperson', 'attendancejourneys'),
                'online' => attendancejourneys_get_string('modalityonline', 'attendancejourneys'),
                'hybrid' => attendancejourneys_get_string('modalityhybrid', 'attendancejourneys'),
            ]
        );
        $repeatarray[] = $mform->createElement(
            'text',
            'sessionlocation',
            attendancejourneys_get_string('sessionlocation', 'attendancejourneys'),
            ['size' => 45]
        );
        $repeatarray[] = $mform->createElement(
            'text',
            'sessionmeetingurl',
            attendancejourneys_get_string('sessionmeetingurl', 'attendancejourneys'),
            ['size' => 45]
        );
        $repeatarray[] = $mform->createElement(
            'textarea',
            'sessiondescription',
            get_string('description'),
            ['rows' => 3, 'cols' => 45]
        );

        $options = [
            'included' => ['default' => 1, 'type' => PARAM_BOOL],
            'selected' => ['default' => 0, 'type' => PARAM_BOOL],
            'sessionname' => ['type' => PARAM_TEXT],
            'sessionmodality' => ['type' => PARAM_ALPHA],
            'sessionlocation' => ['type' => PARAM_TEXT],
            'sessionmeetingurl' => ['type' => PARAM_URL],
            'sessiondescription' => ['type' => PARAM_TEXT],
        ];
        $this->repeat_elements(
            $repeatarray,
            $count,
            $options,
            'occurrencecount',
            'addoccurrences',
            1,
            attendancejourneys_get_string('addanothersession', 'attendancejourneys'),
            true
        );

        $mform->addElement(
            'advcheckbox',
            'allowoverlap',
            attendancejourneys_get_string('seriesallowoverlap', 'attendancejourneys')
        );
        $mform->addHelpButton('allowoverlap', 'seriesallowoverlap', 'attendancejourneys');

        $this->add_action_buttons(true, attendancejourneys_get_string('confirmcreateseries', 'attendancejourneys'));
    }

    /**
     * Validates every included occurrence.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $count = (int) ($data['occurrencecount'] ?? 0);
        if ($count > 50) {
            $errors['reviewhelp'] = attendancejourneys_get_string('repeatcounterror', 'attendancejourneys');
        }
        $included = 0;
        for ($index = 0; $index < $count; $index++) {
            if (empty($data['included'][$index])) {
                continue;
            }
            $included++;
            if (trim($data['sessionname'][$index] ?? '') === '') {
                $errors['sessionname[' . $index . ']'] = get_string('required');
            }
            if ((int) ($data['sessionend'][$index] ?? 0) <= (int) ($data['sessionstart'][$index] ?? 0)) {
                $errors['sessionend[' . $index . ']'] = attendancejourneys_get_string('enddateerror', 'attendancejourneys');
            }
            if (
                !in_array(
                    $data['sessionmodality'][$index] ?? '',
                    ['unspecified', 'inperson', 'online', 'hybrid'],
                    true
                )
            ) {
                $errors['sessionmodality[' . $index . ']'] = attendancejourneys_get_string('invalidmodality', 'attendancejourneys');
            }
            $meetingurl = trim($data['sessionmeetingurl'][$index] ?? '');
            if ($meetingurl !== '' && !preg_match('#^https?://#i', $meetingurl)) {
                $errors['sessionmeetingurl[' . $index . ']'] = attendancejourneys_get_string(
                    'invalidmeetingurl',
                    'attendancejourneys'
                );
            }
        }
        if ($included === 0) {
            $errors['included[0]'] = attendancejourneys_get_string('seriesrequiresession', 'attendancejourneys');
        }
        if (!empty($data['applybulk'])) {
            $operation = $data['bulkoperation'] ?? '';
            $value = trim($data['bulkvalue'] ?? '');
            if (
                !in_array($operation, ['number', 'rename', 'duration', 'modality', 'location', 'meetingurl',
                    'shiftdays', 'shiftminutes'], true)
            ) {
                $errors['bulkoperation'] = attendancejourneys_get_string('invalidseriesbulkoperation', 'attendancejourneys');
            }
            if ($operation !== 'modality' && $value === '') {
                $errors['bulkvalue'] = get_string('required');
            } else if (
                $operation === 'duration' && (!ctype_digit($value) || (int) $value < 1 ||
                    (int) $value > 1440)
            ) {
                $errors['bulkvalue'] = attendancejourneys_get_string('seriesbulkinvalidduration', 'attendancejourneys');
            } else if ($operation === 'meetingurl' && !preg_match('#^https?://#i', $value)) {
                $errors['bulkvalue'] = attendancejourneys_get_string('invalidmeetingurl', 'attendancejourneys');
            } else if (
                $operation === 'shiftdays' && (!preg_match('/^-?\d+$/', $value) ||
                    (int) $value === 0 || abs((int) $value) > 366)
            ) {
                $errors['bulkvalue'] = attendancejourneys_get_string('seriesbulkinvaliddayshift', 'attendancejourneys');
            } else if (
                $operation === 'shiftminutes' && (!preg_match('/^-?\d+$/', $value) ||
                    (int) $value === 0 || abs((int) $value) > 1440)
            ) {
                $errors['bulkvalue'] = attendancejourneys_get_string('seriesbulkinvalidminuteshift', 'attendancejourneys');
            }
            if (
                $operation === 'modality' && !in_array(
                    $data['bulkmodality'] ?? '',
                    ['unspecified', 'inperson', 'online', 'hybrid'],
                    true
                )
            ) {
                $errors['bulkmodality'] = attendancejourneys_get_string('invalidmodality', 'attendancejourneys');
            }
            if (!array_filter($data['selected'] ?? [])) {
                $errors['bulkoperation'] = attendancejourneys_get_string('seriesbulkselectrequired', 'attendancejourneys');
            }
        }
        $selectionaction = !empty($data['selectallbulk']) || !empty($data['clearbulkselection']) ||
            !empty($data['sortbydate']);
        if (!$selectionaction && empty($data['applybulk']) && empty($data['allowoverlap'])) {
            $occurrences = [];
            for ($index = 0; $index < $count; $index++) {
                $occurrences[] = [
                    'included' => empty($data['included'][$index]) ? 0 : 1,
                    'sessiondate' => (int) ($data['sessionstart'][$index] ?? 0),
                    'enddate' => (int) ($data['sessionend'][$index] ?? 0),
                ];
            }
            $overlaps = \mod_attendancejourneys\local\series_builder::find_overlaps(
                $occurrences,
                $this->_customdata['existing'] ?? []
            );
            if ($overlaps) {
                $errors['allowoverlap'] = attendancejourneys_get_string(
                    'seriesoverlapdetected',
                    'attendancejourneys',
                    count($overlaps)
                );
            }
        }
        return $errors;
    }
}
