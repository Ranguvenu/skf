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
use block_learnerscript\local\reportbase;
/**
 * Categories report
 */
class report_categories extends reportbase {
    /**
     * @var array $basicparamdata Basic params list
     */
    public $basicparamdata;
    /**
     * Report constructor
     * @param object $report           Report data
     * @param object $reportproperties Report properties
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = ['columns', 'conditions', 'filters', 'permissions', 'calcs', 'plot'];
        $this->columns = ['categoryfield' => ['categoryfield']];
        $this->courselevel = false;
        $this->parent = false;
        $this->defaultcolumn = 'id';
        $this->searchable = ["name", "description", "parent"];
        $this->excludedroles = ["'student'"];
    }
    /**
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(id)";
    }
    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT * ";
    }
    /**
     * FROM SQL
     */
    public function from() {
        $this->sql .= " FROM {course_categories}";
    }
    /**
     * Concat filter values to the query
     */
    public function filters() {

    }
    /**
     * SQL Where condition
     */
    public function where() {
        global $DB, $USER;
        $this->sql .= " WHERE 1 = 1 AND visible = :visible ";
        $this->params['visible'] = 1;
        if (!is_siteadmin()) {
            $categories = $DB->get_fieldset_sql("SELECT DISTINCT ct.instanceid
                FROM mdl_context as ct
                JOIN mdl_role_assignments as ra ON ra.contextid = ct.id
                WHERE ct.contextlevel = 40 AND ra.userid =". $USER->id );
            $usercategories = implode(',', $categories);
            if (!empty($usercategories)) {
                $this->sql .= " AND id IN ($usercategories) ";
            } else {
                $this->sql .= " AND id = 0 ";
            }
        }
        if ($this->conditionsenabled) {
            $conditions = implode(',', $this->conditionfinalelements);
            if (empty($conditions)) {
                return [[], 0];
            }
            $this->params['lsconditions'] = $conditions;
            $this->sql .= " AND id IN ($conditions)";
        }
        if ($this->lsstartdate > 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND timemodified BETWEEN :lsfstartdate AND :lsfenddate ";
        }
        parent::where();
    }
    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $this->searchable = ["name", "description"];
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
     * Concat group by to the query
     */
    public function groupby() {
    }
    /**
     * This function get the report rows
     * @param  array  $elements Elements list
     * @return array
     */
    public function get_rows($elements) {
        return $elements;
    }

}
