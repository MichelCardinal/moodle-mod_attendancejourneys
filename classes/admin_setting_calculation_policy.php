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

namespace mod_attendancejourneys;

/**
 * Validates related calculation defaults together while preserving their existing config keys.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_calculation_policy extends \admin_setting {
    /** @var array Default values indexed by their existing configuration keys. */
    private const VALUES = [
        'default_percentageenabled' => 1, 'lock_percentageenabled' => 0,
        'default_gradeenabled' => 0, 'lock_gradeenabled' => 0,
    ];

    /**
     * Construct the compound Moodle setting.
     */
    public function __construct() {
        parent::__construct(
            'mod_attendancejourneys/calculationpolicy',
            get_string('admincalculationpolicy', 'attendancejourneys'),
            get_string('admincalculationpolicy_desc', 'attendancejourneys'),
            self::VALUES
        );
    }

    /**
     * Read existing keys without introducing a second policy store.
     * @return array|null
     */
    public function get_setting() {
        $values = [];
        $configured = false;
        foreach (self::VALUES as $key => $default) {
            $stored = $this->config_read($key);
            $configured = $configured || $stored !== null;
            $values[$key] = $stored === null ? $default : (int) $stored;
        }
        return $configured ? $values : null;
    }

    /**
     * Validate and save the related keys as one transaction.
     * @param mixed $data Submitted compound value.
     * @return string Error message, or an empty string on success.
     */
    public function write_setting($data) {
        global $DB, $CFG;
        if (!is_array($data) || array_diff(array_keys(self::VALUES), array_keys($data))) {
            return get_string('adminpolicyinvalid', 'attendancejourneys');
        }
        $values = [];
        foreach (self::VALUES as $key => $default) {
            if (!is_scalar($data[$key]) || !in_array((string) $data[$key], ['0', '1'], true)) {
                return get_string('adminpolicyinvalid', 'attendancejourneys');
            }
            $forced = $CFG->forced_plugin_settings['mod_attendancejourneys'][$key] ?? null;
            $values[$key] = $forced === null ? (int) $data[$key] : (int) $forced;
        }
        if (!$values['default_percentageenabled'] && $values['lock_percentageenabled']) {
            return get_string('journeyrequirespercentage', 'attendancejourneys');
        }
        if ($values['default_gradeenabled'] && !$values['default_percentageenabled']) {
            return get_string('admincalculationinvalid', 'attendancejourneys');
        }
        $transaction = $DB->start_delegated_transaction();
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, $CFG->forced_plugin_settings['mod_attendancejourneys'] ?? [])) {
                $this->config_write($key, $value);
            }
        }
        $transaction->allow_commit();
        return '';
    }

    /**
     * Render labelled controls through Moodle's administration setting wrapper.
     * @param mixed $data Current or rejected submitted values.
     * @param string $query Search query.
     * @return string
     */
    public function output_html($data, $query = '') {
        global $CFG;
        $data = is_array($data) ? $data : self::VALUES;
        $form = \html_writer::start_tag('fieldset');
        $form .= \html_writer::tag('legend', $this->visiblename, ['class' => 'h6']);
        foreach (self::VALUES as $key => $default) {
            $field = preg_replace('/^(default_|lock_)/', '', $key);
            $label = get_string(
                str_starts_with($key, 'lock_') ? 'locksetting' : 'defaultsetting',
                'attendancejourneys',
                get_string($field, 'attendancejourneys')
            );
            $name = $this->get_full_name() . '[' . $key . ']';
            $id = $this->get_id() . '_' . $key;
            $forced = array_key_exists($key, $CFG->forced_plugin_settings['mod_attendancejourneys'] ?? []);
            $checked = $forced ? !empty($CFG->forced_plugin_settings['mod_attendancejourneys'][$key]) : !empty($data[$key]);
            $form .= \html_writer::start_div('mb-3');
            $form .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name,
                'value' => $forced && $checked ? 1 : 0]);
            $attributes = ['type' => 'checkbox', 'name' => $name, 'id' => $id, 'value' => 1];
            if ($checked) {
                $attributes['checked'] = 'checked';
            }
            if ($forced) {
                $attributes['disabled'] = 'disabled';
            }
            $form .= \html_writer::empty_tag('input', $attributes) . ' ';
            $form .= \html_writer::tag('label', $label, ['for' => $id]);
            $help = get_string(str_starts_with($key, 'lock_') ? 'locksetting_desc' : $field . '_help', 'attendancejourneys');
            $form .= \html_writer::div($help, 'small text-muted');
            if ($forced) {
                $form .= \html_writer::div(get_string('configoverride', 'admin'), 'small');
            }
            $form .= \html_writer::end_div();
        }
        $form .= \html_writer::end_tag('fieldset');
        return format_admin_setting($this, $this->visiblename, $form, $this->description, false, '', null, $query);
    }
}
