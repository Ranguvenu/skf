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
 * Resources accessed
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;
use context_system;
use stdClass;
use html_writer;
use moodle_url;
/**
 * Resources accessed report
 */
class report_resources_accessed extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;
    /**
     * Report constructor
     * @param object $report Report object
     */
    public function __construct($report) {
        parent::__construct($report);
        $this->components = ['columns', 'permissions', 'filters', 'plot'];
        $this->parent = false;
        $this->columns = ['resourcesaccessed' => ['userfullname', 'email', 'category', 'coursefullname',
        'action', 'lastaccess', 'activityname', 'activitytype', ], ];
        $this->courselevel = false;
        $this->basicparams = [['name' => 'courses']];
        $this->filters = ['modules'];
        $this->orderable = ['userfullname', 'email', 'category', 'coursefullname', 'courseshortname'];
        $this->defaultcolumn = 'u.id, cm.id';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Resource accessed report init function
     */
    public function init() {
        $this->params['contextlevel'] = 70;
        $this->params['target'] = 'course_module';
        $this->params['action'] = 'viewed';
        if (!isset($this->params['filter_courses']) && $this->params['filter_courses'] > SITEID) {
            $this->initial_basicparams('courses');
            $filterdata = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($filterdata);
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
        $this->sql  = "SELECT COUNT(DISTINCT (CONCAT(u.id,'-', cm.id))) ";
    }
    /**
     * SELECT SQL
     */
    public function select() {
        $this->sql = " SELECT CONCAT(u.id,'-', cm.id), lsl.userid, u.lastname, c.id as courseid,
        c.shortname, c.idnumber, lsl.component";
        $this->selectedcolumns = $this->selectedcolumns ? $this->selectedcolumns : [];

        if (in_array('email', $this->selectedcolumns)) {
            $this->sql .= ", u.email as email";
        }
        if (in_array('userfullname', $this->selectedcolumns)) {
            $this->sql .= ", u.firstname as userfullname";
        }
        if (in_array('category', $this->selectedcolumns)) {
            $this->sql .= ", cc.name as categoryname";
        }
        if (in_array('coursefullname', $this->selectedcolumns)) {
            $this->sql .= ", c.fullname as coursefullname";
        }
        if (in_array('action', $this->selectedcolumns)) {
            $this->sql .= ", lsl.action as action";
        }
        if (in_array('lastaccess', $this->selectedcolumns)) {
            $this->sql .= ", MAX(lsl.timecreated) as timecreated ";
        }
        if (in_array('activityname', $this->selectedcolumns)) {
            $this->sql .= ", CONCAT(
                                    COALESCE(b.name,''),
                                    COALESCE(files.name,''),
                                    COALESCE(folder.name,''),
                                    COALESCE(imscp.name,''),
                                    COALESCE(p.name,''),
                                    COALESCE(url.name,'')
                                    ) activity ";
        }
        if (in_array('activitytype', $this->selectedcolumns)) {
            $this->sql .= ", m.name as modulename ";
        }
    }
    /**
     * FROM SQL
     */
    public function from() {
        $this->sql .= " FROM {logstore_standard_log} lsl";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " JOIN {user} u ON u.id = lsl.userid
                    JOIN {course} c ON c.id = lsl.courseid
                    JOIN {course_categories} cc ON cc.id = c.category
                    JOIN {course_modules} cm ON cm.id = lsl.contextinstanceid AND cm.course = c.id
                    JOIN {modules} m ON m.id = cm.module
                    LEFT JOIN {book} b ON (b.id = lsl.objectid AND m.name = 'book')
                    LEFT JOIN {resource} files ON files.id = lsl.objectid AND m.name = 'resource'
                    LEFT JOIN {folder} folder ON folder.id = lsl.objectid AND m.name = 'folder'
                    LEFT JOIN {imscp} imscp ON imscp.id = lsl.objectid AND m.name = 'imscp'
                    LEFT JOIN {label} label ON label.id = lsl.objectid AND m.name = 'label'
                    LEFT JOIN {page} p ON p.id = lsl.objectid AND m.name = 'page'
                    LEFT JOIN {url} url ON url.id = lsl.objectid AND m.name = 'url' ";
    }
    /**
     * SQL Where condition
     */
    public function where() {
        $this->sql .= " WHERE u.id > 2
                         AND lsl.target = :target AND lsl.contextlevel = :contextlevel AND lsl.action = :action
                         AND m.name IN ('book', 'resource', 'folder', 'imscp', 'label', 'page', 'url')
                         AND c.visible = 1 AND cm.visible = 1 AND cm.deletioninprogress = 0
                         AND u.deleted = 0 AND u.confirmed = 1";
        $coursesql  = (new querylib)->get_learners('', 'lsl.courseid');
        $this->sql .= " AND lsl.userid IN ($coursesql)";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
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
            $this->searchable = ["CONCAT(u.firstname, ' ', u.lastname)", "u.email", "lsl.action",
            "c.fullname", "c.shortname", "c.idnumber", "cc.name", 'b.name', 'files.name',
            'folder.name', 'imscp.name', 'p.name', 'url.name', 'm.name', ];
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
        global $DB;
        if ($this->params['filter_courses'] > SITEID) {
            $this->sql .= " AND lsl.courseid IN (:filter_courses)";
        }
        if (isset($this->params['filter_modules']) && $this->params['filter_modules'] > 0) {
            $this->sql .= " AND m.id IN (:filter_modules) ";
        }

        if ($this->lsstartdate > 0 && $this->lsenddate) {
            $this->sql .= " AND lsl.timecreated BETWEEN :lsfstartdate AND :lsfenddate ";
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
        }
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {
        $this->sql .= " GROUP BY u.id, cm.id, lsl.userid, u.lastname, c.id ,
        c.shortname, c.idnumber, lsl.component, u.email, u.firstname, cc.name,
        c.fullname , lsl.action, b.name, files.name, folder.name, imscp.name, p.name, url.name, m.name ";
    }
    /**
     * Get rows
     * @param  array $logs logs
     * @return stdclass
     */
    public function get_rows($logs) {
        global $DB, $CFG, $OUTPUT, $USER;
        $systemcontext = context_system::instance();
        $data = [];
        if (!empty($logs)) {
            foreach ($logs as $log) {
                $report = new stdClass();
                $userrecord = $DB->get_record('user', ['id' => $log->userid]);
                $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'userprofile']);
                $userprofilepermissions = empty($reportid) ? false :
                (new reportbase($reportid))->check_permissions($systemcontext, $USER->id);
                if (empty($reportid) || empty($userprofilepermissions)) {
                    $report->userfullname .= $OUTPUT->user_picture($userrecord,
                    ['size' => 30]) .html_writer::tag('a', fullname($userrecord),
                    ['href' => new moodle_url('/user/profile.php', ['id' => $log->userid])]);
                } else {
                    $report->userfullname = $OUTPUT->user_picture($userrecord, ['size' => 30]) .
                    html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $reportid,
                    'filter_users' => $userrecord->id]), ucfirst(fullname($userrecord)), ["target" => "_blank"]);
                }
                $report->email = $log->email;
                $report->category = $log->categoryname;
                $report->coursefullname = $log->coursefullname;
                $report->courseid = $log->courseid;
                $report->courseidnumber = $log->idnumber ? $log->idnumber : '--';
                $report->activitytype = get_string('pluginname', $log->modulename);
                $report->activityname = $log->activity;
                $report->action = ucfirst($log->action);
                $report->lastaccess = userdate($log->timecreated);
                $data[] = $report;
            }
        }
        return $data;
    }
}
