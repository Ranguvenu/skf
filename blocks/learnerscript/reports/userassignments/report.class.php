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
 * User assignments report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\ls as ls;
use block_learnerscript\report;
/**
 * Users quizzes report class
 */
class report_userassignments extends reportbase implements report {

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
        $this->courselevel = true;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $columns = ['total', 'inprogress', 'notyetstarted', 'completed', 'totaltimespent',
         'numviews', 'submitted', 'highestgrade', 'lowestgrade', ];
        $this->columns = ['userfield' => ['userfield'], 'userassignments' => $columns];
        $this->basicparams = [['name' => 'courses']];
        $this->filters = ['users'];
        $this->orderable = ['fullname', 'notyetstarted', 'inprogress', 'completed',
         'totaltimespent', 'numviews', 'submitted', 'highestgrade', 'lowestgrade', ];
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Report init
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
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->params['roleid'] = $studentroleid;
    }
    /**
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT u.id) ";
    }
    /**
     * Select SQL
     */
    public function select() {
        $courseid = $this->params['filter_courses'];
        $this->sql = "SELECT DISTINCT u.id, u.id AS userid, CONCAT(u.firstname,' ', u.lastname) AS fullname, c.id AS courseid ";
        if (!empty($this->selectedcolumns)) {
            if (in_array('total', $this->selectedcolumns)) {
                $this->sql .= ", 'total' ";
            }
        }

        parent::select();
    }
    /**
     * Form SQL
     */
    public function from() {
        $this->sql .= " FROM {course} c";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {context} con ON c.id = con.instanceid
                        JOIN {role_assignments} ra ON ra.contextid = con.id
                        JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'
                        JOIN {user} u ON u.id = ra.userid";
        parent::joins();
    }
    /**
     * Adding conditions to the query
     */
    public function where() {
        $userid = isset($this->params['filter_users']) ? $this->params['filter_users'] : [];
        $this->sql .= "  WHERE  c.visible = 1 AND ra.roleid = :roleid AND ra.contextid =con.id
                         AND u.confirmed = 1 AND u.deleted = 0";

        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
            if ($this->rolewisecourses != '') {
                $this->params['rolewisecourses'] = $this->rolewisecourses;
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
            $this->searchable = ["CONCAT(u.firstname, ' ', u.lastname)", "u.email"];
            $statsql = [];
            foreach ($this->searchable as $key => $value) {
                $statsql[] = $DB->sql_like($value, "'%" . $this->search . "%'", $casesensitive =
                false, $accentsensitive = true, $notlike = false);
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
        if ($this->params['filter_courses'] <> SITEID) {
            $courseid = $this->params['filter_courses'];
            $this->sql .= " AND c.id IN (:filter_courses)";
        }
        if ($this->lsstartdate > 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND ra.timemodified BETWEEN :lsfstartdate AND :lsfenddate ";
        }
    }
    /**
     * Concat groupby to SQL
     */
    public function groupby() {

    }
    /**
     * get rows
     * @param  array $users users
     * @return array
     */
    public function get_rows($users = []) {
        return $users;
    }
    /**
     * Columns quesries
     * @param  array $columnname Columns names
     * @param  int $assignid Assign id
     * @param  int $courseid Course id
     * @return string
     */
    public function column_queries($columnname, $assignid, $courseid = null) {
        $where = " AND %placeholder% = $assignid";
        $filtercourseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : SITEID;

        switch ($columnname) {
            case 'notyetstarted':
                $identy = 'ra.userid';
                $query = "SELECT COUNT(DISTINCT cm.instance) AS notyetstarted
                                  FROM {course_modules} AS cm
                                  JOIN {modules} AS m ON m.id = cm.module
                                 WHERE cm.visible = 1 AND cm.deletioninprogress = 0 AND m.name = 'assign'
                                   AND cm.course IN ( SELECT DISTINCT c.id FROM {course} c
                                                        JOIN {context} con ON c.id = con.instanceid
                                                        JOIN {role_assignments} ra ON ra.contextid = con.id
                                                        JOIN {role} r ON r.id =ra.roleid AND r.shortname = 'student'
                                                       WHERE c.visible = 1 AND c.id IN ($filtercourseid) $where)
                                   AND cm.instance NOT IN ( SELECT assignment FROM {assign_submission} asub
                                                                JOIN {role_assignments} ra ON ra.userid = asub.userid
                                                             WHERE asub.status = 'submitted' $where)
                                   AND cm.instance NOT IN (SELECT cm.instance
                                                             FROM {course_modules} AS cm
                                                             JOIN {course} AS c ON c.id = cm.course
                                                             JOIN {modules} AS m ON m.id = cm.module
                                                             JOIN {course_modules_completion} AS cmc
                                                               ON cmc.coursemoduleid = cm.id
                                                             JOIN {role_assignments} ra ON ra.userid = cmc.userid
                                                            WHERE cm.visible = 1 AND cm.deletioninprogress = 0 AND c.visible = 1
                                                              AND m.name = 'assign' AND cmc.completionstate <> 0  AND
                                                              cm.course IN ($filtercourseid) $where
                                                              )  AND cm.course IN ($filtercourseid) ";

                break;
            case 'inprogress':
                $identy = 'u1.id';
                $query = "SELECT COUNT(DISTINCT cm.id) AS inprogress
                               FROM {course_modules} AS cm
                               JOIN {course} AS c ON c.id = cm.course
                               JOIN {modules} AS m ON m.id = cm.module
                               JOIN {assign_submission} AS asub on asub.assignment = cm.instance
                               JOIN {user} u1 ON u1.id = asub.userid
                                AND asub.status = 'submitted'
                              WHERE cm.visible = 1 AND c.visible = 1 AND m.name = 'assign'
                                AND cm.instance NOT IN
                                    (SELECT cm.instance
                                       FROM {course_modules} AS cm
                                       JOIN {course} AS c ON c.id = cm.course
                                       JOIN {modules} AS m ON m.id = cm.module
                                       JOIN {course_modules_completion} AS cmc ON cmc.coursemoduleid = cm.id
                                       JOIN {user} u2 ON u2.id = cmc.userid
                                      WHERE cm.visible = 1 AND cm.deletioninprogress = 0 AND c.visible = 1 AND m.name = 'assign' AND
                                       cm.course IN ($filtercourseid) AND cmc.completionstate > 0 $where)  AND
                                       cm.course IN ($filtercourseid) $where ";
            break;
            case 'completed':
                $identy = 'cmc.userid';
                $query = " SELECT COUNT(cmc.id) AS completed
                               FROM {course_modules} AS cm
                               JOIN {course} AS c ON c.id = cm.course
                               JOIN {modules} AS m ON m.id = cm.module
                               JOIN {course_modules_completion} AS cmc ON cmc.coursemoduleid = cm.id
                              WHERE cm.visible = 1 AND cm.deletioninprogress = 0 AND m.name = 'assign'
                                AND c.visible = 1 AND cmc.completionstate > 0
                                 AND cm.course IN ($filtercourseid) $where ";
            break;
            case 'submitted':
                $identy = 'sub.userid';
                $query = " SELECT COUNT(sub.id) AS submitted FROM {assign_submission} AS sub
                                JOIN {assign} a ON a.id = sub.assignment
                              WHERE sub.status='submitted' AND a.course IN ($filtercourseid) $where ";
            break;
            case 'highestgrade':
                $identy = 'gg.userid';
                $query = "SELECT MAX(gg.finalgrade) AS highestgrade FROM {grade_grades} AS gg
                            JOIN {grade_items} AS gi ON gg.itemid = gi.id
                            JOIN {course_modules} AS cm ON gi.iteminstance = cm.instance
                            WHERE gi.itemmodule = 'assign' AND cm.course IN ($filtercourseid) $where ";
            break;
            case 'lowestgrade':
                $identy = 'gg.userid';
                $query = "SELECT MIN(gg.finalgrade) AS lowestgrade FROM {grade_grades} AS gg
                            JOIN {grade_items} AS gi ON gg.itemid = gi.id
                            JOIN {course_modules} AS cm ON gi.iteminstance = cm.instance
                            WHERE gi.itemmodule = 'assign' AND cm.course IN ($filtercourseid) $where ";
            break;
            case 'totaltimespent':
                $identy = 'mt.userid';
                $query = " SELECT SUM(mt.timespent) AS totaltimespent FROM {block_ls_modtimestats} AS mt
                JOIN {course_modules} cm ON cm.id = mt.activityid JOIN {modules} m ON m.id = cm.module
                WHERE m.name = 'assign' AND cm.course IN ($filtercourseid) $where ";
            break;
            case 'numviews':
                $identy = 'lsl.userid';
                $query = "SELECT COUNT(DISTINCT lsl.id) AS numviews
                                        FROM {logstore_standard_log} lsl
                                        JOIN {course_modules} cm ON cm.id = lsl.contextinstanceid
                                        JOIN {modules} m ON m.id = cm.module
                                        WHERE m.name = 'assign' AND lsl.crud = 'r' AND lsl.contextlevel = 70
                                        AND lsl.anonymous = 0 AND cm.course IN ($filtercourseid)
                                        AND lsl.target = 'course_module' $where";
            break;

            default:
                return false;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }
}
