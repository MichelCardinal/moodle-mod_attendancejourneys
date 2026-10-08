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
 * Form used to create or edit a simple attendance journey.
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class journey extends \moodleform {
    /**
     * Define the form controls, defaults and parameter types.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'journeyid', 0);
        $mform->setType('journeyid', PARAM_INT);

        $mform->addElement('text', 'name', attendancejourneys_get_string('journeyname', 'attendancejourneys'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'customthreshold', get_string('journeycustomthreshold', 'attendancejourneys'));
        $mform->addHelpButton('customthreshold', 'journeycustomthreshold', 'attendancejourneys');
        $mform->addElement('text', 'passinggrade', get_string('passinggrade', 'attendancejourneys'), ['size' => 6]);
        $mform->setType('passinggrade', PARAM_FLOAT);
        $mform->setDefault('passinggrade', $this->_customdata['attendancejourneys']->passinggrade);
        $mform->hideIf('passinggrade', 'customthreshold', 'notchecked');
        if (!empty($this->_customdata['journey']->rootjourneyid)) {
            $mform->freeze(['customthreshold', 'passinggrade']);
            $mform->addElement(
                'static',
                'attemptthresholdnotice',
                '',
                attendancejourneys_get_string('attemptthresholdinherited', 'attendancejourneys')
            );
        }

        if (
            !attendancejourneys_journey_thresholds_allowed() &&
                empty($this->_customdata['journey']->rootjourneyid)
        ) {
            $existingthreshold = $this->_customdata['journey']->passinggrade ?? null;
            $mform->setConstant('customthreshold', $existingthreshold !== null ? 1 : 0);
            $mform->setConstant('passinggrade', $existingthreshold ?? $this->_customdata['attendancejourneys']->passinggrade);
            $mform->freeze(['customthreshold', 'passinggrade']);
            $mform->addElement(
                'static',
                'sitethresholdpolicy',
                '',
                get_string('journeythresholdsitepolicy', 'attendancejourneys')
            );
        }

        if (!empty($this->_customdata['attendancejourneys']->journeygrading)) {
            $mform->addElement('advcheckbox', 'required', get_string('journeyrequired', 'attendancejourneys'));
            $mform->setDefault('required', 1);
            $mform->addHelpButton('required', 'journeyrequired', 'attendancejourneys');
            if (!empty($this->_customdata['journey']->rootjourneyid)) {
                $mform->freeze('required');
            }
        }

        $mform->addElement('select', 'defaultmodality', attendancejourneys_get_string('defaultmodality', 'attendancejourneys'), [
            'unspecified' => attendancejourneys_get_string('modalityunspecified', 'attendancejourneys'),
            'inperson' => attendancejourneys_get_string('modalityinperson', 'attendancejourneys'),
            'online' => attendancejourneys_get_string('modalityonline', 'attendancejourneys'),
            'hybrid' => attendancejourneys_get_string('modalityhybrid', 'attendancejourneys'),
        ]);
        $mform->setDefault('defaultmodality', 'unspecified');
        $mform->addHelpButton('defaultmodality', 'defaultmodality', 'attendancejourneys');

        $mform->addElement(
            'text',
            'capacity',
            attendancejourneys_get_string('journeycapacity', 'attendancejourneys'),
            ['size' => 8]
        );
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 0);
        $mform->addHelpButton('capacity', 'journeycapacity', 'attendancejourneys');
        $mform->addElement(
            'advcheckbox',
            'allowovercapacity',
            attendancejourneys_get_string('journeyallowovercapacity', 'attendancejourneys')
        );
        $mform->addHelpButton('allowovercapacity', 'journeyallowovercapacity', 'attendancejourneys');
        $mform->addElement(
            'advcheckbox',
            'waitlistenabled',
            attendancejourneys_get_string('journeywaitlistenabled', 'attendancejourneys')
        );
        $mform->setDefault('waitlistenabled', 0);
        $mform->addHelpButton('waitlistenabled', 'journeywaitlistenabled', 'attendancejourneys');

        $groups = groups_get_activity_allowed_groups($this->_customdata['cm']);
        $options = [0 => attendancejourneys_get_string('allactiveparticipants', 'attendancejourneys')];
        if (
            !empty($this->_customdata['editing']) &&
            attendancejourneys_journey_has_fixed_audience($this->_customdata['journey'])
        ) {
            $options[0] = attendancejourneys_get_string('journeyselectedaudience', 'attendancejourneys');
        }
        foreach ($groups as $group) {
            $options[$group->id] = format_string($group->name);
        }
        $mform->addElement('select', 'groupid', attendancejourneys_get_string('journeyaudience', 'attendancejourneys'), $options);
        $mform->addHelpButton('groupid', 'journeyaudience', 'attendancejourneys');
        if (!empty($this->_customdata['editing'])) {
            $mform->freeze('groupid');
            $mform->addElement(
                'static',
                'audiencenotice',
                '',
                attendancejourneys_get_string(
                    attendancejourneys_journey_has_fixed_audience($this->_customdata['journey']) ?
                        'journeyaudiencefrozen' : 'journeyaudienceautomaticnotice',
                    'attendancejourneys'
                )
            );
        }

        $mform->addElement('advcheckbox', 'hasdates', attendancejourneys_get_string('journeydefinedates', 'attendancejourneys'));
        $mform->setDefault('hasdates', 1);
        $mform->addHelpButton('hasdates', 'journeydefinedates', 'attendancejourneys');
        $mform->addElement('date_selector', 'startdate', attendancejourneys_get_string('journeystartdate', 'attendancejourneys'));
        $mform->addHelpButton('startdate', 'journeystartdate', 'attendancejourneys');
        $mform->hideIf('startdate', 'hasdates', 'notchecked');
        $mform->addElement('date_selector', 'enddate', attendancejourneys_get_string('journeyenddate', 'attendancejourneys'));
        $mform->addHelpButton('enddate', 'journeyenddate', 'attendancejourneys');
        $mform->hideIf('enddate', 'hasdates', 'notchecked');
        $this->add_action_buttons(true, attendancejourneys_get_string('savejourney', 'attendancejourneys'));
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
        if (!empty($data['customthreshold'])) {
            $threshold = $data['passinggrade'] ?? null;
            if (!is_numeric($threshold) || !is_finite((float) $threshold) || $threshold < 0 || $threshold > 100) {
                $errors['passinggrade'] = get_string('passinggradeerror', 'attendancejourneys');
            }
        }
        if (empty($errors['passinggrade'])) {
            $policyerror = attendancejourneys_journey_threshold_policy_error(
                $this->_customdata['journey'] ?? null,
                empty($data['customthreshold']) ? null : (float) $data['passinggrade']
            );
            if ($policyerror) {
                $errors['passinggrade'] = get_string($policyerror, 'attendancejourneys');
                $errors['customthreshold'] = $errors['passinggrade'];
            }
        }
        $activity = $this->_customdata['attendancejourneys'];
        if (!empty($activity->journeygrading) && !empty($data['journeyid']) && empty($errors['passinggrade'])) {
            $proposed = empty($data['customthreshold']) ? (float) $activity->passinggrade : (float) $data['passinggrade'];
            $journey = $DB->get_record(
                'attendancejourneys_journeys',
                ['attendancejourneysid' => $activity->id, 'id' => $data['journeyid']],
                '*',
                MUST_EXIST
            );
            if (
                !empty($journey->rootjourneyid)
                && $proposed !== attendancejourneys_get_passinggrade($activity, (int) $journey->id)
            ) {
                $errors['passinggrade'] = get_string('attemptthresholdinherited', 'attendancejourneys');
                $errors['customthreshold'] = $errors['passinggrade'];
            }

            foreach (
                $DB->get_records('attendancejourneys_closures', [
                'attendancejourneysid' => $activity->id, 'journeyid' => $data['journeyid'], 'active' => 1,
                ], '', 'id, threshold') as $closure
            ) {
                if ($proposed !== (float) $closure->threshold) {
                    $errors['passinggrade'] = get_string('journeythresholdlocked', 'attendancejourneys');
                    $errors['customthreshold'] = $errors['passinggrade'];
                    break;
                }
            }
        }
        if (!in_array($data['defaultmodality'] ?? '', ['unspecified', 'inperson', 'online', 'hybrid'], true)) {
            $errors['defaultmodality'] = attendancejourneys_get_string('invalidmodality', 'attendancejourneys');
        }
        $capacity = (int) ($data['capacity'] ?? 0);
        if ($capacity < 0) {
            $errors['capacity'] = attendancejourneys_get_string('journeycapacityerror', 'attendancejourneys');
        } else if ($capacity > 0) {
            $context = \context_module::instance($this->_customdata['cm']->id);
            $prospective = (object) [
                'id' => (int) ($data['journeyid'] ?? 0),
                'groupid' => (int) ($data['groupid'] ?? 0),
            ];
            $participants = attendancejourneys_get_journey_participants($context, $prospective);
            if (count($participants) > $capacity && empty($data['allowovercapacity'])) {
                $errors['allowovercapacity'] = attendancejourneys_get_string(
                    'journeycapacityconfirmationrequired',
                    'attendancejourneys',
                    (object) ['count' => count($participants), 'capacity' => $capacity]
                );
            }
        }
        if (!empty($data['hasdates']) && (int) $data['enddate'] < (int) $data['startdate']) {
            $errors['enddate'] = attendancejourneys_get_string('journeydateerror', 'attendancejourneys');
        }
        if (!empty($data['groupid'])) {
            $groups = groups_get_activity_allowed_groups($this->_customdata['cm']);
            if (!isset($groups[(int) $data['groupid']])) {
                $errors['groupid'] = attendancejourneys_get_string('invalidsessiongroup', 'attendancejourneys');
            }
        }
        if (empty($errors['groupid']) && empty($activity->journeygrading)) {
            $activity = $this->_customdata['attendancejourneys'];
            $context = \context_module::instance($this->_customdata['cm']->id);
            $conflicts = attendancejourneys_find_journey_conflicts(
                $context,
                (int) $activity->id,
                (int) ($data['groupid'] ?? 0),
                (int) ($data['journeyid'] ?? 0)
            );
            if ($conflicts) {
                $journeynames = [];
                foreach ($conflicts as $names) {
                    $journeynames = array_merge($journeynames, $names);
                }
                $errors['groupid'] = attendancejourneys_get_string('journeyaudienceconflict', 'attendancejourneys', (object) [
                    'count' => count($conflicts),
                    'journeys' => implode(', ', array_unique($journeynames)),
                ]);
            }
        }
        return $errors;
    }
}
