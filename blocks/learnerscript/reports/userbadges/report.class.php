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
 * User badges report
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
use block_learnerscript\local\ls as ls;
use context_system;
use badge;
use html_writer;
use stdClass;

/**
 * report_userbadges
 */
class report_userbadges extends reportbase implements report {

    /** @var array $searchable  */
    public $searchable;

    /** @var array $orderable  */
    public $orderable;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /**
     * Report construct function
     * @param object $report           User badges report data
     * @param object $reportproperties Report properties
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);

        if ($this->role != $this->currentrole) {
            $this->basicparams = [['name' => 'users']];
        }
        $this->columns = ['userbadges' => ['name', 'issuername', 'coursename', 'timecreated',
                        'dateissued', 'description', 'criteria', 'expiredate', ], ];
        $this->components = ['columns', 'filters', 'permissions', 'plot'];
        $this->filters = ['courses'];
        if (isset($this->role) && $this->role == $this->currentrole) {
            $this->parent = true;
        } else {
            $this->parent = false;
        }
        $this->orderable = ['name', 'issuername', 'timecreated', 'dateissued', 'description',
                        'expiredate', ];
        $this->defaultcolumn = 'b.id';
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
    }

    /**
     * Count SQL
     */
    public function count() {
        $this->sql  = "SELECT COUNT(bi.id) ";
    }

    /**
     * Select SQL
     */
    public function select() {
        $this->sql = "SELECT bi.id, b.courseid, bi.userid, b.id as badgeid, c.fullname ";
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
            if (in_array('dateissued', $this->selectedcolumns)) {
                $this->sql .= ", bi.dateissued AS dateissued";
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
        $this->sql .= " FROM {badge_issued} bi";
    }

    /**
     * SQl Joins
     */
    public function joins() {
        parent::joins();

        $this->sql .= " JOIN {badge} b ON b.id = bi.badgeid
                        LEFT JOIN {course} c ON b.courseid = c.id AND c.visible = 1";

        if (!is_siteadmin($this->userid) && !has_capability('block/learnerscript:managereports', $context)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND b.courseid IN ($this->rolewisecourses) ";
            }
        }
    }

    /**
     * Adding conditions to the query
     */
    public function where() {
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->sql .= " WHERE  bi.visible = 1 AND b.status != 0 AND b.status != 2 AND b.status != 4
                        AND bi.userid = $userid";
        if ($this->lsstartdate >= 0 && $this->lsenddate) {
            $this->params['lsfstartdate'] = round($this->lsstartdate);
            $this->params['lsfenddate'] = round($this->lsenddate);
            $this->sql .= " AND bi.dateissued BETWEEN :lsfstartdate AND :lsfenddate ";
        }
        parent::where();
    }

    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        $context = context_system::instance();
        if (isset($this->search) && $this->search) {
            $this->searchable = ['b.name', 'c.fullname'];
            $statsql = [];
            foreach ($this->searchable as $value) {
                $statsql[] = $DB->sql_like($value, "'%" . $this->search . "%'", false,
                                true, false);
            }
            $fields = implode(" OR ", $statsql);
            $this->sql .= " AND ($fields) ";
        }
        if ((!is_siteadmin() || $this->scheduling) && !has_capability('block/learnerscript:managereports', $context)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND b.courseid IN ($this->rolewisecourses) ";
            }
        }
    }

    /**
     * Concat filters to the query
     */
    public function filters() {
        if (!empty($this->params['filter_courses']) && $this->params['filter_courses'] <> SITEID
            && !$this->scheduling) {
            $this->params['courseid'] = $this->params['filter_courses'];
            $this->sql .= " AND b.courseid = :courseid";
        }
    }

    /**
     * Concat groupby to SQL
     */
    public function groupby() {

    }

    /**
     * Get report data rows
     * @param  array  $badges Badges
     * @return array
     */
    public function get_rows($badges) {
        global $DB, $CFG, $PAGE, $USER;
        $context = context_system::instance();
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $data = [];
        if (!empty($badges) && !empty($this->selectedcolumns)) {
            foreach ($badges as $badge) {
                $batchinstance = new badge($badge->badgeid);
                $context = $batchinstance->get_context();
                $badgeimage = print_badge_image($batchinstance, $context);
                $getcriteria = $PAGE->get_renderer('core_badges');
                $criteria = $getcriteria->print_badge_criteria($batchinstance);
                $courserecord = $DB->get_record('course', ['id' => $badge->courseid]);
                $activityinforeport = new stdClass();
                $params = [];
                $params['userid'] = $userid;
                if ($this->lsstartdate >= 0 && $this->lsenddate) {
                    $params['startdate'] = $this->lsstartdate;
                    $params['enddate'] = $this->lsenddate;
                }
                $activityinforeport->name = html_writer::link(new \moodle_url(
                '/badges/overview.php', ['id' => $badge->badgeid]), $badgeimage.'  '.$badge->name,
                ['class' => 'edit', 'target' => '_blank']);
                $activityinforeport->issuername = $badge->issuername;
                if ($badge->courseid = false || empty($badge->courseid)) {
                    $activityinforeport->coursename = $courserecord->fullname ? $courserecord->fullname : 'System';
                } else {
                    $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'courseprofile'], IGNORE_MULTIPLE);
                    $permissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($context, $USER->id);
                    if (empty($reportid) || empty($permissions)) {
                        $activityinforeport->coursename = html_writer::link(new \moodle_url(
                                                            '/course/view.php',
                                                            ['id' => $courserecord->id]),
                                                            ($courserecord->fullname ? $courserecord->fullname : 'System'),
                                                            ['target' => '_blank', 'class' => 'edit']);
                    } else {
                        $activityinforeport->coursename = html_writer::link(new \moodle_url(
                                                            '/blocks/reportdashboard/courseprofile.php',
                                                            ['filter_courses' => $courserecord->id]),
                                                            ($courserecord->fullname ? $courserecord->fullname : 'System'),
                                                            ['target' => '_blank', 'class' => 'edit']);
                    }
                }
                $activityinforeport->timecreated = date('l, d F Y H:i A', $badge->timecreated);
                $activityinforeport->dateissued = date('l, d F Y H:i A', $badge->dateissued);
                if (!empty($badge->expiredate)) {
                    $activityinforeport->expiredate = date('l, d F Y H:i A', $badge->expiredate);
                } else {
                    $activityinforeport->expiredate = "--";
                }
                $activityinforeport->description = $badge->description;
                $activityinforeport->criteria = $criteria;
                $data[] = $activityinforeport;
            }
        }
        return $data;
    }
}
