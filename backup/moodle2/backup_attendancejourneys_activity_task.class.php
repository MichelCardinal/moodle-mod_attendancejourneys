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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/attendancejourneys/backup/moodle2/backup_attendancejourneys_stepslib.php');

/**
 * Attendance Journeys backup task.
 *
 * @package    mod_attendancejourneys
 * @category   backup
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_attendancejourneys_activity_task extends backup_activity_task {
    /**
     * Use the standard activity backup and restore settings.
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Add the Attendance Journeys XML structure step to this task.
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new backup_attendancejourneys_activity_structure_step(
            'attendancejourneys_structure',
            'attendancejourneys.xml'
        ));
    }

    /**
     * Encode activity links so they can be remapped during restore.
     *
     * @param string $content Content whose activity links must be encoded.
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot . '/mod/attendancejourneys', '#');
        $content = preg_replace("#({$base}/index.php\?id=)([0-9]+)#", '$@ATTENDANCEPLUSINDEX*$2@$', $content);
        return preg_replace("#({$base}/view.php\?id=)([0-9]+)#", '$@ATTENDANCEPLUSVIEWBYID*$2@$', $content);
    }
}
