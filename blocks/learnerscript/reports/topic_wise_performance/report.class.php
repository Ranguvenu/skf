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
 * Topic wise performance report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use context_system;
use context_course;
use stdClass;
use html_writer;
use moodle_url;
/**
 * Topic wise performance class
 */
class report_topic_wise_performance extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;
    /** @var string $relatedctxsql  */
    private $relatedctxsql;

    /**
     * Report constructor
     * @param object $report Report object
     * @param object $reportproperties Report object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $this->columns = ["topicwiseperformance" => ["learner", "email"]];
        $this->basicparams = [['name' => 'courses']];
        $this->parent = false;
        $this->courselevel = true;
        $this->filters = ['users'];
        $this->orderable = ['learner', 'email'];
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Report init
     */
    public function init() {
        global $DB;
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
        $courseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : SITEID;
        $userid = isset($this->params['filter_users']) ? $this->params['filter_users'] : $this->userid;
        $context = context_course::instance($courseid);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $ctxs = $context->get_parent_context_ids(true);
        list($this->relatedctxsql, $params) =
        $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');
        $this->params = array_merge($this->params, $params);
        $this->params['contextlevel'] = CONTEXT_COURSE;
        $this->params['userid'] = $userid;
        $this->params['ej1_active'] = ENROL_USER_ACTIVE;
        $this->params['ej1_enabled'] = ENROL_INSTANCE_ENABLED;
        $this->params['ej1_now1'] = round(time(), -2); // Improves db caching.
        $this->params['ej1_now2'] = $this->params['ej1_now1'];
        $this->params['ej1_courseid'] = $courseid;
        $this->params['courseid'] = $courseid;
        $this->params['roleid'] = $roleid;
    }
    /**
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT u.id) ";
    }
    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT u.id, u.picture, u.firstname, u.lastname,
        CONCAT(u.firstname , u.lastname) as learner, u.email ";
    }
    /**
     * Form SQL
     */
    public function from() {
        $this->sql .= " FROM {user} u";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        parent::joins();
         $this->sql .= "  JOIN (SELECT DISTINCT eu1_u.id, ej1_ue.timecreated
                         FROM {user} eu1_u
                         JOIN {user_enrolments} ej1_ue ON ej1_ue.userid = eu1_u.id
                         JOIN {enrol} ej1_e ON (ej1_e.id = ej1_ue.enrolid AND ej1_e.courseid = :ej1_courseid)
                            WHERE 1 = 1 AND ej1_ue.status = :ej1_active AND ej1_e.status = :ej1_enabled AND
                            ej1_ue.timestart < :ej1_now1 AND (ej1_ue.timeend = 0 OR ej1_ue.timeend > :ej1_now2) AND
                             eu1_u.deleted = 0) e ON e.id = u.id
                         LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)";
    }
    /**
     * Adding conditions to the query
     */
    public function where() {
        $this->sql .= " WHERE u.id IN (SELECT userid
                        FROM {role_assignments}
                        WHERE roleid = :roleid AND contextid $this->relatedctxsql)";
        parent::where();
    }
    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $this->searchable = ["CONCAT(u.firstname, ' ', u.lastname)", "u.email"];
            $statsql = [[]];
            foreach ($this->searchable as $key => $value) {
                $statsql[] = $DB->sql_like($value, "'%" . $this->search . "%'", $casesensitive = false,
                $accentsensitive = true, $notlike = false);
            }
            $fields = implode(" OR ", $statsql);
            $this->sql .= " AND ($fields) ";
        }
    }
    /**
     * Concat filters to the query
     */
    public function filters() {
        $userid = isset($this->params['filter_users']) ? $this->params['filter_users'] : $this->userid;
        $this->params['userid'] = $userid;
        if (isset($userid) && $userid > 0) {
            $this->sql .= " AND u.id = :userid";
        }
        if (isset($this->params['filter_modules']) && $this->params['filter_modules'] > 0) {
            $this->sql .= " AND cm.module = :filter_modules";
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->sql .= " AND u.timecreated BETWEEN :ls_startdate AND :ls_enddate ";
            $this->params['ls_startdate'] = round($this->lsstartdate);
            $this->params['ls_enddate'] = round($this->lsenddate);
        }
    }
    /**
     * Concat groupby to SQL
     */
    public function groupby() {

    }
    /**
     * get rows
     * @param  array $elements
     * @return array
     */
    public function get_rows($elements) {
        global $DB, $CFG, $USER, $OUTPUT;
        $systemcontext = context_system::instance();
        $finalelements = [[]];
        if (!empty($elements)) {
            if (!empty($this->params['filter_courses']) && $this->params['filter_courses'] <> SITEID  && !$this->scheduling) {
                $courseid = $this->params['filter_courses'];
            }
            foreach ($elements as $record) {
                $report = new stdClass();
                $userrecord = $DB->get_record('user', ['id' => $record->id]);
                $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'userprofile'], IGNORE_MULTIPLE);
                $userprofilepermissions = empty($reportid) ? false :
                (new reportbase($reportid))->check_permissions($systemcontext, $this->userid);
                if (empty($reportid) || empty($userprofilepermissions)) {
                    $report->learner .= $OUTPUT->user_picture($userrecord, ['size' => 30])
                    .html_writer::tag('a', fullname($userrecord),
                    ['href' => new moodle_url('/user/profile.php', ['id' => $userrecord->id])]);

                } else {
                    $report->learner = $OUTPUT->user_picture($userrecord, ['size' => 30]) .
                     html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $reportid,
                        'filter_users' => $record->id]), ucfirst(fullname($userrecord)), ["target" => "_blank"]);
                }
                $report->email = $record->email;
                $sections = $DB->get_records_sql("SELECT * FROM {course_sections} WHERE course = :courseid ORDER BY id",
                ['courseid' => $courseid]);
                $i = 0;
                foreach ($sections as $section) {
                    $coursemodulesql = "SELECT SUM(gg.finalgrade) / SUM(gi.grademax) score
                                          FROM {grade_items} gi
                                          JOIN {grade_grades} gg ON gg.itemid = gi.id
                                          JOIN {course_modules} cm ON cm.instance = gi.iteminstance
                                          JOIN {modules} m ON m.id = cm.module AND m.name = gi.itemmodule
                                         WHERE cm.section = :sectionid AND gg.userid = :recordid AND cm.visible = :visible ";
                    $coursemodulescore = $DB->get_field_sql($coursemodulesql, ['sectionid' => $section->id,
                    'recordid' => $record->id, 'visible' => 1, ]);
                    $sectionkey = "section$i";
                    if ($coursemodulescore) {
                        $report->{$sectionkey} = (round($coursemodulescore * 100, 2)).' %';
                    } else {
                        $report->{$sectionkey} = '--';
                    }
                    $i++;
                }
                $data[] = $report;
            }
            return $data;
        }
        return $finalelements;
    }
}
