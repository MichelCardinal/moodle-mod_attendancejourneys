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

namespace mod_attendancejourneys\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for Attendance Journeys.
 *
 * @package    mod_attendancejourneys
 * @category   privacy
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Declare the personal data stored by the activity.
     *
     * @param collection $collection Metadata collection supplied by the Privacy API.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('attendancejourneys_records', [
            'userid' => 'privacy:metadata:records:userid',
            'status' => 'privacy:metadata:records:status',
            'minutesabsent' => 'privacy:metadata:records:minutesabsent',
            'remarks' => 'privacy:metadata:records:remarks',
            'takenby' => 'privacy:metadata:records:takenby',
            'approved' => 'privacy:metadata:records:approved',
            'approvedby' => 'privacy:metadata:records:approvedby',
            'timeapproved' => 'privacy:metadata:records:timeapproved',
            'changesrequested' => 'privacy:metadata:records:changesrequested',
            'reviewnote' => 'privacy:metadata:records:reviewnote',
            'reviewedby' => 'privacy:metadata:records:reviewedby',
            'timereviewed' => 'privacy:metadata:records:timereviewed',
            'timemodified' => 'privacy:metadata:records:timemodified',
        ], 'privacy:metadata:records');
        $collection->add_database_table('attendancejourneys_reviews', [
            'userid' => 'privacy:metadata:reviews:userid',
            'action' => 'privacy:metadata:reviews:action',
            'actorid' => 'privacy:metadata:reviews:actorid',
            'note' => 'privacy:metadata:reviews:note',
            'timecreated' => 'privacy:metadata:reviews:timecreated',
        ], 'privacy:metadata:reviews');
        $collection->add_database_table('attendancejourneys_closures', [
            'journeyid' => 'privacy:metadata:closures:journeyid',
            'userid' => 'privacy:metadata:closures:userid',
            'active' => 'privacy:metadata:closures:active',
            'applicablesessions' => 'privacy:metadata:closures:applicablesessions',
            'recordedsessions' => 'privacy:metadata:closures:recordedsessions',
            'presentminutes' => 'privacy:metadata:closures:presentminutes',
            'possibleminutes' => 'privacy:metadata:closures:possibleminutes',
            'percentage' => 'privacy:metadata:closures:percentage',
            'threshold' => 'privacy:metadata:closures:threshold',
            'result' => 'privacy:metadata:closures:result',
            'closedby' => 'privacy:metadata:closures:closedby',
            'timeclosed' => 'privacy:metadata:closures:timeclosed',
            'reopenedby' => 'privacy:metadata:closures:reopenedby',
            'timereopened' => 'privacy:metadata:closures:timereopened',
        ], 'privacy:metadata:closures');
        $collection->add_database_table('attendancejourneys_journeys', [
            'completedby' => 'privacy:metadata:journeys:completedby',
            'timecompleted' => 'privacy:metadata:journeys:timecompleted',
        ], 'privacy:metadata:journeys');
        $collection->add_database_table('attendancejourneys_members', [
            'journeyid' => 'privacy:metadata:members:journeyid',
            'userid' => 'privacy:metadata:members:userid',
            'attemptnumber' => 'privacy:metadata:members:attemptnumber',
            'active' => 'privacy:metadata:members:active',
            'timeassigned' => 'privacy:metadata:members:timeassigned',
            'timeended' => 'privacy:metadata:members:timeended',
        ], 'privacy:metadata:members');
        $collection->add_database_table('attendancejourneys_waitlist', [
            'journeyid' => 'privacy:metadata:waitlist:journeyid',
            'userid' => 'privacy:metadata:waitlist:userid',
            'status' => 'privacy:metadata:waitlist:status',
            'createdby' => 'privacy:metadata:waitlist:createdby',
            'timecreated' => 'privacy:metadata:waitlist:timecreated',
            'updatedby' => 'privacy:metadata:waitlist:updatedby',
            'timeupdated' => 'privacy:metadata:waitlist:timeupdated',
        ], 'privacy:metadata:waitlist');
        $collection->add_database_table('attendancejourneys_equivalences', [
            'userid' => 'privacy:metadata:equivalences:userid',
            'targetjourneyid' => 'privacy:metadata:equivalences:targetjourneyid',
            'targetsessionid' => 'privacy:metadata:equivalences:targetsessionid',
            'sourcesessionid' => 'privacy:metadata:equivalences:sourcesessionid',
            'status' => 'privacy:metadata:equivalences:status',
            'reason' => 'privacy:metadata:equivalences:reason',
            'createdby' => 'privacy:metadata:equivalences:createdby',
            'timecreated' => 'privacy:metadata:equivalences:timecreated',
            'decidedby' => 'privacy:metadata:equivalences:decidedby',
            'timedecided' => 'privacy:metadata:equivalences:timedecided',
            'decisionnote' => 'privacy:metadata:equivalences:decisionnote',
            'timemodified' => 'privacy:metadata:equivalences:timemodified',
        ], 'privacy:metadata:equivalences');
        $collection->add_database_table('attendancejourneys_equivlog', [
            'equivalenceid' => 'privacy:metadata:equivlog:equivalenceid',
            'userid' => 'privacy:metadata:equivlog:userid',
            'actorid' => 'privacy:metadata:equivlog:actorid',
            'action' => 'privacy:metadata:equivlog:action',
            'previousstatus' => 'privacy:metadata:equivlog:previousstatus',
            'status' => 'privacy:metadata:equivlog:status',
            'note' => 'privacy:metadata:equivlog:note',
            'timecreated' => 'privacy:metadata:equivlog:timecreated',
        ], 'privacy:metadata:equivlog');
        $collection->add_database_table('attendancejourneys_sessionlog', [
            'sessionid' => 'privacy:metadata:sessionlog:sessionid',
            'actorid' => 'privacy:metadata:sessionlog:actorid',
            'action' => 'privacy:metadata:sessionlog:action',
            'reason' => 'privacy:metadata:sessionlog:reason',
            'timecreated' => 'privacy:metadata:sessionlog:timecreated',
        ], 'privacy:metadata:sessionlog');
        $collection->add_database_table('attendancejourneys_attempts', [
            'rootjourneyid' => 'privacy:metadata:members:journeyid',
            'journeyid' => 'privacy:metadata:members:journeyid',
            'userid' => 'privacy:metadata:members:userid',
            'attemptnumber' => 'privacy:metadata:members:attemptnumber',
            'actorid' => 'privacy:metadata:reviews:actorid',
            'timecreated' => 'privacy:metadata:obligations:time',
            'reason' => 'privacy:metadata:obligations:reason',
        ], 'privacy:metadata:attempts');
        foreach (['attendancejourneys_obligations', 'attendancejourneys_obligationlog'] as $table) {
            $fields = [
                'journeyid' => 'privacy:metadata:members:journeyid',
                'userid' => 'privacy:metadata:members:userid',
                'actorid' => 'privacy:metadata:reviews:actorid',
                'waived' => 'privacy:metadata:obligations:waived',
                'starttime' => 'privacy:metadata:obligations:starttime',
                'endtime' => 'privacy:metadata:obligations:endtime',
                'revision' => 'privacy:metadata:obligations:revision',
                'reason' => 'privacy:metadata:obligations:reason',
            ];
            if ($table === 'attendancejourneys_obligationlog') {
                foreach (['waived', 'starttime', 'endtime'] as $field) {
                    $fields['previous' . $field] = 'privacy:metadata:obligations:previous' . $field;
                }
                $fields['timecreated'] = 'privacy:metadata:obligations:time';
            } else {
                $fields['timemodified'] = 'privacy:metadata:obligations:time';
            }
            $collection->add_database_table($table, $fields, 'privacy:metadata:obligations');
        }
        return $collection;
    }

    /**
     * Find activity contexts containing data about this user.
     *
     * @param int $userid User identifier.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {attendancejourneys_records} ar
                  JOIN {attendancejourneys_sessions} aps ON aps.id = ar.sessionid
                  JOIN {attendancejourneys} ap ON ap.id = aps.attendancejourneysid
                  JOIN {modules} m ON m.name = :modname
                  JOIN {course_modules} cm ON cm.instance = ap.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE ar.userid = :participantid OR ar.takenby = :recorderid OR ar.approvedby = :approverid
                    OR ar.reviewedby = :reviewerid
                 UNION
                SELECT DISTINCT ctx.id
                  FROM {attendancejourneys_reviews} arv
                  JOIN {attendancejourneys_sessions} aps ON aps.id = arv.sessionid
                  JOIN {attendancejourneys} ap ON ap.id = aps.attendancejourneysid
                  JOIN {modules} m ON m.name = :modnamereview
                  JOIN {course_modules} cm ON cm.instance = ap.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevelreview
                 WHERE arv.userid = :reviewuserid OR arv.actorid = :reviewactorid
                 UNION
                SELECT DISTINCT ctx.id
                  FROM {attendancejourneys_closures} ac
                  JOIN {attendancejourneys} ap ON ap.id = ac.attendancejourneysid
                  JOIN {modules} m ON m.name = :modname2
                  JOIN {course_modules} cm ON cm.instance = ap.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel2
                 WHERE ac.userid = :closureuserid OR ac.closedby = :closureclosedby OR ac.reopenedby = :reopenedby
                 UNION
                SELECT DISTINCT ctx.id
                  FROM {attendancejourneys_journeys} aj
                  JOIN {attendancejourneys} ap ON ap.id = aj.attendancejourneysid
                  JOIN {modules} m ON m.name = :modname3
                  JOIN {course_modules} cm ON cm.instance = ap.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel3
                 WHERE aj.completedby = :journeycompletedby
                 UNION
                SELECT DISTINCT ctx.id FROM {attendancejourneys_members} am
                  JOIN {attendancejourneys} ap ON ap.id = am.attendancejourneysid
                  JOIN {modules} m ON m.name = :modname4
                  JOIN {course_modules} cm ON cm.instance = ap.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel4
                 WHERE am.userid = :memberuserid
                 UNION
                SELECT DISTINCT ctx.id FROM {attendancejourneys_waitlist} aw
                  JOIN {attendancejourneys} ap ON ap.id = aw.attendancejourneysid
                  JOIN {modules} m ON m.name = :modname5
                  JOIN {course_modules} cm ON cm.instance = ap.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel5
                 WHERE aw.userid = :waituserid OR aw.createdby = :waitcreatedby OR aw.updatedby = :waitupdatedby
                 UNION
                SELECT DISTINCT ctx.id FROM {attendancejourneys_equivalences} ae
                  JOIN {attendancejourneys} ap ON ap.id = ae.attendancejourneysid
                  JOIN {modules} m ON m.name = :modname6
                  JOIN {course_modules} cm ON cm.instance = ap.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel6
                 WHERE ae.userid = :equivuserid OR ae.createdby = :equivcreatedby OR ae.decidedby = :equivdecidedby
                 UNION
                SELECT DISTINCT ctx.id FROM {attendancejourneys_equivlog} el
                  JOIN {attendancejourneys} ap ON ap.id = el.attendancejourneysid
                  JOIN {modules} m ON m.name = :modnamehistory
                  JOIN {course_modules} cm ON cm.instance = ap.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevelhistory
                 WHERE el.userid = :historyuserid OR el.actorid = :historyactorid
                 UNION
                SELECT DISTINCT ctx.id FROM {attendancejourneys_sessionlog} sl
                  JOIN {attendancejourneys} ap ON ap.id = sl.attendancejourneysid
                  JOIN {modules} m ON m.name = :modnamecancel
                  JOIN {course_modules} cm ON cm.instance = ap.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevelcancel
                 WHERE sl.actorid = :cancelactorid";
        $contextlist->add_from_sql($sql, [
            'modname' => 'attendancejourneys',
            'contextlevel' => CONTEXT_MODULE,
            'participantid' => $userid,
            'recorderid' => $userid,
            'approverid' => $userid,
            'reviewerid' => $userid,
            'modnamereview' => 'attendancejourneys', 'contextlevelreview' => CONTEXT_MODULE,
            'reviewuserid' => $userid, 'reviewactorid' => $userid,
            'modname2' => 'attendancejourneys', 'contextlevel2' => CONTEXT_MODULE,
            'closureuserid' => $userid, 'closureclosedby' => $userid, 'reopenedby' => $userid,
            'modname3' => 'attendancejourneys', 'contextlevel3' => CONTEXT_MODULE, 'journeycompletedby' => $userid,
            'modname4' => 'attendancejourneys', 'contextlevel4' => CONTEXT_MODULE, 'memberuserid' => $userid,
            'modname5' => 'attendancejourneys', 'contextlevel5' => CONTEXT_MODULE,
            'waituserid' => $userid, 'waitcreatedby' => $userid, 'waitupdatedby' => $userid,
            'modname6' => 'attendancejourneys', 'contextlevel6' => CONTEXT_MODULE,
            'equivuserid' => $userid, 'equivcreatedby' => $userid, 'equivdecidedby' => $userid,
            'modnamehistory' => 'attendancejourneys', 'contextlevelhistory' => CONTEXT_MODULE,
            'historyuserid' => $userid, 'historyactorid' => $userid,
            'modnamecancel' => 'attendancejourneys', 'contextlevelcancel' => CONTEXT_MODULE, 'cancelactorid' => $userid,
        ]);
        foreach (['attendancejourneys_obligations', 'attendancejourneys_obligationlog', 'attendancejourneys_attempts'] as $table) {
            $contextlist->add_from_sql(
                "SELECT DISTINCT ctx.id FROM {{$table}} item
                   JOIN {course_modules} cm ON cm.instance = item.attendancejourneysid
                   JOIN {modules} m ON m.id = cm.module AND m.name = :module
                   JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :level
                  WHERE item.userid = :participant OR item.actorid = :actor",
                ['module' => 'attendancejourneys', 'level' => CONTEXT_MODULE, 'participant' => $userid, 'actor' => $userid]
            );
        }
        return $contextlist;
    }

    /**
     * Add participants and recorded actors to the context user list.
     *
     * @param userlist $userlist Users in scope for this context.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT ar.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {attendancejourneys_sessions} aps ON aps.attendancejourneysid = cm.instance
                  JOIN {attendancejourneys_records} ar ON ar.sessionid = aps.id
                 WHERE cm.id = :cmid
                 UNION
                SELECT ar.takenby AS userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname2
                  JOIN {attendancejourneys_sessions} aps ON aps.attendancejourneysid = cm.instance
                  JOIN {attendancejourneys_records} ar ON ar.sessionid = aps.id
                 WHERE cm.id = :cmid2 AND ar.takenby > 0
                 UNION
                SELECT ar.approvedby AS userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname7
                  JOIN {attendancejourneys_sessions} aps ON aps.attendancejourneysid = cm.instance
                  JOIN {attendancejourneys_records} ar ON ar.sessionid = aps.id
                 WHERE cm.id = :cmid7 AND ar.approvedby > 0
                 UNION
                SELECT ar.reviewedby AS userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnamereviewer
                  JOIN {attendancejourneys_sessions} aps ON aps.attendancejourneysid = cm.instance
                  JOIN {attendancejourneys_records} ar ON ar.sessionid = aps.id
                 WHERE cm.id = :cmidreviewer AND ar.reviewedby > 0
                 UNION
                SELECT arv.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnamereview
                  JOIN {attendancejourneys_sessions} aps ON aps.attendancejourneysid = cm.instance
                  JOIN {attendancejourneys_reviews} arv ON arv.sessionid = aps.id
                 WHERE cm.id = :cmidreview
                 UNION
                SELECT arv.actorid AS userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnamereviewactor
                  JOIN {attendancejourneys_sessions} aps ON aps.attendancejourneysid = cm.instance
                  JOIN {attendancejourneys_reviews} arv ON arv.sessionid = aps.id
                 WHERE cm.id = :cmidreviewactor AND arv.actorid > 0
                 UNION
                SELECT ac.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname3
                  JOIN {attendancejourneys_closures} ac ON ac.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmid3
                 UNION
                SELECT ac.closedby AS userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnameclosedby
                  JOIN {attendancejourneys_closures} ac ON ac.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmidclosedby AND ac.closedby > 0
                 UNION
                SELECT ac.reopenedby AS userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnamereopenedby
                  JOIN {attendancejourneys_closures} ac ON ac.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmidreopenedby AND ac.reopenedby > 0
                 UNION
                SELECT aj.completedby AS userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname4
                  JOIN {attendancejourneys_journeys} aj ON aj.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmid4 AND aj.completedby > 0
                 UNION
                SELECT am.userid FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname5
                  JOIN {attendancejourneys_members} am ON am.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmid5
                 UNION
                SELECT aw.userid FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnamewait
                  JOIN {attendancejourneys_waitlist} aw ON aw.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmidwait
                 UNION
                SELECT aw.createdby AS userid FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnamewaitcreator
                  JOIN {attendancejourneys_waitlist} aw ON aw.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmidwaitcreator AND aw.createdby > 0
                 UNION
                SELECT aw.updatedby AS userid FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnamewaitupdater
                  JOIN {attendancejourneys_waitlist} aw ON aw.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmidwaitupdater AND aw.updatedby > 0
                 UNION
                SELECT ae.userid FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnameequiv
                  JOIN {attendancejourneys_equivalences} ae ON ae.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmidequiv
                 UNION
                SELECT ae.createdby AS userid FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnameequivcreator
                  JOIN {attendancejourneys_equivalences} ae ON ae.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmidequivcreator AND ae.createdby > 0
                 UNION
                SELECT ae.decidedby AS userid FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnameequivdecider
                  JOIN {attendancejourneys_equivalences} ae ON ae.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmidequivdecider AND ae.decidedby > 0
                 UNION
                SELECT el.userid FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnamehistoryuser
                  JOIN {attendancejourneys_equivlog} el ON el.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmidhistoryuser
                 UNION
                SELECT el.actorid AS userid FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnamehistoryactor
                  JOIN {attendancejourneys_equivlog} el ON el.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmidhistoryactor AND el.actorid > 0
                 UNION
                SELECT sl.actorid AS userid FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modnamecancelactor
                  JOIN {attendancejourneys_sessionlog} sl ON sl.attendancejourneysid = cm.instance
                 WHERE cm.id = :cmidcancelactor AND sl.actorid > 0";
        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'attendancejourneys', 'cmid' => $context->instanceid,
            'modname2' => 'attendancejourneys', 'cmid2' => $context->instanceid,
            'modname3' => 'attendancejourneys', 'cmid3' => $context->instanceid,
            'modname4' => 'attendancejourneys', 'cmid4' => $context->instanceid,
            'modname5' => 'attendancejourneys', 'cmid5' => $context->instanceid,
            'modname7' => 'attendancejourneys', 'cmid7' => $context->instanceid,
            'modnamereviewer' => 'attendancejourneys', 'cmidreviewer' => $context->instanceid,
            'modnamereview' => 'attendancejourneys', 'cmidreview' => $context->instanceid,
            'modnamereviewactor' => 'attendancejourneys', 'cmidreviewactor' => $context->instanceid,
            'modnameclosedby' => 'attendancejourneys', 'cmidclosedby' => $context->instanceid,
            'modnamereopenedby' => 'attendancejourneys', 'cmidreopenedby' => $context->instanceid,
            'modnamewait' => 'attendancejourneys', 'cmidwait' => $context->instanceid,
            'modnamewaitcreator' => 'attendancejourneys', 'cmidwaitcreator' => $context->instanceid,
            'modnamewaitupdater' => 'attendancejourneys', 'cmidwaitupdater' => $context->instanceid,
            'modnameequiv' => 'attendancejourneys', 'cmidequiv' => $context->instanceid,
            'modnameequivcreator' => 'attendancejourneys', 'cmidequivcreator' => $context->instanceid,
            'modnameequivdecider' => 'attendancejourneys', 'cmidequivdecider' => $context->instanceid,
            'modnamehistoryuser' => 'attendancejourneys', 'cmidhistoryuser' => $context->instanceid,
            'modnamehistoryactor' => 'attendancejourneys', 'cmidhistoryactor' => $context->instanceid,
            'modnamecancelactor' => 'attendancejourneys', 'cmidcancelactor' => $context->instanceid,
        ]);
        $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid);
        if ($cm) {
            foreach (
                ['attendancejourneys_obligations', 'attendancejourneys_obligationlog',
                'attendancejourneys_attempts'] as $table
            ) {
                $userlist->add_from_sql(
                    'userid',
                    "SELECT userid FROM {{$table}} WHERE attendancejourneysid = :participantactivity
                     UNION SELECT actorid AS userid FROM {{$table}}
                           WHERE attendancejourneysid = :actoractivity AND actorid > 0",
                    ['participantactivity' => $cm->instance, 'actoractivity' => $cm->instance]
                );
            }
        }
    }

    /**
     * Export personal data for the approved user and activity contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts and user for this privacy request.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $sql = "SELECT ar.*, aps.name AS sessionname, aps.sessiondate, aps.duration
                      FROM {attendancejourneys_records} ar
                      JOIN {attendancejourneys_sessions} aps ON aps.id = ar.sessionid
                     WHERE aps.attendancejourneysid = :instanceid AND ar.userid = :userid
                  ORDER BY aps.sessiondate, ar.id";
            $records = [];
            foreach ($DB->get_records_sql($sql, ['instanceid' => $cm->instance, 'userid' => $user->id]) as $record) {
                $records[] = (object) [
                    'session' => format_string($record->sessionname),
                    'sessiondate' => transform::datetime($record->sessiondate),
                    'durationminutes' => (int) $record->duration,
                    'status' => get_string('status' . $record->status, 'attendancejourneys'),
                    'minutesabsent' => (int) $record->minutesabsent,
                    'remarks' => $record->remarks,
                    'takenby' => (int) $record->takenby,
                    'approved' => !empty($record->approved),
                    'approvedby' => (int) ($record->approvedby ?? 0),
                    'timeapproved' => !empty($record->timeapproved) ? transform::datetime($record->timeapproved) : null,
                    'changesrequested' => !empty($record->changesrequested),
                    'reviewnote' => $record->reviewnote ?? '',
                    'reviewedby' => (int) ($record->reviewedby ?? 0),
                    'timereviewed' => !empty($record->timereviewed) ? transform::datetime($record->timereviewed) : null,
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }
            $recordedactions = $DB->count_records_sql(
                "SELECT COUNT(ar.id)
                   FROM {attendancejourneys_records} ar
                   JOIN {attendancejourneys_sessions} aps ON aps.id = ar.sessionid
                  WHERE aps.attendancejourneysid = :instanceid AND ar.takenby = :userid",
                ['instanceid' => $cm->instance, 'userid' => $user->id]
            );
            $reviewsql = "SELECT arv.*, aps.name AS sessionname
                            FROM {attendancejourneys_reviews} arv
                            JOIN {attendancejourneys_sessions} aps ON aps.id = arv.sessionid
                           WHERE aps.attendancejourneysid = :instanceid
                             AND (arv.userid = :participantid OR arv.actorid = :actorid)
                        ORDER BY arv.timecreated, arv.id";
            $reviewhistory = [];
            foreach (
                $DB->get_records_sql($reviewsql, [
                'instanceid' => $cm->instance,
                'participantid' => $user->id,
                'actorid' => $user->id,
                ]) as $review
            ) {
                $reviewhistory[] = (object) [
                    'session' => format_string($review->sessionname),
                    'participantid' => (int) $review->userid,
                    'action' => $review->action,
                    'performedby' => (int) $review->actorid,
                    'note' => $review->note,
                    'timecreated' => transform::datetime($review->timecreated),
                ];
            }
            $completedjourneys = $DB->count_records('attendancejourneys_journeys', [
                'attendancejourneysid' => $cm->instance, 'completedby' => $user->id,
            ]);
            $closures = $DB->get_records('attendancejourneys_closures', [
                'attendancejourneysid' => $cm->instance, 'userid' => $user->id,
            ], 'timeclosed, id');
            $memberships = $DB->get_records('attendancejourneys_members', [
                'attendancejourneysid' => $cm->instance, 'userid' => $user->id,
            ], 'timeassigned, id');
            $waitlistentries = $DB->get_records('attendancejourneys_waitlist', [
                'attendancejourneysid' => $cm->instance, 'userid' => $user->id,
            ], 'timecreated, id');
            $equivalences = $DB->get_records('attendancejourneys_equivalences', [
                'attendancejourneysid' => $cm->instance, 'userid' => $user->id,
            ], 'timecreated, id');
            $data = (object) array_merge((array) helper::get_context_data($context, $user), [
                'attendance_records' => $records,
                'records_entered_by_user' => $recordedactions,
                'review_history' => $reviewhistory,
                'journeys_completed_by_user' => $completedjourneys,
                'journey_closures' => array_values($closures),
                'journey_memberships' => array_values($memberships),
                'waiting_list_history' => array_values($waitlistentries),
                'session_equivalences' => array_values($equivalences),
                'equivalence_decision_history' => array_values($DB->get_records('attendancejourneys_equivlog', [
                    'attendancejourneysid' => $cm->instance, 'userid' => $user->id,
                ], 'timecreated, id')),
                'attendance_attempts' => array_values($DB->get_records('attendancejourneys_attempts', [
                    'attendancejourneysid' => $cm->instance, 'userid' => $user->id,
                ], 'rootjourneyid, attemptnumber, id')),
                'individual_obligations' => array_values($DB->get_records('attendancejourneys_obligations', [
                    'attendancejourneysid' => $cm->instance, 'userid' => $user->id,
                ], 'journeyid, id')),
                'individual_obligation_history' => array_values($DB->get_records('attendancejourneys_obligationlog', [
                    'attendancejourneysid' => $cm->instance, 'userid' => $user->id,
                ], 'timecreated, id')),
                'authored_actions' => self::get_authored_actions((int) $cm->instance, (int) $user->id),
            ]);
            writer::with_context($context)->export_data([], $data);
            helper::export_context_files($context, $user);
        }
    }

    /**
     * Export the user's recorded actions without disclosing unrelated participant fields.
     *
     * Review-history actions are already exported separately. Each entry here includes
     * only the role held by the requesting user and the fields associated with that role.
     *
     * @param int $activityid Activity whose context was approved for export.
     * @param int $userid User whose data was requested.
     * @return array Recorded actions attributed to the requesting user.
     */
    private static function get_authored_actions(int $activityid, int $userid): array {
        global $DB;
        $definitions = [
            'attendancejourneys_records' => [
                'takenby' => ['timemodified', ['status', 'minutesabsent', 'remarks']],
                'approvedby' => ['timeapproved', ['approved']],
                'reviewedby' => ['timereviewed', ['changesrequested', 'reviewnote']],
            ],
            'attendancejourneys_closures' => [
                'closedby' => ['timeclosed', ['percentage', 'threshold', 'result']],
                'reopenedby' => ['timereopened', []],
            ],
            'attendancejourneys_journeys' => [
                'completedby' => ['timecompleted', []],
            ],
            'attendancejourneys_waitlist' => [
                'createdby' => ['timecreated', []],
                'updatedby' => ['timeupdated', ['status']],
            ],
            'attendancejourneys_sessionlog' => [
                'actorid' => ['timecreated', ['action', 'reason']],
            ],
            'attendancejourneys_equivlog' => [
                'actorid' => ['timecreated', ['action', 'previousstatus', 'status', 'note']],
            ],
            'attendancejourneys_attempts' => [
                'actorid' => ['timecreated', ['rootjourneyid', 'journeyid', 'attemptnumber', 'reason']],
            ],
            'attendancejourneys_obligations' => [
                'actorid' => ['timemodified', ['journeyid', 'waived', 'starttime', 'endtime', 'reason', 'revision']],
            ],
            'attendancejourneys_obligationlog' => [
                'actorid' => ['timecreated', ['journeyid', 'waived', 'starttime', 'endtime', 'reason', 'revision',
                    'previouswaived', 'previousstarttime', 'previousendtime']],
            ],
            'attendancejourneys_equivalences' => [
                'createdby' => ['timecreated', ['reason']],
                'decidedby' => ['timedecided', ['status', 'decisionnote']],
            ],
        ];
        $actions = [];
        foreach ($definitions as $table => $roles) {
            $conditions = [];
            $params = ['activityid' => $activityid];
            foreach (array_keys($roles) as $role) {
                $conditions[] = "item.$role = :$role";
                $params[$role] = $userid;
            }
            // Table and field names come only from the fixed definitions above.
            $from = "{{$table}} item";
            $scope = 'item.attendancejourneysid';
            if ($table === 'attendancejourneys_records') {
                $from .= ' JOIN {attendancejourneys_sessions} aps ON aps.id = item.sessionid';
                $scope = 'aps.attendancejourneysid';
            }
            $sql = "SELECT item.* FROM $from WHERE $scope = :activityid AND (" . implode(' OR ', $conditions) . ')';
            foreach ($DB->get_records_sql($sql, $params) as $record) {
                foreach ($roles as $role => [$timefield, $fields]) {
                    if ((int) $record->$role !== $userid) {
                        continue;
                    }
                    $details = [];
                    foreach ($fields as $field) {
                        $details[$field] = $record->$field;
                    }
                    $actions[] = (object) [
                        'table' => $table,
                        'recordid' => (int) $record->id,
                        'role' => $role,
                        'time' => $record->$timefield ? transform::datetime($record->$timefield) : null,
                        'details' => (object) $details,
                    ];
                }
            }
        }
        return $actions;
    }

    /**
     * Remove personal data and reset attendance results in the approved context.
     *
     * @param \context $context Activity context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $CFG, $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid);
        if (!$cm) {
            return;
        }
        $writelock = new \mod_attendancejourneys\local\write_lock((int) $cm->instance);
        $sessionids = self::get_session_ids($context);
        if ($sessionids) {
            $DB->delete_records_list('attendancejourneys_reviews', 'sessionid', $sessionids);
            $DB->delete_records_list('attendancejourneys_records', 'sessionid', $sessionids);
        }
        $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid);
        if ($cm) {
            require_once($CFG->dirroot . '/mod/attendancejourneys/lib.php');
            $activity = $DB->get_record('attendancejourneys', ['id' => $cm->instance]);
            if ($activity) {
                require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
                $participantids = array_keys(get_enrolled_users(
                    $context,
                    'mod/attendancejourneys:canbelisted',
                    0,
                    'u.id',
                    '',
                    0,
                    0,
                    true
                ));
                $DB->delete_records('attendancejourneys_closures', ['attendancejourneysid' => $activity->id]);
                $DB->delete_records('attendancejourneys_members', ['attendancejourneysid' => $activity->id]);
                $DB->delete_records('attendancejourneys_waitlist', ['attendancejourneysid' => $activity->id]);
                $DB->delete_records('attendancejourneys_attempts', ['attendancejourneysid' => $activity->id]);
                $DB->delete_records('attendancejourneys_obligationlog', ['attendancejourneysid' => $activity->id]);
                $DB->delete_records('attendancejourneys_obligations', ['attendancejourneysid' => $activity->id]);
                $DB->delete_records('attendancejourneys_sessionlog', ['attendancejourneysid' => $activity->id]);
                $DB->delete_records('attendancejourneys_equivlog', ['attendancejourneysid' => $activity->id]);
                $DB->delete_records('attendancejourneys_equivalences', ['attendancejourneysid' => $activity->id]);
                $DB->set_field('attendancejourneys_journeys', 'completedby', 0, ['attendancejourneysid' => $activity->id]);
                attendancejourneys_update_grades($activity);
                attendancejourneys_update_completion(get_course($cm->course), $cm, $participantids);
            }
        }
    }

    /**
     * Remove the approved user data from the approved activity contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts and user for this privacy request.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_user_in_context($context, [$userid]);
        }
    }

    /**
     * Remove the approved users data from their approved activity context.
     *
     * @param approved_userlist $userlist Users in scope for this context.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_user_in_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Remove participant data and clear actor references for the supplied users.
     *
     * @param \context $context Activity context.
     * @param array $userids User identifiers whose data is in scope.
     * @return void
     */
    private static function delete_user_in_context(\context $context, array $userids): void {
        global $CFG, $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        if (!$userids) {
            return;
        }
        $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid);
        if (!$cm) {
            return;
        }
        $writelock = new \mod_attendancejourneys\local\write_lock((int) $cm->instance);
        $sessionids = self::get_session_ids($context);
        if ($sessionids) {
            [$sessionsql, $sessionparams] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'session');
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
            $params = $sessionparams + $userparams;
            $recordids = array_keys($DB->get_records_select(
                'attendancejourneys_records',
                "sessionid $sessionsql AND userid $usersql",
                $params,
                '',
                'id'
            ));
            if ($recordids) {
                $DB->delete_records_list('attendancejourneys_reviews', 'recordid', $recordids);
            }
            $DB->delete_records_select('attendancejourneys_reviews', "sessionid $sessionsql AND userid $usersql", $params);
            $DB->delete_records_select('attendancejourneys_records', "sessionid $sessionsql AND userid $usersql", $params);
            $DB->set_field_select(
                'attendancejourneys_records',
                'takenby',
                0,
                "sessionid $sessionsql AND takenby $usersql",
                $params
            );
            $DB->set_field_select(
                'attendancejourneys_records',
                'approvedby',
                0,
                "sessionid $sessionsql AND approvedby $usersql",
                $params
            );
            $DB->set_field_select(
                'attendancejourneys_records',
                'reviewedby',
                0,
                "sessionid $sessionsql AND reviewedby $usersql",
                $params
            );
            $DB->set_field_select(
                'attendancejourneys_reviews',
                'actorid',
                0,
                "sessionid $sessionsql AND actorid $usersql",
                $params
            );
        }
        $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid);
        if ($cm) {
            require_once($CFG->dirroot . '/mod/attendancejourneys/lib.php');
            $activity = $DB->get_record('attendancejourneys', ['id' => $cm->instance]);
            if ($activity) {
                require_once($CFG->dirroot . '/mod/attendancejourneys/locallib.php');
                [$closureusersql, $closureparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'closureuser');
                $DB->delete_records_select(
                    'attendancejourneys_closures',
                    "attendancejourneysid = :closureactivity AND userid $closureusersql",
                    ['closureactivity' => $activity->id] + $closureparams
                );
                $DB->delete_records_select(
                    'attendancejourneys_members',
                    "attendancejourneysid = :memberactivity AND userid $closureusersql",
                    ['memberactivity' => $activity->id] + $closureparams
                );
                $DB->delete_records_select(
                    'attendancejourneys_waitlist',
                    "attendancejourneysid = :waitactivity AND userid $closureusersql",
                    ['waitactivity' => $activity->id] + $closureparams
                );
                $DB->delete_records_select(
                    'attendancejourneys_equivlog',
                    "attendancejourneysid = :historyactivity AND userid $closureusersql",
                    ['historyactivity' => $activity->id] + $closureparams
                );
                foreach (
                    ['attendancejourneys_obligationlog', 'attendancejourneys_obligations',
                    'attendancejourneys_attempts'] as $table
                ) {
                    $DB->delete_records_select(
                        $table,
                        "attendancejourneysid = :activity AND userid $closureusersql",
                        ['activity' => $activity->id] + $closureparams
                    );
                    // Preserve the participant policy when its author requests erasure.
                    $where = "attendancejourneysid = :activity AND actorid $closureusersql";
                    $params = ['activity' => $activity->id] + $closureparams;
                    $DB->set_field_select($table, 'reason', null, $where, $params);
                    $DB->set_field_select($table, 'actorid', 0, $where, $params);
                }
                // Cancellation is shared session state; erase the actor's identity and explanation only.
                $DB->set_field_select(
                    'attendancejourneys_sessionlog',
                    'reason',
                    null,
                    "attendancejourneysid = :cancelactivity AND actorid $closureusersql",
                    ['cancelactivity' => $activity->id] + $closureparams
                );
                $DB->set_field_select(
                    'attendancejourneys_sessionlog',
                    'actorid',
                    0,
                    "attendancejourneysid = :cancelactivity AND actorid $closureusersql",
                    ['cancelactivity' => $activity->id] + $closureparams
                );
                // Remove the author's free text while retaining the participant's decision timeline.
                $DB->set_field_select(
                    'attendancejourneys_equivlog',
                    'note',
                    null,
                    "attendancejourneysid = :historyactivity AND actorid $closureusersql",
                    ['historyactivity' => $activity->id] + $closureparams
                );
                $DB->set_field_select(
                    'attendancejourneys_equivlog',
                    'actorid',
                    0,
                    "attendancejourneysid = :historyactivity AND actorid $closureusersql",
                    ['historyactivity' => $activity->id] + $closureparams
                );
                $DB->delete_records_select(
                    'attendancejourneys_equivalences',
                    "attendancejourneysid = :equivactivity AND userid $closureusersql",
                    ['equivactivity' => $activity->id] + $closureparams
                );
                $DB->set_field_select(
                    'attendancejourneys_closures',
                    'closedby',
                    0,
                    "attendancejourneysid = :closedactivity AND closedby $closureusersql",
                    ['closedactivity' => $activity->id] + $closureparams
                );
                $DB->set_field_select(
                    'attendancejourneys_closures',
                    'reopenedby',
                    0,
                    "attendancejourneysid = :reopenedactivity AND reopenedby $closureusersql",
                    ['reopenedactivity' => $activity->id] + $closureparams
                );
                $DB->set_field_select(
                    'attendancejourneys_journeys',
                    'completedby',
                    0,
                    "attendancejourneysid = :journeyactivity AND completedby $closureusersql",
                    ['journeyactivity' => $activity->id] + $closureparams
                );
                $DB->set_field_select(
                    'attendancejourneys_waitlist',
                    'createdby',
                    0,
                    "attendancejourneysid = :waitcreatedactivity AND createdby $closureusersql",
                    ['waitcreatedactivity' => $activity->id] + $closureparams
                );
                $DB->set_field_select(
                    'attendancejourneys_waitlist',
                    'updatedby',
                    0,
                    "attendancejourneysid = :waitupdatedactivity AND updatedby $closureusersql",
                    ['waitupdatedactivity' => $activity->id] + $closureparams
                );
                // The current-state row duplicates the latest historical note; erase both copies.
                $DB->set_field_select(
                    'attendancejourneys_equivalences',
                    'reason',
                    null,
                    "attendancejourneysid = :equivtextactivity AND createdby $closureusersql",
                    ['equivtextactivity' => $activity->id] + $closureparams
                );
                $DB->set_field_select(
                    'attendancejourneys_equivalences',
                    'decisionnote',
                    null,
                    "attendancejourneysid = :equivtextactivity AND decidedby $closureusersql",
                    ['equivtextactivity' => $activity->id] + $closureparams
                );
                $DB->set_field_select(
                    'attendancejourneys_equivalences',
                    'createdby',
                    0,
                    "attendancejourneysid = :equivcreatedactivity AND createdby $closureusersql",
                    ['equivcreatedactivity' => $activity->id] + $closureparams
                );
                $DB->set_field_select(
                    'attendancejourneys_equivalences',
                    'decidedby',
                    0,
                    "attendancejourneysid = :equivdecidedactivity AND decidedby $closureusersql",
                    ['equivdecidedactivity' => $activity->id] + $closureparams
                );
                foreach ($userids as $userid) {
                    attendancejourneys_update_grades($activity, (int) $userid);
                }
                attendancejourneys_update_completion(get_course($cm->course), $cm, array_map('intval', $userids));
            }
        }
    }

    /**
     * Return the session identifiers belonging to the activity context.
     *
     * @param \context $context Activity context.
     * @return array
     */
    private static function get_session_ids(\context $context): array {
        global $DB;
        if (!$context instanceof \context_module) {
            return [];
        }
        $cm = get_coursemodule_from_id('attendancejourneys', $context->instanceid);
        if (!$cm) {
            return [];
        }
        return $DB->get_fieldset_select(
            'attendancejourneys_sessions',
            'id',
            'attendancejourneysid = :instanceid',
            ['instanceid' => $cm->instance]
        );
    }
}
