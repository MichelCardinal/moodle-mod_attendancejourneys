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

#[\PHPUnit\Framework\Attributes\Group('mod_attendancejourneys')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_can_decide')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_journey_threshold_policy_error')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_journey_thresholds_allowed')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_instance')]
/**
 * Tests administration policy integrity and delegated decisions.
 *
 * @covers ::attendancejourneys_can_decide
 * @covers ::attendancejourneys_journey_threshold_policy_error
 * @covers ::attendancejourneys_journey_thresholds_allowed
 * @covers ::attendancejourneys_update_instance
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class admin_administration_test extends \advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../lib.php');
        require_once($CFG->libdir . '/adminlib.php');
        foreach (['default_percentageenabled', 'lock_percentageenabled', 'default_gradeenabled', 'lock_gradeenabled'] as $key) {
            unset_config($key, 'mod_attendancejourneys');
        }
    }

    public function test_inconsistent_calculation_defaults_leave_all_keys_unchanged(): void {
        $this->setAdminUser();
        $setting = new admin_setting_calculation_policy();
        $valid = ['default_percentageenabled' => 1, 'lock_percentageenabled' => 0,
            'default_gradeenabled' => 1, 'lock_gradeenabled' => 0];
        $this->assertSame('', $setting->write_setting($valid));
        $before = $setting->get_setting();
        $invalid = ['default_percentageenabled' => 0, 'lock_percentageenabled' => 0,
            'default_gradeenabled' => 1, 'lock_gradeenabled' => 1];
        $this->assertSame(get_string('admincalculationinvalid', 'attendancejourneys'), $setting->write_setting($invalid));
        $this->assertSame($before, $setting->get_setting());
        $this->assertFalse(get_config('mod_attendancejourneys', 'calculationpolicy'));
    }

    public function test_percentage_cannot_be_locked_off_for_new_journey_grading(): void {
        $setting = new admin_setting_calculation_policy();
        $data = ['default_percentageenabled' => 0, 'lock_percentageenabled' => 1,
            'default_gradeenabled' => 0, 'lock_gradeenabled' => 0];
        $this->assertSame(get_string('journeyrequirespercentage', 'attendancejourneys'), $setting->write_setting($data));
        $this->assertNull($setting->get_setting());
    }

    public function test_malformed_policy_submissions_cannot_write_partial_settings(): void {
        $setting = new admin_setting_calculation_policy();
        foreach (
            [null, '1', [], ['default_percentageenabled' => 1],
                ['default_percentageenabled' => [], 'lock_percentageenabled' => 0,
                    'default_gradeenabled' => 0, 'lock_gradeenabled' => 0]] as $input
        ) {
            $this->assertSame(get_string('adminpolicyinvalid', 'attendancejourneys'), $setting->write_setting($input));
        }
        $this->assertNull($setting->get_setting());
    }

    public function test_forced_policy_values_are_respected_and_not_written(): void {
        global $CFG;
        $this->setAdminUser();
        $CFG->forced_plugin_settings['mod_attendancejourneys']['default_percentageenabled'] = 0;
        $setting = new admin_setting_calculation_policy();
        $data = ['default_percentageenabled' => 1, 'lock_percentageenabled' => 0,
            'default_gradeenabled' => 1, 'lock_gradeenabled' => 0];
        $this->assertSame(get_string('admincalculationinvalid', 'attendancejourneys'), $setting->write_setting($data));
        $data['default_gradeenabled'] = 0;
        $this->assertSame('', $setting->write_setting($data));
        $this->assertFalse($GLOBALS['DB']->record_exists(
            'config_plugins',
            ['plugin' => 'mod_attendancejourneys', 'name' => 'default_percentageenabled']
        ));
    }

    public function test_disabled_journey_threshold_policy_preserves_existing_values(): void {
        $this->assertNull(attendancejourneys_journey_threshold_policy_error(null, 60.0));
        set_config('allowjourneythresholds', 0, 'mod_attendancejourneys');
        $this->assertNull(attendancejourneys_journey_threshold_policy_error(null, null));
        $this->assertSame('journeythresholdsitepolicy', attendancejourneys_journey_threshold_policy_error(null, 60.0));
        foreach ([0.0, 60.0, 80.0, 100.0, null] as $value) {
            $journey = (object) ['passinggrade' => $value, 'rootjourneyid' => 0];
            $this->assertNull(attendancejourneys_journey_threshold_policy_error($journey, $value));
            $this->assertSame($value, $journey->passinggrade);
            $different = $value === null ? 80.0 : null;
            $this->assertSame(
                'journeythresholdsitepolicy',
                attendancejourneys_journey_threshold_policy_error($journey, $different)
            );
        }
        $attempt = (object) ['passinggrade' => 60.0, 'rootjourneyid' => 12];
        $this->assertNull(attendancejourneys_journey_threshold_policy_error($attempt, 60.0));
    }

    public function test_activity_callbacks_reject_grade_without_calculation_before_writing(): void {
        global $DB;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        try {
            $this->getDataGenerator()->create_module('attendancejourneys', ['course' => $course->id,
                'journeygrading' => 0, 'percentageenabled' => 0, 'gradeenabled' => 1]);
            $this->fail('An inconsistent activity must not be created');
        } catch (\moodle_exception $error) {
            $this->assertSame('gradecalculationrequired', $error->errorcode);
        }
        $this->assertSame(0, $DB->count_records('attendancejourneys'));
        $activity = $this->getDataGenerator()->create_module('attendancejourneys', ['course' => $course->id,
            'journeygrading' => 0, 'percentageenabled' => 0, 'gradeenabled' => 0]);
        $before = $DB->get_record('attendancejourneys', ['id' => $activity->id], '*', MUST_EXIST);
        $data = clone $before;
        $data->instance = $activity->id;
        $data->gradeenabled = 1;
        try {
            attendancejourneys_update_instance($data);
            $this->fail('An inconsistent activity must not be saved');
        } catch (\moodle_exception $error) {
            $this->assertSame('gradecalculationrequired', $error->errorcode);
        }
        $this->assertEquals($before, $DB->get_record('attendancejourneys', ['id' => $activity->id], '*', MUST_EXIST));
    }

    public function test_forced_boolean_false_disables_new_journey_thresholds(): void {
        global $CFG;
        $CFG->forced_plugin_settings['mod_attendancejourneys']['allowjourneythresholds'] = false;
        $this->assertFalse(attendancejourneys_journey_thresholds_allowed());
        $this->assertSame('journeythresholdsitepolicy', attendancejourneys_journey_threshold_policy_error(null, 60.0));
        $this->assertNull(attendancejourneys_journey_threshold_policy_error(null, null));
    }

    public function test_native_admin_writer_handles_the_compound_policy(): void {
        $this->setAdminUser();
        $setting = new admin_setting_calculation_policy();
        $valid = ['default_percentageenabled' => 1, 'lock_percentageenabled' => 0,
            'default_gradeenabled' => 1, 'lock_gradeenabled' => 0];
        $admin = admin_get_root(true, true);
        $page = $admin->locate('modsettingattendancejourneys');
        $this->assertNotFalse($page);
        $this->assertArrayHasKey(
            $setting->get_full_name(),
            admin_find_write_settings($admin, [$setting->get_full_name() => $valid])
        );
        $this->assertSame(1, admin_write_settings((object) [$setting->get_full_name() => $valid]));
        $this->assertSame($valid, $setting->get_setting());
        $invalid = $valid;
        $invalid['default_percentageenabled'] = 0;
        $this->assertSame(0, admin_write_settings((object) [$setting->get_full_name() => $invalid]));
        $this->assertArrayHasKey($setting->get_full_name(), admin_get_root()->errors);
        $this->assertSame($valid, $setting->get_setting());
    }

    public function test_delegated_decision_requires_both_permissions(): void {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('attendancejourneys', ['course' => $course->id]);
        $context = \context_module::instance($activity->cmid);
        $user = $this->getDataGenerator()->create_user();
        $role = create_role('Delegated decisions', 'apdecisions', '');
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
        assign_capability('mod/attendancejourneys:managejourneys', CAP_ALLOW, $role, $context->id);
        $this->setUser($user);
        foreach (['closejourneys', 'reopenjourneys', 'manageattempts', 'approveequivalences'] as $decision) {
            $this->assertFalse(attendancejourneys_can_decide($context, $decision));
            assign_capability('mod/attendancejourneys:' . $decision, CAP_ALLOW, $role, $context->id);
            accesslib_clear_all_caches_for_unit_testing();
            $this->assertTrue(attendancejourneys_can_decide($context, $decision));
            assign_capability('mod/attendancejourneys:' . $decision, CAP_PREVENT, $role, $context->id, true);
            accesslib_clear_all_caches_for_unit_testing();
            $this->assertFalse(attendancejourneys_can_decide($context, $decision));
        }
        assign_capability('mod/attendancejourneys:closejourneys', CAP_ALLOW, $role, $context->id, true);
        assign_capability('mod/attendancejourneys:managejourneys', CAP_PREVENT, $role, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(attendancejourneys_can_decide($context, 'closejourneys'));
    }
}
