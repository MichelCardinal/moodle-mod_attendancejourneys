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
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_apply_admin_policies')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_admin_policies')]
/**
 * Tests for institutional defaults and server-side locks.
 * @group mod_attendancejourneys
 * @covers ::attendancejourneys_apply_admin_policies
 * @covers ::attendancejourneys_get_admin_policies
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class admin_policy_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../lib.php');
    }

    public function test_defaults_fall_back_to_documented_values(): void {
        $policies = attendancejourneys_get_admin_policies();
        $this->assertSame('professional', $policies['experiencemode']['default']);
        $this->assertSame(1, $policies['percentageenabled']['default']);
        $this->assertSame(80.0, $policies['passinggrade']['default']);
        $this->assertFalse($policies['experiencemode']['locked']);
    }

    public function test_only_locked_values_are_enforced_server_side(): void {
        set_config('default_experiencemode', 'light', 'mod_attendancejourneys');
        set_config('lock_experiencemode', 1, 'mod_attendancejourneys');
        set_config('default_passinggrade', 70, 'mod_attendancejourneys');
        set_config('lock_passinggrade', 0, 'mod_attendancejourneys');
        $data = (object) ['experiencemode' => 'professional', 'passinggrade' => 85];
        $result = attendancejourneys_apply_admin_policies($data);
        $this->assertSame('light', $result->experiencemode);
        $this->assertSame(85, $result->passinggrade);
    }

    public function test_invalid_stored_policy_values_are_safely_normalised(): void {
        set_config('default_experiencemode', 'invalid', 'mod_attendancejourneys');
        set_config('default_excusedmode', 'invalid', 'mod_attendancejourneys');
        set_config('default_passinggrade', 150, 'mod_attendancejourneys');
        $policies = attendancejourneys_get_admin_policies();
        $this->assertSame('professional', $policies['experiencemode']['default']);
        $this->assertSame('excluded', $policies['excusedmode']['default']);
        $this->assertSame(100.0, $policies['passinggrade']['default']);
    }
}
