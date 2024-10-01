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
 * User Activities report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;
use context_course;

/**
 * report_useractivities
 */
class report_useractivities extends reportbase implements report {
    /**
     * @var $aliases
     */
    private $aliases;
    /**
     * @var $activitynames
     */
    private $activitynames;
    /**
     * @var $activities
     */
    private $activities;

    /** @var array $searchable  */
    public $searchable;

    /** @var array $orderable  */
    public $orderable;

    /** @var array $excludedroles  */
    public $excludedroles;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /**
     * Report construct function
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);

        if ($this->role != $this->currentrole) {
            $this->basicparams = [['name' => 'users'], ['name' => 'courses']];
        } else {
            $this->basicparams = [['name' => 'courses']];
        }
        $this->parent = false;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $columns = ['modulename', 'highestgrade', 'lowestgrade', 'finalgrade', 'firstaccess', 'lastaccess',
                    'totaltimespent', 'numviews', 'completedon', 'completionstatus', ];
        $this->columns = ['activityfield' => ['activityfield'], 'useractivitiescolumns' => $columns];
        $this->filters = ['modules', 'activities'];
        $this->orderable = ['modulename', 'moduletype', 'highestgrade', 'lowestgrade',
                            'finalgrade', 'firstaccess', 'lastaccess', 'totaltimespent', 'numviews',
                            'completedon', 'completionstatus', ];
        $this->defaultcolumn = 'main.id';
    }

    /**
     * Report initialization
     */
    public function init() {
        global $DB;
        $filiteruserlists = [];

        if (!isset($this->params['filter_users'])) {
            $this->initial_basicparams('users');
            $this->params['filter_courses'] = isset($this->params['filter_courses'])
                    && $this->params['filter_courses'] > SITEID ? $this->params['filter_courses'] : SITEID;
            $coursecontext = context_course::instance($this->params['filter_courses']);
            $enrolledusers = array_keys(get_enrolled_users($coursecontext));
            $filiteruserlists = [];
            if (!empty($enrolledusers)) {
                $enrolledusers = implode(',', $enrolledusers);
                $filiteruserlists = $DB->get_records_sql_menu("SELECT id, concat(firstname,' ',lastname) as name
                    FROM {user} WHERE deleted = :deleted AND confirmed = :confirmed
                    AND id IN ($enrolledusers)", ['deleted' => 0, 'confirmed' => 1]);
            }
            if (is_siteadmin()) {
                $userfilter = array_keys($filiteruserlists);
                $this->params['filter_users'] = array_shift($userfilter);
            } else {
                $this->params['filter_users'] = $this->userid;
            }
        }
        if (!isset($this->params['filter_courses'])) {
            $this->initial_basicparams('courses');
            if (is_siteadmin()) {
                $userslist = array_keys($filiteruserlists);
                $this->params['filter_users'] = array_shift($userslist);
            } else {
                $this->params['filter_courses'] = $this->courseid;
            }
        }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }

        $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
        $this->aliases = [];
        foreach ($modules as $modulename) {
            $this->aliases[] = $modulename;
            $this->activities[] = "'$modulename'";
            $fields1[] = "COALESCE($modulename.name,'')";
        }
        $this->activitynames = implode(',', $fields1);
    }

    /**
     * Count SQL
     */
    public function count() {
        $this->sql   = "SELECT COUNT(DISTINCT main.id)";
    }

