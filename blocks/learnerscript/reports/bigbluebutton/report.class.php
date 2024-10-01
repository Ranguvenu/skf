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
 * Bigbluebutton report
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
 * Bigbluebutton report
 */
class report_bigbluebutton extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;
    /**
     * @var array $basicparamdata Basic params list
     */
    public $basicparamdata;

    /**
     * Constructor for report.
     * @param object $report
     * @param object $reportproperties
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->courselevel = true;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $columns = ['session', 'course', 'timestart', 'duration', 'activestudents', 'inactivestudents'];
        $this->columns = ['bigbluebuttonfields' => $columns];
        $this->orderable = ['session', 'course', 'duration', 'activestudents'];
        $this->basicparams = [['name' => 'courses']];
        $this->defaultcolumn = 'bbb.id';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Bigbluebutton init function
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
    }
    /**
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT bbb.id) ";
    }
    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT bbb.id, bbb.name as session, c.id as courseid, c.fullname as course,
        bbb.openingtime as timestart, (bbb.closingtime - bbb.openingtime) as duration,
            (SELECT count(DISTINCT bbbl.userid)
            FROM {user} u
            JOIN {bigbluebuttonbn_logs} bbbl ON bbbl.userid = u.id
            JOIN {role_assignments} ra ON ra.userid = bbbl.userid
            JOIN {context} ct ON ct.id = ra.contextid
            JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'
            WHERE bbbl.log = 'Join' AND bbbl.bigbluebuttonbnid = bbb.id AND ct.instanceid = c.id
            AND u.confirmed = 1 AND u.deleted = :deleted) AS activestudents,
            cm.id AS activityid ";
        $this->params['deleted'] = 0;
        parent::select();
    }
    /**
     * From SQL
     */
    public function from() {
        $this->sql .= " FROM {bigbluebuttonbn} bbb ";
    }
    /**
     * SQL Joins
     */
    public function joins() {
        $this->sql .= " JOIN {course} c ON c.id = bbb.course
                        JOIN {course_modules} cm ON cm.instance = bbb.id
                        JOIN {modules} m ON m.id = cm.module AND m.name = 'bigbluebuttonbn'";
        parent::joins();
    }
    /**
     * SQL Where conditions
     */
    public function where() {
        $userid = isset($this->params['filter_users']) ? $this->params['filter_users'] : [];
        $this->sql .= " WHERE 1 = 1 ";

        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND bbb.course IN ($this->rolewisecourses) ";
            }
        }
        parent::where();
    }
    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $this->searchable = ["bbb.name", "c.fullname"];
            $statsql = [];
            foreach ($this->searchable as $key => $value) {
                $statsql[] = $DB->sql_like($value, "'%" . $this->search . "%'", $casesensitive = false,
                $accentsensitive = true, $notlike = false);
            }
            $fields = implode(" OR ", $statsql);
            $this->sql .= " AND ($fields) ";
        }
    }
    /**
     * Concat filter values to the query
     */
    public function filters() {
        if (isset($this->params['filter_courses']) && $this->params['filter_courses'] > SITEID) {
            $this->sql .= " AND bbb.course IN (:filter_courses) ";
        }
        if ($this->lsstartdate > 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND bbb.timecreated BETWEEN :lsfstartdate AND :lsfenddate ";
        }
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {
    }
    /**
     * Get report rows
     * @param  array $users Users list
     * @return array
     */
    public function get_rows($users = []) {
        return $users;
    }
}
