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
 * Grade activity report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;
/**
 * Grade activity report
 */
class report_gradedactivity extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /** @var int $categoriesid  */
    public $categoriesid;
    /**
     * Constructor for report.
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = ['columns', 'filters', 'permissions', 'calcs', 'plot'];
        $columns = ['modulename', 'highestgrade', 'averagegrade', 'lowestgrade', 'totaltimespent', 'numviews'];
        $this->columns = ['activityfield' => ['activityfield'], 'gradedactivity' => $columns];
        $this->courselevel = true;
        $this->basicparams = [['name' => 'courses']];
        $this->filters = ['modules', 'activities'];
        $this->parent = false;
        $this->orderable = ['modulename', 'highestgrade', 'averagegrade', 'lowestgrade', 'totaltimespent', 'course'];
        $this->defaultcolumn = 'main.id';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Grade activity report ini function
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
        $this->categoriesid = isset($this->params['filter_coursecategories']) ? $this->params['filter_coursecategories'] : 0;
    }
    /**
     * Count SQL
     */
    public function count() {
        $this->sql   = "SELECT count(DISTINCT main.id) ";
    }
    /**
     * Select SQL
     */
    public function select() {
        $this->sql  = "SELECT main.id, cm.id as activityid, m.id as module, main.itemname as modulename, main.courseid";
        parent::select();
    }
    /**
     * SQL FROM
     */
    public function from() {
        $this->sql .= " FROM {grade_items} main";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        parent::joins();
        $this->sql .= "  JOIN {course_modules} cm ON cm.instance = main.iteminstance AND main.itemtype = 'mod'
                         JOIN {modules} m ON m.id = cm.module
                         JOIN {course} co ON co.id = cm.course";
    }
    /**
     * SQL Where condition
     */
    public function where() {
        global $DB;
        $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
        foreach ($modules as $modulename) {
            $activities[] = "'$modulename'";
        }
        $activitieslist = implode(',', $activities);

        $this->sql .= " WHERE co.visible = 1 AND cm.visible = 1
        AND m.name IN ($activitieslist) AND m.visible = 1 AND m.name = main.itemmodule";

        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND cm.added BETWEEN :lsfstartdate AND :lsfenddate ";
        }

        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND cm.course IN ($this->rolewisecourses) ";
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
            $this->searchable = ['main.itemname', 'co.fullname'];
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
            $this->sql .= " AND cm.course IN (:filter_courses) ";
        }
        if (!empty($this->params['filter_modules'])) {
            $this->sql .= " AND m.id IN (:filter_modules) ";
        }
        if (!empty($this->params['filter_activities'])) {
            $this->sql .= " AND cm.id IN (:filter_activities) ";
        }
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {
    }
    /**
     * Get rows
     * @param  array $activites Activites
     * @return array
     */
    public function get_rows($activites) {
        return $activites;
    }
    /**
     * This function returns the report columns queries
     * @param  string  $column Column names
     * @param  int  $activityid Activity id
     * @param  int $courseid Courseid
     * @return string
     */
    public function column_queries($column, $activityid, $courseid = null) {
        if ($courseid) {
            $learnersql  = (new querylib)->get_learners('', $courseid);
        } else {
            $learnersql  = (new querylib)->get_learners('', '%courseid%');
        }
        $where = " AND %placeholder% = $activityid";
        $filtercourseids = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : SITEID;
        switch ($column) {
            case 'highestgrade':
                $identy = 'gi.id';
                $query = "SELECT MAX(finalgrade) as highestgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id AND gi.itemtype = 'mod'
                            JOIN {modules} m ON gi.itemmodule = m.name
                            WHERE 1 = 1 AND gi.courseid IN ($filtercourseids) $where";
                break;
            case 'averagegrade':
                 $identy = 'gi.id';
                 $query = "SELECT AVG(finalgrade) as averagegrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id AND gi.itemtype = 'mod'
                            JOIN {modules} m ON gi.itemmodule = m.name
                            WHERE 1 = 1 AND gi.courseid IN ($filtercourseids) $where";
                break;
            case 'lowestgrade':
                 $identy = 'gi.id';
                 $query = "SELECT MIN(finalgrade) as lowestgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id AND gi.itemtype = 'mod'
                            JOIN {modules} m ON gi.itemmodule = m.name
                            WHERE 1 = 1 AND gi.courseid IN ($filtercourseids) $where";
                break;
            case 'totaltimespent':
                $identy = 'gi.id';
                $courseid = 'mt.courseid';
                $query = "SELECT SUM(mt.timespent) as totaltimespent
                             FROM {block_ls_modtimestats} mt
                             JOIN {course_modules} cm ON cm.id = mt.activityid
                             JOIN {modules} m ON cm.module = m.id
                             JOIN {grade_items} gi ON gi.iteminstance = cm.instance AND gi.itemtype = 'mod'
                              AND gi.itemmodule = m.name
                            WHERE 1 = 1 AND cm.course IN ($filtercourseids) AND mt.userid IN ($learnersql) $where";
                break;
            case 'numviews':
                $identy = 'gi.id';
                $courseid = 'lsl.courseid';
                if ($this->reporttype == 'table') {
                    $query = "SELECT count(DISTINCT lsl.userid) as distinctusers, count('X') as numviews
                                           FROM {logstore_standard_log} lsl
                                           JOIN {course_modules} cm ON cm.id = lsl.contextinstanceid
                                           JOIN {modules} m ON cm.module = m.id
                                           JOIN {grade_items} gi ON gi.iteminstance = cm.instance
                                           AND gi.itemtype = 'mod' AND gi.itemmodule = m.name
                                           JOIN {user} u ON u.id = lsl.userid
                                          WHERE lsl.crud = 'r' AND lsl.contextlevel = 70
                                            AND lsl.userid > 2 AND  lsl.anonymous = 0
                                            AND u.confirmed = 1 AND lsl.courseid IN ($filtercourseids)
                                            AND lsl.userid IN ($learnersql) AND u.deleted = 0
                                            AND lsl.target = 'course_module' $where";
                } else {
                    $query = "SELECT count('X') AS numviews
                                           FROM {logstore_standard_log} lsl
                                           JOIN {course_modules} cm ON cm.id = lsl.contextinstanceid
                                           JOIN {modules} m ON cm.module = m.id
                                           JOIN {grade_items} gi ON gi.iteminstance = cm.instance
                                           AND gi.itemtype = 'mod' AND gi.itemmodule = m.name
                                           JOIN {user} u ON u.id = lsl.userid
                                          WHERE lsl.crud = 'r'
                                          AND lsl.contextlevel = 70 AND lsl.courseid IN ($filtercourseids)
                                          AND lsl.userid IN ($learnersql) AND lsl.userid > 2
                                          AND lsl.anonymous = 0 AND u.confirmed = 1 AND u.deleted = 0
                                          AND lsl.target = 'course_module' $where";
                }
                break;

            default:
                return false;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        if ($courseid != null) {
            $query = str_replace('%courseid%', $courseid, $query);
        }

        return $query;
    }
}
