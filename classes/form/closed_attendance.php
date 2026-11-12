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

/**
 * Collects and confirms a change to one closed attendance entry.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class closed_attendance extends \moodleform {
    /**
     * Give the preview and confirmation forms separate submission identifiers.
     *
     * @return string
     */
    protected function get_form_identifier() {
        return parent::get_form_identifier() . ($this->_customdata['confirmation'] ? '_confirm' : '_preview');
    }

    /**
     * Define the correction fields or the previously previewed values.
     */
    public function definition() {
        $form = $this->_form;
        $confirmation = $this->_customdata['confirmation'];
        if ($confirmation) {
            $fields = ['status' => PARAM_ALPHA, 'minutesabsent' => PARAM_INT, 'reason' => PARAM_TEXT, 'token' => PARAM_ALPHANUM];
            foreach ($fields as $name => $type) {
                $form->addElement('hidden', $name);
                $form->setType($name, $type);
            }
            $form->addElement('submit', 'editproposal', get_string('edit'));
        } else {
            $form->addElement('select', 'status', get_string('status', 'attendancejourneys'), $this->_customdata['statuses']);
            $form->setType('status', PARAM_ALPHA);
            $form->addElement('text', 'minutesabsent', get_string('minutesabsent', 'attendancejourneys'));
            $form->setType('minutesabsent', PARAM_INT);
            $form->hideIf('minutesabsent', 'status', 'neq', 'partial');
            $form->addElement(
                'textarea',
                'reason',
                get_string('closedcorrectionreason', 'attendancejourneys'),
                ['rows' => 3, 'maxlength' => 500, 'class' => 'w-100']
            );
            $form->setType('reason', PARAM_TEXT);
            $form->addRule('reason', get_string('required'), 'required', null, 'client');
        }
        $this->add_action_buttons(true, get_string(
            $confirmation ? 'closedcorrectionconfirm' : 'closedcorrectionpreview',
            'attendancejourneys'
        ));
    }

    /**
     * Apply the same validation to preview and confirmation.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!isset($this->_customdata['statuses'][$data['status'] ?? ''])) {
            $errors['status'] = get_string('invaliddata', 'error');
        }
        $duration = $this->_customdata['duration'];
        if (
            ($data['status'] ?? '') === 'partial' &&
                ((int) ($data['minutesabsent'] ?? 0) < 1 || (int) $data['minutesabsent'] >= $duration)
        ) {
            $errors['minutesabsent'] = get_string('partialminuteserror', 'attendancejourneys', $duration);
        }
        $reason = trim($data['reason'] ?? '');
        if ($reason === '') {
            $errors['reason'] = get_string('required');
        } else if (\core_text::strlen($reason) > 500) {
            $errors['reason'] = get_string('maximumchars', 'moodle', 500);
        }
        return $errors;
    }
}
