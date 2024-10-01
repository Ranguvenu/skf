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
 * PAgeresourcetimespent report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls as ls;
/**
 * Pageresource timespent report
 */
class report_pageresourcetimespent extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /**
     * Constructor for report.
     * @param object $report Report data
     * @param object $reportproperties Report properties
     */
    public function __construct($report, $reportproperties = false) {
        parent::__construct($report, $reportproperties);
        $this->parent = true;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $pageresourcetimespentcolumns = ['name', 'totaltimespent'];
        $this->columns = ['activityfield' => ['activityfield'] ,
                          'pageresourcetimespentcolumns' => $pageresourcetimespentcolumns, ];
        $this->courselevel = false;
        $this->filters = ['courses'];
        $this->orderable = ['name', 'course', 'totaltimespent'];
        $this->defaultcolumn = 'p.id';
    }
    /**
     * Pageresource timespent init function
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
        $this->sql = "SELECT COUNT(DISTINCT p.id) ";
    }
    /**
     * SELECT SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT p.id as pid, cm.id, c.id as courseid,
        c.fullname as course, m.name as type, m.id as module ";
        if (!empty($this->selectedcolumns)) {
            if (in_array('name', $this->selectedcolumns)) {
                $this->sql .= ", p.name as name";
            }
            if (in_array('totaltimespent', $this->selectedcolumns)) {
                $learnersql  = (new querylib)->get_learners('', 'mt.courseid');
                $this->sql .= ", (SELECT SUM(mt.timespent)
                                FROM {block_ls_modtimestats} mt
                                WHERE mt.activityid = cm.id AND mt.userid IN ($learnersql)) as totaltimespent";
            }
        }
    }
    /**
     * FROM SQL
     */
    public function from() {
        $this->sql .= " FROM {page} p";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {course_modules} cm ON p.id = cm.instance
                        JOIN {modules} m ON m.id = cm.module AND m.name = 'page'
                        JOIN {course} c ON c.id = cm.course
                       WHERE 1=1 AND c.visible = 1 AND cm.visible = 1 AND cm.deletioninprogress = 0";
    }
    /**
     * SQL where condition
     */
    public function where() {
        global $SESSION;
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND cm.course IN ($this->rolewisecourses) ";
            }
        }
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($SESSION->role == 'student') {
                $this->params['userid'] = $this->userid;
            }
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->sql .= " AND cm.added BETWEEN :lsfstartdate AND :lsfenddate ";
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
        }
        parent::where();
    }
    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $this->searchable = ["p.name", "c.fullname"];
            $statsql = [];
            foreach ($this->searchable as $key => $value) {
                $statsql[] = $DB->sql_like($value, "'%" . $this->search . "%'",
                $casesensitive = false, $accentsensitive = true, $notlike = false);
            }
            $fields = implode(" OR ", $statsql);
            $this->sql .= " AND ($fields) ";
        }
    }
    /**
     * Concat filter values to the query
     */
    public function filters() {
        if (!empty($this->params['filter_courses']) && $this->params['filter_courses'] <> SITEID  && !$this->scheduling) {
            $this->sql .= " AND cm.course IN (:filter_courses)";
        }
        if (isset($this->params['filter_users']) && $this->params['filter_users'] > 0) {
            $this->sql .= " AND l.userid IN (:filter_users)";
        }
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {
        global $CFG;
        if ($CFG->dbtype != 'sqlsrv') {
            $this->sql .= " GROUP BY p.id, cm.id, c.id, m.id ";
        }
    }
    /**
     * Get report rows
     * @param  array $elements Elements
     * @return array
     */
    public function get_rows($elements) {
        return $elements;
    }
}
