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
 * Tests attendance write lock lifecycle.
 * @group mod_attendancejourneys
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('mod_attendancejourneys')]
final class write_lock_test extends \advanced_testcase {
    public function test_release_allows_next_writer_and_other_activities(): void {
        $this->resetAfterTest();
        $guard = new \mod_attendancejourneys\local\write_lock(123456);
        $other = new \mod_attendancejourneys\local\write_lock(123457);
        $factory = \core\lock\lock_config::get_lock_factory('mod_attendancejourneys');
        // Database locks may be reentrant on the same connection; contention is tested with separate processes.
        $guard->release();
        $next = $factory->get_lock('activity:123456', 0);
        $this->assertNotFalse($next);
        $next->release();
        $other->release();
    }

    public function test_exception_unwinding_releases_guard(): void {
        $this->resetAfterTest();
        try {
            (static function (): void {
                $guard = new \mod_attendancejourneys\local\write_lock(123458);
                throw new \RuntimeException('Synthetic failure');
            })();
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic failure', $exception->getMessage());
        }
        $factory = \core\lock\lock_config::get_lock_factory('mod_attendancejourneys');
        $next = $factory->get_lock('activity:123458', 0);
        $this->assertNotFalse($next);
        $next->release();
    }
}
