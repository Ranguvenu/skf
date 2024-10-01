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
 * Daily sessions report
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
 * Daily sessions report
 */
class report_dailysessions extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /**
     * Constructor for report.
     * @param object $report Report data
     */
    public function __construct($report) {
        parent::__construct($report);
        $this->parent = false;
        $this->courselevel = true;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $columns = ['date', 'sessionscount', 'timespent'];
        $this->columns = ['dailysessionscolumns' => $columns];
        $this->filters = ['users'];
        $this->orderable = ['date', 'sessionscount', 'timespent'];
        $this->defaultcolumn = 't1.date';
    }
    /**
     * Daily sessions report init function
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
     * SQL COUNT
     */
    public function count() {
        global $DB;
        $start    = new \DateTime('first day of this month');
        $end      = new \DateTime('last day of this month');
        $lastday = $end->modify('+1 day');
        $interval = \DateInterval::createFromDateString('1 day');
        $period   = new \DatePeriod($start, $interval, $lastday);
        $i = 0;
        foreach ($period as $dt) {
            $i++;
            $days = $dt->format("j") . html_writer::empty_tag('br');
            $dayslist[] = $days;
        }
        $totaldays = count($dayslist);
        $this->sql = "SELECT $totaldays AS totaldays ";
    }
    /**
     * SELECt SQL
     */
    public function select() {
        global $DB, $CFG;
        $start    = new \DateTime('first day of this month');
        $end      = new \DateTime('last day of this month');
        $lastday = $end->modify('+1 day');
        $interval = \DateInterval::createFromDateString('1 day');
        $period   = new \DatePeriod($start, $interval, $lastday);
        $i = 0;
        $concatsql = " ";
        $query = '';
        if (!empty($this->params['filter_users'])) {
            $userid = $this->params['filter_users'];
            $this->params['userid'] = $userid;
            $concatsql .= " AND lsl.userid = :userid";
        }
        foreach ($period as $dt) {
            $orderbyday = $dt->format('d-m-Y');
            $days = $dt->format('jS M Y');
            $daysql = $dt->format('d-m-Y');
            $dayslist[] = $days;
            $sessionsql = "SELECT COUNT(DISTINCT lsl.id) AS sessionscount
                                FROM {logstore_standard_log} AS lsl
                                WHERE 1 = 1 $concatsql AND lsl.target = 'user' AND lsl.crud = 'r' AND
                                lsl.other LIKE '%sessionid%'";
            if ($CFG->dbtype == 'sqlsrv') {
                $sessionsql .= " AND CONVERT(VARCHAR, DATEADD(s, lsl.timecreated, '1970-01-01 00:00:00'), 105) = '" .$daysql. "'";
            } else if ($CFG->dbtype == 'pgsql') {
                $sessionsql .= " AND to_char(to_timestamp(lsl.timecreated), 'dd-mm-YYYY') = '" .$daysql. "'";
            } else {
                $sessionsql .= " AND FROM_UNIXTIME(lsl.timecreated, '%d-%m-%Y') = '" .$daysql. "'";
            }
            $sessionscount = $DB->get_field_sql($sessionsql);

            $timespentsql = "SELECT DISTINCT lsl.id, lsl.timecreated
                                FROM {logstore_standard_log} AS lsl
                                WHERE 1 = 1 $concatsql AND lsl.target = 'user' AND lsl.crud = 'r' AND
                                lsl.action LIKE 'loggedout' ";
            if ($CFG->dbtype == 'sqlsrv') {
                $timespentsql .= " AND CONVERT(VARCHAR, DATEADD(s, lsl.timecreated, '1970-01-01 00:00:00'), 105) = '" .$daysql. "'";
            } else if ($CFG->dbtype == 'pgsql') {
                $timespentsql .= " AND to_char(to_timestamp(lsl.timecreated), 'dd-mm-YYYY') = '" .$daysql. "'";
            } else {
                $timespentsql .= " AND FROM_UNIXTIME(lsl.timecreated, '%d-%m-%Y') = '" .$daysql. "'";
            }
            $timespentsql .= " ORDER BY id DESC ";
            $timespentdata = $DB->get_records_sql($timespentsql);
            if (!empty($timespentdata)) {
                $timediff = [];
                foreach ($timespentdata as $tsql) {
                    if ($CFG->dbtype == 'sqlsrv') {
                        $logintimesql = "SELECT TOP 1 lsl.timecreated
                        FROM {logstore_standard_log} lsl
                        WHERE 1 = 1 $concatsql AND lsl.target = 'user'
                        AND lsl.crud = 'r' AND lsl.action LIKE 'loggedin'
                        AND lsl.timecreated < $tsql->timecreated ";
                    } else {
                        $logintimesql = "SELECT lsl.timecreated
                        FROM {logstore_standard_log} lsl
                        WHERE 1 = 1 $concatsql AND lsl.target = 'user'
                        AND lsl.crud = 'r' AND lsl.action LIKE 'loggedin'
                        AND lsl.timecreated < $tsql->timecreated ";
                    }
                    if ($CFG->dbtype == 'sqlsrv') {
                        $logintimesql .= " AND CONVERT(VARCHAR,
                        DATEADD(s, lsl.timecreated, '1970-01-01 00:00:00'), 105) = '" .$daysql. "'
                        ORDER BY id DESC ";
                    } else if ($CFG->dbtype == 'pgsql') {
                        $logintimesql .= " AND to_char(to_timestamp(lsl.timecreated), 'dd-mm-YYYY') = '" .$daysql. "'
                        ORDER BY id DESC LIMIT 1 ";
                    } else {
                        $logintimesql .= " AND FROM_UNIXTIME(lsl.timecreated, '%d-%m-%Y') = '" .$daysql. "'
                        ORDER BY id DESC LIMIT 1 ";
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
            if ($i == 0) {
                $query .= "SELECT '".$days."' as date, $sessionscount as sessionscount,
                $totaltimespent as timespent, $orderbyday as orderbyday ";
            } else {
                $query .= " UNION SELECT '".$days."' as day, $sessionscount as sessionscount,
                $totaltimespent as timespent, $orderbyday as orderbyday ";
            }
            $i++;
        }
        $this->sql = " SELECT t1.date, t1.sessionscount, t1.timespent, t1.orderbyday
        FROM ($query) AS t1
        ORDER BY t1.orderbyday ASC";
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
     * Get report rows
     * @param  array  $users Users list
     * @return array
     */
    public function get_rows($users = []) {
        return $users;
    }
}