    /**
     * Select SQL
     */
    public function select() {
        global $DB;
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
        $this->aliases = [];
        foreach ($modules as $modulename) {
            $this->aliases[] = $modulename;
            $this->activities[] = "'$modulename'";
            $fields1[] = "COALESCE($modulename.name,'')";
        }
        $this->activitynames = implode(',', $fields1);
        $this->sql  = "SELECT DISTINCT main.id, m.id AS module, main.instance, main.section,
                    c.id AS courseid, u.id AS userid, c.category AS categoryid ";
        if (!empty($this->selectedcolumns)) {
            if (in_array('modulename', $this->selectedcolumns)) {
                $this->sql .= ", CONCAT($this->activitynames) AS modulename";
            }
            if (in_array('moduletype', $this->selectedcolumns)) {
                $this->sql .= ", m.name AS moduletype";
            }
            if (in_array('firstaccess', $this->selectedcolumns)) {
                $this->sql .= ", (SELECT MIN(lsl.timecreated) FROM {logstore_standard_log} lsl
                WHERE lsl.contextinstanceid = main.id AND lsl.userid = u.id ) AS firstaccess";
            }
            if (in_array('lastaccess', $this->selectedcolumns)) {
                $this->sql .= ", (SELECT MAX(lsl.timecreated) FROM {logstore_standard_log} lsl
                WHERE lsl.contextinstanceid = main.id AND lsl.userid = u.id ) AS lastaccess";
            }
            if (in_array('completedon', $this->selectedcolumns)) {
                $this->sql .= ", (SELECT timemodified FROM {course_modules_completion}
                            WHERE completionstate <> 0 AND userid= $userid AND coursemoduleid = main.id) as completedon";
            }
            if (in_array('completionstatus', $this->selectedcolumns)) {
                $this->sql .= ", cmc.completionstate as completionstatus";
            }
        }

        parent::select();
    }

    /**
     * From Sql
     */
    public function from() {
        $this->sql .= " FROM {course_modules} main";
    }

    /**
     * Sql JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {modules} m ON main.module = m.id
                        JOIN {course} c ON c.id = main.course
                        JOIN {context} con ON c.id = con.instanceid
                        JOIN {role_assignments} ra ON ra.contextid = con.id
                        JOIN {user} u ON u.id = ra.userid ";
        foreach ($this->aliases as $alias) {
            $this->sql .= " LEFT JOIN {".$alias."} AS $alias ON $alias.id = main.instance AND m.name = '$alias'";
        }
        $this->sql  .= " LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = main.id AND cmc.userid = u.id";

        parent::joins();
    }

    /**
     * Adding condition to the Query
     */
    public function where() {
        global $DB, $USER;
        $userid = !empty($this->params['filter_users']) ? $this->params['filter_users'] : $this->userid;
        $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
        $this->aliases = [];
        foreach ($modules as $modulename) {
            $this->aliases[] = $modulename;
            $this->activities[] = "'$modulename'";
        }
        $status = isset($this->params['filter_status']) ? $this->params['filter_status'] : '';
        $this->params['subuserid'] = $this->params['filter_users'];
        $activitieslist = implode(',', $this->activities);
        $this->sql .= " WHERE u.deleted = 0 AND u.confirmed = 1 AND u.suspended = 0
                        AND c.visible = 1 AND u.deleted = 0 AND m.name IN ($activitieslist) AND main.visible = 1
                        AND main.deletioninprogress = 0";
        if ($status == 'Inprogress') {
            $this->params['userid'] = $userid;
            $this->sql .= " AND main.id NOT IN (SELECT coursemoduleid FROM {course_modules_completion}
                                                  WHERE completionstate <> 0 AND userid= :subuserid ) ";
        }
        if ($status == 'Completed') {
            $this->sql .= " AND main.id IN (SELECT coursemoduleid FROM {course_modules_completion}
                                              WHERE completionstate <> 0 AND userid= :subuserid ) ";
        }

        $this->sql .= " AND m.visible = 1";

        parent::where();
    }

    /**
     * Concat search values to query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
            foreach ($modules as $modulename) {
                $fields1[] = "COALESCE($modulename.name,'')";
            }
            $fields2 = ['m.name', 'c.fullname'];
            $this->searchable = array_merge($fields1, $fields2);
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
        if ($this->params['filter_courses'] <> SITEID) {
            $this->sql .= " AND main.course = :filter_courses";
        }
        if ($this->params['filter_users'] > 0) {
            $this->sql .= " AND u.id = :filter_users";
        }
        if (isset($this->params['filter_modules']) && $this->params['filter_modules'] > 0) {
            $this->sql .= " AND main.module = :filter_modules";
        }
        if (isset($this->params['filter_activities']) && $this->params['filter_activities'] > 0) {
            $this->sql .= " AND main.id = :filter_activities";
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND main.added BETWEEN :lsfstartdate AND :lsfenddate ";
        }
    }

    /**
     * Concat groupby to the sql
     */
    public function groupby() {

    }

    /**
     * Get report rows list
     * @param  array $activites Activites list
     * @return array
     */
    public function get_rows($activites) {
        return $activites;
    }

    /**
     * Report columns SQL queries
     * @param  string $columnname    Column name
     * @param  int $cmid Course module id
     * @return string
     */
    public function column_queries($columnname, $cmid) {
        $where = " AND %placeholder% = $cmid";
        $filteruserid = isset($this->params['filter_users']) ? $this->params['filter_users'] : 0;
        $query = " ";
        $identity = " ";
        switch ($columnname) {
            case 'finalgrade':
                $identity = 'cm1.id';
                $query = "SELECT SUM(gg.finalgrade)  AS finalgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id
                            JOIN {course_modules} cm1 ON gi.iteminstance = cm1.instance
                            JOIN {modules} m ON m.id = cm1.module
                           WHERE 1 = 1 AND gi.itemtype = 'mod' AND gi.itemmodule = m.name AND gg.userid = $filteruserid $where ";
            break;
            case 'highestgrade':
                $identity = 'cm.id';
                $query = "SELECT MAX(gg.finalgrade) AS highestgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id
                            JOIN {course_modules} cm ON gi.iteminstance = cm.instance
                            JOIN {modules} m ON m.id = cm.module
                           WHERE 1 = 1 AND gi.itemtype = 'mod' AND gi.itemmodule = m.name $where ";
            break;
            case 'lowestgrade':
                $identity = 'cm.id';
                $query = "SELECT MIN(gg.finalgrade) AS lowestgrade
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gg.itemid = gi.id
                            JOIN {course_modules} cm ON gi.iteminstance = cm.instance
                            JOIN {modules} m ON m.id = cm.module
                           WHERE 1 = 1 AND gi.itemtype = 'mod' AND gi.itemmodule = m.name $where ";
            break;
            case 'totaltimespent':
                $identity = 'mt.activityid';
                $query = "SELECT SUM(mt.timespent) AS totaltimespent FROM {block_ls_modtimestats} mt
                        WHERE mt.userid = $filteruserid $where ";
            break;
            case 'numviews':
                $identity = 'lsl.contextinstanceid';
                $query = "SELECT COUNT(lsl.id) AS numviews
                              FROM {logstore_standard_log} lsl
                         WHERE lsl.crud = 'r' AND lsl.contextlevel = 70 AND
                           lsl.userid = $filteruserid AND lsl.target = 'course_module' $where";
            break;
        }
        $query = str_replace('%placeholder%', $identity, $query);
        return $query;
    }
}
