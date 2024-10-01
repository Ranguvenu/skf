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
 * Course Weekly sessions report
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
 * Weekly sessions report class
 */
class report_weeklysessions extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /**
     * Report constructor
     * @param object $report           Report data
     */
    public function __construct($report) {
        parent::__construct($report);
        $this->parent = false;
        $this->courselevel = true;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $columns = ['weekday', 'sessionscount', 'timespent'];
        $this->columns = ['weeklysessions' => $columns];
        $this->filters = ['users'];
        $this->orderable = ['weekday', 'sessionscount', 'timespent'];
        $this->defaultcolumn = 't1.weekday';
    }

    /**
     * Report init
     */
    public function init() {
        global $DB;
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
     * Count SQL
     */
    public function count() {
        global $DB;
        $start    = new \DateTime('monday this week');
        $end      = new \DateTime('next monday');
        $interval = new \DateInterval('P1D');
        $period   = new \DatePeriod($start, $interval, $end);
        foreach ($period as $dt) {
            $weekdays = $dt->format("l") . html_writer::empty_tag('br');
            $dayslist[] = $weekdays;
        }
        $totaldays = count($dayslist);
        $this->sql = "SELECT $totaldays AS totaldays ";
    }
    /**
     * Select SQL
     */
    public function select() {
        global $DB, $CFG;
        $start    = new \DateTime('monday this week');
        $end      = new \DateTime('next monday');
        $interval = new \DateInterval('P1D');
        $period   = new \DatePeriod($start, $interval, $end);
        $i = 0;
        $concatsql = " ";
        if (!empty($this->params['filter_users'])) {
            $userid = $this->params['filter_users'];
            $this->params['userid'] = $userid;
            $concatsql .= " AND lsl.userid = :userid";
        }
        foreach ($period as $dt) {
            $weekdays = $dt->format("l");
            $weekdaynumber = $dt->format("w");
            $weekdaysql = $dt->format('d-m-Y');
            $weekdayslist[] = $weekdays;
            $sessionsql = "SELECT COUNT(DISTINCT lsl.id) AS sessionscount
            FROM mdl_logstore_standard_log AS lsl
            WHERE 1 = 1 $concatsql AND lsl.target = 'user' AND lsl.crud = 'r' AND
            lsl.other LIKE '%sessionid%'";
            if ($CFG->dbtype == 'sqlsrv') {
                $sessionsql .= " AND FORMAT(DATEADD(s, lsl.timecreated, '1970-01-01 00:00:00'), 'dd-MM-yyyy') =
                '" .$weekdaysql. "'";
            } else if ($CFG->dbtype == 'pgsql') {
                $sessionsql .= " AND to_char(to_timestamp(lsl.timecreated), 'dd-mm-YYYY') = '" .$weekdaysql. "'";
            } else {
                $sessionsql .= " AND FROM_UNIXTIME(lsl.timecreated, '%d-%m-%Y') = '" .$weekdaysql. "'";
            }
            $sessionscount = $DB->get_field_sql($sessionsql);

            $timespentsql = "SELECT DISTINCT lsl.id, lsl.timecreated
                                FROM mdl_logstore_standard_log AS lsl
                                WHERE 1 = 1 $concatsql AND lsl.target = 'user' AND lsl.crud = 'r' AND
                                lsl.action LIKE 'loggedout' ";
            if ($CFG->dbtype == 'sqlsrv') {
                $timespentsql .= " AND FORMAT(DATEADD(s, lsl.timecreated, '1970-01-01 00:00:00'), 'dd-MM-yyyy') =
                 '" .$weekdaysql. "'";
            } else if ($CFG->dbtype == 'pgsql') {
                $timespentsql .= " AND to_char(to_timestamp(lsl.timecreated), 'dd-mm-YYYY') = '" .$weekdaysql. "'";
            } else {
                $timespentsql .= " AND FROM_UNIXTIME(lsl.timecreated, '%d-%m-%Y') = '" .$weekdaysql. "'";
            }
            $timespentsql .= " ORDER BY id DESC ";
            $timespentdata = $DB->get_records_sql($timespentsql);
            if (!empty($timespentdata)) {
                $timediff = [];
                foreach ($timespentdata as $tsql) {
                    if ($CFG->dbtype == 'sqlsrv') {
                        $logintimesql = "SELECT TOP 1 lsl.timecreated FROM mdl_logstore_standard_log lsl WHERE 1 =
                         1 $concatsql AND lsl.target = 'user' AND lsl.crud = 'r' AND
                                        lsl.action LIKE 'loggedin' AND lsl.timecreated < $tsql->timecreated ";
                    } else {
                        $logintimesql = "SELECT lsl.timecreated FROM mdl_logstore_standard_log lsl WHERE 1 =
                         1 $concatsql AND lsl.target = 'user' AND lsl.crud = 'r' AND
                                    lsl.action LIKE 'loggedin' AND lsl.timecreated < $tsql->timecreated ";
                    }
                    if ($CFG->dbtype == 'sqlsrv') {
                        $logintimesql .= " AND FORMAT(DATEADD(s, lsl.timecreated, '1970-01-01 00:00:00'), 'dd-MM-yyyy') =
                         '" .$weekdaysql. "' ORDER BY id DESC ";
                    } else if ($CFG->dbtype == 'pgsql') {
                        $logintimesql .= " AND to_char(to_timestamp(lsl.timecreated), 'dd-mm-YYYY') =
                         '" .$weekdaysql. "' ORDER BY id DESC LIMIT 1 ";
                    } else {
                        $logintimesql .= " AND FROM_UNIXTIME(lsl.timecreated, '%d-%m-%Y') =
                         '" .$weekdaysql. "' ORDER BY id DESC LIMIT 1 ";
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
                $query .= "SELECT '".$weekdays."' AS weekday, $sessionscount AS sessionscount,
                $totaltimespent AS timespent, $weekdaynumber AS weekday_number ";
            } else {
                $query .= " UNION SELECT '".$weekdays."' AS weekday, $sessionscount AS sessionscount,
                $totaltimespent AS timespent, $weekdaynumber AS weekday_number ";
            }
            $i++;
        }
        $this->sql = " SELECT t1.weekday, t1.sessionscount, t1.timespent FROM ($query) AS t1 ORDER BY t1.weekday_number ASC";
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
     * This function returns the report columns queries
     * @param  array  $users Users data
     * @return array
     */
    public function get_rows($users = []) {
        return $users;
    }
}
