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
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls as ls;
/**
 * Resources report
 */
class report_resources extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;
    /** @var array $resourcenames  */
    private $resourcenames;
    /** @var array $resourceslist  */
    private $resourceslist;
    /** @var array $aliases  */
    private $aliases = [];
    /** @var array $aliases1  */
    private $aliases1 = [];
    /**
     * Constructor for report
     * @param object $report  Reportdata
     * @param object $reportproperties Report properties
     */
    public function __construct($report, $reportproperties = false) {
        parent::__construct($report, $reportproperties);
        $this->parent = false;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $resourcescolumns = ['activity', 'totaltimespent', 'numviews'];
        $this->columns = ['activityfield' => ['activityfield'] , 'resourcescolumns' => $resourcescolumns];
        $this->basicparams = [['name' => 'courses']];
        $this->courselevel = true;
        $this->orderable = ['course', 'activity', 'totaltimespent'];
        $this->defaultcolumn = 'main.id';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Resources report init function
     */
    public function init() {
        global $DB;
        $modules = $DB->get_fieldset_select('modules',  'name', '');
        $this->aliases = [];
        foreach ($modules as $modulename) {
            $resourcearchetype = plugin_supports('mod', $modulename, FEATURE_MOD_ARCHETYPE);
            if ($resourcearchetype) {
                $this->aliases[] = $modulename;
                $resources[] = "'$modulename'";
                $fields1[] = "COALESCE($modulename.name,'')";
            }
        }
        $this->resourcenames = implode(',', $fields1);
        $this->resourceslist = implode(',', $resources);
        $this->params['siteid'] = SITEID;
        $this->params['target'] = 'course_module';
        $this->params['contextlevel'] = CONTEXT_MODULE;
        $this->params['action'] = 'viewed';
    }
    /**
     * COUNT SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(main.id) ";
    }
    /**
     * SELECT SQL
     */
    public function select() {
        $this->sql = "SELECT main.id, c.id as course,
                        c.fullname as courseid, m.name as moduletype, m.id as module,
                        CONCAT($this->resourcenames) as activity, main.visible as status";
        parent::select();
    }
    /**
     * FROM SQL
     */
    public function from() {
        $this->sql .= " FROM {course_modules} main";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {modules} m ON m.id = main.module
                       JOIN {course} c ON c.id = main.course ";
        foreach ($this->aliases as $alias) {
            $this->sql .= " LEFT JOIN {".$alias."} $alias ON $alias.id = main.instance AND m.name = '$alias'";
        }
        parent::joins();
    }
    /**
     * SQL WHERE condition
     */
    public function where() {
        $this->sql .= " WHERE c.visible = 1 AND c.id <> :siteid AND main.deletioninprogress = 0
        AND m.name IN ($this->resourceslist) AND main.visible = 1 ";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND main.course IN ($this->rolewisecourses) ";
            }
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->sql .= " AND main.added BETWEEN :lsfstartdate AND :lsfenddate ";
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
        $modules = $DB->get_fieldset_select('modules',  'name', '');
        $this->aliases1 = [];
        foreach ($modules as $modulename) {
            $resourcearchetype = plugin_supports('mod', $modulename, FEATURE_MOD_ARCHETYPE);
            if ($resourcearchetype) {
                $this->aliases1[] = $modulename;
                $fields2[] = "COALESCE($modulename.name,'')";
            }
        }
        if (isset($this->search) && $this->search) {
            $this->searchable = array_push($fields2, "c.fullname");
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
            $this->sql .= " AND c.id IN (:filter_courses) ";
        }
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {

    }
    /**
     * Get report rows
     * @param array $elements Elements
     * @return array
     */
    public function get_rows($elements) {
        return $elements;
    }
    /**
     * This function returns the report columns queries
     * @param  string  $columnname Column names
     * @param  int  $coursemoduleid Coursemodule id
     * @param  string  $courses Courses list
     * @return string
     */
    public function column_queries($columnname, $coursemoduleid, $courses = null) {
        if ($courses) {
            $learnersql  = (new querylib)->get_learners('', $courses);
        } else {
            $learnersql  = (new querylib)->get_learners('', '%courses%');
        }
        $where = " AND %placeholder% = $coursemoduleid";
        switch ($columnname) {
            case 'totaltimespent':
                $identy = 'cm.id';
                $courses = 'mt.courseid';
                $query = "SELECT SUM(mt.timespent)
                             FROM {block_ls_modtimestats} mt
                             JOIN {course_modules} cm ON cm.id = mt.activityid
                             WHERE 1 = 1 AND mt.userid IN ($learnersql)
                            $where ";
            break;
            case 'numviews':
                $identy = 'lsl.contextinstanceid';
                $courses = 'lsl.courseid';
                if ($this->reporttype == 'table') {
                    $query = "SELECT COUNT(DISTINCT lsl.userid) as distinctusers, COUNT('X') as numviews
                                  FROM {logstore_standard_log} lsl
                                  JOIN {user} u ON u.id = lsl.userid
                                  JOIN {course_modules} cm ON lsl.contextinstanceid = cm.id
                                 WHERE lsl.crud = 'r' AND lsl.contextlevel = 70  AND lsl.anonymous = 0 AND u.id IN ($learnersql)
                                   AND lsl.userid > 2  AND u.confirmed = 1 AND u.deleted = 0  AND lsl.anonymous = 0
                                   AND lsl.target = 'course_module'
                                   $where ";
                } else {
                    $query = "SELECT COUNT('X') as numviews
                                  FROM {logstore_standard_log} lsl
                                  JOIN {user} u ON u.id = lsl.userid
                                 JOIN {course_modules} cm ON lsl.contextinstanceid = cm.id
                                 WHERE  lsl.crud = 'r' AND lsl.contextlevel = 70 AND lsl.userid > 2
                                 AND u.id IN ($learnersql) AND lsl.anonymous = 0 AND u.confirmed = 1
                                 AND lsl.target = 'course_module'
                                 AND u.deleted = 0  $where";
                }

            break;
            default:
                return false;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        $query = str_replace('%courses%', $courses, $query);
        return $query;
    }
}
