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
 * Cohort Users Report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use context_system;
use stdClass;
use html_writer;
use moodle_url;

/**
 * Cohort users report
 */
class report_cohortusers extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;

    /**
     * Constructor for report.
     * @param object $report
     * @param object $reportproperties
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $this->columns = ['userfield' => ['userfield'], "cohortusercolumns" => ["learner", "email"]];
        $this->basicparams = [['name' => 'cohort']];
        $this->parent = false;
        $this->courselevel = false;
        $this->filters = ['users'];
        $this->orderable = ['learner', 'email'];
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Cohort users init function
     */
    public function init() {
        global $DB;
        if (!isset($this->params['filter_cohort'])) {
            $this->initial_basicparams('cohort');
            $fcohorts = array_keys($this->filterdata);
            $this->params['filter_cohort'] = array_shift($fcohorts);
        }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
        $userid = isset($this->params['filter_users']) ? $this->params['filter_users'] : $this->userid;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->params['userid'] = $userid;
        $this->params['ej1_active'] = ENROL_USER_ACTIVE;
        $this->params['ej1_enabled'] = ENROL_INSTANCE_ENABLED;
        $this->params['ej1_now1'] = round(time(), -2);
        $this->params['ej1_now2'] = $this->params['ej1_now1'];
        $this->params['roleid'] = $roleid;
    }
    /**
     * Count SQL
     */
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT u.id) ";
    }
    /**
     * SELECT SQL
     */
    public function select() {
        $this->sql = "SELECT DISTINCT u.id, u.picture, u.firstname, u.lastname,
        CONCAT(u.firstname , u.lastname) as learner, u.email, u.* ";
    }
    /**
     * FROM SQL
     */
    public function from() {
        $this->sql .= " FROM {user} u";
    }
    /**
     * JOINS SQL
     */
    public function joins() {
        parent::joins();
         $this->sql .= " JOIN {cohort_members} cmem ON cmem.userid = u.id ";
    }
    /**
     * SQL Where Condition
     */
    public function where() {
        $this->sql .= " WHERE 1 = 1";
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
        if (isset($this->params['filter_users'])
            && $this->params['filter_users'] > 0
            && $this->params['filter_users'] != '_qf__force_multiselect_submission') {
            $this->sql .= " AND u.id IN (:filter_users) ";
        }
        if (isset($this->params['filter_cohort']) && $this->params['filter_cohort'] > 0) {
            $this->sql .= " AND cmem.cohortid IN (:filter_cohort)";
        }
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->sql .= " AND u.timecreated BETWEEN :lsfstartdate AND :lsfenddate ";
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
        }
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {
    }
    /**
     * Get report rows
     * @param  object $elements Elements list
     * @return object|array
     */
    public function get_rows($elements) {
        global $DB, $CFG, $OUTPUT;
        $systemcontext = context_system::instance();
        $finalelements = new stdClass();
        if (!empty($elements)) {
            $courseid = isset($this->params['filter_cohort']) ? $this->params['filter_cohort'] : 0;
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
                    $report->learner = $OUTPUT->user_picture($userrecord, ['size' => 30])
                    .html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $reportid,
                        'filter_users' => $record->id]), ucfirst(fullname($userrecord)), ["target" => "_blank"]);
                }
                $report->email = $record->email;
                $report->id = $record->id;
                $sections = $DB->get_records_sql("SELECT DISTINCT c.id, c.fullname
                                FROM {course} c
                                JOIN {context} ctx ON ctx.instanceid = c.id
                                JOIN {role_assignments} ra ON ctx.id = ra.contextid
                                JOIN {cohort_members} cm ON cm.userid = ra.userid
                                JOIN {cohort} co ON co.id = cm.cohortid
                                WHERE co.id = :courseid
                                AND ctx.instanceid = c.id", ['courseid' => $courseid]);
                $i = 0;
                foreach ($sections as $section) {
                    $usercompletionsql = "SELECT cc.timecompleted
                    FROM {course_completions} cc
                    WHERE cc.userid = :recordid AND cc.course = :sectionid";
                    $usercompletion = $DB->get_field_sql($usercompletionsql,
                    ['recordid' => $record->id, 'sectionid' => $section->id]);
                    $sectionkey = "section$i";
                    if ($usercompletion > 0) {
                        $report->{$sectionkey} = html_writer::tag(
                            'span',
                            get_string('completed', 'block_learnerscript'),
                            ['class' => 'label label-success']
                        );
                    } else {
                        $report->{$sectionkey} = html_writer::tag(
                            'span',
                            get_string('not_completed', 'block_learnerscript'),
                            ['class' => 'label label-warning']
                        );
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
