# Administrator guide — Attendance Journeys

## New and historical grading modes

New activities create a required main journey and use independent journey grades. A participant's grade is published only at explicit closure; automatic completion requires every assigned required journey to be closed and passed. Justified absence counts as absence; an authorised session exemption removes the session obligation. Professional mode permits distinct simultaneous journeys with independent thresholds. Light mode retains the simple main-journey workflow.

Upgrading does not convert existing activities or recalculate their historical final results. Use **Review grading mode** and the [upgrade guide](UPGRADE_GUIDE_EN.md) for the explicit, conservative conversion workflow. Historical attendance-record completion settings and configurable excused-time policies must not be treated as the rules for a new activity.

See the [user guide](USER_GUIDE_EN.md) for current journey rules and illustrations.

## Individual journey obligations

In new journey grading mode, open **Reports → participant → Individual journey obligation**. Authorised editing staff may waive the entire obligation or set an explicit individual period. Every new decision, including reinstatement, needs a reason. A final result must be reopened first; Moodle gradebook locks and overrides remain protected.

Leave both date checkboxes disabled to require every applicable session. The start boundary is inclusive and the end boundary exclusive. Sessions wholly outside the period are excluded; a session overlapping it counts in full. Enrolment and assignment audit dates never infer these boundaries. For example, excluding an earlier 100-minute absence while retaining a 100-minute session with 20 absent minutes changes the provisional calculation from 80/200 to 80/100. Recorded attendance stays in the history.

Select **Preview the decision** to review minutes and sessions before and after, including exclusions and reinstatements. **Edit** returns to the proposal without saving. **Confirm this decision** records the reason and decision history; it does not close the journey or publish a grade. If attendance, obligations or access changed after preview, obtain a new preview. **Cancel** leaves the current obligation unchanged.

A whole-journey waiver ignores the dates, removes this obligation from required automatic completion and publishes no artificial grade or success. Other required journeys still have to be closed and passed. If all obligations are waived, automatic completion remains false; an authorised person may use Moodle's native manual completion decision. The summaries identify the waiver; detailed reports retain historical minutes and mark sessions outside the individual obligation. Existing equivalences can prevent removing a session involved in an unresolved or approved decision.

The participant can read their decision history. Other participants cannot read it. Backup with users includes decisions and their history; structure-only backup omits them. When restoring with a course date offset, session dates and pedagogical boundaries move together, while decision and attendance audit timestamps retain their original dates.


## Institutional and course terminology

Site administration can define singular and plural terms separately in French, English and Spanish for four business concepts: **journey, session, participant and room**. Each concept has its own institutional lock. A blank field retains the standard translated label in that language. When a concept is not locked, teachers can override each language in the activity settings; a blank course value inherits the site value for that language. This affects display text only: database identifiers, capabilities, backup data and integrations remain stable.

## Site administration pages

The main settings page links to **Terminology**, **Historical settings** and the **Administrator guide**. The guide is accessible directly from site administration without creating an activity, even when activity help is disabled. Access requires Moodle's site configuration capability.

Default values apply to new activities. A locked value is enforced when an activity is next saved; changing a default or lock does not mass-update activities or recalculate final results. Terminology changes affect display immediately. Historical excused-time settings apply only to the historical calculation mode; new journey grading retains the distinction between justified absence and authorised exemption.

The percentage calculation and gradebook defaults are saved as a validated group: a gradebook default cannot be enabled while percentage calculation is disabled. Moodle records configuration changes normally. This does not publish provisional grades. The calculation cannot be locked off because new journey grading requires it.

**Allow journey-specific thresholds** is enabled by default. Disabling it prevents creating or changing individual thresholds on ordinary journeys, including removing an existing override. Existing thresholds, final results and the inherited threshold of a personal new attempt are retained. New journeys inherit the activity threshold. Re-enabling the policy permits changes subject to the normal final-result protections. Locking the activity threshold alone does not prohibit journey-specific thresholds.

## Delegated pedagogical decisions

Moodle roles can separately allow closing journeys, reopening them, opening personal new attempts and approving or revoking equivalences. Each decision also requires the existing journey-management capability; other course, group and activity access restrictions continue to apply. Equivalence proposals remain distinct from their approval.

On upgrade, Moodle clones each new capability from the existing journey-management permission, including local prohibitions. Existing editing staff retain their rights until the administrator explicitly adapts the role. Check both capabilities when delegating a decision; hiding a button alone is not the access control.

## Institutional configuration

