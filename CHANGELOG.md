# Changelog

## 1.1.0 — 2026-10-08

Promote the validated RC12 as the first public stable release. Technical version 2026100806; no schema, calculation, grade or permission changes. Add the native metadata-only savepoint and public repository/support links. Order English string keys without changing values and declare actual PHPUnit coverage targets for current Moodle coding checks. Earlier entries record unpublished development.

## 1.1.0-rc12 — 2026-10-08

Adopt Attendance Journeys / mod_attendancejourneys, technical version 2026100804. Preserve native attendance calculations and pedagogical workflows. Validate the renamed component on seven native Moodle 4.5–5.3 configurations and rehearse separately backed-up component transitions and restoration on full private copies. Refresh 24 fictional guide images and multilingual documentation; keep English strings in the plugin and prepare translations separately. This ZIP is not an ordinary upgrade of the predecessor component. Previous entries describe that unpublished predecessor; publication remains pending.

## 1.1.0-rc11 — 2026-10-08

Technical version: `2026100803`. Separate site administration into main policies, terminology and historical settings; make the administrator guide accessible without an activity. Validate related calculation and grade defaults together and prevent locking off the calculation required by new journey grading. Add an optional policy governing new journey-specific thresholds while retaining existing thresholds and final results. Delegate closure, reopening, personal retakes and equivalence approval through distinct Moodle capabilities cloned from existing journey-management permissions on upgrade. Update English, French and Spanish help and guides. No pedagogical data conversion. Publication remains pending.

## 1.1.0-rc10 — 2026-10-08

Technical version: `2026100802`. Updated English, French and Spanish user guides with a complete first-journey workflow, current fictional screenshots, closure and retake procedures, roster states, waiting lists and equivalences. Independent journey assignments now display an informational message instead of the historical conflict warning; selected and automatic audiences are labelled accurately. No schema, attendance calculation or final-result changes. Offline AMOS translations and public documentation are prepared separately; nothing is published. Cross-guide links respect role permissions; the help layout fits 320 CSS pixels. Documentation tests now run independently of other test classes.

## 1.1.0-rc9 — 2026-10-08

Technical version: `2026100801`. Sessions without current participants are displayed neutrally and excluded from pending attendance counts and filters. Cancelled sessions retain their cancellation status. Existing attendance and final results remain unchanged.

## 1.1.0-rc8 (2026100800) — 2026-10-08 (in preparation; final acceptance pending)

- Display a neutral marker for missing attendance audit timestamps in the individual report and attendance entry page; preserve stored dates, minutes, grades and history.
- Keep RC6 and RC7 archives unchanged. This release checkpoint adds no schema or data transformation.

## 1.1.0-rc7 — 2026-10-07 (local candidate; final acceptance pending)

- Add authorised preview and confirmation of individual obligation periods, whole-obligation waivers and personal retakes, with decision history and stale-confirmation protection.
- Keep independent theory/laboratory thresholds and one grade item per logical obligation. Retakes inherit their root threshold, use separate sessions and withdraw the prior grade until closure; the latest closed result replaces the previous result even when lower.
- Distinguish current published results from historical attempts in reports and exports; preserve recorded attendance and previous final snapshots.
- Cover new personal decisions with Moodle Privacy, user/no-user backup and restore, conservative upgrades and gradebook/completion protections.
- Preserve valid recorded attendance as capped, explicit equivalence evidence from a waived source; prevent duplicate credit.
- Keep Excel minutes and percentages numeric, missing audit dates blank and long PDF titles wrapped. Update EN/FR/ES documentation and separate translations.
- Version 2026100713 adds a release checkpoint without changing the schema or existing data beyond development version 2026100712. RC6 remains archived unchanged.
- Native Chrome acceptance and current Moodle Marketplace submission review remain pending. No GitHub, Marketplace or AMOS publication.

## 1.1.0-dev11 — 2026-10-07 (unreleased development)

