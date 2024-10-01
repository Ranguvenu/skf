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
 * A Moodle block to create customizable reports.
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
 * Generate Time spent report report
 */
class report_timespent extends reportbase implements report {

    /**
     * @var array $orderable
     */
    public $orderable;

    /**
     * Generate report
     * @param object $report
     */
    public function __construct($report) {
        parent::__construct($report);
        $this->parent = false;
        $this->courselevel = true;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $timespentcolumns = ['totaltimespent'];
        $this->columns = ['userfield' => ['userfield'] , 'timespentcolumns' => $timespentcolumns];
        $this->basicparams = [['name' => 'courses']];
        $this->orderable = ['totaltimespent', 'fullname', 'email'];
        $this->defaultcolumn = 'lcts.userid';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Report init
     */
    public function init() {
        if (!isset($this->params['filter_courses'])) {
            $this->initial_basicparams('courses');
            $filterdata = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($filterdata);
        }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
        $this->categoriesid = isset($this->params['filter_coursecategories']) ? $this->params['filter_coursecategories'] : 0;
    }
    /**
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT lcts.userid) ";
    }
    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT lcts.userid, lcts.courseid, CONCAT(u.firstname, ' ', u.lastname) AS fullname ";
        if (!empty($this->selectedcolumns)) {
            if (in_array('totaltimespent', $this->selectedcolumns)) {
                $this->sql .= ", SUM(lcts.timespent) AS totaltimespent";
            }
        }
        parent::select();
    }

    /**
     * Form SQL
     */
    public function from() {
        $this->sql .= " FROM {block_ls_coursetimestats} lcts";
    }

    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {user} u ON u.id = lcts.userid";
        parent::joins();
    }
    /**
     * Adding conditions to the query
     */
    public function where() {
        $learnersql  = (new querylib)->get_learners('', 'lcts.courseid');
        $this->sql .= " WHERE u.confirmed = 1 AND u.deleted = 0 AND u.id > 2";
        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND lcts.courseid IN ($this->rolewisecourses) ";
            }
        }
        $this->sql .= " AND lcts.userid in ($learnersql) ";
        parent::where();
    }
    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $this->searchable = ["CONCAT(u.firstname, ' ', u.lastname)", "u.email"];
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
     * Concat filters to the query
     */
    public function filters() {
        if (isset($this->params['filter_users']) && $this->params['filter_users'] > 0) {
            $this->sql .= " AND lcts.userid IN (:filter_users)";
        }
        if (!empty($this->params['filter_courses']) && $this->params['filter_courses'] <> SITEID  && !$this->scheduling) {
            $this->sql .= " AND lcts.courseid IN (:filter_courses)";
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND lcts.timemodified BETWEEN :lsfstartdate AND :lsfenddate ";
        }
    }
    /**
     * Concat groupby to SQL
     */
    public function groupby() {
        $this->sql .= " GROUP BY lcts.userid, lcts.courseid, u.firstname, u.lastname";
    }
    /**
     * get rows
     * @param  array $elements
     * @return array
     */
    public function get_rows($elements) {
        return $elements;
    }
}
