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
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/completionlib.php');
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;

/**
 * assignment Participation Report
 */
class report_assignmentparticipation extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;
    /**
     * @var array $basicparamdata Basic params list
     */
    public $basicparamdata;
    /**
     * Report Constructor
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->columns = ['assignmentfield' => ['assignmentfield'],
        'assignmentparticipationcolumns' => ['username', 'finalgrade', 'status', 'noofdaysdelayed',
        'duedate', 'submitteddate', ], ];
        $this->parent = true;
        $this->basicparams = [['name' => 'courses']];
        $this->courselevel = true;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $this->orderable = ['username', 'finalgrade', 'status', 'noofdaysdelayed', 'duedate', 'submitteddate'];
        $this->searchable = ['a.name', 'u.username'];
        $this->defaultcolumn = "concat(a.id, '-', c.id, '-', ra.userid)";
    }
    /**
     * Report Initialization
     */
    public function init() {
        $this->courseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : [];
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->params['userid'] = $userid;
    }
    /**
     * SQL Count
     */
    public function count() {
        $this->sql = "SELECT count(DISTINCT (concat(a.id, '-', c.id, '-', ra.userid))) ";
    }
    /**
     * SQL Select
     */
    public function select() {
        $this->sql = "SELECT DISTINCT concat(a.id, '-', c.id, '-', ra.userid), a.id, a.name as name,
        asb.timemodified as overduedate, cm.course as courseid, c.fullname as course, ra.userid as userid,
        a.duedate as due_date, asb.status as submissionstatus,
        m.id as module, m.name as type, cm.id as activityid, u.username as username ";
        if (!empty($this->selectedcolumns)) {
            if (in_array('noofdaysdelayed', $this->selectedcolumns)) {
                $this->sql .= ", (SELECT cmc.timemodified
                FROM {course_modules_completion} cmc
                WHERE cm.id = cmc.coursemoduleid  AND cmc.userid = ra.userid) as noofdaysdelayed";
            }
        }
        parent::select();
    }
    /**
     * SQL From
     */
    public function from() {
        $this->sql .= " FROM {modules} m";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= "   JOIN {course_modules} cm ON cm.module = m.id
        JOIN {assign} a ON a.id = cm.instance
        JOIN {course} c ON c.id = cm.course
        JOIN {context} ctx ON c.id = ctx.instanceid
        JOIN {role_assignments} ra ON ctx.id = ra.contextid
        JOIN {role} r on r.id=ra.roleid AND r.shortname='student'
        JOIN {user} u ON u.id = ra.userid ";

        if (empty($this->params['filter_status']) || $this->params['filter_status'] == 'all') {
            $this->sql .= " LEFT JOIN {assign_submission} asb ON asb.assignment = a.id AND asb.userid = ra.userid";
        } else if ($this->params['filter_status'] == 'inprogress') {
            $this->sql .= "JOIN {assign_submission} asb ON asb.assignment = a.id AND asb.userid = ra.userid
            AND asb.status = 'submitted'";
        } else if ($this->params['filter_status'] == 'completed') {
            $this->sql .= " LEFT JOIN {assign_submission} asb ON asb.assignment = a.id
            AND asb.userid = ra.userid
            JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
            AND cmc.userid = ra.userid AND cmc.completionstate>0";
        }

        parent::joins();
    }
    /**
     * SQL Where Condition
     */
    public function where() {
        $this->sql .= " WHERE c.visible = 1 AND cm.visible = 1
        AND cm.deletioninprogress = 0 AND m.name = 'assign' AND m.visible = 1 ";
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
        if (isset($this->params['filter_assignment']) && $this->params['filter_assignment']) {
            $this->sql .= " AND a.id = :assignmentid";
            $this->params['assignmentid'] = $this->params['filter_assignment'];
        }
        parent::where();
    }
    /**
     * Search
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $this->searchable = ['a.name', 'u.username'];
            $statsql = [];
            foreach ($this->searchable as $key => $value) {
                $statsql[] = $DB->sql_like($value, "'%" . $this->search . "%'", $casesensitive = false,
                $accentsensitive = true, $notlike = false);
            }
            $fields = implode(" OR ", $statsql);
            $this->sql .= " AND ($fields) ";
        }
    }
    /**
     * SQL Filters
     */
    public function filters() {

    }
    /**
     * Concat Group by to sql
     */
    public function groupby() {
        $this->sql .= " GROUP BY a.id, c.id, ra.userid, a.name, asb.timemodified,
        cm.course, c.fullname, a.duedate, asb.status, m.id, m.name, cm.id, u.username";
    }
    /**
     * This function gets the report rows
     * @param  array  $assignments assignments list
     * @param  string  $sqlorder
     * @return array
     */
    public function get_rows($assignments = [], $sqlorder = '') {
        return $assignments;
    }
    /**
     * This function gets the report rows
     * @param  array  $columnname
     * @param  string  $assignid
     * @param  int  $courseid
     * @return array
     */
    public function column_queries($columnname, $assignid, $courseid = null) {
        global $DB;
    }
}
