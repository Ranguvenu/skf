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
 * My Scorm
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls as ls;
/**
 * My Scorm report
 */
class report_myscorm extends reportbase {

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
        $this->columns = ['myscormcolumns' => ['course', 'scormname', 'attempt' , 'activitystate',
        'finalgrade', 'firstaccess', 'lastaccess', 'totaltimespent', 'numviews', ], ];
        $this->parent = true;
        if (isset($this->role) && $this->role == 'student') {
            $this->parent = true;
        } else {
            $this->parent = false;
        }
        if (is_siteadmin() || $this->role != 'student') {
            $this->basicparams = [['name' => 'users']];
        }
        $this->components = ['columns', 'filters', 'permissions', 'calcs', 'plot'];
        $this->courselevel = false;
        $this->filters = ['courses'];
        $this->orderable = ['course', 'scormname', 'activitystate', 'attempt', 'finalgrade', 'totaltimespent', 'numviews'];
        $this->defaultcolumn = 's.id';
    }
    /**
     * My Scorm report init function
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
    }
    /**
     * COUNT SQL
     */
    public function count() {
        $this->sql .= "SELECT COUNT(DISTINCT s.id) ";
    }
    /**
     * SELECT SQL
     */
    public function select() {
            $userid = isset($this->params['userid']) ? $this->params['userid'] : 0;
            $this->sql = "SELECT DISTINCT s.id, c.id as courseid, ra.userid as userid, cm.id as cmid,
                                            m.id as moduleid, s.id as scormid, c.fullname as course, s.name as scormname";

            parent::select();

    }
    /**
     * FROM SQL
     */
    public function from() {
            $this->sql .= " FROM {role_assignments} ra";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {role} r on r.id=ra.roleid AND r.shortname='student'
                        JOIN {context} ctx ON ctx.id = ra.contextid
                        JOIN {course} c ON c.id = ctx.instanceid
                        JOIN {scorm} s ON s.course = c.id
                        JOIN {course_modules} cm ON cm.instance = s.id
                        JOIN {modules} m ON m.id = cm.module
                        JOIN {scorm_scoes} ss ON ss.scorm = s.id";

        parent::joins();
    }
    /**
     * SQL WHERE Conditions
     */
    public function where() {
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->params['userid'] = $userid;
        $this->sql .= " WHERE ra.userid = :userid  AND cm.visible = 1 AND
                                  cm.deletioninprogress = 0 AND c.visible = 1 AND m.name = 'scorm'";

        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
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
            $this->searchable = ["c.fullname", "s.name"];
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
     * Concat filter values to the query
     */
    public function filters() {
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->courseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : [];
        $this->params['userid'] = $userid;
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->sql .= " AND ra.timemodified BETWEEN :lsfstartdate AND :lsfenddate ";
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
        }
        if (!empty($this->courseid) && $this->courseid != '_qf__force_multiselect_submission') {
            $courseid = $this->courseid;
            $this->sql .= " AND c.id = :courseid";
            $this->params['courseid'] = $courseid;
        }
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {

    }
    /**
     * This function get the report rows
     * @param  array  $elements Elements list
     * @return array
     */
    public function get_rows($elements) {
        return $elements;
    }
    /**
     * This function returns the report columns queries
     * @param  string  $columnname
     * @param  int  $scormid Scormid
     * @return string
     */
    public function column_queries($columnname, $scormid) {
        global $CFG;
        $where = " AND %placeholder% = $scormid";
        $userid = isset($this->params['userid']) ? $this->params['userid'] : 0;
        $query = " ";
        $identy = " ";
        switch ($columnname) {
            case 'attempt':
                $identy = 'scormid';
                $query = "SELECT attempt as attempt
                            FROM {scorm_attempt} WHERE 1 = 1 $where
                             AND userid = $userid ORDER BY id DESC LIMIT 1  ";
                break;
            case 'activitystate':
                $identy = 'sa.scormid';
                $query = "SELECT ssv.value as activitystate FROM {scorm_scoes_value} ssv
                           JOIN {scorm_element} se ON se.id = ssv.elementid
                           JOIN {scorm_attempt} sa ON sa.id = ssv.attemptid
                           WHERE se.element = 'cmi.core.lesson_status'
                             AND sa.userid = $userid $where ORDER BY ssv.id DESC LIMIT 1 ";
            break;
            case 'finalgrade':
                $identy = 'gi.iteminstance';
                $query = "SELECT gg.finalgrade as finalgrade
                            FROM {grade_grades} gg JOIN {grade_items} gi ON gi.id = gg.itemid
                            WHERE gi.itemmodule = 'scorm' AND gg.userid = $userid $where";
            break;
            case 'firstaccess':
                $identy = 'scormid';
                $query = "SELECT ssv.value as activitystate FROM {scorm_scoes_value} ssv
                JOIN {scorm_element} se ON se.id = ssv.elementid
                JOIN {scorm_attempt} sa ON sa.id = ssv.attemptid
                WHERE se.element = 'x.start.time'
                  AND sa.userid = $userid $where ORDER BY sa.attempt ASC  LIMIT 1 ";
            break;
            case 'totaltimespent':
                $identy = 'cm.instance';
                $query = "SELECT SUM(mt.timespent) as totaltimespent
                            FROM {block_ls_modtimestats} as mt
                            JOIN {course_modules} cm ON cm.id = mt.activityid
                            JOIN {modules} m ON m.id = cm.module
                            WHERE m.name = 'scorm' AND mt.userid = $userid $where";
            break;
            case 'numviews':
                $identy = 'cm.instance';
                $query = "SELECT COUNT(lsl.id) as numviews
                              FROM {logstore_standard_log} lsl
                              JOIN {course_modules} cm ON cm.id = lsl.contextinstanceid
                              JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                              JOIN {user} u ON u.id = lsl.userid AND u.confirmed = 1 AND u.deleted = 0
                             WHERE lsl.crud = 'r' AND lsl.contextlevel = 70 AND lsl.anonymous = 0
                               AND lsl.userid = $userid AND lsl.target = 'course_module' $where ";
            break;
            default:
                return false;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }

}
