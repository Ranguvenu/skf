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
use block_learnerscript\local\ls;
use completion_info;
use completion_completion;
use html_writer;
/**
 * User courses columns
 */
class plugin_usercoursescolumns extends pluginbase {

    /**
     * @var string $role User role
     */
    public $role;

    /**
     * @var string $reportinstance User role
     */
    public $reportinstance;

    /**
     * @var array $reportfilterparams User role
     */
    public $reportfilterparams;

    /**
     * User courses init function
     */
    public function init() {
        $this->fullname = get_string('usercoursescolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['usercourses'];
    }

    /**
     * User courses column summary
     * @param object $data User courses column name
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
    public function execute($data, $row, $reporttype) {
        global $DB, $CFG;
        $course = $DB->get_record('course', ['id' => $row->courseid]);
        switch($data->column) {
            case 'timeenrolled':
                $row->{$data->column} = $row->timeenrolled ?
                userdate($row->timeenrolled, '', $row->timezone) : 'N/A';
              break;
            case 'progressbar':
                $progressbar = \core_completion\progress::get_course_progress_percentage($course, $row->userid);
                $progressbar = !empty($progressbar) ? floor($progressbar) : 0;
                $row->{$data->column} = html_writer::start_div('d-flex progresscontainer align-items-center').
                html_writer::start_div('mr-2 flex-grow-1 progress').
                html_writer::div('', "progress-bar",
                ['role' => "progressbar", 'aria-valuenow' => $progressbar,
                'aria-valuemin' => "0", 'aria-valuemax' => "100",
                'style' => (($progressbar == 0) ? '' : ("width:" . $progressbar . "%")),
                ]) .
                html_writer::end_div().
                 html_writer::span($progressbar.'%', 'progressvalue').
                 html_writer::end_div();
                break;
            case 'status':
                require_once("{$CFG->libdir}/completionlib.php");
                $info = new completion_info($course);
                $coursecomplete = $info->is_course_complete($row->userid);
                $criteriacomplete = $info->count_course_user_data($row->userid);
                $params = [
                    'userid' => $row->userid,
                    'course' => $row->courseid,
                ];
                $ccompletion = new completion_completion($params);
                $progressbar = \core_completion\progress::get_course_progress_percentage($course, $row->userid);
                $progressbar = !empty($progressbar) ? floor($progressbar) : 0;
                if ($coursecomplete) {
                    $row->{$data->column} = get_string('completed');
                } else if (!$criteriacomplete && !$ccompletion->timestarted && $progressbar == 0) {
                    $row->{$data->column} = get_string('notyetstarted', 'completion');
                } else {
                    $row->{$data->column} = get_string('inprogress', 'completion');
                }
                break;
            case 'grade':
                if (!isset($row->grade) && isset($data->subquery)) {
                     $grade = $DB->get_field_sql($data->subquery);
                } else {
                    $grade = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($grade) ? round($grade * 100, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($grade) ? round($grade * 100, 2) : 0;
                }
            break;
            case 'totaltimespent':
                if (!isset($row->totaltimespent) && isset($data->subquery)) {
                     $totaltimespent = $DB->get_field_sql($data->subquery);
                } else {
                    $totaltimespent = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($totaltimespent) ? (new ls)->strtime($totaltimespent) : '--';
                } else {
                    $row->{$data->column} = !empty($totaltimespent) ? $totaltimespent : 0;
                }
            break;
            case 'completedassignments':
                if (!isset($row->completedassignments) && isset($data->subquery)) {
                    $completedassignments = $DB->get_field_sql($data->subquery);
                } else {
                    $completedassignments = $row->{$data->column};
                }
                $row->{$data->column} = !empty($completedassignments) ? $completedassignments : 0;
            break;
            case 'completedquizzes':
                if (!isset($row->completedquizzes) && isset($data->subquery)) {
                    $completedquizzes = $DB->get_field_sql($data->subquery);
                } else {
                    $completedquizzes = $row->{$data->column};
                }
                $row->{$data->column} = !empty($completedquizzes) ? $completedquizzes : 0;
            break;
            case 'completedscorms':
                if (!isset($row->completedscorms) && isset($data->subquery)) {
                    $completedscorms = $DB->get_field_sql($data->subquery);
                } else {
                    $completedscorms = $row->{$data->column};
                }
                $row->{$data->column} = !empty($completedscorms) ? $completedscorms : 0;
            break;
            case 'marks':
                if (!isset($row->marks) && isset($data->subquery)) {
                    $marks = $DB->get_field_sql($data->subquery);
                } else {
                    $marks = $row->{$data->column};
                }
                $row->{$data->column} = !empty($marks) ? round($marks, 2) : 0;
            break;
            case 'badgesissued':
                if (!isset($row->badgesissued) && isset($data->subquery)) {
                    $badgesissued = $DB->get_field_sql($data->subquery);
                } else {
                    $badgesissued = $row->{$data->column};
                }
                 $row->{$data->column} = !empty($badgesissued) ? $badgesissued : 0;
            break;
            case 'completedactivities':
                if (!isset($row->completedactivities) && isset($data->subquery)) {
                    $completedactivities = $DB->get_field_sql($data->subquery);
                } else {
                    $completedactivities = $row->{$data->column};
                }
                 $row->{$data->column} = !empty($completedactivities) ? $completedactivities : 0;
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }
}
