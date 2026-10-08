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

#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_delete_all_calendar_events')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_refresh_events')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_update_calendar_event')]
/**
 * Moodle calendar integration tests.
 *
 * @covers ::attendancejourneys_delete_all_calendar_events
 * @covers ::attendancejourneys_refresh_events
 * @covers ::attendancejourneys_update_calendar_event
 * @package    mod_attendancejourneys
 * @category   test
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar_test extends \advanced_testcase {
    public function test_disabled_calendar_never_creates_an_event(): void {
        global $DB;
        [$activity, $session] = $this->create_fixture(false);
        attendancejourneys_update_calendar_event($activity, $session);
        $this->assertFalse($DB->record_exists('event', $this->event_conditions($activity, $session)));
    }

    public function test_enabled_calendar_updates_one_event_without_creating_duplicates(): void {
        global $DB;
        [$activity, $session] = $this->create_fixture(true);
        attendancejourneys_update_calendar_event($activity, $session);
        $conditions = $this->event_conditions($activity, $session);
        $this->assertSame(1, $DB->count_records('event', $conditions));
        $event = $DB->get_record('event', $conditions, '*', MUST_EXIST);
        $this->assertSame('Initial session', $event->name);
        $this->assertSame(60 * MINSECS, (int) $event->timeduration);

        $session->name = 'Updated session';
        $session->sessiondate += DAYSECS;
        $session->duration = 90;
        attendancejourneys_update_calendar_event($activity, $session);
        $this->assertSame(1, $DB->count_records('event', $conditions));
        $updated = $DB->get_record('event', $conditions, '*', MUST_EXIST);
        $this->assertSame((int) $event->id, (int) $updated->id);
        $this->assertSame('Updated session', $updated->name);
        $this->assertSame((int) $session->sessiondate, (int) $updated->timestart);
        $this->assertSame(90 * MINSECS, (int) $updated->timeduration);

        $activity->calendarenabled = 0;
        attendancejourneys_update_calendar_event($activity, $session);
        $this->assertSame(0, $DB->count_records('event', $conditions));
    }

    public function test_refresh_removes_stale_events_but_never_other_module_events(): void {
        global $DB;
        [$activity, $session] = $this->create_fixture(true);
        attendancejourneys_update_calendar_event($activity, $session);
        $DB->insert_record('event', (object) [
            'name' => 'Stale Attendance Journeys event', 'description' => '', 'format' => FORMAT_HTML,
            'courseid' => $activity->course, 'groupid' => 0, 'userid' => 0, 'repeatid' => 0,
            'modulename' => 'attendancejourneys', 'instance' => $activity->id, 'eventtype' => 'session-999999',
            'timestart' => time(), 'timeduration' => 0, 'timesort' => time(), 'visible' => 1,
            'timemodified' => time(), 'subscriptionid' => null, 'sequence' => 1,
        ]);
        $foreignid = $DB->insert_record('event', (object) [
            'name' => 'Other module event', 'description' => '', 'format' => FORMAT_HTML,
            'courseid' => $activity->course, 'groupid' => 0, 'userid' => 0, 'repeatid' => 0,
            'modulename' => 'assign', 'instance' => $activity->id, 'eventtype' => 'due',
            'timestart' => time(), 'timeduration' => 0, 'timesort' => time(), 'visible' => 1,
            'timemodified' => time(), 'subscriptionid' => null, 'sequence' => 1,
        ]);

        attendancejourneys_refresh_events((int) $activity->course, $activity);
        $this->assertFalse($DB->record_exists('event', [
            'modulename' => 'attendancejourneys', 'instance' => $activity->id, 'eventtype' => 'session-999999',
        ]));
        $this->assertTrue($DB->record_exists('event', ['id' => $foreignid]));
        $this->assertSame(1, $DB->count_records('event', $this->event_conditions($activity, $session)));
    }

    public function test_delete_all_uses_calendar_api_scope_without_touching_foreign_events(): void {
        global $DB;
        [$activity, $session] = $this->create_fixture(true);
        attendancejourneys_update_calendar_event($activity, $session);
        $foreignid = $DB->insert_record('event', (object) [
            'name' => 'Other module event', 'description' => '', 'format' => FORMAT_HTML,
            'courseid' => $activity->course, 'groupid' => 0, 'userid' => 0, 'repeatid' => 0,
            'modulename' => 'assign', 'instance' => $activity->id, 'eventtype' => 'due',
            'timestart' => time(), 'timeduration' => 0, 'timesort' => time(), 'visible' => 1,
            'timemodified' => time(), 'subscriptionid' => null, 'sequence' => 1,
        ]);
        attendancejourneys_delete_all_calendar_events((int) $activity->id);
        $this->assertSame(0, $DB->count_records('event', [
            'modulename' => 'attendancejourneys', 'instance' => $activity->id,
        ]));
        $this->assertTrue($DB->record_exists('event', ['id' => $foreignid]));
    }

    /**
     * Create the activity and related records needed by this test.
     *
     * @param bool $enabled Whether calendar integration is enabled.
     * @return array
     */
    private function create_fixture(bool $enabled): array {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/attendancejourneys/lib.php');
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('attendancejourneys', [
            'course' => $course->id, 'calendarenabled' => $enabled ? 1 : 0,
        ]);
        $session = (object) [
            'attendancejourneysid' => $activity->id, 'journeyid' => 0, 'name' => 'Initial session',
            'sessiondate' => time() + DAYSECS, 'duration' => 60, 'groupid' => 0, 'description' => 'Description',
            'modality' => 'online', 'location' => '', 'meetingurl' => 'https://example.invalid/meeting',
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $session->id = $DB->insert_record('attendancejourneys_sessions', $session);
        return [$activity, $session];
    }

    /**
     * Build the conditions identifying this session calendar event.
     *
     * @param \stdClass $activity Attendance activity and its configuration.
     * @param \stdClass $session Attendance session.
     * @return array
     */
    private function event_conditions(\stdClass $activity, \stdClass $session): array {
        return [
            'modulename' => 'attendancejourneys', 'instance' => $activity->id,
            'eventtype' => 'session-' . $session->id,
        ];
    }
}