- Enable authorised individual closure and reopening for new Light activities so their main-journey grades can be published; keep legacy Light restrictions and advanced operations protected. Correct trilingual journey rules, mode matrices and acceptance scenarios.
- Keep one labelled keyboard-scroll region around report tables when Moodle adds a responsive wrapper; preserve the caller’s table options and support older Moodle APIs.
- Display a neutral provisional result in the individual summary until a new-mode journey is closed; preserve the provisional percentage and historical activity behaviour.
- Add auditable session cancellation and reinstatement with required reasons, capability/group checks and stale confirmation protection. Preserve attendance and past final snapshots; exclude cancelled time from calculations, recording, calendar and exports.
- Preserve cancellation state through backup/restore; include personal history only with user data and support Moodle Privacy erasure/anonymisation.
- Apply whole-audience permissions to session editing, clearing and every batch source; check destination groups and fixed participants before adding/moving sessions.
- Require a journey for new-mode sessions, keep independent audiences in legacy mode, protect closed destination scopes and advance session revisions monotonically on edits.
- Use the shared journey calculator in collective exports for cancelled sessions and approved equivalences; preserve historical grading and frozen final snapshots.
- Explain independent journey grades, required-journey completion and justified absence versus session exemption in the English, French and Spanish guides; identify older screenshots as historical illustrations.
- Align initial and interactive attendance-sheet previews with the recorded calculation: justified absence, session exemption and historical excused policies. Localise the calculation and hide unused absence-minute fields and labels.
- Allow attendance calculation labels to wrap on narrow screens, including the session-exemption explanation.
- Localise export filenames and PDF report titles with the existing Moodle language strings.
- Export Excel minutes and percentages as numeric cells through Moodle's native Excel API; retain plain-text identifiers and remarks without interpreting them as formulas.


## 1.1.0-dev10 — 2026-10-07 (unreleased development)

- Protect session and journey deletion when equivalences or historical final results depend on them; enforce session deletion group boundaries.
- Preserve each session equivalence approval, rejection and revocation with the actor, explanation and date, in the same transaction as the current decision. Reject stale submissions.
- Include decision history in user-data backups and restore mapped participants, actors and equivalences. Cover discovery, export, deletion and actor anonymisation through Moodle Privacy.
- Seed only the latest known legacy decision as an explicitly partial historical baseline; earlier overwritten decisions cannot be reconstructed. Do not recalculate final grades.
- This alpha source has not been packaged or published; compatibility and final browser validation remain pending.

## 1.1.0-dev9 — 2026-10-07 (unreleased development)

- Add stable per-journey grade items, independent thresholds and all-required-journey completion; publish final grades only at explicit closure and reopen a selected journey without withdrawing another result.
- Create a main required journey for new activities; distinguish enrolled audiences from explicit assignments, including an explicitly empty audience.
- Separate justified absence from staff exemption in the new mode; preserve historical calculation settings and frozen closure results.
- Enforce closure guards for future or missing attendance and pending approvals; respect exact threshold decisions at gradebook rounding boundaries.
- Show native gradebook lock/manual-override notices to staff and enforce separate-group boundaries in journey management.
- Add a read-only historical conversion review and explicit conversion for compatible unfinished activities, preserving records and the original grade item. Leave incompatible or finalised histories unchanged; recheck capabilities, session key and the reviewed state before conversion.
- Add schema fields and preserve them through backup/restore. These sources are alpha and have not been packaged or published; the final compatibility/browser audit remains pending.

## 1.1.0-rc6 — 2026-10-07

- Serialize room changes with session assignment through Moodle's activity lock. A concurrent deletion rechecks room usage after the session writer completes.
- Show individual-report export and collective-report navigation only to users with report access. Students retain access to their own report and return to the activity overview; server-side permissions are unchanged.
- Remove unnecessary comparison-plugin references from installation guides and update browser validation notes. Preserve copyright and licence notices.
- Verify concurrent room use and role-specific report controls through the real controllers. Chrome teacher attendance entry under Boost and student reports under repaired New Learning were checked; earlier Excel/PDF and 320-pixel workflow evidence remains applicable to unchanged code.
- No database schema changes. VoiceOver remains deferred. This remains a release candidate; no public repository or Moodle submission has been made.

## 1.1.0-rc5 — 2026-10-07

- Present PDF reports as labelled record tables using Moodle’s bundled PDF library, with repeated headings across page breaks. Preserve every exported field, including blanks and zeroes, and escape plain-text remarks.
- Cover individual, collective, journey, journey-detail and audit PDF exports. Other formats keep the existing native dataformat pipeline.
- Validate 85 tests and 537 assertions on seven configurations covering Moodle 4.5–5.3, plus controller-level export and permission checks. Keep 868 language strings aligned in English, French and Spanish.
- No database schema changes. Browser download finalisation and the complete stable 320-pixel workflow remain pending. This remains a release candidate.


## 1.1.0-rc4 — 2026-10-07

