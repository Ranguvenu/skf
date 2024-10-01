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
 * Monthly Sessions
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
use html_writer;
/**
 * Monthly Sessions
 */
class report_monthlysessions extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;
    /**
     * Constructor for report.
     * @param object $report Report data
     * @param object $reportproperties Report properties
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = false;
        $this->courselevel = false;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $columns = ['month', 'sessionscount', 'timespent'];
        $this->columns = ['monthlysessions' => $columns];
        $this->filters = ['users'];
        $this->orderable = [];
        $this->defaultcolumn = 't1.month';
    }
    /**
     * Monthly session report init function
     */
    public function init() {
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
     * count SQL
     */
    public function count() {
        $start    = new \DateTime('first day of January');
        $end      = new \DateTime('last day of December');
        $interval = \DateInterval::createFromDateString('1 month');
        $period   = new \DatePeriod($start, $interval, $end);
        $i = 0;
        foreach ($period as $dt) {
            $i++;
            $months = $dt->format("F") . html_writer::empty_tag('br');
            $monthslist[] = $months;
        }
        $totalmonths = count($monthslist);
        $this->sql = "SELECT $totalmonths as totalmonths ";
    }
    /**
     * SELECT SQL
     */
    public function select() {
        global $DB, $CFG;
        $start    = new \DateTime('first day of January');
        $end      = new \DateTime('last day of December');
        $interval = \DateInterval::createFromDateString('1 month');
        $period   = new \DatePeriod($start, $interval, $end);
        $i = 0;
        $concatsql = " ";
        if (!empty($this->params['filter_users'])) {
            $userid = $this->params['filter_users'];
            $concatsql .= " AND lsl.userid IN ($userid)";
        }
        foreach ($period as $dt) {
            $monthorder = $dt->format("m");
            $months = $dt->format("F");
            $monthsql = $dt->format('m-Y');
            $monthslist[] = $months;
            $sessionsql = "SELECT count(DISTINCT lsl.id) as sessionscount
                                FROM mdl_logstore_standard_log as lsl
                                WHERE 1 = 1 $concatsql AND lsl.target = 'user' AND lsl.crud = 'r' AND
                                lsl.other LIKE '%sessionid%'";
            if ($CFG->dbtype == 'sqlsrv') {
                $sessionsql .= " AND FORMAT(DATEADD(s, lsl.timecreated, '1970-01-01 00:00:00'), 'MM-yyyy') = '" .$monthsql. "'";
            } else if ($CFG->dbtype == 'pgsql') {
                $sessionsql .= " AND to_char(to_timestamp(lsl.timecreated), 'mm-YYYY') = '" .$monthsql. "'";
            } else {
                $sessionsql .= " AND FROM_UNIXTIME(lsl.timecreated, '%m-%Y') = '" .$monthsql. "'";
            }
            $sessionscount = $DB->get_field_sql($sessionsql);

            $timespentsql = "SELECT DISTINCT lsl.id, lsl.timecreated
                                FROM mdl_logstore_standard_log as lsl
                                WHERE 1 = 1 $concatsql AND lsl.target = 'user' AND lsl.crud = 'r' AND
                                lsl.action LIKE 'loggedout' ";
            if ($CFG->dbtype == 'sqlsrv') {
                $timespentsql .= " AND FORMAT(DATEADD(s, lsl.timecreated, '1970-01-01 00:00:00'), 'MM-yyyy') = '" .$monthsql. "'";
            } else if ($CFG->dbtype == 'pgsql') {
                $timespentsql .= " AND to_char(to_timestamp(lsl.timecreated), 'mm-YYYY') = '" .$monthsql. "'";
            } else {
                $timespentsql .= " AND FROM_UNIXTIME(lsl.timecreated, '%m-%Y') = '" .$monthsql. "'";
            }
            $timespentsql .= " ORDER BY id DESC ";
            $timespentdata = $DB->get_records_sql($timespentsql);
            if (!empty($timespentdata)) {
                $timediff = [];
                foreach ($timespentdata as $tsql) {
                    if ($CFG->dbtype == 'sqlsrv') {
                        $logintimesql = "SELECT TOP 1 lsl.timecreated
                        FROM mdl_logstore_standard_log lsl
                        WHERE 1 = 1 $concatsql AND lsl.target = 'user'
                        AND lsl.crud = 'r' AND lsl.action LIKE 'loggedin'
                        AND lsl.timecreated < $tsql->timecreated ";
                    } else {
                        $logintimesql = "SELECT lsl.timecreated
                        FROM mdl_logstore_standard_log lsl
                        WHERE 1 = 1 $concatsql AND lsl.target = 'user'
                        AND lsl.crud = 'r' AND lsl.action LIKE 'loggedin'
                        AND lsl.timecreated < $tsql->timecreated ";
                    }
                    if ($CFG->dbtype == 'sqlsrv') {
                        $logintimesql .= " AND FORMAT(DATEADD(s, lsl.timecreated, '1970-01-01 00:00:00'), 'MM-yyyy') =
                        '" .$monthsql. "' ORDER BY id DESC ";
                    } else if ($CFG->dbtype == 'pgsql') {
                        $logintimesql .= " AND to_char(to_timestamp(lsl.timecreated), 'mm-YYYY') =
                        '" .$monthsql. "' ORDER BY id DESC LIMIT 1 ";
                    } else {
                        $logintimesql .= " AND FROM_UNIXTIME(lsl.timecreated, '%m-%Y') =
                        '" .$monthsql. "' ORDER BY id DESC LIMIT 1 ";
                    }
                    $logintime = $DB->get_field_sql($logintimesql);
                    if (!empty($logintime)) {
                        $timediff[] = $tsql->timecreated - $logintime;
                    }
                }
                $totaltimespent = array_sum($timediff);
            } else {
                $totaltimespent = 0;
            }
            $query = '';
            if ($i == 0) {
                $query .= "SELECT '".$months."' as month, $sessionscount as sessionscount,
                $totaltimespent as timespent, $monthorder as monthorder ";
            } else {
                $query .= " UNION SELECT '".$months."' as month, $sessionscount as sessionscount,
                $totaltimespent as timespent, $monthorder as monthorder ";
            }
            $i++;
        }
        $this->sql = " SELECT t1.month, t1.sessionscount, t1.timespent, t1.monthorder
        FROM ($query) as t1
        ORDER BY t1.monthorder asC";
        parent::select();
    }
    /**
     * FROM SQL
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
     * SQL Where conditions
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
     * Concat filter values to the query
     */
    public function filters() {
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {
    }
    /**
     * Constructor for report.
     * @param  array $users Users list
     * @return array
     */
    public function get_rows($users = []) {
        return $users;
    }
}
