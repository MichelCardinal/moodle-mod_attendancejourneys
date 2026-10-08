> **Renaming transition:** This is the working Attendance Journeys component (`mod_attendancejourneys`), candidate 1.1.0 / 2026100806. It is not a normal upgrade ZIP for an existing `mod_attendanceplus` installation. Do not uninstall the predecessor or replace its folder. Existing pre-publication laboratories require a backed-up, separately validated component transition. Original RC11 backups remain unchanged; restore them in a matching RC11 recovery environment before transition and create a new backup with the renamed component. Native automatic restoration of predecessor-component backups is not claimed.

# Installation and upgrade

The release 1.1.0 (2026100806) adopts Attendance Journeys and targets Moodle 4.5 through 5.3. It retains the predecessor’s pedagogical functions and requires separate transition validation for existing pre-publication laboratories. Moodle Marketplace approval is not claimed. Use a PHP and database version supported by your Moodle branch. Manual installation on Moodle 5.1 and later uses `public/mod/attendancejourneys`; the ZIP root is `attendancejourneys`.

The plugin ZIP now contains English strings only, following Moodle submission guidelines. French and Spanish translations are preserved separately for submission to AMOS after approval. Before upgrading a laboratory that relies on the previously bundled translations, preserve them as local language customisations in Moodledata (`lang/fr_local/attendancejourneys.php` and `lang/es_local/attendancejourneys.php`). Merge any existing local customisations instead of overwriting them, then purge language caches. Local packs take precedence over future official translations and should be reviewed when AMOS packs become available. The plugin works in English without these optional packs.

## Historical activities and changing grading mode

The database upgrade does not convert existing activities: their calculation mode, grades and final snapshots are retained. Newly created activities use a required main journey and separate journey grades, published at explicit closure.

A user with course activity editing and journey management rights can open **Review grading mode** in a historical activity, including the Light interface. Separate-group mode also requires access to all groups. The review is read-only: sessions, attendance records, final snapshots, justified absences, existing grades and gradebook protections.

Direct conversion is limited to an activity without final snapshots, numeric grades (including zero), locks/manual overrides, existing completion decisions or incompatible historical policies. It retains the original grade item, attendance records and minutes, and the existing journey and assignments. When no journey exists, it creates a main journey and attaches the sessions. The proposed threshold and audience are displayed before confirmation.

Grades remain unpublished until closure. Automatic completion then requires all assigned required journeys to be closed and passed; historical attendance-record completion rules are replaced. The change cannot be undone in the activity settings. If the reviewed data has changed, confirmation is rejected and a new review is required.

Multiple historical journeys, mixed sessions within/outside journeys, equivalences, finalised results or justified absences previously credited/excluded require a separate preservation plan. The review explains these limits; the activity remains usable in its historical mode. Do not delete grades or history to bypass this control.

## Before installation

1. First use a staging site running the same Moodle version as production representative of production.
2. Back up the database and Moodledata directory.
3. Confirm that the ZIP directly contains the `attendancejourneys` directory.
4. Confirm that Moodle identifies the package as `mod_attendancejourneys`; do not rename its component or directory.

## Installation or upgrade

1. Open Site administration → Plugins → Install plugins.
2. Upload the new ZIP.
3. Confirm that Moodle identifies `mod_attendancejourneys`.
4. Continue the database upgrade.
5. Purge Moodle caches if an older label remains visible.

## Minimum post-upgrade checks

1. Open an existing Attendance Journeys activity.
2. Open Sessions, Journeys, Take attendance, Reports and Administration.
3. Create a temporary session and record attendance.
4. Check the individual report and audit log.
5. Confirm the grade and completion with a test account.
6. Back up and restore the activity in a test course.

## Rollback

Never upload an older ZIP over a database that has already run a newer upgrade. To roll back, restore the code, database and Moodledata together from the same pre-upgrade backup.

## Equivalence decision history

Version 2026100709 adds an append-only decision history table. Existing decided equivalences receive a partial baseline containing only their latest known decision; an unknown date remains unknown. Older overwritten decisions are not invented. New decisions and history entries are committed together. Backups without user data omit this history. Privacy requests may erase participant history or anonymise actors and remove their notes. No final attendance result is recalculated by this upgrade.


Version 2026100710 adds a cancellation flag and append-only session decision history. Existing sessions remain scheduled and no final grade is recalculated. Cancellation requires the new journey grading mode; active closures must be reopened and pending/approved equivalences resolved first. Backups without user data retain the cancellation flag but omit personal history and reasons.


## Individual decisions and explicit personal attempts

Version 2026100711 adds individual periods, whole-journey waivers and their decision history. Existing participants retain the default obligation without inferred exclusions or waivers. Version 2026100712 adds an explicit root link for retake journeys and personal opening decisions. Every existing journey remains independent and no retake is inferred from names, assignment numbers or old dates. Existing attendance, final snapshots and protected grades are preserved.

A new attempt uses separate sessions within the same logical obligation, with one grade item and the root threshold. Its explicit opening withdraws the preceding final grade and re-evaluates automatic completion; its latest closed result replaces the preceding result even if lower. Historical records remain. Opening, reopening and correcting results must respect Moodle grade locks and manual overrides.

Backups with users remap root and retake identifiers and personal decisions. Backups without users retain structural journey links but omit personal openings and decision history. Course date offsets move pedagogical periods, not audit timestamps. Privacy erasure and actor anonymisation apply to the new personal data.

After upgrading, verify a passed original attempt, open a justified retake, confirm its previous grade is withdrawn, plan new sessions, then close the retake and check the single grade item, current/historical report statuses, automatic completion, exports and backup/restore. Test independent theory and laboratory obligations with different thresholds separately. No existing activity is converted automatically by this upgrade.