- Allow participant names and email addresses to wrap inside narrow profile cards, and constrain the native report export selector to its container.
- Update English, French and Spanish testing guides with the validated 81-test matrix, branch-specific PHPUnit setup and known browser limitations. Include Moodle 5.3 in the French manual installation instructions.
- No PHP behaviour or database schema changes. Moodle Stylelint passes for the updated CSS. This remains a release candidate.

## 1.1.0-rc3 — 2026-10-07

- Remove activity instances through Moodle’s complete deletion API before uninstalling the plugin, preventing orphaned completion records.
- Use current course-format actions on Moodle 5.2+ and the supported legacy API on earlier branches.
- Add regression coverage for completion, files, grades and contexts, preservation of unrelated activities, and repeated cleanup.
- No database schema changes. This release candidate includes a native uninstall/reinstall validation in an isolated laboratory.

## 1.1.0-rc2 — 2026-10-07

- Localise recorded attendance details in the individual report, administrative audit and audit export, while preserving the original language-neutral audit snapshots and staff-authored notes.
- Correct the individual report filter label to refer to sessions. Keep English, French and Spanish strings aligned.
- Add regression coverage for all attendance statuses, immutable history, multiline remarks, unknown snapshot formats and HTML escaping.
- No database schema changes. This is a release candidate for evaluation before production use.

## 1.1.0-rc1 — 2026-10-07

- Extend compatibility to Moodle 5.3. Validate 79 tests and 498 assertions on all five branches from Moodle 4.5 to 5.3, including Moodle 5.3 with PHP 8.4 and PostgreSQL 17.
- Apply Moodle coding and documentation standards; rebuild AMD modules with Moodle Grunt and use Moodle's accessible confirmation dialogue for bulk attendance changes.
- Include English strings only in the installable plugin. Preserve French and Spanish translations separately for AMOS submission and local laboratory language customisations.
- Export attributed staff actions through the Privacy API, clear completion attribution on activity-wide erasure, handle orphaned review history, reject non-module contexts and coordinate privacy deletion with attendance writes.
- No database schema changes. This is a release candidate; installation, upgrade and theme verification are still in progress.

## 1.0.4 — 2026-09-07

- Fix normal GET navigation to administrative forms and confirmation pages without a session key; continue requiring a valid key for submissions.
- Restrict individual review history to the current activity and retain correct session names when filtering the report.
- Verify direct upgrade from original 1.0.0, cross-site native backup/restore with users, grades and completion, and teacher/student browser workflows. Add controller regressions for form navigation and cross-course history isolation.
- No database schema changes.

## 1.0.3 — 2026-09-07

- Paginate attendance entry at 100 participants, limit writes and approval to the selected page, coordinate administrative operations with attendance writes, strengthen keyboard focus indicators, and provide quick-start guides. Validate Moodle 5.0/5.1, page isolation, administrative concurrency, and portable demonstration restore.
- No database schema changes.

## 1.0.2 — 2026-09-07

- Serialize teacher, individual student and bulk student submissions per activity with Moodle's Lock API. Reload versions and records after acquiring the lock, preventing stale submissions and concurrent grade insertion conflicts.
- Require a valid session key for submissions and an explicit sheet version for teacher submissions.
- Reduce the collective report's unnecessary equivalence queries and loaded record fields for large classes.
- Remove a nested main landmark on the activity home page.
- Add lock lifecycle tests and privacy export/deletion assertions. Validate synthetic classes of 30, 300 and 1,000 students, simultaneous submissions and access controls locally.
- No database schema change.

## 1.0.1 — 2026-09-07

- Extend declared support to Moodle 5.2, with tests on Moodle 5.2.2 and regression coverage on Moodle 4.5.13.
- Preserve spacing, select controls, status badges and screen-reader labels across Bootstrap 4 and 5 themes.
- Add PHPUnit 11 group attributes while retaining PHPUnit 9 annotations for Moodle 4.5.
- Pass 75 tests and 426 assertions on Moodle 4.5/PHP 8.3 and Moodle 5.2.2/PHP 8.3 and 8.4.
- No database schema change; existing attendance data is retained during upgrade.

## 1.0.0 — 2026-08-15

- Published the first stable release after the complete release-candidate validation campaign.
- Confirmed the professional and Light workflows, multilingual terminology, illustrated Help centre and Moodle-native integrations.
- Validated the principal plugin pages on Moodle 4.5 and Moodle 5.0.
- Passed 75 automated tests and 426 assertions on Moodle 4.5.

## 0.99.57-rc85 — 2026-08-15

