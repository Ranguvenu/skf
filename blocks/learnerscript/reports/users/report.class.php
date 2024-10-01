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
 * User report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use context_system;
/**
 * report_users
 */
class report_users extends reportbase {

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
     * @param object $report           Report data
     * @param object $reportproperties Report properties
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = ['columns', 'conditions', 'permissions', 'filters', 'plot'];
        $this->parent = true;
        $this->columns = ['userfield' => ['userfield'], 'usercolumns' => ['enrolled', 'inprogress',
            'completed', 'grade', 'badges', 'progress', 'status', ], ];
        $this->orderable = ['fullname', 'email', 'enrolled', 'inprogress', 'completed', 'grade', 'progress',
                            'badges', ];
        $this->filters = ['users'];
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = ["'student'"];

    }
    /**
     * Count SQL
     */
    public function count() {
        $this->sql  = " SELECT COUNT(DISTINCT u.id) ";
    }

    /**
     * Select SQL
     */
    public function select() {
        $this->sql = " SELECT DISTINCT u.id , CONCAT(u.firstname,' ',u.lastname) AS fullname, u.*";
        parent::select();
    }

    /**
     * From SQL
     */
    public function from() {
        $this->sql .= " FROM {user} u";
    }

    /**
     * SQl Joins
     */
    public function joins() {
        $this->sql .= " JOIN {role_assignments} ra ON ra.userid = u.id
                      JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                      JOIN {course} c ON c.id = ctx.instanceid
                      JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student' ";

        parent::joins();
    }

    /**
     * Adding conditions to the query
     */
    public function where() {
	$context = context_system::instance();
        $this->sql .= " WHERE u.confirmed = 1 AND u.deleted = 0 AND u.id > 2";
        if ($this->conditionsenabled) {
            $conditions = implode(',', $this->conditionfinalelements);
            if (empty($conditions)) {
                return [[], 0];
            }
            $this->params['lsconditions'] = $conditions;
            $this->sql .= " AND u.id IN ( :lsconditions )";
        }

        if (!is_siteadmin($this->userid)  && !has_capability('block/learnerscript:managereports', $context)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND c.id IN ($this->rolewisecourses) ";
            } else {
                return [[], 0];
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
            foreach ($this->searchable as $value) {
                $statsql[] = $DB->sql_like($value, "'%" . $this->search . "%'", false, true, false);
            }
            $fields = implode(" OR ", $statsql);
            $this->sql .= " AND ($fields) ";
        }
    }

    /**
     * Concat filters to the query
     */
    public function filters() {
        if (isset($this->params['filter_users'])
            && $this->params['filter_users'] > 0
            && $this->params['filter_users'] != '_qf__force_multiselect_submission') {
            $userid = $this->params['filter_users'];
            $this->params['userid'] = $userid;
            $this->sql .= " AND u.id IN (:userid) ";
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND u.timecreated BETWEEN :lsfstartdate AND :lsfenddate";
        }
    }

    /**
     * Concat groupby to SQL
     */
    public function groupby() {
        $this->sql .= " GROUP BY u.id ";
    }

    /**
     * Get rows description
     * @param  array  $users Users list
     * @return array
     */
    public function get_rows($users) {
        return $users;
    }

    /**
     * Column queries description
     * @param  array  $column Report columns
     * @param  int  $userid User ID
     * @return string
     */
    public function column_queries($column, $userid) {
        $where = " AND %placeholder% = $userid";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $coursefilter = " AND c.id IN ($this->rolewisecourses) ";
            }
        } else {
            $coursefilter = "";
        }
        $query = " ";
        $identity = " ";
        switch ($column) {
            case 'enrolled':
                $identity = "ra.userid";
                $query = "SELECT COUNT(DISTINCT c.id) AS enrolled
                          FROM {role_assignments} ra
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                          JOIN {context} ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                          WHERE 1 = 1 $where $coursefilter ";
                break;
            case 'inprogress':
                $identity = "ra.userid";
                $query = "SELECT (COUNT(DISTINCT c.id) - COUNT(DISTINCT cc.id)) AS inprogress
                          FROM {role_assignments} ra
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                          JOIN {context} ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                     LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid
                     AND cc.userid = ra.userid AND cc.timecompleted > 0
                         WHERE 1 = 1 $where $coursefilter ";
                break;
            case 'completed':
                $identity = "cc.userid";
                $query = "SELECT COUNT(DISTINCT cc.course) AS completed
                          FROM {role_assignments} ra
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                          JOIN {context} ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                          JOIN {course_completions} cc ON cc.course = ctx.instanceid
                          AND cc.userid = ra.userid AND cc.timecompleted > 0
                          WHERE 1 = 1 $where $coursefilter ";
                break;
            case 'progress':
                $identity = "ra.userid";
                $query = "SELECT
                ROUND((CAST(COUNT(DISTINCT cc.course) AS DECIMAL) / CAST(COUNT(DISTINCT c.id) AS DECIMAL)) * 100, 2)
                as progress
                            FROM {role_assignments} ra
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                            JOIN {context} ctx ON ctx.id = ra.contextid
                            JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                       LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ra.userid
                             AND cc.timecompleted > 0 WHERE 1 = 1 $where $coursefilter";
                break;
            case 'badges':
                $identity = "bi.userid";
                $query = "SELECT COUNT(bi.id) AS badges
                FROM {badge_issued} bi
                JOIN {badge} b ON b.id = bi.badgeid
                JOIN {course} c ON c.id = b.courseid AND c.visible = 1
                WHERE  bi.visible = 1 AND b.status != 0
                AND b.status != 2 AND b.status != 4
                $where $coursefilter";
                break;
            case 'grade':
                 $identity = "gg.userid";
                 $query = "SELECT CONCAT(ROUND(SUM(gg.finalgrade), 2),' / ', ROUND(SUM(gi.grademax), 2)) AS grade
                           FROM {grade_grades} AS gg
                           JOIN {grade_items} gi ON gi.id = gg.itemid
                           JOIN {course_completions} cc ON cc.course = gi.courseid
                           JOIN {course} c ON cc.course = c.id AND c.visible=1
                          WHERE gi.itemtype = 'course' AND cc.course = gi.courseid
                            AND cc.timecompleted IS NOT NULL
                            AND gg.userid = cc.userid
                             $where $coursefilter ";
                break;
        }
        $query = str_replace('%placeholder%', $identity, $query);
        return $query;
    }
}
