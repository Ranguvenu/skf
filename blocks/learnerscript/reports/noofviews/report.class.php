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
 * No. of views report
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
 * Number of views report class
 */
class report_noofviews extends reportbase implements report {

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
        parent::__construct($report);
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $this->columns = ['noofviews' => ['learner', 'views']];
        $this->courselevel = false;
        $this->basicparams = [['name' => 'courses'], ['name' => 'activities']];
        $this->parent = false;
        $this->orderable = [];
        $this->defaultcolumn = 'lsl.userid';
        $this->excludedroles = ["'student'"];
    }

    /**
     * Report initialization
     */
    public function init() {
        if (!isset($this->params['filter_courses'])) {
            $this->initial_basicparams('courses');
            $this->params['filter_courses'] = array_shift(array_keys($this->filterdata));
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
        $this->sql   = "SELECT COUNT(DISTINCT lsl.userid) ";
    }

    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT lsl.userid AS userid, COUNT(lsl.id) AS views";
        if (!empty($this->selectedcolumns) && in_array('learner', $this->selectedcolumns)) {
            $this->sql .= ", CONCAT(u.firstname, ' ', u.lastname) AS learner";
        }
    }

    /**
     * From SQL
     */
    public function from() {
        $this->sql .= " FROM {logstore_standard_log} lsl";
    }

    /**
     * JOINS SQL
     */
    public function joins() {
        $this->sql .= " JOIN {user} u ON u.id = lsl.userid";
    }

    /**
     * Adding condition to the query
     */
    public function where() {
        $learnersql  = (new querylib)->get_learners('', $this->params['filter_courses']);
        $this->params['confirmed'] = 1;
        $this->params['deleted'] = 0;
        $this->sql .= " WHERE lsl.crud = 'r' AND u.confirmed = :confirmed AND u.deleted = :deleted  ";
        if ($learnersql) {
            $this->sql .= " AND lsl.userid in ($learnersql)";
        }
        if ($this->lsstartdate > 0 && $this->lsenddate) {
            $this->sql .= " AND lsl.timecreated BETWEEN :lsfstartdate AND :lsfenddate ";
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
            $statsql = [];
            $this->searchable = ["CONCAT(u.firstname, ' ' , u.lastname)"];
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
        if (!empty($this->params['filter_activities'])) {
            $this->sql .= " AND lsl.contextinstanceid IN (:filter_activities) AND lsl.contextlevel = 70
            AND lsl.target = 'course_module'";
        }
        if (!empty($this->params['filter_courses']) && $this->params['filter_courses'] <> SITEID  && !$this->scheduling) {
            $this->sql .= " AND lsl.courseid IN (:filter_courses) ";
        }
    }

    /**
     * Concat groupby to sql
     */
    public function groupby() {
        $this->sql .= " GROUP BY lsl.userid, CONCAT(u.firstname, ' ', u.lastname) ";
    }

    /**
     * Get list of report data
     * @param  array $activites Activites
     * @return array
     */
    public function get_rows($activites) {
        return $activites;
    }
}
