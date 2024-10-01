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
 * Courses Overview report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use context_system;

/**
 * Course overview report data class
 */
class report_coursesoverview extends reportbase implements report {

    /** @var array $searchable  */
    public $searchable;

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /**
     * @var $userid
     */
    public $userid;
    /**
     * Report constructor
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = ['columns', 'conditions', 'filters', 'permissions', 'plot'];
        $columns = ['coursename', 'totalactivities', 'completedactivities', 'inprogressactivities', 'grades', 'totaltimespent'];
        $this->columns = ['coursesoverview' => $columns];

        if ($this->role != $this->currentrole) {
            $this->basicparams = [['name' => 'users']];
        }
        if ($this->role == $this->currentrole) {
            $this->parent = true;
        } else {
            $this->parent = false;
        }
        $this->filters = ['courses', 'modules'];
        $this->orderable = ['totalactivities', 'completedactivities', 'inprogressactivities', 'coursename', 'totaltimespent'];
        $this->defaultcolumn = 'c.id';
    }

    /**
     * Report initialization
     */
    public function init() {
        if ($this->role != $this->currentrole && !isset($this->params['filter_users'])) {
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
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT c.id)";
    }

    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT c.id, c.fullname AS coursename";
        parent::select();
    }

    /**
     * Form SQL
     */
    public function from() {
        $this->sql .= " FROM {role_assignments} ra";
    }

    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {context} ctx ON ctx.id = ra.contextid
                         JOIN {course} c ON c.id = ctx.instanceid
                         JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'";
        parent::joins();
    }

    /**
     * Adding conditions to the query
     */
    public function where() {
        $context = context_system::instance();
        $this->params['userid'] = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->sql .= " WHERE ra.userid = :userid AND c.visible = 1";
        if (!empty($conditionfinalelements)) {
            $conditions = implode(',', $conditionfinalelements);
            $this->sql .= " AND c.id IN (:conditions)";
            $this->params['conditions'] = $conditions;
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND ra.timemodified BETWEEN :lsfstartdate AND :lsfenddate ";
        }
        if (!is_siteadmin($this->userid) && !has_capability('block/learnerscript:managereports', $context)) {
            if ($this->rolewisecourses != '') {
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
            $this->searchable = ["c.fullname"];
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
        $filtercourses = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : SITEID;
        $this->params['subuserid'] = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                                        ? $this->params['filter_users'] : $this->userid;
        if ($filtercourses > SITEID) {
            $this->sql .= " AND c.id IN ($filtercourses)";
        }
        if (empty($this->params['filter_status']) || $this->params['filter_status'] == 'All') {
            $this->sql .= " ";
        }
        if (!empty($this->params['filter_status']) && $this->params['filter_status'] == 'Completed') {
            $this->sql .= " AND c.id IN (SELECT DISTINCT course FROM {course_completions}
                            WHERE userid = :subuserid AND timecompleted > 0)";
        }
        if (!empty($this->params['filter_status']) && $this->params['filter_status'] == 'Inprogress') {
            $this->sql .= " AND c.id NOT IN (SELECT DISTINCT course FROM {course_completions}
                            WHERE userid = :subuserid AND timecompleted > 0)";
        }
    }

    /**
     * Concat groupby to SQL
     */
    public function groupby() {

    }

    /**
     * This function get the report rows data
     * @param  array $courses Courses
     * @return array
     */
    public function get_rows($courses) {
        return $courses;
    }

    /**
     * This function gets the report columns queries
     * @param  string $columnname Column name
     * @param  int $courseid Course id
     * @return string
     */
    public function column_queries($columnname, $courseid) {
        global $DB, $CFG;
        $filteruserid = isset($this->params['filter_users']) ? $this->params['filter_users'] : $this->userid;
        $filtermoduleid = isset($this->params['filter_modules']) ? $this->params['filter_modules'] : 0;

        $where = " AND %placeholder% = $courseid";
        $concatsql = " ";
        if (!empty($filtermoduleid)) {
            $concatsql = " AND cm.module = $filtermoduleid";
        }
        $query = " ";
        $identity = " ";
        switch ($columnname) {
            case 'totalactivities' :
                $identity = 'cm.course';
                $query = "SELECT COUNT(cm.id) AS totalactivities
                              FROM {course_modules} cm
                             WHERE cm.visible = 1 AND cm.deletioninprogress = 0
                             $concatsql $where ";
            break;
            case 'completedactivities' :
                $identity = 'cm.course';
                $query = "SELECT COUNT(DISTINCT cmc.coursemoduleid) AS completedactivities
                               FROM {course_modules_completion} cmc
                               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                              WHERE cm.visible = 1  AND cmc.userid = $filteruserid AND cmc.completionstate > 0
                              AND cm.deletioninprogress = 0 $concatsql $where ";

            break;
            case 'inprogressactivities' :
                $identity = 'cm.course';
                $query = "SELECT COUNT(DISTINCT cm.id) AS inprogressactivities
                               FROM {course_modules} cm
                              WHERE  cm.visible = 1  AND cm.deletioninprogress = 0
                              AND cm.id NOT IN (SELECT coursemoduleid
                                                    FROM {course_modules_completion}
                                                    WHERE userid = " . $filteruserid . "  AND completionstate > 0)
                                                    $concatsql $where ";
            break;
            case 'grades' :
                $identity = 'gi.courseid';
                $modulename = $DB->get_field('modules', 'name', ['id' => $filtermoduleid]);
                $gradesql = "SELECT CASE WHEN SUM(gi.grademax) > 0
                    THEN  CASE WHEN SUM(gg.finalgrade) > 0 THEN
                    CONCAT(ROUND(SUM(gg.finalgrade), 2),' / ', ROUND(SUM(gi.grademax), 2))
                    ELSE CONCAT(0,' / ', ROUND(SUM(gi.grademax), 2)) END
                    ELSE '--' END
                               FROM {grade_grades} gg
                               JOIN {grade_items} gi ON gi.id = gg.itemid
                              WHERE gg.userid = $filteruserid  $where ";
                if (!empty($filtermoduleid)) {
                    $gradesql .= " AND gi.itemmodule = '$modulename' AND gi.itemtype != 'course'";
                } else {
                    $gradesql .= " AND gi.itemtype = 'course'";
                }
                $query = $gradesql;
            break;
            case 'totaltimespent' :
                $identity = 'blc.courseid';
                if (!empty($filtermoduleid)) {
                    $query = " SELECT SUM(blc.timespent) AS totaltimespent
                            FROM {block_ls_modtimestats} blc
                            JOIN {course_modules} cm ON cm.id = blc.activityid
                            WHERE blc.userid > 2 AND cm.deletioninprogress = 0
                            AND cm.visible = 1
                            AND blc.userid = $filteruserid  $where $concatsql ";
                } else {
                    $query = " SELECT SUM(blc.timespent) AS totaltimespent
                            FROM {block_ls_coursetimestats} blc
                            WHERE blc.userid > 2 AND blc.userid = $filteruserid $where ";
                }
                break;
        }
        $query = str_replace('%placeholder%', $identity, $query);
        return $query;
    }
}
