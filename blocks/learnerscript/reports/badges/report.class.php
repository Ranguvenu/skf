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
 * Badges report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnerscript\lsreports;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/badgeslib.php');
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls as ls;
use context_system;
use badge;
use completion_info;
use stdClass;
use html_writer;
use moodle_url;
/**
 * Badges report
 */
class report_badges extends reportbase implements report {

    /** @var array $orderable  */
    public $orderable;
    /**
     * @var array $basicparamdata Basic params list
     */
    public $basicparamdata;

    /**
     * Bagdes report constructor
     *
     * @param  object $report
     * @param  object $reportproperties
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->columns = ['badges' => ['name', 'issuername', 'coursename', 'timecreated',
        'description', 'criteria', 'recipients', 'expiredate', ], ];
        $this->parent = true;
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $this->courselevel = false;
        $this->filters = ['courses'];
        $this->orderable = ['name'];
        $this->defaultcolumn = 'b.id';
        $this->excludedroles = ["'student'"];
    }
    /**
     * Count sql
     */
    public function count() {
        $this->sql  = "SELECT COUNT(DISTINCT b.id) ";
    }
    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT b.id, b.courseid, b.attachment, b.issuerurl, b.usercreated, b.expireperiod ";
        if (!empty($this->selectedcolumns)) {
            if (in_array('name', $this->selectedcolumns)) {
                $this->sql .= ", b.name AS name";
            }
            if (in_array('issuername', $this->selectedcolumns)) {
                $this->sql .= ", b.issuername AS issuername";
            }
            if (in_array('timecreated', $this->selectedcolumns)) {
                $this->sql .= ", b.timecreated AS timecreated";
            }
            if (in_array('description', $this->selectedcolumns)) {
                $this->sql .= ", b.description AS description";
            }
            if (in_array('expiredate', $this->selectedcolumns)) {
                $this->sql .= ", b.expiredate AS expiredate";
            }
        }
    }
    /**
     * From SQL
     */
    public function from() {
        $this->sql .= " FROM {badge} b";
    }
    /**
     * SQL JOINS
     */
    public function joins() {
        $this->sql .= " LEFT JOIN {course} c ON c.id = b.courseid AND c.visible = 1";
    }
    /**
     * SQL Where condition
     */
    public function where() {
        $this->sql .= " WHERE b.status != 0 AND b.status != 2 AND b.status != 4";
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND b.timecreated BETWEEN :lsfstartdate AND :lsfenddate ";
        }
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND b.courseid IN ($this->rolewisecourses) ";
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
            $this->searchable = ['b.issuername', 'b.name', 'c.fullname'];
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
        if (!empty($this->params['filter_courses']) && $this->params['filter_courses'] <> SITEID  && !$this->scheduling) {
            $this->sql .= " AND b.courseid IN (:filter_courses)";
        }
    }
    /**
     * Concat group by to the query
     */
    public function groupby() {

    }
    /**
     * This function gets the report rows
     * @param  array  $badges Badges list
     * @return array
     */
    public function get_rows($badges) {
        global $DB, $CFG, $PAGE;
        $systemcontext = context_system::instance();
        $data = [];
        if (!empty($badges)) {
            foreach ($badges as $badge) {
                if (!$badge->id) {
                    continue;
                }
                $batchinstance = new badge($badge->id);
                $context = $batchinstance->get_context();
                $badgeimage = print_badge_image($batchinstance, $context);
                $getcriteria = $PAGE->get_renderer('core_badges');
                $criteria = $getcriteria->print_badge_criteria($batchinstance);
                $courserecord = $DB->get_record('course', ['id' => $badge->courseid]);
                $activityinforeport = new stdClass();
                $recipients = $DB->count_records_sql('SELECT COUNT(b.userid)
                                        FROM {badge_issued} b INNER JOIN {user} u ON b.userid = u.id
                                        WHERE b.badgeid = :badgeid AND u.deleted = 0 AND u.confirmed = 1
                                        ', ['badgeid' => $badge->id]);
                if ($this->lsstartdate >= 0 && $this->lsenddate) {
                    $params['startdate'] = $this->lsstartdate;
                    $params['enddate'] = $this->lsenddate;
                }
                $activityinforeport->name = html_writer::link(
                    new moodle_url('/badges/overview.php', ['id' => $badge->id]),
                    $badgeimage . ' ' . $badge->name,
                    ['target' => '_blank', 'class' => 'edit']
                );
                $activityinforeport->issuername = $badge->issuername;
                if ($badge->courseid = null || empty($badge->courseid)) {
                    $activityinforeport->coursename = $courserecord->fullname ? $courserecord->fullname : 'System';
                } else {
                    $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'courseprofile'], IGNORE_MULTIPLE);
                    $profilepermissions = empty($reportid) ? false :
                    (new reportbase($reportid))->check_permissions($systemcontext, $this->userid);
                    if (empty($reportid) || empty($profilepermissions)) {
                        $activityinforeport->coursename = html_writer::link(
                            new moodle_url('/course/view.php', ['id' => $courserecord->id]),
                            $courserecord->fullname
                        );
                    } else {
                        $activityinforeport->coursename = html_writer::link(
                            new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $reportid,
                                'filter_courses' => $courserecord->id]),
                            ($courserecord->fullname ? $courserecord->fullname : 'System'),
                            ['target' => '_blank', 'class' => 'edit']
                        );
                    }
                }

                $activityinforeport->timecreated = date('l, d F Y H:i A', $badge->timecreated);
                if (!empty($badge->expiredate)) {
                    $badgeexpiredate = date('l, d F Y', $badge->expiredate);
                } else if (!empty($badge->expireperiod)) {
                    if ($badge->expireperiod < 60) {
                        $badgeexpiredate = get_string('expireperiods', 'badges', round($badge->expireperiod, 2));
                    } else if ($badge->expireperiod < 60 * 60) {
                        $badgeexpiredate = get_string('expireperiodm', 'badges', round($badge->expireperiod / 60, 2));
                    } else if ($badge->expireperiod < 60 * 60 * 24) {
                        $badgeexpiredate = get_string('expireperiodh', 'badges', round($badge->expireperiod / 60 / 60, 2));
                    } else {
                        $badgeexpiredate = get_string('expireperiod', 'badges', round($badge->expireperiod / 60 / 60 / 24, 2));
                    }
                } else {
                    $badgeexpiredate = "--";
                }
                $activityinforeport->expiredate = $badgeexpiredate;
                $activityinforeport->description = $badge->description;
                $activityinforeport->criteria = $criteria;
                $activityinforeport->recipients = html_writer::link(new moodle_url('/badges/recipients.php', ['id' => $badge->id]),
                    $recipients,
                    ['target' => '_blank', 'class' => 'edit']
                );
                $data[] = $activityinforeport;
            }
        }
        return $data;
    }
}
