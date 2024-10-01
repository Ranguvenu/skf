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
 * Course Activities report
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
 * Active students report
 */
class report_activestudents extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;
    /**
     * report constructor
     * @param object $report
     */
    public function __construct($report) {
        parent::__construct($report);
        $this->parent = false;
        $this->courselevel = false;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $columns = ['learner', 'email', 'sessionjoinedat', 'sessionsduration'];
        $this->columns = ['bigbluebuttonfields' => $columns];
        $this->orderable = ['learner', 'email', 'sessionjoinedat'];
        $this->basicparams = [['name' => 'session']];
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = ["'student'"];

    }
    /**
     * Report init function
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
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT u.id) ";
    }
    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT u.id, CONCAT(u.firstname, ' ', u.lastname) AS learner,
        u.email, MAX(bbl.timecreated) AS sessionjoinedat, bbl.bigbluebuttonbnid,
        (SELECT mt.timespent AS totaltimespent
        FROM {block_ls_modtimestats} mt
        JOIN {course_modules} cm1 ON cm1.id = mt.activityid
        JOIN {modules} m1 ON m1.id = cm1.module
        WHERE mt.userid > :mtuserid AND m1.name = :bluebuttonname
        AND mt.userid = u.id AND cm1.module = cm.module AND cm1.id = cm.id) AS sessionsduration";
        $this->params['mtuserid'] = 2;
        $this->params['bluebuttonname'] = 'bigbluebuttonbn';
        parent::select();
    }
    /**
     * From SQL
     */
    public function from() {
        $this->sql .= " FROM {user} u ";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {bigbluebuttonbn_logs} bbl ON bbl.userid = u.id
        JOIN {course_modules} cm ON cm.instance = bbl.bigbluebuttonbnid
        JOIN {modules} m ON m.id = cm.module AND m.name = 'bigbluebuttonbn'";
        parent::joins();
    }
    /**
     * Where Condition
     */
    public function where() {
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
        global $DB;
        if (isset($this->params['filter_session']) && $this->params['filter_session'] > 0) {
            $this->sql .= " JOIN {course} c ON c.id = bbl.courseid
            JOIN {context} ct ON ct.instanceid = c.id
            JOIN {role_assignments} ra ON ra.contextid = ct.id AND bbl.userid = ra.userid
            JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'";
            $this->sql .= " WHERE bbl.log = 'Join' AND u.confirmed = 1 AND u.deleted = 0 ";
            if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
                if ($this->rolewisecourses != '') {
                    $this->sql .= " AND bbl.courseid IN ($this->rolewisecourses) ";
                }
            }
            if (isset($this->search) && $this->search) {
                $this->searchable = ["CONCAT(u.firstname, ' ', u.lastname)"];
                $statsql = [];
                foreach ($this->searchable as $key => $value) {
                    $statsql[] = $DB->sql_like($value, "'%" . $this->search . "%'", $casesensitive = false,
                    $accentsensitive = true, $notlike = false);
                }
                $fields = implode(" OR ", $statsql);
                $this->sql .= " AND ($fields) ";
            }
            $this->sql .= " AND bbl.bigbluebuttonbnid IN (:filter_session) ";
            $courseid = $DB->get_field_sql("SELECT course
            FROM {bigbluebuttonbn}
            WHERE id = :sessionid", ['sessionid' => $this->params['filter_session']]);
            $this->sql .= " AND ct.instanceid = $courseid ";
        } else {
            $this->sql .= " WHERE 1=1 ";
        }
        if ($this->lsstartdate > 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND bbl.timecreated BETWEEN :lsfstartdate AND :lsfenddate ";
        }
    }
    /**
     * Concat groupby to SQL
     */
    public function groupby() {
        $this->sql .= " GROUP BY u.id, bbl.bigbluebuttonbnid, u.firstname, u.lastname, u.email, cm.module, cm.id";
    }
    /**
     * This function gets the report rows
     * @param  array  $users Users list
     * @return array
     */
    public function get_rows($users = []) {
        return $users;
    }
}
