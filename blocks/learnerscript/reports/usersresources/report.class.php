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
 * Course Users resources report
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
 * Users resources report class
 */
class report_usersresources extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /**
     * Report constructor
     * @param object $report Report object
     */
    public function __construct($report) {
        parent::__construct($report);
        $this->parent = false;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $resourcescolumns = ['totalresources', 'totaltimespent', 'numviews'];
        $this->columns = ['userfield' => ['userfield'] , 'usersresources' => $resourcescolumns];
        $this->basicparams = [['name' => 'courses']];
        $this->filters = ['users'];
        $this->courselevel = true;
        $this->orderable = ['fullname', 'totalresources', 'totaltimespent'];
        $this->searchable = ["CONCAT(u.firstname, ' ', u.lastname)", "u.email"];
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Report init
     */
    public function init() {
        if (!isset($this->params['filter_courses'])) {
            $this->initial_basicparams('courses');
            $fcourses = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($fcourses);
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
        $this->sql = "SELECT count(DISTINCT u.id)";
    }
    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT u.id, u.email, CONCAT(u.firstname,' ', u.lastname) AS fullname ";
        parent::select();
    }

    /**
     * Form SQL
     */
    public function from() {
        $this->sql .= " FROM {user} u
                        JOIN {role_assignments} ra ON ra.userid = u.id
                        JOIN {context} con ON ra.contextid = con.id
                        JOIN {course} c ON c.id = con.instanceid                        
                        JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'";
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
        global $DB;
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->params['studentroleid'] = $studentroleid;
        $this->sql .= " WHERE ra.roleid = :studentroleid AND ra.contextid = con.id
                        AND u.confirmed = 1 AND u.deleted = 0 ";
        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND c.id IN ($this->rolewisecourses) ";
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
        $userid = isset($this->params['filter_users']) ? $this->params['filter_users'] : [];
        if (!empty($userid) && $userid != '_qf__force_multiselect_submission') {
            is_array($userid) ? $userid = implode(',', $userid) : $userid;
            $this->params['userid'] = $userid;
            $this->sql .= " AND u.id IN (:userid)";
        }
        if ($this->params['filter_courses'] > SITEID) {
            $this->sql .= " AND c.id IN (:filter_courses)";
        }
    }
    /**
     * Concat groupby to SQL
     */
    public function groupby() {

    }

    /**
     * get rows
     * @param  array $elements users
     * @return array
     */
    public function get_rows($elements) {
        return $elements;
    }
    /**
     * Columns quesries
     * @param  array $columnname Columns names
     * @param  int $userid User id
     * @return string
     */
    public function column_queries($columnname, $userid) {
        global $DB;
        $coursesql  = (new querylib)->get_learners($userid, '');
        $where = " AND %placeholder% = $userid";
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $where  .= " AND %placeholder2% BETWEEN $this->lsstartdate AND $this->lsenddate ";
        }

        $modules = $DB->get_fieldset_select('modules',  'name', '');
        foreach ($modules as $modulename) {
            $resourcearchetype = plugin_supports('mod', $modulename, FEATURE_MOD_ARCHETYPE);
            if ($resourcearchetype) {
                $resources[] = "'$modulename'";
            }
        }
        $imploderesources = implode(', ', $resources);

        $filtercourseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : SITEID;
        switch ($columnname) {
            case 'totalresources':
                $identy = 'ra.userid';
                $identy2 = 'ra.timemodified';
                $query = "SELECT COUNT(DISTINCT cm.id) AS totalscorms
                            FROM {course} AS c
                            JOIN {context} AS con ON con.contextlevel = 50 AND c.id = con.instanceid
                            JOIN {role_assignments} AS ra ON ra.contextid = con.id AND ra.roleid = 5
                            JOIN {course_modules} AS cm ON cm.course = c.id
                            JOIN {modules} AS m ON m.id = cm.module
                            WHERE con.id = ra.contextid AND cm.visible = 1 AND cm.deletioninprogress = 0 AND c.visible = 1
                            AND m.name IN ($imploderesources) AND c.id = $filtercourseid $where ";
            break;
            case 'totaltimespent':
                $identy = 'mt.userid';
                $identy2 = 'mt.timemodified';
                $query = "SELECT SUM(mt.timespent) AS totaltimespent
                            FROM {block_ls_modtimestats} AS mt
                            JOIN {course_modules} cm ON cm.id = mt.activityid
                            JOIN {modules} m ON m.id = cm.module WHERE m.name IN ($imploderesources)
                                AND mt.courseid = $filtercourseid AND mt.activityid != $filtercourseid $where ";
            break;
            case 'numviews':
                $identy = 'lsl.userid';
                $identy2 = 'lsl.timecreated';
                $query = "SELECT COUNT(lsl.userid) AS distinctusers
                            FROM {logstore_standard_log} lsl
                            JOIN {course_modules} cm ON cm.id = lsl.contextinstanceid
                            JOIN {modules} m ON m.id = cm.module
                            WHERE m.name IN ($imploderesources) AND lsl.crud = 'r'
                            AND lsl.contextlevel = 70 AND lsl.anonymous = 0
                            AND lsl.courseid = $filtercourseid
                            AND lsl.target = 'course_module' $where ";
            break;

            default:
            return false;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        $query = str_replace('%placeholder2%', $identy2, $query);
        return $query;
    }
}
