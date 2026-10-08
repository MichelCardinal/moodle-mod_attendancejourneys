> **Renaming transition:** This is the working Attendance Journeys component (`mod_attendancejourneys`), candidate 1.1.0-rc12 / 2026100804. It is not a normal upgrade ZIP for an existing `mod_attendanceplus` installation. Do not uninstall the predecessor or replace its folder. Existing pre-publication laboratories require a backed-up, separately validated component transition. Original RC11 backups remain unchanged; restore them in a matching RC11 recovery environment before transition and create a new backup with the renamed component. Native automatic restoration of predecessor-component backups is not claimed.

# Install and try Attendance Journeys

These instructions accompany `1.1.0-rc10` (`2026100802`) for Moodle 4.5 through 5.3. Use its matching archive and validate first in a laboratory. Nothing is published.

## Installation

Use Site administration → Plugins → Install plugins. Upload the corresponding AttendanceJourneys ZIP and follow Moodle's validation and upgrade. The component must be `mod_attendancejourneys`.

For manual installation, place `attendancejourneys` in `mod/` on Moodle 4.5/5.0, or `public/mod/` on Moodle 5.1 through 5.3. Open administration notifications. Composer is not needed to install this plugin.

Before upgrading an operational site, back up its database, Moodledata and code. Try upgrading a copy first. Rolling back requires a consistent backup; replacing only the plugin directory with an older ZIP is insufficient.

## First trial

1. Create a test course with a teacher and three fictional students.
2. Add Attendance Journeys. Light mode starts with one main journey; Professional mode supports separate obligations and advanced operations.
3. Create a 60-minute past or ongoing session.
4. As teacher, record full presence, absence and partial attendance with 15 minutes absent. Save and review the report.
5. To test self-recording, enable it, create an eligible session and sign in as a fictional student. The declaration awaits staff approval.

Full presence means all session minutes; absence means none; partial subtracts minutes absent. A percentage remains provisional without a final grade until individual closure. All required sessions must have ended and missing entries and approvals must be resolved first.

Students must not see another student's report or administration actions. Review custom role permissions when used.

## Illustrated example and configuration

Follow the [user guide](USER_GUIDE_EN.md) for theory at 60%, laboratory at 80% and explicit closure. An older demonstration backup retains its historical grading policy; restoring it does not automatically convert it to independent journey grading.

- Save attendance before changing its 100-participant page.
- Match grade and completion settings to the pedagogical policy.
- Calendar, grades and completion follow the enabled options.
- Read the preview and confirmation before reset or deletion.
- Use Moodle privacy tools and your institution's retention policy.

The plugin works without an external service. Validate any third-party theme or extension used on your installation. Local French/Spanish customisations and future AMOS translations are separate from the English-only plugin archive.