- Synchronized the English, French and Spanish package overviews with the audited feature set and current release metadata.
- Documented Light mode, multilingual business terminology, rooms, Moodle groups, advanced session series and the optional illustrated Help centre.
- Completed the pre-final technical, linguistic, structural and visual audit on Moodle 4.5 and Moodle 5.0.
- Passed 75 automated tests and 426 assertions on Moodle 4.5.
- This release remains a candidate and is not the final version.

## 0.99.56-rc84 — 2026-08-15

- Added an institution-wide Moodle admin setting that can hide the integrated Help tab from every activity.
- Redirected old integrated-help URLs safely to the activity overview when the centre is disabled.
- Kept Moodle’s native contextual help and all bundled documentation intact and immediately recoverable.
- Updated administrator guidance and automated configuration coverage in French, English and Spanish.
- This release remains a candidate and is not the final version.

## 0.99.55-rc83 — 2026-08-15

- Corrected singular/plural interpretation when a French canonical term has the same form for both numbers, notably `parcours`.
- Added French elision and contraction handling for vowel-initial custom terms, producing forms such as `un horaire`, `l’horaire`, `de l’horaire` and `les horaires`.
- Added automated regression coverage and validated the result visually with temporary local terminology that was removed after testing.
- Passed 74 automated tests and 423 assertions on Moodle 4.5.
- This release remains a candidate and is not the final version.

## 0.99.54-rc82 — 2026-08-15

- Completed the illustrated French, English and Spanish guides with 54 ethical demonstration screenshots created in Moodle Boost.
- Corrected student access to their own individual report while preserving the Moodle capability boundary that blocks other participants’ reports.
- Prevented customised business terminology from changing technical image and link URLs in the integrated documentation.
- Visually audited all main pages on Moodle 4.5 and Moodle 5.0 without horizontal overflow, missing plugin strings or page errors.
- Passed 73 automated tests and 421 assertions on Moodle 4.5.
- This release remains a candidate and is not the final version.

## 0.99.51-rc79 — 2026-08-15

- Added an integrated Help tab with automatic French, English or Spanish document selection.
- Restricted administrative, testing and upgrade documents according to Moodle capabilities and site-administrator status.
- Applied each activity’s customised business terminology to the displayed documentation.
- Added visible Singular and Plural headings above compact terminology fields.
- Added automated coverage for all 24 bundled documents.
- This release remains a candidate and is not the final version.

## 0.99.50-rc78 — 2026-08-15

- Moved terminology near the end of the activity-specific settings and collapsed it by default.
- Replaced the long field list with one compact singular/plural row per business concept.
- Shows the course language first and places additional languages behind Moodle’s native advanced-fields control.
- Preserved all multilingual values, inheritance rules and independent institutional locks.
- This release remains a candidate and is not the final version.

## 0.99.49-rc77 — 2026-08-15

- Extended contextual terminology to journeys, sessions, participants and rooms.
- Added independent singular/plural values for French, English and Spanish at site and activity level.
- Added an independent institutional lock for each business concept.
- Preserved all concepts in Moodle backup and restore and retained RC76 journey terms during upgrades.
- Expanded automated tests and trilingual guidance.
- This release remains a candidate and is not the final version.

## 0.99.48-rc76 — 2026-08-15

- Added independent French, English and Spanish terminology at site and activity level.
- Migrated existing single-language terminology into the appropriate language without discarding it.
- Preserved multilingual terms through Moodle backup, restore and activity deletion.
- Updated trilingual guidance and automated terminology coverage.
- This release remains a candidate and is not the final version.

## 0.99.47-rc75 — 2026-08-15

- Corrected activity-context resolution so saved course terminology is used on every plugin page.
- Extended terminology processing to all user-facing plugin strings and all three language packs, including dynamic labels.
- Added a regression test for automatic current-activity resolution.
- This release remains a candidate and is not the final version.

## 0.99.46-rc74 — 2026-08-15

- Added institutional and activity-level singular/plural terminology for attendance journeys.
- Added a Moodle-native site setting to lock the institutional terminology across all activities.
- Applied contextual terminology to journey workspaces, forms, reports and exports without changing internal identifiers or APIs.
- Preserved custom terms in Moodle backup and restore, expanded trilingual documentation and added automated policy and terminology tests.
- This release remains a candidate and is not the final version.

## 0.99.45-rc73 — 2026-08-15

