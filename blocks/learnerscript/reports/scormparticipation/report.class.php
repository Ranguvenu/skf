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
 * Scorm participation report
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
 * Topic scorm participation class
 */
class report_scormparticipation extends reportbase {

    /** @var array $orderable  */
    public $orderable;
    /**
     * Report constructor
     * @param object $report Report object
     */
    public function __construct($report) {
        parent::__construct($report);
        $this->columns = ['scormparticipationcolumns' => ['username', 'course', 'scormname', 'attempt' ,
         'activitystate', 'finalgrade', 'firstaccess', 'lastaccess', 'totaltimespent', ], ];
        $this->parent = false;
        if (is_siteadmin() || $this->role != 'student') {
            $this->basicparams = [['name' => 'courses']];
        }
        $this->components = ['columns', 'filters', 'permissions', 'calcs', 'plot'];
        $this->courselevel = false;
        $this->orderable = ['username', 'course', 'scormname', 'attempt' ,
         'activitystate', 'finalgrade', 'firstaccess', 'lastaccess', 'totaltimespent', ];
        $this->defaultcolumn = "concat(s.id, '-', c.id, '-', ra.userid)";
    }
    /**
     * Report init
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
     * Count SQL
     */
    public function count() {
        $this->sql .= "SELECT COUNT(DISTINCT (CONCAT(s.id, '-', c.id, '-', ra.userid))) ";
    }
    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT(concat(s.id, '-', c.id, '-', ra.userid)), s.id ,c.id AS courseid,
                        ra.userid AS userid, cm.id AS cmid,
                        m.id AS moduleid, c.fullname AS course, s.name AS scormname, u.username as username, s.id as scormid";
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
        $this->sql .= " JOIN {user} u ON u.id =ra.userid
                    JOIN {role} r on r.id=ra.roleid AND r.shortname='student'
                   JOIN {context} ctx ON ctx.id = ra.contextid
                   JOIN {course} c ON c.id = ctx.instanceid
                   JOIN {scorm} s ON s.course = c.id
                   JOIN {course_modules} cm ON cm.instance = s.id
                   JOIN {modules} m ON m.id = cm.module
                   JOIN {scorm_scoes} ss ON ss.scorm = s.id
              LEFT JOIN {course_modules_completion} cc ON cc.coursemoduleid = cm.id";

        parent::joins();

    }
    /**
     * Adding conditions to the query
     */
    public function where() {
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : '';
        $this->params['userid'] = $userid;
        $this->sql .= " WHERE cm.visible = 1 AND
        cm.deletioninprogress = 0 AND c.visible = 1 AND m.name = 'scorm' AND u.id > 2 ";
        if (isset($this->params['filter_scorm']) && $this->params['filter_scorm']) {
            $this->sql .= " AND s.id = ".$this->params['filter_scorm'];
        }
        if (isset($this->params['filter_status']) && $this->params['filter_status'] == 'completed') {
            $this->sql .= " AND cc.userid = ra.userid AND cc.completionstate <> 0";
        }
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
            $this->searchable = ["c.fullname", "s.name", "u.username"];
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
     * Concat filters to the query
     */
    public function filters() {

        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : '';
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
     * Concat groupby to SQL
     */
    public function groupby() {
        $this->sql .= " GROUP BY s.id, c.id, ra.userid, cm.id, m.id, c.fullname, s.name, u.username";
    }
    /**
     * get rows
     * @param  array $elements
     * @return array
     */
    public function get_rows($elements) {
        return $elements;
    }

}
