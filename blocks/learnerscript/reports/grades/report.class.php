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
 * Grades report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/completionlib.php');
require_once("$CFG->dirroot/enrol/locallib.php");
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use completion_info;
use stdClass;
use grade_grade;

/**
 * Grade report class
 */
class report_grades extends reportbase implements report {

    /** @var array $searchable  */
    public $searchable;

    /** @var array $orderable  */
    public $orderable;

    /** @var array $excludedroles  */
    public $excludedroles;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /**
     * Report construct
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->columns = ['userfield' => ['userfield'], 'gradecolumns' => ['grade', 'status']];
        $this->parent = false;
        $this->basicparams = [['name' => 'courses'], ['name' => 'activities']];
        $this->components = ['columns', 'conditions', 'filters',  'permissions', 'calcs', 'plot'];
        $this->courselevel = true;
        $this->orderable = ['fullname', 'email'];
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = ["'student'"];
    }

    /**
     * Report initialization
     */
    public function init() {
        global $DB;
         $this->courseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : SITEID;
         $this->cmid = isset($this->params['filter_activities']) ? $this->params['filter_activities'] : 0;
         $this->params['deleted'] = 0;
         $this->params['suspended'] = 0;
         $this->params['confirmed'] = 1;
         $this->params['contextlevel'] = CONTEXT_COURSE;
         $this->params['ej1_active'] = ENROL_USER_ACTIVE;
         $this->params['ej1_enabled'] = ENROL_INSTANCE_ENABLED;
         $this->params['ej1_now1'] = round(time(), -2); // Improves db caching.
         $this->params['ej1_courseid'] = $this->courseid;
         $this->params['courseid'] = $this->courseid;
         $this->params['roleid'] = $DB->get_field('role', 'id', ['shortname' => 'student']);
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            $params = [];
            $list = [];
            foreach ($basicparams as $key => $basicparam) {
                $params[] = $basicparam;
                if (empty($this->params['filter_' . $basicparam]) && !in_array($basicparam, $params)) {
                    $list[$basicparam] = 1;
                }
            }
            if (!empty($list)) {
                return false;
            }
        }
        if (!isset($this->params['filter_courses'])) {
            $this->initial_basicparams('courses');
            $coursedata = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($coursedata);
        }
        if (!isset($this->params['filter_activities'])) {
            $this->initial_basicparams('activities');
            $userdata = array_keys($this->filterdata);
            $this->params['filter_activities'] = array_shift($userdata);
        }

        if (empty($this->params['filter_activities']) && !empty($this->params['filter_courses'])
            && $this->params['filter_courses'] <> SITEID  && !$this->scheduling) {
            $modinfo = get_fast_modinfo($this->params['filter_courses']);
            if (!empty($modinfo->cms)) {
                foreach ($modinfo->cms as $k => $cm) {
                    $courseactivities[$k] = $cm->name;
                }
            }
            $activitiesdata = array_keys($courseactivities);
            $this->params['filter_activities'] = array_shift($activitiesdata);
        }
        if (!isset($this->params['filter_activities'])) {
            return [[], 0];
        }
    }

    /**
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(u.id) ";
    }

    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT u.id , CONCAT(u.firstname, u.lastname) as fullname, u.* ";
    }
    /**
     * From SQL
     */
    public function from() {
        $this->sql .= "FROM {user} u";
    }
    /**
     * Adding conditions to the query
     */
    public function where() {
        if (!empty($this->params['filter_activities'])) {
            $activitysql = ' AND cm.id IN ('.$this->params['filter_activities'].') ';
        }
        $this->sql .= " WHERE 1 = 1 AND u.id IN (SELECT u.id
                        FROM {course} c
                        JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                        JOIN {role_assignments} ra ON ctx.id = ra.contextid
                        JOIN {context} con ON c.id = con.instanceid
                        JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'
                        JOIN {course_modules} cm ON cm.course = c.id
                        JOIN {user} u ON u.id = ra.userid
                        WHERE c.visible = 1 AND ra.roleid = :roleid AND ra.contextid = con.id
                        AND u.confirmed = 1 AND u.deleted = 0  AND u.suspended = 0
                        AND cm.visible = 1 AND c.id = :ej1_courseid
                        AND cm.deletioninprogress = 0 $activitysql)
                        AND u.deleted = 0 AND u.confirmed = 1 AND u.suspended = 0 ";

        if ($this->conditionsenabled) {
            $conditions = implode(',', $this->conditionfinalelements);
            if (empty($conditions)) {
                return [[], 0];
            }
            $this->params['lsconditions'] = $conditions;
            $this->sql .= " AND u.id IN ( :lsconditions )";
        }

        if ($this->lsstartdate > 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND u.timecreated BETWEEN :lsfstartdate AND :lsfenddate ";
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

    }

    /**
     * Concat groupby to SQL
     */
    public function groupby() {

    }
    /**
     * Get list of report data
     * @param  array $users  Users list
     * @return array
     */
    public function get_rows($users) {
        global $DB, $CFG;
        $this->courseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : SITEID;
        $this->cmid = isset($this->params['filter_activities']) ? $this->params['filter_activities'] : null;
        if (!$this->cmid) {
            return [];
        }
        $course = $DB->get_record('course', ['id' => $this->courseid]);
        $completioninfo = new completion_info($course);
        $params['cmid'] = $this->cmid;
        $params['itemtype'] = 'mod';
        $params['cmvisible'] = 1;
        $params['deletioninprogress'] = 0;
        $sql = " SELECT gi.*
                   FROM {grade_items} gi
                   JOIN {course_modules} cm ON cm.instance = gi.iteminstance
                   JOIN {modules} m ON m.id = cm.module
                  WHERE cm.id = :cmid AND gi.itemtype = :itemtype
                    AND cm.visible = :cmvisible
                    AND cm.deletioninprogress = :deletioninprogress
                    AND m.name = gi.itemmodule AND gi.courseid = cm.course";
        $gradeitem = $DB->get_record_sql($sql, $params);
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/grade/report/grader/lib.php');
        $data = [];
        if (!empty($users)) {
            foreach ($users as $user) {
                if (!empty($gradeitem)) {
                    $gradedata = grade_grade::fetch_users_grades($gradeitem, [$user->id]);
                } else {
                    $gradedata = false;
                }
                if (!empty($gradedata)) {
                    $user->grade = $gradedata[$user->id]->finalgrade ? round($gradedata[$user->id]->finalgrade, 2) : '-';
                } else {
                    $user->grade = '-';
                }
                $cm = new stdClass();
                $cm->id = $this->cmid;
                $completion = $completioninfo->get_data($cm, false, $user->id);
                switch($completion->completionstate) {
                    case COMPLETION_INCOMPLETE :
                            $completionstatus = get_string('notcompleted', 'block_learnerscript');
                    break;
                    case COMPLETION_COMPLETE :
                            $completionstatus = get_string('completed', 'block_learnerscript');
                    break;
                    case COMPLETION_COMPLETE_PASS :
                            $completionstatus = get_string('achieved', 'block_learnerscript');
                    break;
                    case COMPLETION_COMPLETE_FAIL :
                            $completionstatus = get_string('fail', 'block_learnerscript');
                    break;
                }
                $user->status = $completionstatus;
                $data[] = $user;
            }
        }
        return $data;
    }
}