- Added the complete Spanish language pack with all 809 plugin strings.
- Added Spanish user, administrator, mode, role, upgrade, acceptance, accessibility and testing documentation.
- Preserved every Moodle placeholder and validated parity with the English and French language packs.
- This release remains a candidate and is not the final version.

## 0.99.44-rc72 — 2026-08-15

- Removed named customer-environment references from the distributed history.
- Verified neutrality across code, languages, styles, tests, documentation and filenames.
- Confirmed that no logic depends on IOMAD, a commercial theme, a domain or a particular customer.
- This release remains a candidate and is not the final version.

## 0.99.43-rc71 — 2026-08-15

- Performed a live RC70 acceptance pass on Moodle 4.5 in Light mode and Moodle 5.0 in Professional mode.
- Validated primary pages, Professional workspaces, Light redirects and forms without missing labels.
- Corrected institutional-description Markdown spacing so literal `\\n\\n` characters are never displayed.
- This release remains a candidate and is not the final version.

## 0.99.42-rc70 — 2026-08-14

- Completed the pre-final audit of Light and Professional modes, permissions, protected mutations, languages, help, privacy, backup and Moodle integrations.
- Added field-specific explanations for every institutional value on the site administration page.
- Added bilingual administrator guides and Light/Professional mode matrices to the ZIP.
- Preserved the stable scope: no CRM integration or speculative business rule was introduced into this candidate.

## 0.99.41-rc69 — 2026-08-14

- Moved each institutional-lock explanation directly below the setting it governs in Moodle's activity form.
- Preserved the verified server-side enforcement and restored the test-site setting after validation.

## 0.99.40-rc68 — 2026-08-14

- Added Moodle-native site administration settings for institutional defaults.
- Added optional server-side locks for experience mode, calculations, threshold, gradebook, excused absences, student self-recording and calendar publication.
- Replaced all direct calendar-event deletion in active plugin code and legacy upgrades with Moodle's calendar API.
- Migrated the remaining inline JavaScript behaviours to reusable AMD modules.
- Added bilingual documentation and automated coverage for institutional policies and calendar cleanup.

## 0.99.30-rc58 — 2026-08-14

- Integrated approved equivalences with the central attendance calculator, reports, completion and gradebook.
- Kept the expected session duration as possible time while transferring actual present minutes capped at that duration.
- Prioritised normal attendance, prevented source-session double counting and identified equivalence credit in the individual report.
- Protected closed results and added unit coverage for differing durations and capped credit.

## 0.99.29-rc57 — 2026-08-14

- Added approval, rejection and revocation decisions for equivalences in the individual participant report.
- Recorded a decision note, staff member and timestamp for an auditable institutional history.
- Revalidated eligibility at approval time and prevented closing a result while a request remains pending.
- Approved equivalences remain calculation-neutral until the next integration step.

## 0.99.28-rc56 — 2026-08-14

- Added equivalence-request creation to the individual participant report.
- Replacement sessions must belong to another journey and already contain attendance for the participant.
- Required a rationale, prevented duplicate active requests and displayed request status in the participant record.
- Requests remain calculation-neutral until the approval step.

## 0.99.27-rc55 — 2026-08-14

- Added the data foundation for cross-journey session equivalences.
- The model distinguishes the expected session, attended replacement session, participant, rationale and auditable decision.
- Attendance results are not affected until the approval workflow is completed in a subsequent step.

## 0.99.26-rc54 — 2026-08-14

- Added a visible administrative history of waiting-list promotions and removals.
- Displayed the participant, decision, decision date and staff member responsible.
- Preserved bilingual operation and the existing Moodle-backed audit data.

## 0.99.25-rc53 — 2026-08-14

- Added an optional, ordered waiting list to each attendance journey.
- Added deliberate staff-controlled promotion and removal actions with an auditable status history.
- Warned before an intentional promotion beyond capacity while keeping authorised operational flexibility.
- Integrated waiting-list data with Moodle backup, restore, privacy, course reset and participant reset lifecycles.
- Validated 56 tests and 317 assertions on Moodle 4.5.13.

## 0.99.24-rc52 — 2026-08-14

- Added optional journey capacity independently from room capacity.
- Added available-place, full-capacity and exceeded-capacity indicators to journey management and detail views.
- Required an explicit authorised confirmation when creating, reducing or adding a participant beyond capacity, without imposing a rigid block.
- Preserved journey capacity in Moodle backup and restore.
- Validated 56 tests and 311 assertions on Moodle 4.5.13.

## 0.99.23-rc51 — 2026-08-14

