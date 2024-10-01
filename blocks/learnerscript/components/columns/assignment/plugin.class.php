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
 * A Moodle block for creating customizable reports
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\ls;
use context_system;
use html_writer;
use moodle_url;
/**
 * Assignment report columns
 */
class plugin_assignment extends pluginbase {

    /** @var string $role  */
    public $role;

    /**
     * @var array $reportinstance User role
     */
    public $reportinstance;

    /**
     * @var string $reportfilterparams User role
     */
    public $reportfilterparams;
    /**
     * Assignment column init function
     */
    public function init() {
        $this->fullname = get_string('assignment', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['assignment'];
    }
    /**
     * Assignment column summary
     * @param object $data Assignment column name
     * @return string
     */
    public function summary($data) {
        return format_string($data->columname);
    }
    /**
     * This function return field column format
     * @param object $data Field data
     * @return array
     */
    public function colformat($data) {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return [$align, $size, $wrap];
    }
    /**
     * This function executes the columns data
     * @param object $data Columns data
     * @param object $row Row data
     * @param string $reporttype Report type
     * @return object
     */
    public function execute($data, $row, $reporttype = null) {
        global $DB, $CFG, $USER;
        $context = context_system::instance();
        $activityid = $DB->get_field_sql("SELECT cm.id
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
        AND cm.instance = :rowid", ['rowid' => $row->id]);
        $assignmentid = $DB->get_field('block_learnerscript', 'id', ['type' => 'assignmentparticipation'], IGNORE_MULTIPLE);
        $checkpermissions = empty($assignmentid) ? false : (new reportbase($assignmentid))->check_permissions($context, $USER->id);
        switch ($data->column) {
            case 'submittedusers':
                if (!isset($row->submittedusers)) {
                    $submittedusers = $DB->get_field_sql($data->subquery);
                } else {
                    $submittedusers = $row->{$data->column};
                }

                if (empty($assignmentid) || empty($checkpermissions)) {
                    $row->{$data->column} = !empty($submittedusers) ? $submittedusers : '--';
                } else {
                    if (!empty($submittedusers)) {
                        $row->{$data->column} = html_writer::link(new \moodle_url('/blocks/learnerscript/viewreport.php',
                        ['id' => $assignmentid, 'filter_courses' => $row->course, 'filter_assignment' => $row->id,
                        'filter_status' => 'inprogress', ]), $submittedusers);
                    } else {
                        $row->{$data->column} = '--';
                    }
                }
                break;
            case 'completedusers':
                if (!isset($row->completedusers)) {
                    $completedusers = $DB->get_field_sql($data->subquery);
                } else {
                    $completedusers = $row->{$data->column};
                }
                if (empty($assignmentid) || empty($checkpermissions)) {
                    $row->{$data->column} = !empty($completedusers) ? $completedusers : '--';
                } else {
                    $row->{$data->column} = !empty($completedusers) ?
                    html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php', [
                        'id' => $assignmentid,
                        'filter_courses' => $row->course,
                        'filter_assignment' => $row->id,
                        'filter_status' => 'completed']), $completedusers) : '--';

                }
                break;
            case 'needgrading':
                if (!isset($row->needgrading)) {
                    $needgrading = $DB->get_field_sql($data->subquery);
                } else {
                    $needgrading = $row->{$data->column};
                }
                $row->{$data->column} = !empty($needgrading) ? $needgrading : '--';
                break;
            case 'avggrade':
                if (!isset($row->avggrade)) {
                    $avggrade = $DB->get_field_sql($data->subquery);
                } else {
                    $avggrade = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($avggrade) ? round($avggrade, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($avggrade) ? round($avggrade, 2) : 0;
                }
                break;
            case 'gradepass':
                if (!isset($row->gradepass)) {
                    $gradepass = $DB->get_field_sql($data->subquery);
                } else {
                    $gradepass = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($gradepass) ? round($gradepass, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($gradepass) ? round($gradepass, 2) : 0;
                }
                break;
            case 'grademax':
                if (!isset($row->grademax)) {
                    $grademax = $DB->get_field_sql($data->subquery);
                } else {
                    $grademax = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($grademax) ? round($grademax, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($grademax) ? round($grademax, 2) : 0;
                }
                break;
            case 'totaltimespent':
                if (!isset($row->totaltimespent)) {
                    $totaltimespent = $DB->get_field_sql($data->subquery);
                } else {
                    $totaltimespent = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($totaltimespent) ? (new ls)->strTime($totaltimespent) : '--';
                } else {
                    $row->{$data->column} = !empty($totaltimespent) ? $totaltimespent : 0;
                }
                break;
            case 'numviews':
                if (!isset($row->numviews)) {
                    $numviews = $DB->get_record_sql($data->subquery);
                }
                $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'noofviews'], IGNORE_MULTIPLE);
                $checkpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($context, $USER->id);
                if (empty($reportid) || empty($checkpermissions)) {
                    $row->{$data->column} = get_string('numviews', 'report_outline', $numviews);
                } else {
                    $row->{$data->column} = html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                    ['id' => $reportid, 'filter_courses' => $row->course, 'filter_activities' => $activityid]),
                    get_string('numviews', 'report_outline', $numviews), ["target" => "_blank"]);
                }
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : ' ';
    }
}
