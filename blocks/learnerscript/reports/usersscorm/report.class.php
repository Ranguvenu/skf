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
 * Course Users scorm report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\ls as ls;

/**
 * Users scorm report class
 */
class report_usersscorm extends reportbase {

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /** @var int $categoriesid  */
    public $categoriesid;

    /**
     * Report constructor
     * @param object $report Report object
     */
    public function __construct($report) {
        global $USER;
        parent::__construct($report);
        $this->components = ['columns', 'permissions', 'filters', 'plot'];
        $this->parent = false;
        $this->columns = ['userfield' => ['userfield'] , 'usersscormcolumns' => ['inprogress',
            'completed', 'notattempted', 'total', 'lastaccess', 'firstaccess', 'totaltimespent', ], ];
        $this->basicparams = [['name' => 'courses']];
        $this->courselevel = true;
        $this->filters = ['users'];
        $this->orderable = ['fullname', 'inprogress', 'completed', 'notattempted', 'totaltimespent', 'firstaccess', 'lastaccess'];
        $this->searchable = ["CONCAT(u.firstname, ' ', u.lastname)", "u.email"];
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = ["'student'"];

    }

    /**
     * Report init
     */
    public function init() {
        if (!isset($this->params['filter_courses'])) {
            $this->initial_basicparams('courses');
            $fcourses = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($fcourses);
        }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
        $this->categoriesid = isset($this->params['filter_coursecategories']) ? $this->params['filter_coursecategories'] : 0;
    }
    /**
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT u.id)";
    }
    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT u.id , CONCAT(u.firstname,' ',u.lastname) AS fullname, c.id AS course ";
        parent::select();
    }
    /**
     * Form SQL
     */
    public function from() {
        $this->sql .= " FROM {user} u
                        JOIN {role_assignments} ra ON ra.userid = u.id
                        JOIN {context} con ON con.id = ra.contextid
                        JOIN {course} c ON c.id = con.instanceid
                        JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'
                        LEFT JOIN {scorm_attempt} sa ON sa.userid = u.id
                        LEFT JOIN {scorm_scoes_value} ssv ON ssv.attemptid = sa.id
                        LEFT JOIN {scorm_element} se ON se.id = ssv.elementid";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " ";
        parent::joins();
    }
    /**
     * Adding conditions to the query
     */
    public function where() {
        global $DB;
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->params['studentroleid'] = $studentroleid;
        $this->sql .= " WHERE ra.roleid = :studentroleid AND ra.contextid = con.id
                        AND u.confirmed = 1 AND u.deleted = 0 ";
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
            $statsql = [];
            foreach ($this->searchable as $key => $value) {
                $statsql[] = $DB->sql_like($value, "'%" . $this->search . "%'", $casesensitive = false, $accentsensitive =
                 true, $notlike = false);
            }
            $fields = implode(" OR ", $statsql);
            $this->sql .= " AND ($fields) ";
        }
    }
    /**
     * Concat filters to the query
     */
    public function filters() {
        global $DB, $CFG;
        $userid = isset($this->params['filter_users']) ? $this->params['filter_users'] : [];
        if (!empty($userid) && $userid != '_qf__force_multiselect_submission') {
            is_array($userid) ? $userid = implode(',', $userid) : $userid;
            $this->params['userid'] = $userid;
            $this->sql .= " AND u.id IN (:userid)";
        }
        if ($this->params['filter_courses'] <> SITEID) {
            $this->sql .= " AND c.id IN (:filter_courses)";
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            if ($CFG->dbtype == 'pgsql') {
                $this->sql .= " AND se.element = 'x.start.time' AND ssv.value::INTEGER BETWEEN :lsfstartdate AND :lsfenddate ";
            } else {
                $this->sql .= " AND se.element = 'x.start.time' AND ssv.value BETWEEN :lsfstartdate AND :lsfenddate ";
            }
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
    public function get_rows($users) {
        return $users;
    }
    /**
     * Columns quesries
     * @param  array $columnname Columns names
     * @param  int $userid User id
     * @return string
     */
    public function column_queries($columnname, $userid) {
        global $DB;
        $coursesql  = (new querylib)->get_learners($userid, '');
        $where = " AND %placeholder% = $userid";
        $filtercourseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : SITEID;
        $query = " ";
        $identy = " ";
        switch ($columnname) {
            case 'inprogress':
                $identy = 'st.userid';
                $query  = "SELECT COUNT(DISTINCT s.id) AS inprogress
                                FROM {scorm} as s
                                JOIN {scorm_attempt} st ON st.scormid = s.id
                                JOIN {course_modules} AS cm ON cm.instance = s.id AND cm.visible =1 AND cm.deletioninprogress = 0
                                JOIN {role_assignments} AS ra ON st.userid = ra.userid
                                JOIN {role} as r on r.id = ra.roleid AND r.shortname='student'
                                JOIN {context} AS ctx ON ctx.id = ra.contextid AND ctx.instanceid =cm.course
                                JOIN {course} as c ON c.id = ctx.instanceid  AND c.visible =1
                                JOIN {modules} AS m ON m.id = cm.module AND m.name = 'scorm'
                                WHERE s.id NOT IN
                                    (SELECT s.id
                                       FROM {scorm} as s
                                       JOIN {course_modules} as cm ON cm.instance = s.id
                                       JOIN {course} as c ON c.id = cm.course
                                       JOIN {modules} as m ON m.id = cm.module
                                       JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                                       AND cmc.completionstate > 0
                                      WHERE cm.visible = 1 AND cm.deletioninprogress = 0 AND c.visible = 1 AND m.name = 'scorm'
                                      AND cmc.userid = st.userid AND cm.course IN ($coursesql))
                                AND s.course = $filtercourseid $where ";
                break;
            case 'notattempted':
                $identy = 'ra.userid';
                $query  = "SELECT COUNT(DISTINCT cm.instance) AS notattempted
                            FROM {course} AS c
                            JOIN {context} AS con ON con.contextlevel = 50 AND c.id = con.instanceid
                            JOIN {role_assignments} AS ra ON con.id = ra.contextid AND ra.roleid = 5
                            JOIN {course_modules} AS cm ON cm.course = c.id
                            JOIN {modules} AS m ON m.id = cm.module
                            WHERE con.id = ra.contextid AND cm.visible = 1 AND cm.deletioninprogress = 0 AND m.name = 'scorm'
                            AND c.visible = 1
                            AND cm.instance NOT IN (SELECT sa.scormid
                                                    FROM {scorm_attempt} sa
                                                    JOIN {scorm_scoes_value} ssv ON ssv.attemptid = sa.id
                                                    JOIN {scorm_element} se ON se.id = ssv.elementid WHERE 1 = 1
                                                    AND se.element = 'x.start.time' AND sa.userid = ra.userid)
                            AND cm.instance NOT IN (SELECT DISTINCT s.id
                                    FROM {scorm} as s
                                    JOIN {course_modules} as cm ON cm.instance = s.id
                                    JOIN {modules} as m ON m.id = cm.module
                                    JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.completionstate > 0
                                    JOIN {role_assignments} AS ra ON cmc.userid = ra.userid
                                    JOIN {role} as r on r.id=ra.roleid AND r.shortname='student'
                                    JOIN {context} AS ctx ON ctx.id = ra.contextid AND ctx.instanceid =cm.course
                                    JOIN {course} as c ON c.id = ctx.instanceid
                                    WHERE cm.visible = 1 AND cm.deletioninprogress = 0 AND c.visible = 1 AND m.name = 'scorm'
                                    AND cmc.userid = ra.userid) AND cm.course = $filtercourseid
                                    $where ";
            break;
            case 'completed':
                $identy = 'cmc.userid';
                $query  = "SELECT COUNT(DISTINCT s.id) AS completed
                            FROM {scorm} as s
                            JOIN {course_modules} as cm ON cm.instance = s.id
                            JOIN {modules} as m ON m.id = cm.module
                            JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.completionstate > 0
                            JOIN {role_assignments} AS ra ON cmc.userid = ra.userid
                            JOIN {role} as r on r.id=ra.roleid AND r.shortname='student'
                            JOIN {context} AS ctx ON ctx.id = ra.contextid AND ctx.instanceid =cm.course
                            JOIN {course} as c ON c.id = ctx.instanceid
                            WHERE cm.visible = 1 AND cm.deletioninprogress = 0 AND c.visible = 1 AND m.name = 'scorm'
                            AND s.course = $filtercourseid $where ";
            break;
            case 'firstaccess':
                $identy = 'sa.userid';
                $query = "SELECT MIN(ssv.value)
                FROM mdl_scorm_attempt sa
                JOIN mdl_scorm_scoes_value ssv ON ssv.attemptid = sa.id
                JOIN mdl_scorm_element se ON se.id = ssv.elementid
                JOIN mdl_scorm s ON s.id = sa.scormid
                WHERE 1 = 1 AND se.element = 'x.start.time' AND s.course = $filtercourseid $where";
            break;
            case 'totalscorms':
                $identy = 'ue.userid';
                $query = "SELECT COUNT(DISTINCT cm.id) AS totalscorms
                            FROM {course} AS c
                            JOIN {context} AS con ON con.contextlevel = 50 AND c.id = con.instanceid
                            JOIN {role_assignments} AS ra ON con.id = ra.contextid AND ra.roleid = 5
                            JOIN {course_modules} AS cm ON cm.course = c.id
                            JOIN {modules} AS m ON m.id = cm.module
                            JOIN {scorm} AS s ON s.course = c.id
                            LEFT JOIN {scorm_attempt} AS st ON st.scormid = s.id
                            JOIN {scorm_scoes} ss ON ss.scorm = s.id
                            WHERE con.id = ra.contextid AND cm.visible = 1 AND cm.deletioninprogress = 0
                            AND c.visible = 1 AND m.name = 'scorm' AND c.id = $filtercourseid $where ";
            break;
            case 'totaltimespent':
                $identy = 'mt.userid';
                $query = "SELECT SUM(mt.timespent) AS totaltimespent  FROM {block_ls_modtimestats} AS mt
                         JOIN {course_modules} cm ON cm.id = mt.activityid
                         JOIN {modules} m ON m.id = cm.module WHERE m.name='scorm' AND mt.courseid = $filtercourseid $where ";
            break;
            case 'numviews':
                $identy = 'lsl.userid';
                $query = "SELECT COUNT(DISTINCT lsl.id) AS numviews
                                        FROM {logstore_standard_log} lsl
                                        JOIN {course_modules} cm ON cm.id = lsl.contextinstanceid
                                        JOIN {modules} m ON m.id = cm.module
                                        WHERE m.name = 'scorm' AND lsl.crud = 'r' AND lsl.contextlevel = 70 AND lsl.anonymous = 0
                                        AND cm.course IN ($filtercourseid)
                                        AND lsl.target = 'course_module' $where";
            break;

            default:
            return false;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }
}