- Added a Moodle-native course-group workspace inside Attendance Journeys.
- Authorised users can create groups, edit their identity and manage enrolled-course membership without duplicating group data.
- Groups become immediately available to journeys and sessions while advanced deletion and grouping operations remain in Moodle administration.
- Applied Moodle’s native `moodle/course:managegroups` permission and verified the separation between editing and non-editing teachers.
- Validated 55 tests and 307 assertions on Moodle 4.5.13.

## 0.99.22-rc50 — 2026-08-14

- Added reusable activity-local rooms with code, capacity, directions, notes and active/inactive lifecycle.
- Integrated saved rooms into in-person and hybrid sessions while retaining manual locations.
- Preserved readable location snapshots and room mappings in Moodle backup and restore.
- Prevented deletion of rooms already used by sessions.
- Validated 55 tests and 304 assertions on Moodle 4.5.13.

## 0.99.21-rc49 — 2026-08-14

- Added audience selection directly to the bulk session-duplication workflow.
- Copies can retain each source assignment or be assigned together to all participants, a Moodle group or an active journey.
- Expanded the preview to show original and proposed dates, audiences and journeys before creation.
- Recalculated overlap warnings against the proposed audience and revalidated targets on confirmation.
- Validated 55 tests and 299 assertions on Moodle 4.5.13.

## 0.99.20-rc48 — 2026-08-14

- Added bulk duplication of selected sessions with a calendar-day or minute offset.
- Added before/after preview, detailed overlap detection and explicit confirmation for intentional conflicts.
- Copies retain scheduling and audience information but never copy attendance records or final results.
- Excluded scopes protected by active final results and added optimistic concurrency protection.
- Validated 55 tests and 296 assertions on Moodle 4.5.13.

## 0.99.19-rc47 — 2026-08-14

- Added previewed bulk renaming for existing sessions, including a shared name or stable chronological numbering.
- Added bulk replacement or clearing of session descriptions without altering audiences, attendance calculations or final results.
- Extended optimistic concurrency protection to session names and descriptions.
- Added bilingual Moodle-native labels and guidance for the new administrative changes.
- Validated 54 tests and 291 assertions on Moodle 4.5.13.

## 0.99.18-rc46 — 2026-08-14

- Added previewed bulk assignment of empty sessions to all participants, Moodle groups or active journeys.
- Applied each journey’s configured group automatically and detected conflicts for the proposed audience.
- Excluded every session containing attendance or contributing to a final result.
- Added concurrency protection, calendar updates and recalculation after confirmed assignments.
- Validated 53 tests and 288 assertions on Moodle 4.5.13.

## 0.99.17-rc45 — 2026-08-14

- Added previewed bulk changes for duration, modality, location and virtual-class links.
- Added Moodle-native modality choices and support for clearing obsolete location or meeting-link values.
- Protected durations contributing to active final results while allowing safe delivery-information corrections.
- Rechecked conflicts after duration changes and retained explicit confirmation and concurrency protection.
- Validated 52 tests and 283 assertions on Moodle 4.5.13.

## 0.99.16-rc44 — 2026-08-14

- Added bulk schedule moves for existing sessions selected from the session catalogue.
- Added calendar-day and minute shifts with an explicit before/after preview.
- Added same-audience conflict detection and explicit acknowledgement for intentional overlaps.
- Excluded sessions protected by active final results and added optimistic concurrency protection.
- Updated Moodle calendar events, completion and grades after confirmed changes.
- Validated 51 tests and 279 assertions on Moodle 4.5.13.

## 0.99.15-rc43 — 2026-08-14

- Audited the full safe-deletion lifecycle for journeys and their sessions.
- Added automated proof that deleting a record-free journey removes its Moodle calendar events.
- Confirmed that unrelated independent sessions and their calendar events remain untouched.
- Retained the existing protection against deleting journeys containing attendance or final results.
- Validated 49 tests and 274 assertions on Moodle 4.5.13.

## 0.99.14-rc42 — 2026-08-14

- Completed a live visual review of an eight-session draft in a Moodle course layout with sidebar blocks.
- Confirmed the long-series cards, summary and detailed overlap report in the installed theme.
- Changed selection and sorting controls to secondary Moodle actions so creation and application remain visually primary.
- Created only a temporary private draft during validation; no test session was saved.

## 0.99.13-rc41 — 2026-08-14

