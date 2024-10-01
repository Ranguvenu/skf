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
/**
 * Pending activities report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\ls as ls;
use block_learnerscript\report;
use DateTime;
/**
 * Pending activities report
 */
class report_pendingactivities extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;
    /**
     * Constructor for report
     * @param object $report Report
     * @param object $reportproperties Reportproperties
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = false;
        $this->courselevel = false;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $this->columns = ['activityfield' => ['activityfield'],
        'pendingactivities' => ['activityname', 'course', 'startdate', 'enddate', 'attempt'], ];
        $this->basicparams = [['name' => 'users']];
        $this->filters = ['courses', 'activities'];
        $this->orderable = ['activityname', 'course', 'startdate', 'enddate', 'attempt'];
        $this->defaultcolumn = '';
    }
    /**
     * Pending activities report init function
     */
    public function init() {
        global $DB;
        if (!isset($this->params['filter_activities'])) {
            $this->initial_basicparams('activities');
            $coursefilter = array_keys($this->filterdata);
            $this->params['filter_activities'] = array_shift($coursefilter);
        }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
    }
    /**
     * COUNT SQL
     */
    public function count() {
        global $DB;
        $date = new DateTime();
        $timestamp = $date->getTimestamp();
        $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
        $coursemodules = $DB->get_records_sql_menu("SELECT md.id, md.name
        FROM {course_modules} cs
        JOIN {modules} md ON cs.module = md.id
        WHERE cs.visible = :visible", ['visible' => 1]);
        $aliases = [];
        $activities = [];
        $fields1 = [];
        foreach ($modules as $modulename) {
            if (in_array($modulename, $coursemodules)) {
                $aliases[] = $modulename;
                $activities[] = "'$modulename'";
                $fields1[] = "COALESCE($modulename.name,'')";
            }
        }
        $activitynames = implode(',', $activities);
        if (!isset($this->params['filter_courses'])) {
            $this->initial_basicparams('courses');
            $filterdata = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($filterdata);
        }
        $userid = $this->params['filter_users'] ? $this->params['filter_users'] : $this->userid;
        $filters = '';
        if (isset($this->params['filter_courses']) && $this->params['filter_courses'] > 0) {
            $filters .= " AND main.course = " .$this->params['filter_courses'];
        }
        if (isset($this->params['filter_activities']) && $this->params['filter_activities'] > 0) {
            $filters .= " AND main.id = ".$this->params['filter_activities'];
        }
             $filters .= " AND u.id = $userid";
        $this->sql = "SELECT SUM(totalcount.activitycount) as total FROM (";

        foreach ($aliases as $alias) {
            if ($alias == 'assign') {
                $this->sql .= " SELECT COUNT(main.id) as activitycount
                FROM {course_modules}  main
                JOIN {modules} m ON main.module = m.id
                JOIN {course} c ON c.id = main.course
                JOIN {context} ctx ON ctx.instanceid = c.id
                JOIN {role_assignments} ra ON ra.contextid = ctx.id
                JOIN {user} u ON u.id = ra.userid
                JOIN {".$alias."}  $alias ON $alias.id = main.instance AND m.name = '$alias' AND $alias.duedate < $timestamp
                JOIN {assign_submission}  asb ON asb.assignment= $alias.id AND asb.userid =$userid
                WHERE m.visible = 1 AND m.name IN ($activitynames) AND main.visible = 1 AND asb.status != 'submitted'".$filters;
            }
            if ($alias == 'quiz') {
                $this->sql .= " UNION  ALL SELECT COUNT(main.id) as activitycount
                FROM {course_modules}  main
                JOIN {modules} m ON main.module = m.id
                JOIN {course} c ON c.id = main.course
                JOIN {context} ctx ON ctx.instanceid = c.id
                JOIN {role_assignments} ra ON ra.contextid = ctx.id
                JOIN {user} u ON u.id = ra.userid
                JOIN {".$alias."}  $alias ON $alias.id = main.instance AND m.name = '$alias' AND $alias.timeclose < ".$timestamp."
                WHERE $alias.id
                NOT IN (SELECT qa.quiz
                FROM {quiz_attempts} qa
                WHERE qa.userid = ".$userid.")
                AND m.visible = 1 AND m.name IN ($activitynames)
                AND main.visible = 1 AND $alias.timeclose!=0 AND $alias.timeopen!=0".$filters;
            }
            if ($alias == 'scorm') {
                $this->sql .= " UNION  ALL SELECT COUNT(main.id) as activitycount
                FROM {course_modules}  main
                JOIN {modules} m ON main.module = m.id
                JOIN {course} c ON c.id = main.course
                JOIN {context} ctx ON ctx.instanceid = c.id
                JOIN {role_assignments} ra ON ra.contextid = ctx.id
                JOIN {user} u ON u.id = ra.userid
                JOIN {".$alias."}  $alias ON $alias.id = main.instance
                AND m.name = '$alias' AND $alias.timeclose < ".$timestamp."
                WHERE $alias.id NOT IN (SELECT scormid FROM {scorm_attempt} WHERE userid = ".$userid.")
                AND $alias.id NOT IN(SELECT cm.module FROM {course_modules_completion} cmc
                JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                WHERE cmc.userid = ".$userid." AND cmc.completionstate <> 0) AND m.visible = 1 AND m.name IN ($activitynames)
                AND main.visible = 1 AND $alias.timeclose!=0 AND $alias.timeopen!=0".$filters;
            }
        }
            $this->sql .= ") as totalcount";
    }
    /**
     * SELECT SQL
     */
    public function select() {
        global $DB;
        $date = new DateTime();
        $timestamp = $date->getTimestamp();
        $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
        $coursemodules = $DB->get_records_sql_menu("SELECT md.id, md.name
        FROM {course_modules}  cs
        JOIN {modules}  md ON cs.module = md.id
        WHERE cs.visible = :visible", ['visible' => 1]);
        $aliases = [];
        $activities = [];
        foreach ($modules as $modulename) {
            if (in_array($modulename, $coursemodules)) {
                $aliases[] = $modulename;
                $activities[] = "'$modulename'";
            }
        }
        $activitynames = implode(',', $activities);
        if (!isset($this->params['filter_courses'])) {
            $this->initial_basicparams('courses');
            $filterdata = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($filterdata);
        }
        $userid = $this->params['filter_users'] ? $this->params['filter_users'] : $this->userid;
        $filters = '';
        if (isset($this->params['filter_courses']) && $this->params['filter_courses'] > 0) {
            $filters .= " AND main.course = " .$this->params['filter_courses'];
        }
        if (isset($this->params['filter_activities']) && $this->params['filter_activities'] > 0) {
            $filters .= " AND main.id = ".$this->params['filter_activities'];
        }
            $filters .= " AND u.id = $userid";
        foreach ($aliases as $alias) {

            if ($alias == 'assign') {
                $this->sql = " SELECT DISTINCT(main.id), m.id as moduleid, main.instance,
                main.course, $alias.name as activityname, $alias.allowsubmissionsfromdate as startdate,
                $alias.duedate as lastdate
                FROM {course_modules} main
                JOIN {modules} m ON main.module = m.id
                JOIN {course} c ON c.id = main.course
                JOIN {context} con ON c.id = con.instanceid AND con.contextlevel = 50
                JOIN {role_assignments} ra ON ra.contextid = con.id
                JOIN {user} u ON u.id = ra.userid
                JOIN {".$alias."} $alias ON $alias.id = main.instance AND m.name = '$alias' AND $alias.duedate < $timestamp
                JOIN {assign_submission} b ON asb.assignment= $alias.id AND asb.userid =$userid
                WHERE m.visible = 1 AND m.name IN ($activitynames) AND main.visible = 1
                AND asb.status != 'submitted'".$filters;
            }
            if ($alias == 'quiz') {
                $this->sql .= " UNION SELECT DISTINCT(main.id), m.id as moduleid, main.instance,
                                main.course ,$alias.name as activityname, $alias.timeopen as startdate,
                                $alias.timeclose as lastdate
                FROM {course_modules} main
                JOIN {modules} m ON main.module = m.id
                JOIN {course} c ON c.id = main.course
                JOIN {context} con ON c.id = con.instanceid AND con.contextlevel = 50
                JOIN {role_assignments} ra ON ra.contextid = con.id
                JOIN {user} u ON u.id = ra.userid
                JOIN {".$alias."} $alias ON $alias.id = main.instance AND m.name = '$alias'
                AND $alias.timeclose < ".$timestamp."
                WHERE $alias.id
                NOT IN (SELECT qa.quiz FROM  {quiz_attempts} qa WHERE qa.userid = ".$userid.")
                AND m.visible = 1 AND m.name IN ($activitynames) AND main.visible = 1 AND $alias.timeclose!=0
                AND $alias.timeopen!=0".$filters;
            }
            if ($alias == 'scorm') {
                $this->sql .= " UNION SELECT DISTINCT(main.id), m.id as moduleid, main.instance,
                                main.course ,$alias.name as activityname, $alias.timeopen as startdate,
                                $alias.timeclose as lastdate
                FROM {course_modules} main
                JOIN {modules} m ON main.module = m.id
                JOIN {course} c ON c.id = main.course
                JOIN {context} con ON c.id = con.instanceid AND con.contextlevel = 50
                JOIN {role_assignments} ra ON ra.contextid = con.id
                JOIN {user} u ON u.id = ra.userid
                JOIN {".$alias."} $alias ON $alias.id = main.instance AND m.name = '$alias'
                AND $alias.timeclose < ".$timestamp."
                 WHERE $alias.id NOT IN (SELECT scormid FROM {scorm_attempt}
                 WHERE userid = ".$userid.") AND $alias.id
                 NOT IN (SELECT cm.module FROM {course_modules_completion} cmc JOIN {course_modules} cm
                 ON cm.id = cmc.coursemoduleid
                 WHERE cmc.userid = ".$userid." AND cmc.completionstate <> 0)
                 AND m.visible = 1 AND m.name IN ($activitynames)
                 AND main.visible = 1 AND $alias.timeclose!=0 AND $alias.timeopen!=0".$filters;
            }

        }
        parent::select();
    }
    /**
     * FROM SQL
     */
    public function from() {

    }
    /**
     * SQL JOINS
     */
    public function joins() {

    }
    /**
     * SQL Where condition
     */
    public function where() {
    }
    /**
     * Concat search values to the query
     */
    public function search() {
    }
    /**
     * Concat filter values to the query
     */
    public function filters() {
    }
    /**
     * Concat group by the query
     */
    public function groupby() {
    }
    /**
     * Get report rows
     * @param  array  $users Users
     * @return array
     */
    public function get_rows($users = []) {
        return $users;
    }
}
