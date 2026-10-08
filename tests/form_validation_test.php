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
 * Server-side validation tests for session and journey forms.
 * @group mod_attendancejourneys
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('mod_attendancejourneys')]
final class form_validation_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../locallib.php');
        $this->setAdminUser();
    }

    /**
     * Retake proposals require explicit details and use a distinct confirmation form.
     */
    public function test_attempt_forms_require_details_and_separate_confirmation(): void {
        $proposal = new \mod_attendancejourneys\form\attempt_proposal();
        $base = ['name' => 'Retake', 'reason' => 'Approved new opportunity'];
        $this->assertSame([], $proposal->validation($base, []));
        $this->assertArrayHasKey('reason', $proposal->validation(['name' => 'Retake', 'reason' => '  '], []));
        $this->assertArrayHasKey('name', $proposal->validation(['name' => '  ', 'reason' => 'Approved'], []));
        $this->assertArrayHasKey('name', $proposal->validation(['name' => str_repeat('é', 256), 'reason' => 'Approved'], []));
        $confirmation = new \mod_attendancejourneys\form\attempt_confirmation();
        $confirmation->set_data((object) ['id' => 7, 'userid' => 8, 'rootjourneyid' => 9,
            'name' => '<script>untrusted</script>', 'reason' => 'Approved', 'token' => str_repeat('a', 64)]);
        $html = $confirmation->render();
        $this->assertStringContainsString('_qf__mod_attendancejourneys_form_attempt_proposal', $proposal->render());
        $this->assertStringNotContainsString('_qf__mod_attendancejourneys_form_attempt_proposal', $html);
        $this->assertStringContainsString('_qf__mod_attendancejourneys_form_attempt_confirmation', $html);
        $this->assertStringContainsString('name="editproposal"', $html);
        $this->assertStringContainsString('name="sesskey"', $html);
        $this->assertStringContainsString(str_repeat('a', 64), $html);
        $this->assertStringNotContainsString('<script>untrusted</script>', $html);
    }

    /**
     * Individual decisions require reasons and reject reversed periods without blocking a whole waiver.
     */
    public function test_individual_obligation_form_validates_explicit_decisions(): void {
        $form = new \mod_attendancejourneys\form\individual_obligation();
        $base = ['waived' => 0, 'starttime' => 100, 'endtime' => 200, 'reason' => 'Approved pedagogical period'];
        $this->assertSame([], $form->validation($base, []));
        $invalid = $base;
        $invalid['reason'] = '   ';
        $this->assertArrayHasKey('reason', $form->validation($invalid, []));
        $invalid = $base;
        $invalid['endtime'] = 100;
        $this->assertArrayHasKey('endtime', $form->validation($invalid, []));
        $invalid['waived'] = 1;
        $this->assertSame([], $form->validation($invalid, []));
        $base['starttime'] = 0;
        $base['endtime'] = 0;
        $this->assertSame([], $form->validation($base, []));
    }

    /**
     * Correction and confirmation use separate native form markers and escaped proposal values.
     */
    public function test_obligation_confirmation_form_keeps_the_proposal_separate(): void {
        $proposal = new \mod_attendancejourneys\form\individual_obligation();
        $confirmation = new \mod_attendancejourneys\form\obligation_confirmation();
        $confirmation->set_data((object) ['id' => 7, 'userid' => 8, 'journeyid' => 9,
            'waived' => 0, 'starttime' => 0, 'endtime' => 0,
            'token' => str_repeat('a', 64), 'reason' => '<script>untrusted</script>']);
        $html = $confirmation->render();
        $this->assertStringContainsString('_qf__mod_attendancejourneys_form_individual_obligation', $proposal->render());
        $this->assertStringNotContainsString('_qf__mod_attendancejourneys_form_individual_obligation', $html);
        $this->assertStringContainsString('_qf__mod_attendancejourneys_form_obligation_confirmation', $html);
        $this->assertStringContainsString('name="editproposal"', $html);
        $this->assertStringContainsString(str_repeat('a', 64), $html);
        $this->assertStringNotContainsString('<script>untrusted</script>', $html);
    }

    /**
     * Native activity settings expose the final journey rule and retain historical options.
     */
    public function test_native_activity_form_completion_contract(): void {
        global $CFG, $COURSE, $PAGE;
        require_once($CFG->dirroot . '/mod/attendancejourneys/mod_form.php');
        $COURSE = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $PAGE->set_course($COURSE);
        $PAGE->set_url('/course/modedit.php');
        foreach ([0, 1] as $mode) {
            $activity = $this->getDataGenerator()->create_module('attendancejourneys', [
                'course' => $COURSE->id, 'journeygrading' => $mode,
            ]);
            $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $COURSE->id, false, MUST_EXIST);
            $current = clone $activity;
            $current->instance = $activity->id;
            $current->coursemodule = $cm->id;
            $current->modulename = 'attendancejourneys';
            $form = new \mod_attendancejourneys_mod_form($current, 0, $cm, $COURSE);
            $html = $form->render();
            if ($mode) {
                $this->assertStringContainsString(get_string('completionjourneys', 'attendancejourneys'), $html);
                $this->assertStringNotContainsString(get_string('completionrecorded', 'attendancejourneys'), $html);
            } else {
                $this->assertStringContainsString(get_string('completionrecorded', 'attendancejourneys'), $html);
                $this->assertStringNotContainsString(get_string('completionjourneys', 'attendancejourneys'), $html);
            }
        }
    }

    public function test_session_form_rejects_invalid_dates_series_modality_url_and_group(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
        $form = new \mod_attendancejourneys\form\session(null, [
            'cm' => $cm, 'attendancejourneys' => $activity, 'series' => true, 'journeys' => [],
        ]);
        $start = time();
        $errors = $form->validation([
            'name' => 'Invalid session', 'sessiondate' => $start, 'enddate' => $start,
            'journeyid' => 0, 'groupid' => 987654, 'modality' => 'teleportation',
            'meetingurl' => 'javascript:alert(1)', 'repeatcount' => 1, 'repeatinterval' => 7,
            'seriesnamemode' => 'invalid',
        ], []);

        foreach (['enddate', 'repeatcount', 'modality', 'meetingurl', 'groupid'] as $field) {
            $this->assertArrayHasKey($field, $errors, $field);
        }
    }

    public function test_session_form_accepts_valid_bilingual_and_online_values(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
        $group = $generator->create_group(['courseid' => $course->id, 'name' => 'Groupe Équipe A']);
        $form = new \mod_attendancejourneys\form\session(null, [
            'cm' => $cm, 'attendancejourneys' => $activity, 'series' => true, 'journeys' => [],
        ]);
        $start = time();
        $errors = $form->validation([
            'name' => 'Séance – Santé et sécurité', 'sessiondate' => $start, 'enddate' => $start + HOURSECS,
            'journeyid' => 0, 'groupid' => $group->id, 'modality' => 'online',
            'meetingurl' => 'https://classe.exemple.ca/réunion', 'repeatcount' => 12, 'repeatinterval' => 7,
            'seriesnamemode' => 'numbered',
        ], []);
        $this->assertSame([], $errors);
    }

    /**
     * New grading always requires an offered journey, including in the simplified interface.
     */
    public function test_journey_grading_session_requires_visible_journey_in_both_modes(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('attendancejourneys', [
            'course' => $course->id, 'journeygrading' => 1,
        ]);
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
        $journeyid = $DB->insert_record('attendancejourneys_journeys', (object) [
            'attendancejourneysid' => $activity->id, 'name' => 'Required theory',
        ]);
        $journey = $DB->get_record('attendancejourneys_journeys', ['id' => $journeyid], '*', MUST_EXIST);
        $start = time();
        $data = [
            'name' => 'Theory session', 'sessiondate' => $start, 'enddate' => $start + HOURSECS,
            'journeyid' => $journeyid, 'groupid' => 0, 'modality' => 'unspecified',
        ];
        foreach (['light', 'professional'] as $mode) {
            $activity->experiencemode = $mode;
            $form = new \mod_attendancejourneys\form\session(null, [
                'cm' => $cm, 'attendancejourneys' => $activity, 'journeys' => [$journeyid => $journey],
            ]);
            $this->assertMatchesRegularExpression('/<select[^>]*name="journeyid"/', $form->render());
            $this->assertSame([], $form->validation($data, []));
            foreach ([0, $journeyid + 1000] as $invalidid) {
                $invalid = $data;
                $invalid['journeyid'] = $invalidid;
                $this->assertArrayHasKey('journeyid', $form->validation($invalid, []));
            }
        }
    }

    public function test_series_review_accepts_individual_names_dates_and_exclusions(): void {
        $form = new \mod_attendancejourneys\form\series_review(null, ['count' => 3]);
        $start = strtotime('2026-09-01 09:00:00');
        $errors = $form->validation([
            'occurrencecount' => 3,
            'included' => [1, 1, 0],
            'sessionname' => ['Jour 1', 'Examen final', 'Session retirée'],
            'sessionstart' => [$start, $start + DAYSECS, $start + (2 * DAYSECS)],
            'sessionend' => [$start + HOURSECS, $start + DAYSECS + (2 * HOURSECS),
                $start + (2 * DAYSECS) + HOURSECS],
            'sessionmodality' => ['inperson', 'online', 'unspecified'],
            'sessionmeetingurl' => ['', 'https://classe.exemple.ca/examen', ''],
        ], []);
        $this->assertSame([], $errors);
    }

    public function test_series_review_requires_one_valid_included_session(): void {
        $form = new \mod_attendancejourneys\form\series_review(null, ['count' => 2]);
        $start = strtotime('2026-09-01 09:00:00');
        $invalid = $form->validation([
            'occurrencecount' => 2,
            'included' => [1, 1],
            'sessionname' => ['', 'Examen'],
            'sessionstart' => [$start, $start + DAYSECS],
            'sessionend' => [$start, $start + DAYSECS - MINSECS],
            'sessionmodality' => ['inperson', 'online'], 'sessionmeetingurl' => ['', 'https://example.com'],
        ], []);
        $this->assertArrayHasKey('sessionname[0]', $invalid);
        $this->assertArrayHasKey('sessionend[0]', $invalid);
        $this->assertArrayHasKey('sessionend[1]', $invalid);

        $empty = $form->validation([
            'occurrencecount' => 2,
            'included' => [0, 0],
            'sessionname' => ['Jour 1', 'Jour 2'],
            'sessionstart' => [$start, $start + DAYSECS],
            'sessionend' => [$start + HOURSECS, $start + DAYSECS + HOURSECS],
            'sessionmodality' => ['inperson', 'online'], 'sessionmeetingurl' => ['', 'https://example.com'],
        ], []);
        $this->assertArrayHasKey('included[0]', $empty);
    }

    public function test_series_review_validates_bulk_changes(): void {
        $form = new \mod_attendancejourneys\form\series_review(null, ['count' => 2]);
        $start = strtotime('2026-09-01 09:00:00');
        $base = [
            'occurrencecount' => 2, 'included' => [1, 1],
            'sessionname' => ['Jour 1', 'Jour 2'],
            'sessionstart' => [$start, $start + DAYSECS],
            'sessionend' => [$start + HOURSECS, $start + DAYSECS + HOURSECS],
            'sessionmodality' => ['inperson', 'online'], 'sessionmeetingurl' => ['', 'https://example.com'],
            'applybulk' => 1, 'bulkoperation' => 'duration',
        ];
        $errors = $form->validation($base + ['selected' => [0, 0], 'bulkvalue' => '0'], []);
        $this->assertArrayHasKey('bulkvalue', $errors);
        $this->assertArrayHasKey('bulkoperation', $errors);

        $valid = $form->validation($base + ['selected' => [1, 0], 'bulkvalue' => '90'], []);
        $this->assertSame([], $valid);

        $days = $base;
        $days['bulkoperation'] = 'shiftdays';
        $this->assertSame([], $form->validation($days + ['selected' => [1, 1], 'bulkvalue' => '-14'], []));
        $invalidminutes = $base;
        $invalidminutes['bulkoperation'] = 'shiftminutes';
        $shifterrors = $form->validation($invalidminutes + ['selected' => [1, 0], 'bulkvalue' => '2000'], []);
        $this->assertArrayHasKey('bulkvalue', $shifterrors);
    }

    public function test_series_review_requires_explicit_overlap_confirmation(): void {
        $form = new \mod_attendancejourneys\form\series_review(null, ['count' => 2, 'existing' => []]);
        $start = strtotime('2026-09-01 09:00:00');
        $data = [
            'occurrencecount' => 2, 'included' => [1, 1],
            'sessionname' => ['Atelier A', 'Atelier B'],
            'sessionstart' => [$start, $start + (30 * MINSECS)],
            'sessionend' => [$start + HOURSECS, $start + (90 * MINSECS)],
            'sessionmodality' => ['inperson', 'inperson'], 'sessionmeetingurl' => ['', ''],
        ];
        $errors = $form->validation($data, []);
        $this->assertArrayHasKey('allowoverlap', $errors);
        $this->assertSame([], $form->validation($data + ['allowoverlap' => 1], []));
    }

    public function test_journey_form_rejects_invalid_period_modality_and_group(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
        $form = new \mod_attendancejourneys\form\journey(null, [
            'cm' => $cm, 'attendancejourneys' => $activity, 'editing' => false,
        ]);
        $errors = $form->validation([
            'journeyid' => 0, 'name' => 'Invalid journey', 'defaultmodality' => 'unknown',
            'groupid' => 987654, 'hasdates' => 1, 'startdate' => time(), 'enddate' => time() - DAYSECS,
        ], []);
        foreach (['defaultmodality', 'enddate', 'groupid'] as $field) {
            $this->assertArrayHasKey($field, $errors, $field);
        }
    }

    public function test_journey_form_accepts_same_day_period_and_valid_group(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
        $group = $generator->create_group(['courseid' => $course->id]);
        $form = new \mod_attendancejourneys\form\journey(null, [
            'cm' => $cm, 'attendancejourneys' => $activity, 'editing' => false,
        ]);
        $date = strtotime('today');
        $errors = $form->validation([
            'journeyid' => 0, 'name' => 'Parcours valide', 'defaultmodality' => 'hybrid',
            'groupid' => $group->id, 'hasdates' => 1, 'startdate' => $date, 'enddate' => $date,
        ], []);
        $this->assertSame([], $errors);
    }

    /**
     * A custom threshold accepts percentages only, including the zero and 100 boundaries.
     */
    public function test_journey_threshold_validation(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
        $form = new \mod_attendancejourneys\form\journey(null, [
            'cm' => $cm, 'attendancejourneys' => $activity, 'editing' => false,
        ]);
        $base = ['journeyid' => 0, 'name' => 'Theory', 'defaultmodality' => 'inperson',
            'groupid' => 0, 'hasdates' => 0, 'customthreshold' => 1];
        foreach (['', 'invalid', -1, 100.01] as $value) {
            $errors = $form->validation($base + ['passinggrade' => $value], []);
            $this->assertArrayHasKey('passinggrade', $errors);
        }
        foreach ([0, 60, 80, 100] as $value) {
            $this->assertSame([], $form->validation($base + ['passinggrade' => $value], []));
        }
        $base['customthreshold'] = 0;
        $this->assertSame([], $form->validation($base, []));
    }

    /**
     * Retake planning inherits the logical threshold and rejects a different submitted value.
     */
    public function test_retake_form_inherits_root_threshold(): void {
        global $DB;
        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $activity = $g->create_module('attendancejourneys', ['course' => $course->id, 'journeygrading' => 1]);
        $root = $DB->get_record('attendancejourneys_journeys', ['attendancejourneysid' => $activity->id], '*', MUST_EXIST);
        $DB->set_field('attendancejourneys_journeys', 'passinggrade', 80, ['id' => $root->id]);
        $child = clone $root;
        unset($child->id);
        $child->rootjourneyid = $root->id;
        $child->passinggrade = 60;
        $child->audiencemode = 2;
        $child->id = $DB->insert_record('attendancejourneys_journeys', $child);
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
        $form = new \mod_attendancejourneys\form\journey(
            null,
            ['cm' => $cm, 'attendancejourneys' => $activity, 'editing' => true, 'journey' => $child]
        );
        $data = ['journeyid' => $child->id, 'name' => 'Retake', 'defaultmodality' => 'inperson',
            'groupid' => 0, 'hasdates' => 0, 'customthreshold' => 1, 'passinggrade' => 60];
        $this->assertArrayHasKey('passinggrade', $form->validation($data, []));
        $data['passinggrade'] = 80;
        $this->assertSame([], $form->validation($data, []));
        $form->set_data((object) $data);
        $html = $form->render();
        $this->assertStringContainsString(get_string('attemptthresholdinherited', 'attendancejourneys'), $html);
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $editable = $xpath->query("//input[@name='passinggrade' and @type='text' and not(@disabled) and not(@readonly)]");
        $this->assertSame(0, $editable->length);
    }

    public function test_journey_capacity_requires_an_explicit_override_when_exceeded(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('attendancejourneys', ['course' => $course->id]);
        $generator->create_and_enrol($course, 'student');
        $generator->create_and_enrol($course, 'student');
        $cm = get_coursemodule_from_instance('attendancejourneys', $activity->id, $course->id, false, MUST_EXIST);
        $form = new \mod_attendancejourneys\form\journey(null, [
            'cm' => $cm, 'attendancejourneys' => $activity, 'editing' => false,
        ]);
        $data = [
            'journeyid' => 0, 'name' => 'Limited journey', 'defaultmodality' => 'inperson',
            'capacity' => 1, 'groupid' => 0, 'hasdates' => 0, 'startdate' => 0, 'enddate' => 0,
        ];
        $this->assertArrayHasKey('allowovercapacity', $form->validation($data, []));
        $this->assertSame([], $form->validation($data + ['allowovercapacity' => 1], []));
    }
}
