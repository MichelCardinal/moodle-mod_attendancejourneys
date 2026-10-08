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

namespace mod_attendancejourneys\local;

/**
 * Serialises attendance submissions and their grade updates within one activity.
 *
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class write_lock {
    /** @var \core\lock\lock Underlying Moodle lock. */
    private $lock;

    /** @var int Activity protected by this guard. */
    private int $activityid;

    /**
     * Acquire before reading the sheet version or existing attendance records.
     *
     * @param int $activityid Attendance activity identifier.
     */
    public function __construct(int $activityid) {
        $this->activityid = $activityid;
        $factory = \core\lock\lock_config::get_lock_factory('mod_attendancejourneys');
        $this->lock = $factory->get_lock('activity:' . $activityid, 30);
        if (!$this->lock) {
            throw new \moodle_exception('locktimeout');
        }
        // Redirect exits PHP; shutdown must release even when destructors run late.
        $lock = $this->lock;
        register_shutdown_function(static function () use ($lock): void {
            $lock->release();
        });
    }

    /**
     * Whether this guard protects the requested activity.
     *
     * @param int $activityid Activity identifier.
     * @return bool
     */
    public function protects(int $activityid): bool {
        return $this->activityid === $activityid && $this->lock !== null;
    }

    /**
     * Release explicitly, also safe after a previous release.
     */
    public function release(): void {
        if ($this->lock) {
            $this->lock->release();
            $this->lock = null;
        }
    }

    /**
     * Release when the guard leaves scope, including exception unwinding.
     */
    public function __destruct() {
        $this->release();
    }
}
