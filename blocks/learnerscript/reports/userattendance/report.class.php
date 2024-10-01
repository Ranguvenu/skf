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
 * Users attendance report
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

/**
 * Users quizzes report class
 */
class report_userattendance extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /**
     * Report constructor
     * @param object $report Report object
     */
    public function __construct($report) {
        parent::__construct($report);
        $this->parent = false;
        $this->courselevel = true;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $columns = ['date', 'usercount'];
        $this->columns = ['userattendance' => $columns];
        $this->basicparams = [['name' => 'courses']];
        $this->orderable = ['date', 'usercount'];
        $this->defaultcolumn = 't1.date';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Report init
     */
    public function init() {
        global $DB;
        if (!isset($this->params['filter_courses'])) {
            $this->initial_basicparams('courses');
            $coursefilter = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($coursefilter);
        }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->params['roleid'] = $studentroleid;
    }
    /**
     * Count SQL
     */
    public function count() {
        global $DB;
        if ($this->lsstartdate == 0) {
            $lastmonthdate = new \DateTime('1 month ago');
            $onemonthago = $lastmonthdate->format('Y-m-d');
            $startdate = $onemonthago;
        } else {
            $firstdate = $DB->get_field_sql("SELECT $this->lsstartdate AS startdate");
            $startdate = userdate($firstdate, '%Y-%m-%d');
        }
        $lastdate = $DB->get_field_sql("SELECT $this->lsenddate AS enddate");
        $enddate = userdate($lastdate, '%Y-%m-%d');
        $sdate = strtotime($startdate);
        $edate = strtotime($enddate);
        $i = 0;
        for ($currentdate = $sdate; $currentdate <= $edate; $currentdate += (86400)) {
            $i++;
            $date = date('d-m-Y', $currentdate);
            $dates[] = $date;
        }
        $total = count($dates);
        $this->sql = " SELECT $total AS total ";
    }
    /**
     * Select SQL
     */
    public function select() {
        global $DB, $CFG;
        if ($this->lsstartdate == 0) {
            $lastmonthdate = new \DateTime('1 month ago');
            $onemonthago = $lastmonthdate->format('Y-m-d');
            $startdate = $onemonthago;
        } else {
            $firstdate = $DB->get_field_sql("SELECT $this->lsstartdate AS startdate");
            $startdate = userdate($firstdate, '%Y-%m-%d');
        }
        $lastdate = $DB->get_field_sql("SELECT $this->lsenddate AS enddate");
        $enddate = userdate($lastdate, '%Y-%m-%d');
        $sdate = strtotime($startdate);
        $edate = strtotime($enddate);
        $i = 0;
        $query = " ";
        $concatsql = " ";
        if ($this->params['filter_courses'] > SITEID) {
            $courseid = $this->params['filter_courses'];
            $concatsql .= " AND lsl.courseid IN (:filter_courses)";
        }

        for ($currentdate = $sdate; $currentdate <= $edate; $currentdate += (86400)) {
            $date = date('d-m-Y', $currentdate);
            $usrcntsql = "SELECT COUNT(DISTINCT lsl.userid) AS usercount
                            FROM {logstore_standard_log} lsl
                            JOIN {user} u ON u.id = lsl.userid
                            JOIN {role_assignments} ra ON ra.userid = u.id
                            JOIN {context} ctx ON ctx.id = ra.contextid
                            JOIN {course} c ON c.id = ctx.instanceid
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                            WHERE lsl.action = 'viewed' $concatsql AND c.id = lsl.courseid";

            if ($CFG->dbtype == 'sqlsrv') {
                $usrcntsql .= " AND CONVERT(VARCHAR, DATEADD(s, lsl.timecreated, '1970-01-01 00:00:00'), 105) = '" .$date. "'";
            } else if ($CFG->dbtype == 'pgsql') {
                $usrcntsql .= " AND to_char(to_timestamp(lsl.timecreated), 'dd-mm-YYYY') = '" .$date. "'";
            } else {
                $usrcntsql .= " AND FROM_UNIXTIME(lsl.timecreated, '%d-%m-%Y') = '" .$date. "' ";
            }
            $usercount = $DB->get_field_sql($usrcntsql, ['filter_courses' => $this->params['filter_courses']]);

            $attendancearray[] = "('".$date."'," . $usercount . ", " . $currentdate . ")";
            if ($i == 0) {
                $query .= "SELECT '".$date."' AS date, $usercount AS usercount, $currentdate AS currentdate";
            } else {
                $query .= " UNION SELECT '".$date."' AS date, $usercount AS usercount, $currentdate AS currentdate ";
            }
            $i++;
        }
        $userattendance = implode(',', $attendancearray);
        $this->sql = " SELECT t1.date, t1.usercount, t1.currentdate FROM ($query) AS t1 ORDER BY t1.currentdate DESC";
        parent::select();
    }
    /**
     * Form SQL
     */
    public function from() {

        $this->sql .= " ";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " ";
        parent::joins();
    }
    /**
     * Adding conditions to the query
     */
    public function where() {
        $this->sql .= " ";
        parent::where();
    }
    /**
     * Concat search values to the query
     */
    public function search() {
    }
    /**
     * Concat filters to the query
     */
    public function filters() {

    }
    /**
     * Concat groupby to SQL
     */
    public function groupby() {

    }
    /**
     * get rows
     * @param  array $users users
     * @return array
     */
    public function get_rows($users = []) {
        return $users;
    }
}
