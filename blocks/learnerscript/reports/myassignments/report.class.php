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
 * My assignments report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/completionlib.php');
require_login();
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;
/**
 * My Assignments Report
 */
class report_myassignments extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /**
     * Report constructor
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->columns = ['assignmentfield' => ['assignmentfield'],
        'myassignments' => ['gradepass', 'grademax', 'finalgrade', 'noofsubmissions', 'status',
        'highestgrade', 'lowestgrade', 'noofdaysdelayed', 'overduedate', ], ];
        if (isset($this->role) && $this->role == 'student') {
            $this->parent = true;
        } else {
            $this->parent = false;
        }
        if ($this->role != 'student' || is_siteadmin($this->userid)) {
            $this->basicparams = [['name' => 'users']];
        }
        $this->courselevel = false;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $this->filters = ['courses'];
        $this->orderable = ['name', 'gradepass', 'grademax', 'finalgrade', 'noofsubmissions',
        'highestgrade', 'lowestgrade', 'course', ];
        $this->searchable = ['c.fullname', 'a.name'];

        $this->defaultcolumn = 'a.id';
    }
    /**
     * My assignment report init function
     */
    public function init() {
        if ($this->role != 'student' && !isset($this->params['filter_users'])) {
            $this->initial_basicparams('users');
            $fusers = array_keys($this->filterdata);
            $this->params['filter_users'] = array_shift($fusers);
        }
        $this->courseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : [];
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->params['userid'] = $userid;
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
        $this->sql = "SELECT count(DISTINCT a.id) ";
    }
    /**
     * SELECT SQL
     */
    public function select() {
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->sql = "SELECT DISTINCT a.id, a.name as name, asb.timemodified as overduedate,
        cm.course as courseid, c.fullname as course, ra.userid as userid,
        a.duedate, asb.status as submissionstatus,
        m.id as module, m.name as type, cm.id as activityid ";
        if (!empty($this->selectedcolumns)) {
            if (in_array('noofdaysdelayed', $this->selectedcolumns)) {
                $this->params['userid'] = $userid;
                   $this->sql .= ", (SELECT cmc.timemodified
                   FROM {course_modules_completion} cmc
                   WHERE cm.id = cmc.coursemoduleid  AND cmc.userid = :userid) as noofdaysdelayed";
            }
        }
        parent::select();
    }
    /**
     * FROM SQL
     */
    public function from() {
        $this->sql .= " FROM {modules} m";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->sql .= "   JOIN {course_modules} cm ON cm.module = m.id
                          JOIN {assign} a ON a.id = cm.instance
                          JOIN {course} c ON c.id = cm.course
                          JOIN {context} ctx ON c.id = ctx.instanceid
                          JOIN {role_assignments} ra ON ctx.id = ra.contextid AND ra.userid = $userid ";

        if (empty($this->params['filter_status']) || $this->params['filter_status'] == 'all') {
            $this->sql .= " LEFT JOIN {assign_submission} asb ON asb.assignment = a.id AND asb.userid = $userid";
        } else if ($this->params['filter_status'] == 'inprogress') {
            $this->sql .= "JOIN {assign_submission} asb ON asb.assignment = a.id AND asb.userid = $userid";
        } else if ($this->params['filter_status'] == 'completed') {
            $this->sql .= " JOIN {assign_submission} asb ON asb.assignment = a.id AND asb.userid = $userid
                          JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = $userid";
        }
        parent::joins();
    }
    /**
     * SQL Where conditions
     */
    public function where() {
        $this->sql .= " WHERE c.visible = 1 AND cm.visible = 1 AND cm.deletioninprogress = 0
        AND m.name = 'assign' AND m.visible = 1";
        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND c.id IN ($this->rolewisecourses) ";
            }
        }
        if (!empty($this->courseid) && $this->courseid != '_qf__force_multiselect_submission') {
            $courseid = $this->courseid;
            $this->sql .= " AND cm.course = :courseid";
            $this->params['courseid'] = $courseid;
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->sql .= " AND ra.timemodified BETWEEN :startdate AND :enddate ";
            $this->params['startdate'] = round($this->lsstartdate);
            $this->params['enddate'] = round($this->lsenddate);
        }
        parent::where();
    }
    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $this->searchable = ['a.name', 'c.fullname'];
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

    }
    /**
     * Concat group by to the query
     */
    public function groupby() {
    }
    /**
     * Constructor for report.
     * @param  array  $assignments Assignments list
     * @param  string $sqlorder  order
     * @return array
     */
    public function get_rows($assignments = [], $sqlorder = '') {
        return $assignments;
    }
    /**
     * This function returns the report columns queries
     * @param  string  $columnname Column names
     * @param  int  $assignid Assignment id
     * @param  int  $courseid Courseid
     * @return string
     */
    public function column_queries($columnname, $assignid, $courseid = null) {
        $where = " AND %placeholder% = $assignid";
        $userid = isset($this->params['filter_users']) ? $this->params['filter_users'] : $this->userid;
        switch ($columnname) {
            case 'gradepass':
                $identy = 'gi.iteminstance';
                $query = "SELECT gi.gradepass as gradepass
                            FROM {grade_items} gi
                           WHERE gi.itemmodule = 'assign' $where ";
                break;
            case 'grademax':
                $identy = 'a1.id';
                $query = "SELECT a1.grade as grademax
                            FROM {assign} a1
                           WHERE 1 = 1 $where ";
                break;
            case 'finalgrade':
                $identy = 'gi.iteminstance';
                $query = "SELECT gg.finalgrade as finalgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id
                           WHERE 1 = 1 AND gi.itemmodule = 'assign' AND gg.userid = $userid $where  ";
            break;
            case 'highestgrade':
                $identy = 'gi.iteminstance';
                $query = "SELECT MAX(gg.finalgrade) as highestgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id
                           WHERE 1 = 1 AND gi.itemmodule = 'assign' $where  ";
            break;
            case 'lowestgrade':
                $identy = 'gi.iteminstance';
                $query = "SELECT MIN(gg.finalgrade)  as lowestgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id
                           WHERE 1 = 1 AND gi.itemmodule = 'assign' $where  ";
            break;
            case 'noofsubmissions':
                $identy = 'asb.assignment';
                $query = "SELECT count(asb.id) as noofsubmissions FROM {assign_submission} asb
                             WHERE asb.status = 'submitted' AND asb.userid = $userid $where ";
            break;

            default:
                return false;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }
}
