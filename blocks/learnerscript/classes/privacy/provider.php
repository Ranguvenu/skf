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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_learnerscript\privacy;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use context_system;

/**
 * A Moodle block to create customizable reports.
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider,
\core_privacy\local\request\core_userlist_provider,
\core_privacy\local\request\plugin\provider {
    /**
     * Get information about the user data stored by this plugin.
     *
     * @param  collection $collection An object for storing metadata.
     * @return collection The metadata.
     */
    public static function get_metadata(collection $collection): collection {

        $learnerscriptreports = [
            'courseid' => 'privacy:metadata:lscourseid',
            'ownerid' => 'privacy:metadata:ownerid',
            'visible' => 'privacy:metadata:visible',
            'name' => 'privacy:metadata:name',
            'summary' => 'privacy:metadata:summary',
            'type' => 'privacy:metadata:type',
            'components' => 'privacy:metadata:components',
            'export' => 'privacy:metadata:export',
            'global' => 'privacy:metadata:global',
            'lastexecutiontime' => 'privacy:metadata:lastexecutiontime',
            'disabletable' => 'privacy:metadata:disabletable',
        ];

        $scheduletasks = [
            'reportid' => 'privacy:metadata:reportid',
            'userid' => 'privacy:metadata:userid',
            'exporttofilesystem' => 'privacy:metadata:exporttofilesystem',
            'exportformat' => 'privacy:metadata:exportformat',
            'frequency' => 'privacy:metadata:frequency',
            'schedule' => 'privacy:metadata:schedule',
            'nextschedule' => 'privacy:metadata:nextschedule',
            'roleid' => 'privacy:metadata:roleid',
            'sendinguserid' => 'privacy:metadata:sendinguserid',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
            'contextlevel' => 'privacy:metadata:contextlevel',
        ];

        $coursetimespent = [
            'userid' => 'privacy:metadata:courseuserid',
            'courseid' => 'privacy:metadata:courseid',
            'timespent' => 'privacy:metadata:coursetimespent',
            'timecreated' => 'privacy:metadata:coursetimecreated',
            'timemodified' => 'privacy:metadata:coursetimemodified',
        ];

        $modulestimespent = [
            'userid' => 'privacy:metadata:moduserid',
            'courseid' => 'privacy:metadata:modcourseid',
            'instanceid' => 'privacy:metadata:instanceid',
            'activityid' => 'privacy:metadata:activityid',
            'timespent' => 'privacy:metadata:modtimespent',
            'timecreated' => 'privacy:metadata:modtimecreated',
            'timemodified' => 'privacy:metadata:modtimemodified',
        ];

        $userlmsaccess = [
            'userid' => 'privacy:metadata:loggeduserid',
            'logindata' => 'privacy:metadata:logindata',
            'timecreated' => 'privacy:metadata:usertimecreated',
            'timemodified' => 'privacy:metadata:usertimemodified',
        ];

        $collection->add_database_table('block_learnerscript', $learnerscriptreports, 'privacy:metadata:learnerscriptreports');
        $collection->add_database_table('block_ls_schedule', $scheduletasks, 'privacy:metadata:scheduletablesummary');
        $collection->add_database_table('block_ls_coursetimestats',
                    $coursetimespent, 'privacy:metadata:coursetimesummary');
        $collection->add_database_table('block_ls_modtimestats',
                    $modulestimespent, 'privacy:metadata:modulestimesummary');
        $collection->add_database_table('block_ls_userlmsaccess', $userlmsaccess, 'privacy:metadata:userlmsaccess');

        return $collection;
    }
    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new \core_privacy\local\request\contextlist;
        $params = ['userid' => $userid, 'contextlevel' => CONTEXT_USER];
        $sql = "SELECT ctx.id
                FROM {context} ctx
                JOIN {block_learnerscript} bcr ON ctx.instanceid = bcr.ownerid AND ctx.contextlevel = :contextlevel
                WHERE bcr.ownerid = :userid";
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT ctx.id
                FROM {context} ctx
                JOIN {block_ls_schedule} bls ON ctx.instanceid = bls.userid AND ctx.contextlevel = :contextlevel
                WHERE bls.userid = :userid";
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT ctx.id
                FROM {context} ctx
                JOIN {block_ls_userlmsaccess} blms ON ctx.instanceid = blms.userid AND ctx.contextlevel = :contextlevel
                WHERE blms.userid = :userid";
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT ctx.id
                FROM {context} ctx
                JOIN {block_ls_coursetimestats} blc ON ctx.instanceid = blc.courseid
                WHERE blc.userid = :userid AND ctx.contextlevel = :contextlevel";
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT ctx.id
                FROM {context} ctx
                JOIN {block_ls_modtimestats} blm ON ctx.instanceid = blm.courseid
                WHERE blm.userid = :userid AND ctx.contextlevel = :contextlevel";
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_user) {
            return;
        }

        $params = [
            'contextid' => $context->id,
            'contextuser' => CONTEXT_USER,
        ];

        $sql = "SELECT lsl.userid
                  FROM {block_ls_modtimestats} lsl
                  JOIN {context} ctx
                       ON ctx.instanceid = lsl.userid
                       AND ctx.contextlevel = :contextuser
                 WHERE ctx.id = :contextid";

        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT lsl.userid
                  FROM {block_ls_coursetimestats} lsl
                  JOIN {context} ctx
                       ON ctx.instanceid = lsl.userid
                       AND ctx.contextlevel = :contextuser
                 WHERE ctx.id = :contextid";

        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT lsl.userid
                  FROM {block_ls_schedule} lsl
                  JOIN {context} ctx
                       ON ctx.instanceid = lsl.userid
                       AND ctx.contextlevel = :contextuser
                 WHERE ctx.id = :contextid";

        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT lsl.userid
                  FROM {block_ls_userlmsaccess} lsl
                  JOIN {context} ctx
                       ON ctx.instanceid = lsl.userid
                       AND ctx.contextlevel = :contextuser
                 WHERE ctx.id = :contextid";

        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT lsl.ownerid AS userid
                  FROM {block_learnerscript} lsl
                  JOIN {context} ctx
                       ON ctx.instanceid = lsl.ownerid
                       AND ctx.contextlevel = :contextuser
                 WHERE ctx.id = :contextid";

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        if (empty($contextlist->count())) {
            return;
        }
        $context = context_user::instance($userid);

        $sql = "SELECT * FROM {block_ls_modtimestats}
                     WHERE userid = :userid";
        $params['userid'] = $userid;
        if ($users = $DB->get_records_sql($sql, $params)) {
            foreach ($users as $record) {
                writer::with_context($context)->export_data((array)$context, $record);
            }
        }

        $sql = "SELECT * FROM {block_ls_coursetimestats}
                     WHERE userid = :userid";
        $params['userid'] = $userid;
        if ($users = $DB->get_records_sql($sql, $params)) {
            foreach ($users as $record) {
                writer::with_context($context)->export_data((array)$context, $record);
            }
        }

        $sql = "SELECT * FROM {block_ls_schedule}
                     WHERE userid = :userid";
        $params['userid'] = $userid;
        if ($users = $DB->get_records_sql($sql, $params)) {
            foreach ($users as $record) {
                writer::with_context($context)->export_data((array)$context, $record);
            }
        }

        $sql = "SELECT * FROM {block_ls_userlmsaccess}
                     WHERE userid = :userid";
        $params['userid'] = $userid;
        if ($users = $DB->get_records_sql($sql, $params)) {
            foreach ($users as $record) {
                writer::with_context($context)->export_data((array)$context, $record);
            }
        }

        $sql = "SELECT * FROM {block_learnerscript}
                     WHERE ownerid = :userid";
        $params['userid'] = $userid;
        if ($users = $DB->get_records_sql($sql, $params)) {
            foreach ($users as $record) {
                writer::with_context($context)->export_data((array)$context, $record);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // Only delete data for a user context.
        if ($context->contextlevel == CONTEXT_USER) {
            static::delete_user_data($context->instanceid);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel == CONTEXT_USER) {
            // We can only delete our own data in the user context, nothing in course or system.
            static::delete_user_data($context->instanceid);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $userid) {
                // We can only delete our own data in the user context, nothing in course or system.
                static::delete_user_data($userid);
                break;
            }
        }
    }

    /**
     * Deletes non vital information about a user.
     *
     * @param  int $userid  The user ID to delete
     */
    protected static function delete_user_data(int $userid) {
        global $DB;

        // Delete user's course timespent information.
        $DB->delete_records('block_ls_coursetimestats', ['userid' => $userid]);
        // Delete user's activities timespent information.
        $DB->delete_records('block_ls_modtimestats', ['userid' => $userid]);
        // Delete user's report scheduled information.
        $DB->delete_records('block_ls_schedule', ['userid' => $userid]);
        // Delete user's lms access information.
        $DB->delete_records('block_ls_userlmsaccess', ['userid' => $userid]);
        // Delete user's report information.
        $DB->delete_records('block_learnerscript', ['ownerid' => $userid]);
    }
}
