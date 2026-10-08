# Institutional acceptance test

Run this protocol on a staging site before general deployment.

## Required accounts

- one site administrator;
- one editing teacher;
- one non-editing teacher;
- two students in different groups;
- one suspended or inactive student.

## Mandatory scenarios

1. Confirm that only the editing teacher can create sessions and journeys.
2. Confirm that the non-editing teacher can take attendance and view reports without changing the structure.
3. Confirm that a student sees only their own data.
4. In a historical activity, check common, group and journey sessions. In a new activity, confirm every session belongs to a journey and follows its audience.
5. Save an incomplete sheet containing Unrecorded statuses.
6. Test Present, Absent, Partial and Excused.
7. Have a student self-record, request a correction, resubmit and approve the declaration.
8. Confirm that the student cannot modify an approved declaration.
9. Close a result, verify grade and completion, then reopen it.
10. In a new Professional activity, assign the participant to a second distinct journey and confirm the first assignment and its history remain visible. Do not treat the second assignment as a new attempt at the first journey.
11. Suspend an enrolment and confirm that the participant disappears from new attendance sheets without losing history.
12. Check the audit log and every export format required by the institution.
13. Back up and restore the activity in a test course.
14. Test exceptional reset using only a fictitious participant.

15. Create two required journeys for one participant, with thresholds 60 % and 80 %. Record 70 % attendance in each: one meets its threshold and the other does not.
16. Close only the first journey: only its grade is published and required completion remains incomplete. Close the second at 70 %: completion remains incomplete. Reopen only the second, correct it to 80 % and close it; verify both completion and preservation of the first grade.
17. Confirm that future sessions, missing required attendance and unresolved approvals prevent final closure; unrecorded sessions are not automatically treated as absence.
18. Compare justified absence with an authorised session exemption: justified time remains required, exempt time is excluded, and an exemption cannot be self-declared.
19. Cancel and reinstate a session with reasons. Preserve attendance and decision history, update required minutes, and confirm that cancelling every session does not automatically pass a journey.
20. Approve an equivalence from a real official source. Verify the target-duration cap, no effect before approval and refusal to reuse the same source for a second credit.
21. Protect a grade through Moodle locking or manual override and verify the notice before an authorised correction.
22. In a new Light activity, open the individual report as an authorised editing teacher, preview closure, publish its main-journey result and reopen it. Confirm that students cannot close it and that advanced journey management and bulk closure remain unavailable.

## Acceptance criteria

- no blank page or Moodle error;
- no data exposed from another group or participant;
- no final result or certificate triggered before the intended closure;
- no duplicate calendar notification;
- coherent backup, restore, grade and completion;
- written approval from the educational owner and Moodle administrator.
