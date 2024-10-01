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

namespace block_learnerscript\local;
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->dirroot/enrol/locallib.php");

use context_system;

/**
 * A Moodle block to create customizable reports.
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class querylib {
    /**
     * List of Enrolled Courses for a Particular RoleWise User
     * @param  integer  $userid      User ID for Particular user
     * @param  string  $role         Role ShortName
     * @param  integer $contextlevel Role Contextlevel
     * @param  integer $courseid     Course ID for exception particular course
     * @param  string  $concatsql    Sql Conditions
     * @param  string  $limitconcat  Limit 0, 10 like....
     * @param  boolean $count        Count for get results count or list of records
     * @param  boolean $check        Check that user has role in LMS
     * @param  string  $datefiltersql Date filter SQL
     * @param  boolean $menu          Menu
     * @return integer|object If $count true, returns courses count or returns Enrolled Cousrse as per role for that user
     */
    public function get_rolecourses($userid, $role, $contextlevel,
    $courseid = SITEID, $concatsql = '', $limitconcat = '', $count = false,
    $check = false, $datefiltersql = '', $menu = false) {
        global $DB, $SESSION;
        $params = ['courseid' => $courseid];
        $params['contextlevel'] = isset($SESSION->ls_contextlevel) ? $SESSION->ls_contextlevel : $contextlevel;
        $params['userid'] = $userid;
        $params['userid1'] = $params['userid'];
        $params['userids'] = $userid;
        $params['role'] = $role;
        $params['active'] = ENROL_USER_ACTIVE;
        $params['enabled'] = ENROL_INSTANCE_ENABLED;
        $params['now1'] = round(time(), -2); // Improves db caching.
        $params['now2'] = $params['now1'];
        if ($count) {
            $coursessql = "SELECT COUNT(c.id) AS totalcount FROM {course} c";
        } else {
            $coursessql = "SELECT DISTINCT c.id, c.fullname, c.timecreated AS timecreated
            FROM {course} c";
        }

        if ($SESSION->ls_contextlevel == CONTEXT_SYSTEM || $contextlevel == CONTEXT_SYSTEM) {
            $coursessql .= " LEFT JOIN {context} AS ctx ON ctx.instanceid = 0 AND ctx.contextlevel = :contextlevel";
        } else if ($SESSION->ls_contextlevel == CONTEXT_COURSECAT
         || $contextlevel == CONTEXT_COURSECAT) {
            $coursessql .= " JOIN {course_categories} cc ON cc.id = c.category
                LEFT JOIN {context} ctx ON ctx.instanceid = cc.id AND ctx.contextlevel = :contextlevel";
        } else {
            $coursessql .= " LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        }
        $coursessql .= " JOIN {role_assignments} ra ON ra.contextid = ctx.id
                 JOIN {role} r ON r.id = ra.roleid
                  WHERE c.id <> :courseid AND c.visible = 1 AND ra.userid = :userid AND r.shortname = :role
                       $concatsql ORDER BY c.id ASC $limitconcat";
        try {
            if ($count) {
                $courses = $DB->count_records_sql($coursessql, $params);
            } else {
                if ($menu) {
                    $courses = $DB->get_records_sql_menu($coursessql, $params);
                } else {
                    $courses = $DB->get_records_sql($coursessql, $params);
                }
            }
        } catch (\dml_exception $ex) {
            throw new \moodle_exception(get_string('queryexception', 'block_learnerscript'));
        }
        if ($check) {
            return !empty($courses) ? true : false;
        }
        return $courses;
    }

    /**
     * Get filter courses
     *
     * @param  object  $pluginclass   Report pluginclass
     * @param  int  $filtercourses    Selected filter courses
     * @param  boolean $selectoption  Selected course option
     * @param  boolean $search        Course search text
     * @param  array $filterdata      Filter data
     * @param  boolean $type          Course filter type
     * @param  int  $userid           User ID
     * @return array Course options
     */
    public function filter_get_courses($pluginclass, $filtercourses, $selectoption = true,
    $search = false, $filterdata = [], $type = false, $userid = null) {
        global $DB, $USER, $SESSION;
        $context = context_system::instance();
        $limitnum = 1;
        $searchvalue = '';
        $concatsql = "";
        $searchsql = "";
        $courseoptions = [];
        if ($search) {
            $searchvalue .= $search;
            $params['search'] = '%' . $search . '%';
            $searchsql .= " AND " . $DB->sql_like('fullname', ":search", false);
            $limitnum = 0;
        }
        if ($selectoption) {
            $courseoptions[0] = isset($pluginclass->singleselection)
            && $pluginclass->singleselection ? get_string('filter_course', 'block_learnerscript') :
            ['id' => 0, 'text' => get_string('select') . ' ' . get_string('course')];
        }
        if (!empty($filterdata)
        && !empty($filterdata['filter_users'])
        && (($filterdata['filter_users_type'] == 'basic'
            && $filterdata['filter_courses_type'] == 'custom')
            || $pluginclass->reportclass->basicparams[0]['name'] == 'users')) {
            $userid = $filterdata['filter_users'];
        }
        if (!empty($filterdata) && !empty($filterdata['filter_coursecategories'])) {
            $concatsql .= " AND category = " . $filterdata['filter_coursecategories'];
        }

        if (!empty($filterdata)
        && !empty($filterdata['filter_courses'])
        && ((isset($filterdata['filter_users_type'])
        && $filterdata['filter_users_type'] != 'basic'
        && $filterdata['filter_courses_type'] != 'basic') || !$type)) {
            $concatsql .= " AND id = " . $filterdata['filter_courses'];
        }
        if (!empty($filtercourses && !$search)) {
            $concatsql .= " AND id = " . $filtercourses;
        }
        if (!isset($pluginclass->reportclass->userid)) {
            $pluginclass->reportclass->userid = $USER->id;
        }
        if (is_siteadmin($pluginclass->reportclass->userid)
        || has_capability('block/learnerscript:managereports', $context)) {
            if ($userid > 0) {
                $courselist = array_keys(enrol_get_users_courses($userid));
                if (!empty($courselist)) {
                    if (!empty($pluginclass->reportclass->rolewisecourses)) {
                        $rolecourses = explode(',', $pluginclass->reportclass->rolewisecourses);
                        $courselist = array_intersect($courselist, $rolecourses);
                    }
                    list($csql, $cparams) = $DB->get_in_or_equal($courselist, SQL_PARAMS_NAMED);
                    $cparams['siteid'] = SITEID;
                    $cparams['visible'] = 1;
                    $cparams['searchvalue'] = '%' . $searchvalue . '%';
                    $courses = $DB->get_records_select('course', "id > :siteid AND visible=:visible
                    AND " . $DB->sql_like('fullname', ":searchvalue", false). " AND id $csql" . $concatsql,
                    $cparams, '', 'id, fullname', 0, $limitnum);
                } else {
                    $courses = [];
                }
            } else {
                $courses = $DB->get_records_select('course', "id > :siteid
                AND visible=:visible AND " . $DB->sql_like('fullname', ":searchvalue", false) . $concatsql,
                ['siteid' => SITEID, 'visible' => 1, 'searchvalue' => '%' . $searchvalue . '%'],
                '', 'id, fullname', 0, $limitnum);
            }
        } else {
            if (empty($pluginclass->reportclass->rolewisecourses)) {
                $courses = $this->get_rolecourses($USER->id, $SESSION->role, $SESSION->ls_contextlevel, SITEID, '', '');

            } else {
                $rolewisecourses = explode(',', $pluginclass->reportclass->rolewisecourses);
                list($usql, $params) = $DB->get_in_or_equal($rolewisecourses, SQL_PARAMS_NAMED);
                $usql .= " AND visible=1 $concatsql $searchsql";
                if ($search) {
                    $searchvalue .= $search;
                    $params['search'] = '%' . $search . '%';
                    $searchsql .= " AND " . $DB->sql_like('fullname', ":search", false);
                    $limitnum = 0;
                }
                $courses = $DB->get_records_select('course', "id $usql", $params);
            }
        }
        foreach ($courses as $c) {
            if ($c->id == SITEID) {
                continue;
            }
            if ($search) {
                $courseoptions[] = ['id' => $c->id, 'text' => format_string($c->fullname)];
            } else {
                $courseoptions[$c->id] = format_string($c->fullname);
            }
        }
        return $courseoptions;
    }

    /**
     * Get filter users
     *
     * @param  object  $pluginclass   Report pluginclass
     * @param  boolean $selectoption  Selected course option
     * @param  boolean $search        Course search text
     * @param  array $filterdata      Filter data
     * @param  boolean $type          Course filter type
     * @param  int  $filterusers    Selected filter courses
     * @param  int  $courses           User ID
     * @return array Users options
     */
    public function filter_get_users($pluginclass, $selectoption = true,
    $search = false, $filterdata = [], $type = false, $filterusers='', $courses = null) {
        global $DB, $USER, $SESSION;
        $context = context_system::instance();
        $searchsql = "";
        $concatsql = "";
        $concatsql1 = "";
        $limitnum = 1;
        $params = [];
        if ($search) {
            $params['search'] = '%' . $search . '%';
            $searchsql = " AND " . $DB->sql_like("CONCAT(u.firstname, ' ', u.lastname)", ":search", false);
            $concatsql .= $searchsql;
            $limitnum = 0;
        }
        if ($pluginclass->report->type != 'sql') {
            $pluginclass->report->components = isset($pluginclass->report->components) ? $pluginclass->report->components : '';
            $components = (new \block_learnerscript\local\ls)->cr_unserialize($pluginclass->report->components);
            if (!empty($components->conditions->elements)) {
                $conditions = $components['conditions'];
                $reportclassname = 'block_learnerscript\lsreports\report_' . $pluginclass->report->type;
                $properties = new \stdClass();
                $reportclass = new $reportclassname($pluginclass->report, $properties);
                $userslist = $reportclass->elements_by_conditions($conditions);
            } else {
                if (!empty($filterdata) && !empty($filterdata['filter_users'])
                && ((isset($filterdata['filter_courses_type'])
                && $filterdata['filter_courses_type'] != 'basic'
                && $filterdata['filter_users_type'] != 'basic') || !$type)) {
                    $userid = $filterdata['filter_users'];
                    $concatsql .= " AND u.id = $userid";
                }
                if (!empty($filterdata) && !empty($filterdata['filter_courses'])
                && $filterdata['filter_courses_type'] == 'basic'
                && $filterdata['filter_users_type'] == 'custom') {
                    $courseid = $filterdata['filter_courses'];
                    $concatsql1 .= " AND c.id = $courseid ";
                }
                if (!empty($filterusers) && !$search) {
                    $concatsql .= " AND u.id = $filterusers";
                }
                if (empty($pluginclass->reportclass)) {
                    $pluginclass->reportclass = new \stdClass;
                    $pluginclass->reportclass->userid = $USER->id;
                }
                if (is_siteadmin($pluginclass->reportclass->userid) || has_capability('block/learnerscript:managereports', $context)) {
                    $sql = "SELECT DISTINCT u.*
                               FROM {course} c
                               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                              JOIN {role_assignments} ra ON ra.contextid = ctx.id
                              JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                              JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                              WHERE 1 = 1 $concatsql $concatsql1";
                    $userslist = $DB->get_records_sql($sql, $params, 0, $limitnum);
                } else {
                    if (empty($pluginclass->reportclass->rolewisecourses)) {
                        if ($SESSION->role == 'coursecreator'
                        && ($SESSION->ls_contextlevel == CONTEXT_SYSTEM
                        || $SESSION->ls_contextlevel == CONTEXT_COURSECAT)) {
                            $courses = $this->get_rolecourses($USER->id, $SESSION->role,
                            $SESSION->ls_contextlevel, SITEID, '', '');
                                $courselists = [];
                            foreach ($courses as $key => $course) {
                                $courselists[] = $course->id;
                            }
                            list($ccsql, $params) = $DB->get_in_or_equal($courselists);
                            $courselist = join(',', $courselists);
                            if (!empty($courselist)) {
                                $sql = "SELECT DISTINCT u.*
                                            FROM {course} AS c
                                            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                                            JOIN {role_assignments} ra ON ra.contextid = ctx.id
                                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                                            JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                                            WHERE 1 = 1 AND c.id $ccsql AND u.deleted = 0
                                            $concatsql $concatsql1";
                                            $userslist = $DB->get_records_sql($sql, $params, 0, $limitnum);
                            } else {
                                $userslist = [];
                            }
                        } else {
                                $userslist = [];
                        }
                    } else {
                            $courselist = $pluginclass->reportclass->rolewisecourses;
                            $sql = "SELECT DISTINCT u.*
                                    FROM {course} AS c
                                    JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                                    JOIN {role_assignments} ra ON ra.contextid = ctx.id
                                    JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                                    JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                                    WHERE c.id in ($courselist) $concatsql $concatsql1";
                        $userslist = $DB->get_records_sql($sql, $params, 0, $limitnum);
                    }
                }

            }
        } else {
            $sql = " SELECT * FROM {user} as u WHERE id > 2 AND u.deleted = 0 $concatsql";
            $userslist = $DB->get_records_sql($sql);
        }

        $usersoptions = [];
        if ($selectoption) {
            $usersoptions[0] = isset($pluginclass->singleselection)
            && $pluginclass->singleselection ?
            get_string('filter_user', 'block_learnerscript') :
            ['id' => 0, 'text' => get_string('select') . ' ' . get_string('users')];
        }
        if (!empty($userslist)) {
            foreach ($userslist as $c) {
                if (isset($c->id)) {
                    if ($search) {
                        $usersoptions[] = ['id' => $c->id, 'text' => format_string(fullname($c))];
                    } else {
                        $usersoptions[$c->id] = fullname($c);
                    }
                }
            }
        }
        return $usersoptions;
    }

    /**
     * Get students list
     *
     * @param  string  $useroperatorsql   User SQL
     * @param  string  $courseoperatorsql Course SQL
     * @return string SQL query
     */
    public function get_learners($useroperatorsql = '', $courseoperatorsql = '') {

        if (empty($courseoperatorsql) && empty($useroperatorsql)) {
            return false;
        }
        if (!empty($useroperatorsql)) {
            $sql = " SELECT DISTINCT c.id ";
        }
        if (!empty($courseoperatorsql)) {
            $sql = " SELECT DISTINCT u.id ";
        }
        $sql .= " FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                  JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1 AND u.deleted = 0
                  AND u.suspended = 0 AND c.visible = 1";
        if (!empty($courseoperatorsql)) {
            $sql .= " WHERE c.id = $courseoperatorsql";
        }
        if (!empty($useroperatorsql)) {
            $sql .= " WHERE ra.userid = $useroperatorsql";
        }
        return $sql;
    }
}
