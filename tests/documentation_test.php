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
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_apply_terminology_to_html')]
#[\PHPUnit\Framework\Attributes\CoversFunction('attendancejourneys_documentation_enabled')]
/**
 * Tests for the bundled trilingual documentation.
 * @group mod_attendancejourneys
 * @covers ::attendancejourneys_apply_terminology_to_html
 * @covers ::attendancejourneys_documentation_enabled
 * @package mod_attendancejourneys
 * @copyright 2026 Michel Cardinal
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class documentation_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
    }

    public function test_help_centre_is_enabled_by_default_and_can_be_disabled_globally(): void {
        $this->resetAfterTest();
        $this->assertTrue(attendancejourneys_documentation_enabled());
        set_config('documentationenabled', 0, 'mod_attendancejourneys');
        $this->assertFalse(attendancejourneys_documentation_enabled());
        set_config('documentationenabled', 1, 'mod_attendancejourneys');
        $this->assertTrue(attendancejourneys_documentation_enabled());
    }

    public function test_every_integrated_document_exists_in_all_supported_languages(): void {
        $documents = [
            'fr' => ['GUIDE_UTILISATEUR_FR.md', 'GUIDE_ADMINISTRATEUR_FR.md', 'MISE_A_NIVEAU_FR.md',
                'MATRICE_ROLES_FR.md', 'MATRICE_MODES_FR.md', 'ACCESSIBILITE_FR.md', 'TESTS_FR.md',
                'RECETTE_FONCTIONNELLE_FR.md'],
            'en' => ['USER_GUIDE_EN.md', 'ADMINISTRATOR_GUIDE_EN.md', 'UPGRADE_GUIDE_EN.md',
                'ROLE_MATRIX_EN.md', 'MODE_MATRIX_EN.md', 'ACCESSIBILITY_EN.md', 'TESTING_EN.md',
                'ACCEPTANCE_TEST_EN.md'],
            'es' => ['GUIA_USUARIO_ES.md', 'GUIA_ADMINISTRADOR_ES.md', 'GUIA_ACTUALIZACION_ES.md',
                'MATRIZ_ROLES_ES.md', 'MATRIZ_MODOS_ES.md', 'ACCESIBILIDAD_ES.md', 'PRUEBAS_ES.md',
                'PRUEBA_ACEPTACION_ES.md'],
        ];
        foreach ($documents as $language => $files) {
            $this->assertCount(8, $files, $language);
            foreach ($files as $file) {
                $path = __DIR__ . '/../docs/' . $file;
                $this->assertFileExists($path);
                $this->assertGreaterThan(100, filesize($path), $file);
            }
        }
    }

    public function test_custom_terminology_does_not_change_documentation_urls(): void {
        $this->resetAfterTest();
        force_current_language('en');
        set_config('default_journeytermsingular_en', 'Schedule', 'mod_attendancejourneys');
        set_config('default_journeytermplural_en', 'Schedules', 'mod_attendancejourneys');

        $html = '<p>Journeys and Journey</p>'
            . '<img src="/docs/images/en/03-admin-journey.jpg" alt="Journey overview">'
            . '<a href="/help/journeys">Journey help</a>';
        $result = attendancejourneys_apply_terminology_to_html($html);

        $this->assertStringContainsString('<p>Schedules and Schedule</p>', $result);
        $this->assertStringContainsString('src="/docs/images/en/03-admin-journey.jpg"', $result);
        $this->assertStringContainsString('href="/help/journeys"', $result);
        $this->assertStringContainsString('alt="Schedule overview"', $result);
        $this->assertStringContainsString('>Schedule help</a>', $result);
    }
}
