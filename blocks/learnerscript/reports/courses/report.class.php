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
 * Courses report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\querylib;
use context_system;

/**
 * Courses report class
 */
class report_courses extends reportbase implements report {

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
        global $DB;
        parent::__construct($report, $reportproperties);
        $coursecolumns = $DB->get_columns('course');
        $usercolumns = $DB->get_columns('user');
        $columns = ['enrolments', 'completed', 'activities', 'competencies', 'progress', 'avggrade',
                    'highgrade', 'lowgrade', 'badges', 'totaltimespent', 'numviews', ];
        $this->columns = ['coursefield' => ['coursefield'] ,
                          'coursescolumns' => $columns, ];
        $this->conditions = ['courses' => array_keys($coursecolumns),
                             'user' => array_keys($usercolumns), ];
        $this->components = ['columns', 'conditions', 'filters', 'permissions', 'plot'];
        $this->filters = ['coursecategories', 'courses'];
        $this->parent = true;
        $this->orderable = ['enrolments', 'completed', 'activities', 'competencies', 'avggrade',
                        'progress', 'highgrade', 'lowgrade', 'badges', 'totaltimespent', 'fullname', 'numviews', ];

        $this->searchable = ['main.fullname', 'cat.name'];
        $this->defaultcolumn = 'main.id';
        $this->excludedroles = ["'student'"];
    }

    /**
     * Report initialization
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
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT main.id)";
    }

    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT main.id, main.*, main.id AS course ";
        parent::select();
    }

    /**
     * Form SQL
     */
    public function from() {
        $this->sql .= " FROM {course} main JOIN {course_categories} cat ON main.category = cat.id";
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
        $context = context_system::instance();
        $this->sql .= " WHERE main.visible = :visible AND main.id <> :siteid ";
        $this->params['visible'] = 1;
        if (!is_siteadmin($this->userid) && !has_capability('block/learnerscript:managereports', $context)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND main.id IN ($this->rolewisecourses) ";
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
        if (!empty($this->params['filter_courses']) && $this->params['filter_courses'] <> SITEID  && !$this->scheduling) {
            $this->sql .= " AND main.id IN (:filter_courses) ";
        }
        if (!empty($this->params['filter_coursecategories'])) {
            $this->sql .= " AND main.category IN (:filter_coursecategories) ";
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND main.timecreated BETWEEN :lsfstartdate AND :lsfenddate ";
        }
        if ($this->conditionsenabled) {
            $conditions = implode(',', $this->conditionfinalelements);
            if (empty($conditions)) {
                return [[], 0];
            }
            $this->params['lsconditions'] = $conditions;
            $this->sql .= " AND main.id IN ( :lsconditions )";
        }
    }

    /**
     * Concat groupby to SQL
     */
    public function groupby() {

    }

    /**
     * This function get the report rows
     * @param  array  $courses Courses data
     * @return array
     */
    public function get_rows($courses) {
        return $courses;
    }

    /**
     * This function gets the report column queries
     * @param  string  $columnname Column name
     * @param int $courseid Courseid
     * @param string $courses Courses
     * @return string
     */
    public function column_queries($columnname, $courseid, $courses = null) {
        if ($courses) {
            $learnersql  = (new querylib)->get_learners('', $courses);
        } else {
            $learnersql  = (new querylib)->get_learners('', '%courseid%');
        }
        $where = " AND %placeholder% = $courseid";
        $query = " ";
        $identity = " ";
        switch ($columnname) {
            case 'activities':
                $identity = 'course';
                $query  = "SELECT COUNT(id) AS activities FROM {course_modules} WHERE 1 = 1 AND visible = 1
                            AND deletioninprogress = 0 $where ";
            break;
            case 'enrolments':
                $identity = 'ct.instanceid';
                $query  = "SELECT COUNT(DISTINCT ra.id) AS enrolled
                                     FROM {role_assignments} ra
                                     JOIN {context} ct ON ct.id = ra.contextid
                                     JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'
                                     JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1 AND u.deleted = 0
                                     AND u.suspended = 0
                                    WHERE 1 = 1 $where ";
            break;
            case 'completed':
                $identity = 'ct.instanceid';
                $query = "SELECT COUNT(DISTINCT cc.userid) AS completed
                                     FROM {role_assignments} ra
                                     JOIN {context} ct ON ct.id = ra.contextid
                                     JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'
                                     JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1 AND u.deleted = 0
                                     AND u.suspended = 0
                                     JOIN {course_completions} cc ON cc.course = ct.instanceid AND cc.timecompleted > 0
                                     AND cc.userid = ra.userid
                                    WHERE 1 = 1 $where ";
            break;
            case 'highgrade':
                $identity = 'gi.courseid';
                $query = "SELECT MAX(finalgrade) AS highgrade
                          FROM {grade_grades} g
                          JOIN {grade_items} gi ON gi.itemtype = 'course' AND g.itemid = gi.id
                         WHERE g.finalgrade IS NOT NULL AND g.userid IN ($learnersql) $where ";
            break;
            case 'lowgrade':
                $identity = 'gi.courseid';
                $query = "SELECT MIN(finalgrade) AS lowgrade
                          FROM {grade_grades} g
                          JOIN {grade_items} gi ON gi.itemtype = 'course' AND g.itemid = gi.id
                         WHERE g.finalgrade IS NOT NULL AND g.userid IN ($learnersql) $where ";
            break;
            case 'avggrade':
                $identity = 'gi.courseid';
                $query = "SELECT AVG(finalgrade) AS avggrade
                          FROM {grade_grades} g
                          JOIN {grade_items} gi ON gi.itemtype = 'course' AND g.itemid = gi.id
                         WHERE g.finalgrade IS NOT NULL AND g.userid IN ($learnersql) $where ";
            break;
            case 'badges':
                $identity = 'b.courseid';
                $query = "SELECT COUNT(b.id) AS badges  FROM {badge} b WHERE b.status != 0  AND b.status != 2 $where ";
            break;
            case 'totaltimespent':
                $identity = 'bt.courseid';
                $query = "SELECT SUM(bt.timespent) AS totaltimespent FROM {block_ls_coursetimestats} bt
                           WHERE 1 = 1 AND bt.userid IN ($learnersql) $where ";
            break;
            case 'competencies':
                $identity = 'ccom.courseid';
                $query = " SELECT COUNT(ccom.id)
                            FROM {competency_coursecomp} ccom
                            WHERE 1 = 1 $where ";
            break;
        }
        $query = str_replace('%placeholder%', $identity, $query);
        $query = str_replace('%courseid%', $identity, $query);
        return $query;
    }
}
