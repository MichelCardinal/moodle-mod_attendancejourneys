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
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_journey_terms')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_string')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_get_terminology')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_replace_ambiguous_french_terminology')]
/**
 * Tests for contextual, display-only journey terminology.
 * @group mod_attendancejourneys
 * @covers ::attendancejourneys_get_journey_terms
 * @covers ::attendancejourneys_get_string
 * @covers ::attendancejourneys_get_terminology
 * @covers ::attendancejourneys_replace_ambiguous_french_terminology
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class terminology_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
    }

    public function test_activity_terms_override_unlocked_site_defaults(): void {
        set_config('default_journeytermsingular_en', 'Cohort', 'mod_attendancejourneys');
        set_config('default_journeytermplural_en', 'Cohorts', 'mod_attendancejourneys');
        $activity = $this->create_activity_with_terms('en', 'Schedule', 'Schedules');
        $this->assertSame(
            ['singular' => 'Schedule', 'plural' => 'Schedules'],
            attendancejourneys_get_journey_terms($activity, 'en')
        );
    }

    public function test_blank_activity_terms_inherit_site_defaults(): void {
        set_config('default_journeytermsingular_en', 'Cohort', 'mod_attendancejourneys');
        set_config('default_journeytermplural_en', 'Cohorts', 'mod_attendancejourneys');
        $activity = $this->getDataGenerator()->create_module('attendancejourneys', [
            'course' => $this->getDataGenerator()->create_course()->id,
        ]);
        $this->assertSame(
            ['singular' => 'Cohort', 'plural' => 'Cohorts'],
            attendancejourneys_get_journey_terms($activity, 'en')
        );
    }

    public function test_site_lock_overrides_activity_terms(): void {
        set_config('default_journeytermsingular_en', 'Cohort', 'mod_attendancejourneys');
        set_config('default_journeytermplural_en', 'Cohorts', 'mod_attendancejourneys');
        set_config('lock_journeyterminology', 1, 'mod_attendancejourneys');
        $activity = $this->create_activity_with_terms('en', 'Schedule', 'Schedules');
        $this->assertSame(
            ['singular' => 'Cohort', 'plural' => 'Cohorts'],
            attendancejourneys_get_journey_terms($activity, 'en')
        );
    }

    public function test_current_page_activity_is_used_when_no_activity_is_passed(): void {
        $GLOBALS['attendancejourneys'] = $this->create_activity_with_terms('en', 'Schedule', 'Schedules');
        try {
            $this->assertSame(
                ['singular' => 'Schedule', 'plural' => 'Schedules'],
                attendancejourneys_get_journey_terms(null, 'en')
            );
        } finally {
            unset($GLOBALS['attendancejourneys']);
        }
    }

    public function test_wrapper_replaces_singular_and_plural_without_touching_interpolation(): void {
        force_current_language('en');
        try {
            $activity = $this->create_activity_with_terms('en', 'Cohort', 'Cohorts');
            $this->assertSame(
                'Manage cohorts',
                attendancejourneys_get_string('managejourneys', 'attendancejourneys', null, $activity)
            );
            $message = attendancejourneys_get_string(
                'journeydeleteblocked',
                'attendancejourneys',
                (object) ['records' => 3, 'results' => 1],
                $activity
            );
            $this->assertStringContainsString('cohort', \core_text::strtolower($message));
            $this->assertStringContainsString('3', $message);
        } finally {
            force_current_language(null);
        }
    }

    public function test_terms_are_independent_for_each_language(): void {
        global $DB;
        $activity = $this->create_activity_with_terms('fr', 'Horaire', 'Horaires');
        $DB->insert_record('attendancejourneys_terms', (object) [
            'attendancejourneysid' => $activity->id,
            'concept' => 'journey',
            'langcode' => 'en',
            'singular' => '',
            'plural' => '',
        ]);
        $this->assertSame(
            ['singular' => 'Horaire', 'plural' => 'Horaires'],
            attendancejourneys_get_journey_terms($activity, 'fr')
        );
        $this->assertSame(
            ['singular' => 'Journey', 'plural' => 'Journeys'],
            attendancejourneys_get_journey_terms($activity, 'en')
        );
        // The temporary test site may not have Moodle's Spanish language pack installed.
        // In that case Moodle correctly falls back to English. Translations are maintained
        // separately for AMOS. Compare with Moodle's string manager rather than hard-coding
        // a language that the test site may not expose.
        $stringmanager = get_string_manager();
        $this->assertSame([
            'singular' => $stringmanager->get_string('journey', 'attendancejourneys', null, 'es'),
            'plural' => $stringmanager->get_string('journeys', 'attendancejourneys', null, 'es'),
        ], attendancejourneys_get_journey_terms($activity, 'es'));
    }

    public function test_french_ambiguous_term_uses_singular_context_and_elision(): void {
        $text = 'Un parcours, le parcours, du parcours, ce parcours et les parcours.';
        $this->assertSame(
            'Un horaire, l’horaire, de l’horaire, cet horaire et les horaires.',
            attendancejourneys_replace_ambiguous_french_terminology(
                $text,
                'parcours',
                'Horaire',
                'Horaires'
            )
        );
        $this->assertSame(
            'La fiche d’un horaire rassemble les membres d’un horaire.',
            attendancejourneys_replace_ambiguous_french_terminology(
                'La fiche d’un parcours rassemble les membres d’un parcours.',
                'parcours',
                'Horaire',
                'Horaires'
            )
        );
    }

    public function test_all_business_concepts_are_replaced_independently(): void {
        global $DB;
        $activity = $this->create_activity_with_terms('en', 'Schedule', 'Schedules');
        foreach (
            [
            'session' => ['Meeting', 'Meetings'],
            'participant' => ['Learner', 'Learners'],
            'room' => ['Venue', 'Venues'],
            ] as $concept => $values
        ) {
            $DB->insert_record('attendancejourneys_terms', (object) [
                'attendancejourneysid' => $activity->id,
                'concept' => $concept,
                'langcode' => 'en',
                'singular' => $values[0],
                'plural' => $values[1],
            ]);
        }
        $this->assertSame(
            ['singular' => 'Meeting', 'plural' => 'Meetings'],
            attendancejourneys_get_terminology('session', $activity, 'en')
        );
        $this->assertSame(
            ['singular' => 'Learner', 'plural' => 'Learners'],
            attendancejourneys_get_terminology('participant', $activity, 'en')
        );
        $this->assertSame(
            ['singular' => 'Venue', 'plural' => 'Venues'],
            attendancejourneys_get_terminology('room', $activity, 'en')
        );
        force_current_language('en');
        try {
            $this->assertSame(
                'Manage venues',
                attendancejourneys_get_string('managerooms', 'attendancejourneys', null, $activity)
            );
            $this->assertSame(
                'All active learners',
                attendancejourneys_get_string('allactiveparticipants', 'attendancejourneys', null, $activity)
            );
        } finally {
            force_current_language(null);
        }
    }

    public function test_each_concept_can_be_locked_independently(): void {
        global $DB;
        set_config('default_sessiontermsingular_en', 'Class', 'mod_attendancejourneys');
        set_config('default_sessiontermplural_en', 'Classes', 'mod_attendancejourneys');
        set_config('lock_sessionterminology', 1, 'mod_attendancejourneys');
        $activity = $this->create_activity_with_terms('en', 'Schedule', 'Schedules');
        $DB->insert_record('attendancejourneys_terms', (object) [
            'attendancejourneysid' => $activity->id,
            'concept' => 'session',
            'langcode' => 'en',
            'singular' => 'Meeting',
            'plural' => 'Meetings',
        ]);
        $this->assertSame(
            ['singular' => 'Class', 'plural' => 'Classes'],
            attendancejourneys_get_terminology('session', $activity, 'en')
        );
        $this->assertSame(
            ['singular' => 'Schedule', 'plural' => 'Schedules'],
            attendancejourneys_get_terminology('journey', $activity, 'en')
        );
    }

    /**
     * Create an activity with custom terminology for the requested language.
     *
     * @param string $langcode Language code for the custom terms.
     * @param string $singular Singular display term.
     * @param string $plural Plural display term.
     * @return \stdClass
     */
    private function create_activity_with_terms(string $langcode, string $singular, string $plural): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('attendancejourneys', ['course' => $course->id]);
        $DB->insert_record('attendancejourneys_terms', (object) [
            'attendancejourneysid' => $activity->id,
            'concept' => 'journey',
            'langcode' => $langcode,
            'singular' => $singular,
            'plural' => $plural,
        ]);
        return $activity;
    }
}
