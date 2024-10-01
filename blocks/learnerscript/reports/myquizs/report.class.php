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
 * My quizs report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/completionlib.php');
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;
/**
 * My quiz report
 */
class report_myquizs extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;
    /**
     * Report constructor
     * @param object $report           Report data
     * @param object $reportproperties Report properties
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->columns = ['quizfield' => ['quizfield'], 'myquizs' => ['quizattempts', 'status', 'state',
        'grademax', 'finalgrade', 'gradepass', 'highestgrade', 'lowestgrade', ], ];
        if (isset($this->role) && $this->role == 'student') {
            $this->parent = true;
        } else {
            $this->parent = false;
        }
        if ($this->role != 'student' || is_siteadmin()) {
            $this->basicparams = [['name' => 'users']];
        }
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $this->courselevel = false;
        $this->filters = ['courses'];
        $this->orderable = ['quizattempts', 'grademax', 'finalgrade', 'gradepass',
        'highestgrade', 'lowestgrade', 'name', 'course', ];
        $this->defaultcolumn = 'q.id';
    }
    /**
     * My quiz init function
     */
    public function init() {
        if ($this->role != 'student' && !isset($this->params['filter_users'])) {
            $this->initial_basicparams('users');
            $fusers = array_keys($this->filterdata);
            $this->params['filter_users'] = array_shift($fusers);
        }
        $this->params['userid'] = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
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
        $this->sql  = "SELECT COUNT(DISTINCT q.id) ";
    }
    /**
     * SELECT SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT q.id, q.name as name, m.name as modname,
        cm.id as activityid , ra.userid as userid, q.course as courseid, q.* ";
        parent::select();
    }
    /**
     * FROM SQL
     */
    public function from() {
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        if (empty($this->params['filter_status']) || $this->params['filter_status'] == 'all') {
            $this->sql    .= " FROM {modules} m
                               JOIN {course_modules} cm ON cm.module = m.id
                               JOIN {quiz} q ON q.id = cm.instance
                          LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.userid = $userid
                               JOIN {course} c ON c.id = cm.course
                               JOIN {context} ctx ON c.id = ctx.instanceid
                               JOIN {role_assignments} ra ON ctx.id = ra.contextid AND ra.userid = $userid ";
            $this->sql      .= "  AND m.visible = 1";
        } else if ($this->params['filter_status'] == 'inprogress') {
            $this->sql   .= "   FROM {quiz} q
                                JOIN {course_modules} cm ON cm.instance = q.id
                                JOIN {modules} m ON m.id = cm.module
                                JOIN {course} c ON c.id = cm.course
                                JOIN {context} ctx ON c.id = ctx.instanceid
                                JOIN {role_assignments} ra ON ctx.id = ra.contextid AND ra.userid = $userid";
            $params['userid'] = $userid;
        } else if ($this->params['filter_status'] == 'completed') {
            $this->sql   .= "   FROM {quiz} q
                                JOIN {course_modules} cm ON cm.instance = q.id
                                JOIN {modules} m ON m.id = cm.module
                                JOIN {course} c ON c.id = cm.course
                                JOIN {context} ctx ON c.id = ctx.instanceid
                                JOIN {role_assignments} ra ON ctx.id = ra.contextid AND ra.userid = $userid
                                JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id";
            $params['userid'] = $userid;
        } else if ($this->params['filter_status'] == 'notattempted') {
            $this->sql   .= " FROM {quiz} q
                              JOIN {course} c ON c.id = q.course
                              JOIN {course_modules} cm ON cm.instance = q.id
                              JOIN {modules} m ON m.id = cm.module
                              JOIN {context} ctx ON c.id = ctx.instanceid
                              JOIN {role_assignments} ra ON ctx.id = ra.contextid AND ra.userid = $userid ";
            $params['userid'] = $userid;
        }
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql  .= "";
        parent::joins();
    }
    /**
     * SQL Where condition
     */
    public function where() {
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        if (empty($this->params['filter_status']) || $this->params['filter_status'] == 'all') {
            $this->sql    .= " WHERE c.visible = 1 AND cm.visible = 1 AND cm.deletioninprogress = 0
                                AND m.visible = 1 AND m.name = 'quiz'";
        } else if ($this->params['filter_status'] == 'inprogress') {
            $this->sql   .= " WHERE cm.id IN (SELECT cm.id As coursemoduleid
                                FROM {quiz_attempts} qat
                                JOIN {course_modules} cm ON cm.instance = qat.quiz
                                AND cm.visible = 1 AND cm.deletioninprogress = 0
                                JOIN {course} c ON c.id = cm.course AND c.visible = 1
                                JOIN {modules} m on m.id=cm.module AND m.name = 'quiz'
                                JOIN {user} u1 ON u1.id = qat.userid
                                WHERE c.visible = 1 AND qat.state = 'inprogress'
                                AND qat.userid = $userid)";
            $params['userid'] = $userid;
        } else if ($this->params['filter_status'] == 'completed') {
            $this->sql   .= "  WHERE cmc.userid = :userid AND m.name='quiz'
                                AND cm.visible = 1 AND cm.deletioninprogress = 0 AND cmc.completionstate > 0 AND c.visible = 1";
            $params['userid'] = $userid;
        } else if ($this->params['filter_status'] == 'notattempted') {
            $this->sql   .= " WHERE cm.visible = 1
                               AND c.visible = 1 AND cm.deletioninprogress = 0 AND m.name = 'quiz'
                               AND q.id NOT IN (SELECT qa.quiz FROM  {quiz_attempts} qa
                                                 WHERE qa.userid = $userid)
                              ";
            $params['userid'] = $userid;
        }
        parent::where();
    }
    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $this->searchable = ['q.name', 'c.fullname', 'CasT(q.grade as VARCHAR)', 'm.name'];
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
        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND c.id IN ($this->rolewisecourses) ";
            }
        }
        if (!empty($this->params['filter_courses']) && $this->params['filter_courses'] <> SITEID  && !$this->scheduling) {
            $this->sql .= " AND cm.course = :filter_courses";
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->sql .= " AND ra.timemodified BETWEEN :lsfstartdate AND :lsfenddate ";
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
        }
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {

    }
    /**
     * This function get the report rows
     * @param  array  $quizs Quiz list
     * @return array
     */
    public function get_rows($quizs) {
        return $quizs;
    }
    /**
     * This function returns the report columns queries
     * @param  string  $columnname Column names
     * @param  int  $quizid Activity id
     * @return string
     */
    public function column_queries($columnname, $quizid) {
        $where = " AND %placeholder% = $quizid";
        $userid = isset($this->params['userid']) ? $this->params['userid'] : $this->userid;
        switch ($columnname) {
            case 'gradetype':
                $identy = 'gi.iteminstance';
                $query = "SELECT gi.gradetype as gradetype FROM {grade_items} gi WHERE gi.itemmodule = 'quiz' $where ";
                break;
            case 'gradepass':
                $identy = 'gi.iteminstance';
                $query = "SELECT gi.gradepass as gradepass FROM {grade_items} gi WHERE gi.itemmodule = 'quiz' $where ";
                break;
            case 'finalgrade':
                $identy = 'gi.iteminstance';
                $query = "SELECT gg.finalgrade as finalgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id
                           WHERE 1 = 1 AND gi.itemmodule = 'quiz' AND gg.userid = $userid $where  ";
            break;
            case 'grademax':
                $identy = 'q1.id';
                $query = "SELECT q1.grade as grademax FROM {quiz} q1
                           WHERE 1 = 1 $where  ";
            break;
            case 'highestgrade':
                $identy = 'gi.iteminstance';
                $query = "SELECT MAX(gg.finalgrade)  as highestgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id
                           WHERE 1 = 1 AND gi.itemmodule = 'quiz' $where  ";
            break;
            case 'lowestgrade':
                $identy = 'gi.iteminstance';
                $query = "SELECT MIN(gg.finalgrade)  as lowestgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id
                           WHERE 1 = 1 AND gi.itemmodule = 'quiz' $where  ";
            break;
            case 'quizattempts':
                $identy = 'quiz';
                $query = "SELECT COUNT(id) as quizattempts FROM {quiz_attempts}
                                      WHERE 1 = 1 AND userid = $userid $where  ";
            break;
            default:
                return false;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }
}