- Reworked long session-series previews into clearly separated occurrence cards.
- Gave bulk-editing controls a distinct planning panel and improved action wrapping.
- Improved date controls, fields and buttons in narrow Moodle columns and on mobile screens.
- Kept the established Moodle form workflow and all series data unchanged.

## 0.99.12-rc40 — 2026-08-14

- Added stable chronological sorting after manual and bulk scheduling changes.
- Added a series dashboard with included, excluded, duration, period, modality and conflict indicators.
- Calculated totals from included meetings only and retained all occurrence metadata while sorting.
- Improved long-series review without changing the standard Moodle form workflow.
- Expanded the validated suite to 49 tests and 270 assertions.

## 0.99.11-rc39 — 2026-08-14

- Added bulk calendar-day moves for any selected subset of proposed meetings.
- Added positive or negative minute shifts while preserving meeting durations.
- Preserved local times across daylight-saving transitions for day-based moves.
- Recalculated detailed overlap warnings after every scheduling change.
- Expanded the validated suite to 48 tests and 263 assertions.

## 0.99.10-rc38 — 2026-08-14

- Replaced the generic overlap count with a detailed, actionable conflict report.
- Displayed both session names, complete times, overlap duration, audience and conflict source.
- Added a direct link to review an existing conflicting session in a separate tab without losing the draft.
- Kept intentional overlaps available through explicit acknowledgement.
- Validated 47 tests and 257 assertions on Moodle 4.5.13.

## 0.99.9-rc37 — 2026-08-14

- Added one-click selection of all included meetings and clearing of the bulk selection.
- Preserved every preview edit while selection commands are applied.
- Detected overlaps within a proposed series and against existing sessions for the same audience.
- Required explicit acknowledgement before intentional overlapping meetings can be created.
- Expanded the validated suite to 47 tests and 252 assertions.

## 0.99.8-rc36 — 2026-08-14

- Added per-occurrence delivery mode, location, online link and description to the series preview.
- Added bulk delivery-mode, location and online-link changes for selected meetings.
- Validated every included occurrence independently before the transaction begins.
- Preserved common audience, group and journey targeting to prevent accidental assignment changes.
- Validated 45 tests and 248 assertions on Moodle 4.5.13.

## 0.99.7-rc35 — 2026-08-14

- Added optional automatic numbering when a session series is generated.
- Added independent selection controls for bulk preview changes without altering inclusion choices.
- Added bulk sequential naming, shared naming and duration changes for any selected subset.
- Preserved local meeting times across daylight-saving transitions.
- Expanded the validated suite to 45 tests and 245 assertions.

## 0.99.6-rc34 — 2026-08-14

- Added a Moodle-native review step for generated session series while preserving immediate creation.
- Allowed each proposed meeting to have its own name, date and times or to be excluded before saving.
- Kept previews private to the current user and activity, with automatic two-hour expiry.
- Revalidated audiences and active journeys at confirmation and created the selected meetings transactionally.
- Expanded the validated suite to 42 tests and 230 assertions.

## 0.99.5-rc33 — 2026-08-13

- Corrected collective exports so pending student declarations never contribute to official attendance totals.
- Verified official report calculations, combined audit filters and all six Moodle export formats with French accented content.
- Added validation coverage for session and journey dates, groups, delivery modes and online links.
- Completed bilingual-language, XMLDB, permission, mutation-endpoint and responsive-interface checks.
- Expanded the validated suite to 40 tests and 225 assertions.

## 0.99.4-rc32 — 2026-08-13

- Centralised session attendance clearing in one transaction-safe helper.
- Verified that clearing keeps the session, removes attendance and review history, and advances its concurrency version.
- Verified that active final results prevent clearing until they are reopened.
- Added tests distinguishing harmless content edits from structural and audience changes.
- Expanded the validated suite to 32 tests and 187 assertions.

## 0.99.3-rc31 — 2026-08-13

- Added defensive ownership validation before any journey deletion.
- Verified safe deletion of record-free journeys and their sessions while preserving unrelated sessions.
- Verified that journeys containing attendance cannot be deleted.
- Verified that participant reset removes only the targeted Attendance Journeys records, review history, assignments and final results.
- Verified that Moodle accounts, enrolments and other participants' records remain intact.
- Expanded the validated suite to 29 tests and 167 assertions.

## 0.99.2-rc30 — 2026-08-13

- Added integration tests for common sessions and Moodle-group targeting.
- Verified that a frozen journey audience excludes participants who were not assigned.
- Verified that suspended enrolments disappear from new attendance sheets without deleting history.
- Verified that re-enrolment into a new journey receives a new attempt number and never reactivates self-recording for the former attempt.
- Expanded the validated suite to 25 tests and 143 assertions.

