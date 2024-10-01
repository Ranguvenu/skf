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
 * My Forums report
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
 * My Forums report
 */
class report_myforums extends reportbase implements report {

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
        global $DB;
        parent::__construct($report, $reportproperties);
        $this->columns = ['myforums' => ['forumname', 'coursename', 'noofdisscussions', 'noofreplies', 'wordcount']];
        if (isset($this->role) && $this->role == 'student') {
            $this->parent = true;
        } else {
            $this->parent = false;
        }
        if ($this->role != 'student') {
            $this->basicparams = [['name' => 'users']];
        }
        $this->courselevel = false;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $this->filters = ['courses'];
        $this->orderable = ['forumname', 'coursename', 'noofdisscussions', 'noofreplies', 'wordcount'];
        $this->defaultcolumn = 'f.id';
    }
    /**
     * My forums init function
     */
    public function init() {
        if ($this->role != 'student' && !isset($this->params['filter_users'])) {
            $this->initial_basicparams('users');
            $fusers = array_keys($this->filterdata);
            $this->params['filter_users'] = array_shift($fusers);
        }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
        $this->courseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : [];
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->params['userid'] = $userid;
    }
    /**
     * COUNT SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT CONCAT(f.id, '-', cm.id))";
    }
    /**
     * SELECT SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT CONCAT(f.id, '-', cm.id), f.id, f.name as forumname,
        cm.course as courseid, c.fullname as coursename,  m.id as module, m.name as type, cm.id as activityid ";
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
        $this->sql .= "  JOIN {course_modules} cm ON cm.module = m.id
                         JOIN {forum} f ON f.id = cm.instance
                         JOIN {course} c ON c.id = cm.course
                         JOIN {context} ctx ON c.id = ctx.instanceid
                         JOIN {role_assignments} ra ON ctx.id = ra.contextid AND ra.userid = $userid  ";
        parent::joins();
    }
    /**
     * SQL Where Conditions
     */
    public function where() {
        global $SESSION;
        $mycourses = (new querylib)->get_rolecourses($this->params['userid'],
        'student', $SESSION->ls_contextlevel, SITEID, '', '');
        $mycourseids = implode(',', array_keys($mycourses));
        $this->sql .= " WHERE c.visible = 1 AND cm.visible = 1 AND cm.deletioninprogress = :deletioninprogress
        AND f.type =:general AND m.name = :forum";
        $this->params['general'] = 'general';
        $this->params['forum'] = 'forum';
        $this->params['deletioninprogress'] = 0;
        if (!empty($mycourses)) {
            $this->sql .= " AND c.id IN ($mycourseids)";
        }
        if (!empty($this->courseid) && $this->courseid != '_qf__force_multiselect_submission') {
            $courseid = $this->courseid;
            $this->sql .= " AND cm.course = :courseid";
            $this->params['courseid'] = $courseid;
        }
        parent::where();
    }
    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $this->searchable = ['f.name', 'c.fullname'];
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
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND ra.timemodified BETWEEN :lsfstartdate AND :lsfenddate ";
        }
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {

    }
    /**
     * Get report rows
     * @param  array  $forums Forums list
     * @return array
     */
    public function get_rows($forums = []) {
        return $forums;
    }
    /**
     * This function returns the report columns queries
     * @param  string  $columnname Column names
     * @param  int  $forumid Forum id
     * @param  int  $courseid Courseid
     * @return string
     */
    public function column_queries($columnname, $forumid, $courseid = null) {
        global $CFG;
         $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $where = " AND %placeholder% = $forumid";

        switch ($columnname) {
            case 'noofdisscussions':
                $identy = 'fd.forum';
                $query = "SELECT COUNT(fd.id) as noofdisscussions
                                FROM {forum_discussions} fd WHERE 1 = 1 $where ";
                break;
            case 'noofreplies':
                $identy = 'fd.forum';
                $query = "SELECT COUNT(fp.id) as noofreplies
                                FROM {forum_posts} fp
                                JOIN {forum_discussions} fd ON fp.discussion = fd.id
                                WHERE fp.subject LIKE '%Re:%' AND fp.userid = $userid $where ";
            break;
            case 'wordcount':
                $identy = 'fd.forum';
                if ($CFG->dbtype == 'sqlsrv') {
                    $query = "SELECT SUM(LEN(fp.message) -
                                         LEN(REPLACE((fp.message), ' ', '')) + 1)  as wordcount
                                FROM {forum_posts} fp
                                JOIN {forum_discussions} fd ON fp.discussion = fd.id
                                WHERE fp.userid = $userid $where ";
                } else {
                    $query = "SELECT SUM(LENGTH(fp.message) -
                                         LENGTH(REPLACE((fp.message), ' ', '')) + 1)  as wordcount
                                FROM {forum_posts} fp
                                JOIN {forum_discussions} fd ON fp.discussion = fd.id
                                WHERE fp.userid = $userid $where ";
                }
            break;
            default:
                return false;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }
}
