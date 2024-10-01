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
 * Need grading report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
/**
 * Need grading report
 */
class report_needgrading extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /**
     * Constructor for report.
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = ['columns', 'filters', 'permissions', 'calcs', 'plot'];
        $columns = ['username', 'course', 'module', 'assignment', 'datesubmitted', 'delay', 'grade'];
        $this->columns = ['needgrading' => $columns];
        $this->courselevel = false;
        $this->filters = ['users'];
        $this->parent = true;
        $this->orderable = ['username', 'course', 'module', 'assignment', 'datesubmitted', 'delay', 'grade'];
        $this->defaultcolumn = "concat(gg.itemid, '-', cm.course, '-', gg.userid, '-',cm.id)";
        $this->excludedroles = ["'student'"];
    }
    /**
     * COUNT SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT concat(gg.itemid, '-', cm.course, '-', gg.userid)) ";
    }
    /**
     * SELECT SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT(concat(gg.itemid, '-', cm.course, '-', gg.userid, '-',cm.id)) ,
        co.fullname as course, (SELECT concat(u.firstname,' ',u.lastname)
        FROM {user} u
        WHERE u.id=gg.userid) as username, gg.userid, gi.itemname as assignment,
        gg.timecreated as timecreated, m.name as module, cm.id as cmd";
    }
    /**
     * FROM SQL
     */
    public function from() {
        $this->sql .= " FROM {grade_grades} gg";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {grade_items} gi on gi.id = gg.itemid
                        JOIN {course_modules} cm ON cm.instance = gi.iteminstance AND gi.itemtype = 'mod'
                        JOIN {modules} m ON m.id = cm.module
                        JOIN {course} co ON co.id = cm.course
                        ";
    }
    /**
     * SQL Where condition
     */
    public function where() {
        global $DB;
        $userid = $this->userid;
        $this->sql .= " WHERE co.visible = 1 AND cm.visible = 1 AND m.visible = 1
                        AND cm.course IN( SELECT DISTINCT c.id
                        FROM {role_assignments} AS rl
                        JOIN {context} AS cxt ON cxt.id = rl.contextid
                        JOIN {user} AS u ON u.id=rl.userid
                        JOIN {course} AS c ON c.id=cxt.instanceid
                        JOIN {role} r ON r.id = rl.roleid
                        WHERE rl.userid>2 AND c.visible = 1 AND c.id = cxt.instanceid
                        AND cxt.contextlevel =50 AND rl.userid = $userid AND rl.roleid = 3)
                        AND gg.finalgrade is null AND gi.itemmodule = 'assign' AND gg.timecreated is not null
                        AND m.name = gi.itemmodule";

    }
    /**
     * COncat search values to the query
     */
    public function search() {

    }
    /**
     * Concat filter values to the query
     */
    public function filters() {
        if (isset($this->params['filter_users']) && $this->params['filter_users']) {
             $this->sql .= " AND gg.userid = ".$this->params['filter_users'];
        }
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {
        $this->sql .= " GROUP BY gg.itemid, cm.course, gg.userid, cm.id, co.fullname, gi.itemname, gg.timecreated, m.name";
    }

    /**
     * Get rows
     * @param  array $activites Activites
     * @return array
     */
    public function get_rows($activites) {
        return $activites;
    }
    /**
     * This function returns the report columns queries
     * @param  string  $column Column names
     * @param  int  $activityid Activity id
     * @param  int  $courseid courseid
     */
    public function column_queries($column, $activityid, $courseid = null) {
        global $DB;
    }
}
