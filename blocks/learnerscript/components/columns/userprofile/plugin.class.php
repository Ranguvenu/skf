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
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\reportbase;
use context_system;
use moodle_url;
use html_writer;

/**
 * User userprofile
 */
class plugin_userprofile extends pluginbase {

    /**
     * User profile init function
     */
    public function init() {
        $this->fullname = get_string('userprofile', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = false;
        $this->reporttypes = ['users'];
    }

    /**
     * User profile column summary
     * @param object $data User profile column name
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
     * @return object
     */
    public function execute($data, $row) {
        global $DB, $USER, $OUTPUT;
        $context = context_system::instance();
        $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'coursesoverview'], IGNORE_MULTIPLE);
        $quizreportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'myquizs'], IGNORE_MULTIPLE);
        $assignreportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'myassignments'], IGNORE_MULTIPLE);
        $scormreportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'myscorm'], IGNORE_MULTIPLE);
        $userbadgeid = $DB->get_field('block_learnerscript', 'id', ['type' => 'userbadges'], IGNORE_MULTIPLE);
        $courseoverviewpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($context, $USER->id);
        $searchicon = $OUTPUT->pix_icon('search', '', 'block_learnerscript', ['class' => 'searchicon']);
        switch ($data->column) {
            case 'enrolled':
                if (!isset($row->enrolled) && isset($data->subquery)) {
                    $enrolled = $DB->get_field_sql($data->subquery);
                } else {
                    $enrolled = $row->{$data->column};
                }
                $allurl = new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $reportid, 'filter_users' => $row->id]);
                if (empty($courseoverviewpermissions) || empty($reportid)) {
                    $row->{$data->column} = $enrolled;
                } else {
                    $row->{$data->column} = html_writer::tag('a', $enrolled.$searchicon, ['href' => $allurl]);
                }
                break;
            case 'inprogress':
                if (!isset($row->inprogress) && isset($data->subquery)) {
                    $inprogress = $DB->get_field_sql($data->subquery);
                } else {
                    $inprogress = $row->{$data->column};
                }
                $inprogressurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                ['id' => $reportid, 'filter_users' => $row->id,
                'filter_status' => get_string('inprogress', 'block_learnerscript'), ]);
                if (empty($courseoverviewpermissions) || empty($reportid)) {
                    $row->{$data->column} = $inprogress;
                } else {
                    $row->{$data->column} = html_writer::tag('a', $inprogress.$searchicon, ['href' => $inprogressurl]);
                }
                break;
            case 'completed':
                if (!isset($row->completed) && isset($data->subquery)) {
                    $completed = $DB->get_field_sql($data->subquery);
                } else {
                    $completed = $row->{$data->column};
                }
                $completedurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                ['id' => $reportid, 'filter_users' => $row->id, 'filter_status' => get_string('completed', 'block_learnerscript')]);
                if (empty($courseoverviewpermissions) || empty($reportid)) {
                    $row->{$data->column} = $completed;
                } else {
                    $row->{$data->column} = html_writer::tag('a', $completed.$searchicon, ['href' => $completedurl]);
                }
                break;
            case 'assignments':
                if (!isset($row->assignments) && isset($data->subquery)) {
                    $assignments = $DB->get_field_sql($data->subquery);
                } else {
                    $assignments = $row->{$data->column};
                }
                $assignpermissions = empty($assignreportid) ? false :
                (new reportbase($assignreportid))->check_permissions($context, $USER->id);
                $assignmenturl = new moodle_url('/blocks/learnerscript/viewreport.php',
                ['id' => $assignreportid, 'filter_users' => $row->id]);
                if (empty($assignpermissions) || empty($assignreportid)) {
                    $row->{$data->column} = $assignments;
                } else {
                    $row->{$data->column} = html_writer::tag('a', $assignments.$searchicon, ['href' => $assignmenturl]);
                }
                break;
            case 'quizes':
                if (!isset($row->quizes) && isset($data->subquery)) {
                    $quizes = $DB->get_field_sql($data->subquery);
                } else {
                    $quizes = $row->{$data->column};
                }
                $quizpermissions = empty($quizreportid) ? false :
                (new reportbase($quizreportid))->check_permissions($context, $USER->id);
                $quizurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                ['id' => $quizreportid, 'filter_users' => $row->id]);
                if (empty($quizpermissions) || empty($quizreportid)) {
                    $row->{$data->column} = $quizes;
                } else {
                    $row->{$data->column} = html_writer::tag('a', $quizes.$searchicon, ['href' => $quizurl]);
                }
            break;
            case 'scorms':
                if (!isset($row->scorms) && isset($data->subquery)) {
                    $scorms = $DB->get_field_sql($data->subquery);
                } else {
                    $scorms = $row->{$data->column};
                }
                $scormpermissions = empty($scormreportid) ? false :
                (new reportbase($scormreportid))->check_permissions($context, $USER->id);
                $scormurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                ['id' => $scormreportid, 'filter_users' => $row->id]);
                if (empty($scormpermissions) || empty($scormreportid)) {
                    $row->{$data->column} = $scorms;
                } else {
                    $row->{$data->column} = html_writer::tag('a', $scorms.$searchicon, ['href' => $scormurl]);
                }
            break;
            case 'badges':
                if (!isset($row->badges) && isset($data->subquery)) {
                    $badges = $DB->get_field_sql($data->subquery);
                } else {
                    $badges = $row->{$data->column};
                }
                $badgepermissions = empty($userbadgeid) ? false :
                (new reportbase($userbadgeid))->check_permissions($context, $USER->id);
                $badgeurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                ['id' => $userbadgeid, 'filter_users' => $row->id]);
                if (empty($badgepermissions) || empty($userbadgeid)) {
                    $row->{$data->column} = $badges;
                } else {
                    $row->{$data->column} = html_writer::tag('a', $badges.$searchicon, ['href' => $badgeurl]);
                }
                break;
            case 'completedcoursesgrade':
                if (!isset($row->completedcoursesgrade)) {
                    $completedcoursesgrade = $DB->get_field_sql($data->subquery);
                } else {
                    $completedcoursesgrade = $row->{$data->column};
                }
                $row->{$data->column} = (!empty($completedcoursesgrade)) ? $completedcoursesgrade : '--';
                break;
            case 'progress':
                if (!isset($row->progress)) {
                    $progress = $DB->get_field_sql($data->subquery);
                } else {
                    $progress = $row->{$data->column};
                }
                $progress = (!empty($progress)) ? round($progress) : 0;
                $row->{$data->column} = html_writer::start_div('d-flex progresscontainer align-items-center').
                html_writer::start_div('mr-2 flex-grow-1 progress').
                html_writer::div('', "progress-bar",
                ['role' => "progressbar", 'aria-valuenow' => $progress,
                'aria-valuemin' => "0", 'aria-valuemax' => "100",
                'style' => (($progress == 0) ? '' : ("width:" . $progress . "%")),
                ]) .
                html_writer::end_div().
                 html_writer::span($progress.'%', 'progressvalue').
                 html_writer::end_div();
            break;
            case 'status':
                $userstatus = $DB->get_record_sql('SELECT suspended, deleted FROM {user} WHERE id = :id', ['id' => $row->id]);
                if ($userstatus->suspended) {
                    $userstaus = html_writer::tag(
                        'span',
                        get_string('suspended'),
                        ['class' => 'label label-warning']
                    );
                } else if ($userstatus->deleted) {
                    $userstaus = html_writer::tag(
                        'span',
                        get_string('deleted'),
                        ['class' => 'label label-warning']
                    );
                } else {
                    $userstaus = html_writer::tag(
                        'span',
                        get_string('active'),
                        ['class' => 'label label-success']
                    );
                }
                $row->{$data->column} = $userstaus;

            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }
}
