# Roles and permissions matrix

This matrix describes the default permissions in Attendance Journeys. Moodle administrators may adapt these capabilities in custom roles.

| Action | Student | Non-editing teacher | Editing teacher | Manager | Site administrator |
|---|---:|---:|---:|---:|---:|
| View the activity and their own situation | Yes | Yes | Yes | Yes | Yes |
| Record their own attendance when enabled | Yes | No | No | No | Yes, by capability |
| Take or approve attendance | No | Yes | Yes | Yes | Yes |
| View reports for authorised participants | No | Yes | Yes | Yes | Yes |
| Create and manage sessions | No | No | Yes | Yes | Yes |
| Create and manage journeys | No | No | Yes | Yes | Yes |
| Close and publish final journey results | No | No | Yes | Yes | Yes |
| Reopen final journey results | No | No | Yes | Yes | Yes |
| Open a personal new attempt | No | No | Yes | Yes | Yes |
| Approve, reject or revoke equivalences | No | No | Yes | Yes | Yes |
| Change activity settings | No | No | Yes | Yes | Yes |
| Reset a participant’s data | No | No | No | No | Yes by default |

## Moodle capabilities

- `mod/attendancejourneys:view`: view the activity.
- `mod/attendancejourneys:canbelisted`: belong to the eligible student audience.
- `mod/attendancejourneys:selfrecord`: record one's own attendance.
- `mod/attendancejourneys:takeattendance`: take and approve attendance.
- `mod/attendancejourneys:viewreports`: view reports permitted by the context and group rules.
- `mod/attendancejourneys:managesessions`: manage sessions.
- `mod/attendancejourneys:managejourneys`: manage journeys; specific decisions also require their separate capability.
- `mod/attendancejourneys:closejourneys`: close journeys and publish final results.
- `mod/attendancejourneys:reopenjourneys`: reopen final results.
- `mod/attendancejourneys:manageattempts`: open a personal new attempt.
- `mod/attendancejourneys:approveequivalences`: approve, reject or revoke equivalences.
- `mod/attendancejourneys:manage`: change activity-specific settings.
- `mod/attendancejourneys:resetuserdata`: use destructive administration functions.

Separate-group restrictions and Moodle's `moodle/site:accessallgroups` capability are honoured. A student is never granted access to another participant's individual report.
