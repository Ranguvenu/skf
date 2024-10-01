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
 * Course competency report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\ls as ls;
use block_learnerscript\report;
/**
 * Competency Report
 */
class report_competency extends reportbase implements report {

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
        $columns = ['competency', 'framework', 'course'];
        $this->columns = ['competencycolumns' => $columns];
        $this->orderable = ['competency', 'completedusers'];
        $this->defaultcolumn = 'com.id';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Cometency report init function
     */
    public function init() {
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
     * COUNT SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT com.id) ";
    }
    /**
     * SELECT SQL
     */
    public function select() {
        $courseid = $this->params['filter_courses'];
        $this->sql = "SELECT DISTINCT com.id, com.shortname AS competency, comf.shortname AS framework  ";
        parent::select();
    }
    /**
     * FROM SQL
     */
    public function from() {
        $this->sql .= " FROM {competency} com ";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {competency_framework} comf ON comf.id = com.competencyframeworkid
";
        parent::joins();
    }
    /**
     * SQL Where condition
     */
    public function where() {
        $this->sql .= " WHERE 1 = 1 ";

        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND ccom.courseid IN ($this->rolewisecourses) ";
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
            $this->searchable = ["com.shortname", "comf.shortname"];
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
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {
        $this->sql .= " GROUP BY com.id, com.shortname, comf.shortname ";
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
