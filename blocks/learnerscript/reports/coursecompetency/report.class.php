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
 * Course Competency report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use context_system;

/**
 * Course competency report class
 */
class report_coursecompetency extends reportbase implements report {

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
     * @param object $report           Report data
     * @param object $reportproperties Report properties
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = false;
        $this->courselevel = true;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $columns = ['competency', 'framework', 'activity', 'completedusers'];
        $this->columns = ['coursecompetency' => $columns];
        $this->basicparams = [['name' => 'courses']];
        $this->orderable = ['competency', 'completedusers'];
        $this->defaultcolumn = 'com.id';
        $this->excludedroles = ["'student'"];
    }

    /**
     * Report initialization
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
        $this->sql = "SELECT COUNT(DISTINCT com.id) ";
    }

    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT com.id, com.shortname AS competency, comf.shortname AS framework,
                        ccom.courseid, (SELECT COUNT(u.id) FROM {user} u
                                WHERE u.id = ucom.userid AND u.confirmed = 1
                                AND u.deleted = 0 AND u.suspended = 0) AS completedusers ";
        parent::select();
    }

    /**
     * Form SQL
     */
    public function from() {
        $this->sql .= " FROM {competency} com ";
    }

    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {competency_framework} comf ON comf.id = com.competencyframeworkid
                        JOIN {competency_coursecomp} ccom ON ccom.competencyid = com.id
                        LEFT JOIN {competency_usercompcourse} ucom ON ucom.competencyid = com.id AND ucom.courseid = ccom.courseid
                        AND ucom.proficiency IS NOT NULL";
        parent::joins();
    }

    /**
     * Adding conditions to the query
     */
    public function where() {
        $this->sql .= " WHERE 1 = 1 ";

        $context = context_system::instance();
        if ((!is_siteadmin() || $this->scheduling) && !has_capability('block/learnerscript:managereports', $context)) {
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
            $this->searchable = ["CONCAT(u.firstname, ' ', u.lastname)", "u.email", "com.shortname",
                                "comf.shortname", ];
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
        if ($this->params['filter_courses'] > SITEID) {
            $this->sql .= " AND ccom.courseid IN (:filter_courses)";
        }
    }

    /**
     * Concat groupby to SQL
     */
    public function groupby() {
          $this->sql .= " GROUP BY com.id, com.shortname, comf.id, comf.shortname, ccom.courseid, ucom.userid ";
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
