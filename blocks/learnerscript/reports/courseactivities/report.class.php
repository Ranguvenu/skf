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
use block_learnerscript\report;

/**
 * Course activities report class
 */
class report_courseactivities extends reportbase implements report {

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
        parent::__construct($report, $reportproperties);
        $this->columns = ['activityfield' => ['activityfield'],
                               'courseactivitiescolumns' => ['activityname', 'learnerscompleted', 'grademax',
                               'gradepass', 'averagegrade', 'highestgrade', 'lowestgrade', 'progress', 'totaltimespent',
                               'numviews', 'grades', ], ];
        $this->parent = false;
        $this->basicparams = [['name' => 'courses']];
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $this->courselevel = true;
        $this->orderable = ['activityname', 'learnerscompleted', 'grademax', 'gradepass', 'averagegrade', 'highestgrade',
                            'lowestgrade', 'totaltimespent', ];
        $this->defaultcolumn = 'main.id';
        $this->excludedroles = ["'student'"];
    }

    /**
     * Count SQL
     */
    public function count() {
        global $DB;
        $this->sql = "SELECT COUNT(DISTINCT main.id) ";
    }

    /**
     * Select SQL
     */
    public function select() {
        global $DB;
        $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
        foreach ($modules as $modulename) {
            $fields1[] = "COALESCE($modulename.name,'')";
        }
        $activitynames = implode(',', $fields1);
        $this->sql = " SELECT DISTINCT(main.id) , m.id AS moduleid, main.instance,
                                main.course";
        $this->sql .= ", CONCAT($activitynames) AS activityname";
        if (!empty($this->selectedcolumns)) {
            if (in_array('grades', $this->selectedcolumns)) {
                $this->sql .= ", 'Grades'";
            }
        }
        parent::select();
    }

    /**
     * Form SQL
     */
    public function from() {
        $this->sql .= " FROM {course_modules} main
                       JOIN {modules} m ON main.module = m.id";
    }

    /**
     * SQL JOINS
     */
    public function joins() {
        global $DB;
        parent::joins();
        $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
        foreach ($modules as $modulename) {
            $aliases[] = $modulename;
        }
        foreach ($aliases as $alias) {
            $this->sql .= " LEFT JOIN {".$alias."} $alias ON $alias.id = main.instance AND m.name = '$alias'";
        }
         $this->sql .= " LEFT JOIN {grade_items} gi ON gi.itemmodule = m.name
                       AND gi.courseid = main.course AND gi.iteminstance = main.instance ";

    }

    /**
     * Adding conditions to the query
     */
    public function where() {
        global $DB;
        $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
        foreach ($modules as $modulename) {
            $activities[] = "'$modulename'";
        }
        $activitynames = implode(',', $activities);
        $this->sql .= " WHERE m.visible = 1 AND m.name IN ($activitynames) AND main.visible = 1
                        AND main.deletioninprogress = 0";
        if ($this->lsstartdate > 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND main.added BETWEEN :lsfstartdate AND :lsfenddate ";
        }
        parent::where();
    }

    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $modules = $DB->get_fieldset_select('modules',  'name', '', ['visible' => 1]);
            foreach ($modules as $modulename) {
                $fields1[] = "COALESCE($modulename.name,'')";
            }
            $fields2 = ['m.name'];
            $this->searchable = array_merge($fields1, $fields2);
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
        if (!isset($this->params['filter_courses'])) {
            $this->initial_basicparams('courses');
            $filterdata = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($filterdata);
        }
        if ($this->params['filter_courses'] > SITEID) {
             $this->sql .= " AND main.course = :filter_courses";
        }
        if (isset($this->params['filter_modules']) && $this->params['filter_modules'] > 0) {
            $this->sql .= " AND main.module = :filter_modules";
        }
        if (isset($this->params['filter_activities']) && $this->params['filter_activities'] > 0) {
            $this->sql .= " AND main.id = :filter_activities";
        }
    }

    /**
     * Concat groupby to SQL
     */
    public function groupby() {

    }

    /**
     * This function get the report rows
     * @param  array  $activites Activities list
     * @return array
     */
    public function get_rows($activites = []) {
        return $activites;
    }

    /**
     * This function returns the report columns queries
     * @param  string  $column Column names
     * @param  int  $activityid Activity id
     * @param  string  $courses Courses list
     * @return string
     */
    public function column_queries($column, $activityid, $courses = null) {
        global $CFG;
        if ($courses) {
            $learnersql  = (new querylib)->get_learners('', $courses);
        } else {
            $learnersql  = (new querylib)->get_learners('', '%courseid%');
        }
        $courseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : SITEID;
        $where = " AND %placeholder% = $activityid";
        $query = " ";
        $identity = " ";
        switch ($column) {
            case 'grademax':
                $identity = 'cm1.id';
                $query = "SELECT gi.grademax AS grademax
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id AND gi.itemtype = 'mod'
                            JOIN {course_modules} cm1 ON cm1.instance = gi.iteminstance
                            JOIN {modules} m ON m.id = cm1.module
                            JOIN {course_sections} csc ON csc.id = cm1.section
                           WHERE cm1.course = $courseid AND m.name = gi.itemmodule
                            $where GROUP BY cm1.id, gi.grademax LIMIT 1
                            ";
                break;
            case 'gradepass':
                $identity = 'cm1.id';
                $query = "SELECT gi.gradepass AS gradepass
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id AND gi.itemtype = 'mod'
                            JOIN {course_modules} cm1 ON cm1.instance = gi.iteminstance
                            JOIN {modules} m ON m.id = cm1.module
                            JOIN {course_sections} csc ON csc.id = cm1.section
                           WHERE cm1.course = $courseid AND m.name = gi.itemmodule
                            $where GROUP BY cm1.id, gi.gradepass LIMIT 1";
                break;
            case 'learnerscompleted':
                $identity = 'cm.id';
                $courses = 'cm.course';
                $query = " SELECT COUNT(cmc.id) AS learnerscompleted
                            FROM {course_modules_completion} cmc
                            JOIN {course_modules} cm ON cmc.coursemoduleid = cm.id
                           WHERE cmc.completionstate > 0 AND cmc.userid > 2 AND cmc.userid IN ($learnersql)
                                 $where ";
                break;
            case 'highestgrade':
                $identity = 'cm1.id';
                $query = "SELECT MAX(finalgrade) AS highestgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id AND gi.itemtype = 'mod'
                            JOIN {course_modules} cm1 ON cm1.instance = gi.iteminstance
                            JOIN {modules} m ON m.id = cm1.module
                            JOIN {course_sections} csc ON csc.id = cm1.section
                           WHERE cm1.course = $courseid AND m.name = gi.itemmodule
                            $where ";
                break;
            case 'averagegrade':
                 $identity = 'cm1.id';
                 $query = "SELECT AVG(finalgrade) AS averagegrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id AND gi.itemtype = 'mod'
                            JOIN {course_modules} cm1 ON cm1.instance = gi.iteminstance
                            JOIN {modules} m ON m.id = cm1.module
                            JOIN {course_sections} csc ON csc.id = cm1.section
                            WHERE cm1.course = $courseid AND m.name = gi.itemmodule $where ";
                break;
            case 'lowestgrade':
                 $identity = 'cm1.id';
                 $query = "SELECT MIN(finalgrade) AS lowestgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id AND gi.itemtype = 'mod'
                            JOIN {course_modules} cm1 ON cm1.instance = gi.iteminstance
                            JOIN {modules} m ON m.id = cm1.module
                            JOIN {course_sections} csc ON csc.id = cm1.section
                           WHERE cm1.course = $courseid AND m.name = gi.itemmodule $where ";
                break;
            case 'progress':
                 $identity = 'cm.id';
                 $courses = 'cm.course';
                  $query = "SELECT CASE WHEN total = 0 THEN 0 ELSE ((completed / total )* 100) END AS progress
                            FROM (SELECT COUNT(cmc.id) as completed
                            FROM {course_modules_completion} cmc
                            JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                            WHERE cmc.completionstate > 0 AND cmc.userid IN ($learnersql) $where ) AS completed,
                            (SELECT count(DISTINCT u.id) as total FROM {user} u
                            JOIN {role_assignments} ra ON ra.userid = u.id
                            JOIN {context} ctx ON ctx.id = ra.contextid
                            JOIN {course} c ON c.id = ctx.instanceid
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                            JOIN {course_modules} cm ON cm.course = c.id
                           WHERE ra.userid IN ($learnersql) $where ) as total";
                break;
            case 'totaltimespent':
                $identity = 'mt.activityid';
                $courses = 'mt.courseid';
                $query = "SELECT SUM(mt.timespent) AS totaltimespent
                          FROM {block_ls_modtimestats} mt
                         WHERE mt.courseid = $courseid AND mt.userid IN ($learnersql) $where ";
            break;
            case 'numviews':
                $identity = 'lsl.contextinstanceid';
                $courses = 'lsl.courseid';
                if ($this->reporttype == 'table') {
                    $query = "SELECT COUNT(DISTINCT lsl.userid)  AS distinctusers, COUNT('X') AS numviews
                                                FROM {logstore_standard_log} lsl
                                                JOIN {user} u ON u.id = lsl.userid
                                               WHERE lsl.crud = 'r' AND lsl.contextlevel = 70
                                                 AND lsl.courseid = $courseid AND lsl.anonymous = 0
                                                 AND u.confirmed = 1 AND u.deleted = 0 AND lsl.target = 'course_module'
                                                 AND lsl.userid IN ($learnersql) $where ";
                } else {
                    $query = "SELECT COUNT('X') AS numviews
                                FROM {logstore_standard_log} lsl JOIN {user} u ON u.id = lsl.userid
                               WHERE lsl.crud = 'r' AND lsl.contextlevel = 70 AND lsl.courseid = $courseid
                                 AND lsl.anonymous = 0 AND lsl.userid IN ($learnersql) AND u.confirmed = 1
                                 AND u.deleted = 0 lsl.target = 'course_module' AND $where";
                }
            break;
        }

        $query = str_replace('%placeholder%', $identity, $query);
        $query = str_replace('%courseid%', $courses, $query);
        return $query;
    }
}