The **Site administration → Plugins → Activity modules → Attendance Journeys** page uses Moodle's native configuration API. It defines the proposed values for new activities: Light or Professional mode, percentage calculation, threshold, gradebook, Excused status, student self-recording and calendar integration.

The institution-wide **Show the integrated help centre** setting hides the Help tab from every activity. An existing help-centre URL then redirects to the activity overview with a clear notice. The documents remain bundled with the plugin and reappear without data loss when the setting is enabled again. Moodle’s native contextual help in forms always remains available.

Each value has an optional lock. Without a lock, an editing teacher may adapt the value in an activity. With a lock, the institutional value is displayed as read-only in the form and enforced again during server-side saving. An existing activity adopts a newly locked value when it is edited.

## Choosing a mode

- **Light**: simple sessions, attendance recording, optional self-recording and essential reports. Advanced features are hidden and their URLs are protected server-side.
- **Professional**: adds simultaneous journeys, capacity and waitlists, equivalences, rooms, Moodle groups, bulk operations, exceptional administration and audit. New Light activities also support individual final closure of their main journey.

The mode does not create two plugin editions or duplicate data. It changes the experience available in each activity.

The activity form organises settings into the Moodle sections **User experience**, **Attendance calculation**, **Recording permissions**, **Moodle calendar** and **Terminology**.

## Recommended permissions

- Student: view the activity and their own information; self-record only when both the option and capability are enabled.
- Non-editing teacher: take attendance and view reports.
- Editing teacher or manager: also manage sessions, journeys and operational resources.
- Site administrator: exceptional reset and institutional audit.

These behaviours use Moodle capabilities and can be adapted in **Define roles** or through course/activity overrides. Grant `mod/attendancejourneys:resetuserdata` only to people authorised to delete institutional data.

## Moodle data and integrations

- Grades use the Gradebook API.
- Completion uses the custom completion API.
- Calendar integration is optional and disabled by default; events use the Calendar API.
- Groups created in Attendance Journeys are real Moodle course groups.
- Reports use the data formats installed in Moodle.
- Backup/restore, course reset and the Privacy API are supported.

## Operations and maintenance

The **Administration** tab can search or filter participants and initiate an exceptional reset. Its warning explicitly states that only Attendance Journeys data is removed; Moodle accounts, enrolments, groups, other grades and system logs remain intact.

The **Audit log** is separate from editable data and retains the institutional trail of sensitive operations.

Test every upgrade on a staging site, then back up the database and Moodledata before production installation. After an upgrade, purge caches, open one Light and one Professional activity, and run the acceptance test supplied in the ZIP.

Provisional results must not trigger certificates. Use closure to publish a final result. Reopening must be an authorised decision and remains traceable.

## Two required journeys: a practical example

| | Attendance | Threshold | State | Gradebook |
|---|---:|---:|---|---|
| Theory | 70 % | 60 % | Closed, passed | 70 % |
| Laboratory | 70 % | 80 % | Open, provisional | No grade |

Completion remains incomplete while Laboratory is open. If it is closed at 70 %, its grade is published but its 80 % threshold is not met: completion still remains incomplete. Reopening Laboratory withdraws only its final grade. After an authorised correction and closure at 80 %, both required journeys have passed. Moodle controls course grade aggregation; a successful Theory result does not compensate for a failed required Laboratory journey.


## New attempts at the same obligation

In the participant’s individual report, open **Open a new attempt** after closing the current attempt. Authorised staff provide a name and a reason, preview the consequences, then confirm or cancel. A locked or manually overridden Moodle grade prevents the opening. A waiver must first be restored.

Confirmation opens a separate personal attempt with the original obligation’s threshold. Plan new sessions for it: no sessions or attendance are copied. The previous final grade is withdrawn and automatic completion is re-evaluated until the new attempt is closed. The latest closed result replaces the previous result even if lower; a higher earlier result is never used as a fallback. Reopening the current attempt withdraws its result again. Correcting a previous attempt keeps its history and does not change which attempt is current.

One obligation retains one grade item and one completion requirement across all its attempts. Independent theory and laboratory obligations retain separate thresholds, grades and requirements. An assignment number within the activity is not the personal attempt number for an obligation.

An individual report for one obligation opens its current attempt directly. With multiple obligations, choose the obligation’s current attempt or a history link. Previous attendance and closures remain available. Physical-journey collective reports and Excel/PDF exports retain the requested journey’s records; **Previous final result** or **Previous attempt** means this result does not determine the current grade. Session-level exports include a separate **Attempt result status** column, so an attendance status such as Present is not confused with publication of a final result.
