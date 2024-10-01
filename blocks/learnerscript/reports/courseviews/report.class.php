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
 * Courses View report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

/**
 * Course views report data class
 */
class report_courseviews extends reportbase implements report {

    /** @var array $searchable  */
    public $searchable;

    /** @var array $orderable  */
    public $orderable;

    /** @var array $excludedroles  */
    public $excludedroles;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /**
     * Report constructor
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->components = ['columns', 'filters', 'permissions', 'calcs', 'plot'];
        $this->columns = ['courseviews' => ['learner', 'views']];
        $this->courselevel = true;
        $this->basicparams = [['name' => 'courses']];
        $this->parent = false;
        $this->orderable = ['learner', 'views'];
        $this->defaultcolumn = 'lsl.userid';
        $this->excludedroles = ["'student'"];
    }

    /**
     * Report initialization
     */
    public function init() {
        if (!isset($this->params['filter_courses'])) {
            $this->initial_basicparams('courses');
            $coursedata = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($coursedata);
        }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
        $this->courseid = $this->params['filter_courses'];
    }

    /**
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT lsl.userid) ";
    }

    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT lsl.userid AS userid, COUNT(lsl.id) AS views";
        if (!empty($this->selectedcolumns) && in_array('learner', $this->selectedcolumns)) {
            $this->sql .= ", CONCAT(u.firstname, ' ', u.lastname) AS learner";
        }
    }

    /**
     * Form SQL
     */
    public function from() {
        $this->sql .= " FROM {logstore_standard_log} lsl";
    }

    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {user} u ON u.id = lsl.userid";
        parent::joins();
    }

    /**
     * Adding conditions to the query
     */
    public function where() {
        $learnersql  = (new querylib)->get_learners('', $this->courseid);
        $this->sql .= " WHERE lsl.crud = 'r' AND lsl.userid > 2 AND u.confirmed = 1
                         AND u.deleted = :deleted AND lsl.courseid = :courseid
                         ";
        if ($learnersql) {
            $this->sql .= " AND lsl.userid IN ($learnersql)";
        }
        $this->params['deleted'] = 0;
        $this->params['courseid'] = $this->courseid;
        if ($this->lsstartdate > 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND lsl.timecreated BETWEEN :lsfstartdate AND :lsfenddate ";
        }
        parent::where();
    }

    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $this->searchable = ["CONCAT(u.firstname, ' ', u.lastname)"];
            $statsql = [];
            foreach ($this->searchable as $value) {
                $statsql[] = $DB->sql_like($value, "'%" . $this->search . "%'", false,
                                true, false);
            }
            $fields = implode(" OR ", $statsql);
            $this->sql .= " AND ($fields) ";
        }
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
        $this->sql .= " GROUP BY lsl.userid, u.firstname, u.lastname";
    }

    /**
     * This function get the report rows data
     * @param  array $activites Activites
     * @return array
     */
    public function get_rows($activites) {
        return $activites;
    }
}