## 0.99.1-rc29 — 2026-08-13

- Centralised the student declaration edit-lock rule used by individual and bulk entry pages.
- Verified that pending declarations and requested corrections remain editable by their author.
- Verified that approved declarations and staff-entered attendance remain locked to students.
- Added approval-authority, immutable review-history and audit-snapshot tests.
- Expanded the validated suite to 21 tests and 126 assertions.

## 0.99.0-rc28 — 2026-08-13

- Added Moodle calendar integration tests for disabled and enabled configurations.
- Verified that session edits update one existing event without creating duplicates.
- Verified that disabling calendar integration removes only Attendance Journeys events.
- Verified that stale-event cleanup never removes another module's calendar entries.
- Expanded the validated suite to 17 tests and 107 assertions.

## 0.98.0-rc27 — 2026-08-13

- Added gradebook integration tests proving that only closed results publish final grades.
- Verified that reopening a result clears the final grade while retaining history.
- Added tests for recorded, all-sessions, closed-result and passing-threshold completion rules.
- Expanded the validated suite to 14 tests and 94 assertions.

## 0.97.0-rc26 — 2026-08-13

- Added real Moodle course backup and restore integration tests.
- Verified complete restoration of sessions, journeys, attendance records, review history, assignments and final results.
- Verified that a structure-only restore excludes user data and reopens copied journeys coherently.
- Expanded the validated suite to 12 tests and 83 assertions.

## 0.96.0-rc25 — 2026-08-13

- Added a standard Moodle test-data generator for Attendance Journeys.
- Added automated default-role capability tests.
- Added privacy discovery tests covering participants, attendance takers, approvers, reviewers and journey administrators.
- Added a journey-result closure and reopening lifecycle test.
- Expanded the validated suite to 10 tests and 67 assertions.

## 0.95.0-rc24 — 2026-08-13

- Added a reusable isolated Moodle PHPUnit environment for Attendance Journeys.
- Corrected invalid empty-string defaults detected by Moodle XMLDB during test installation.
- Added the upgrade path that removes those defaults from existing installations.

## 0.94.0-rc23 — 2026-08-13

- Audited backup, restore, activity deletion, course reset and privacy lifecycle handling.
- Completed privacy user discovery for reviewers and journey-result administrators.
- Added a database test ensuring activity deletion removes every dependent operational record.

## 0.93.0-rc22 — 2026-08-13

- Audited the default Moodle capability matrix for students, non-editing teachers, editing teachers, managers and site administrators.
- Added bilingual role-permission and accessibility documentation.
- Linked minute validation errors to their fields for assistive technologies.
- Added live announcements for calculated attendance updates.
- Standardised visible keyboard focus across plugin controls.

## 0.92.0-rc21 — 2026-08-13

- Completed the operational documentation in French and English.
- Added English user, upgrade and acceptance-test guides.
- Harmonised terminology between the interface and documentation.

## 0.91.0-rc20 — 2026-08-13

- Completed Moodle-native contextual help across settings, completion, sessions and journeys.
- Added concise French and English explanations for calculations, excused absences and finalisation.
- Added accessible help popovers for dates, locations, online links, recurrence and journey assignments.

## 0.90.0-rc19 — 2026-08-13

- Added the production-ready documentation set.
- Added installation, upgrade and rollback guidance.
- Added a French operational guide and institutional acceptance protocol.
- Documented capabilities, privacy, backup and release limitations.

## 0.89.0-rc18 — 2026-08-13

- Completed the Moodle 4.5 stabilisation campaign.
- Fixed course-reset loading of immutable audit-history helpers.
- Validated native backup and restore, including audit history.
- Validated six Moodle data-export formats.

## 0.88.0-rc17 — 2026-08-13

- Added a central, read-only institutional audit log.
- Added filters by participant, session, group, action and period.
- Added audit-log exports.
- Added immutable attendance-created and attendance-updated actions.

## 0.87.0-rc16 — 2026-08-13

- Added immutable approval, correction-request and resubmission history.
- Added history to individual reports, privacy exports and Moodle backups.

## Earlier release candidates

Earlier iterations introduced journeys and attempts, manual result finalisation, Moodle groups, inactive-enrolment handling, role separation, student self-recording and approval, gradebook and completion integration, reports, exports, calendar control, delivery modes, administration tools and the professional Moodle-aligned interface.
