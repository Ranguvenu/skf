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
use completion_info;
use stdClass;
use moodle_url;
use DateTime;
use html_writer;

/**
 * Assignment participation report columns
 */
class plugin_assignmentparticipationcolumns extends pluginbase {
    /**
     * Assignment participation report columns init function
     */
    public function init() {
        $this->fullname = get_string('assignmentparticipationcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['assignmentparticipation'];
    }
    /**
     * Assignment participation report column summary
     * @param object $data Assignment participation report column name
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
     * @return object
     */
    public function execute($data, $row) {
        global $DB;
        $datesql = '';
        switch ($data->column) {
            case 'username':
                if (isset($row->userid) && $row->userid) {
                    $row->{$data->column} = $DB->get_field('user', 'username', ['id' => $row->userid]);
                } else {
                    $row->{$data->column} = 'NA';
                }
                break;
            case 'submitteddate':
                $submitteddate = $DB->get_field_sql("SELECT asb.timemodified
                FROM {assign} a
                JOIN {assign_submission} asb ON asb.assignment = a.id
                WHERE asb.userid = :userid AND a.id = :assignmentid
                AND asb.status = 'submitted'",
                ['userid' => $row->userid, 'assignmentid' => $row->id]);
                $row->{$data->column} = $submitteddate ? userdate($submitteddate) : 'NA';
                break;
            case 'finalgrade':
                $module = $DB->get_field('modules', 'name', ['id' => $row->module]);
                $finalgrade = $DB->get_field_sql("SELECT gg.finalgrade AS finalgrade
                FROM {grade_grades} gg
                JOIN {grade_items} gi ON gg.itemid = gi.id
                WHERE 1 = 1 AND gi.itemmodule = 'assign' AND gg.userid = :userid
                AND gi.iteminstance = :assignid", ['userid' => $row->userid, 'assignid' => $row->id]);
                if (!empty($finalgrade)) {
                    $row->{$data->column} = round($finalgrade, 2);
                } else {
                    $url = new moodle_url('/mod/'.$module.'/view.php',
                    ['id' => $row->activityid, 'action' => 'grader', 'userid' => $row->userid]);
                    $row->{$data->column} = html_writer::link(
                        $url,
                        html_writer::tag(
                            'button',
                            get_string('grade', 'block_learnerscript'),
                            ['type' => 'button', 'class' => 'btn btn-primary']
                        )
                    );
                }
                break;
            case 'status':
                $courserecord = $DB->get_record('course', ['id' => $row->courseid]);
                $completioninfo = new completion_info($courserecord);
                $coursemodulecompletion = $DB->get_record_sql("SELECT id
                FROM {course_modules_completion}
                WHERE userid = :userid AND coursemoduleid = :activityid",
                ['userid' => $row->userid, 'activityid' => $row->activityid], IGNORE_MULTIPLE);
                if (!empty($coursemodulecompletion)) {
                    try {
                        $cm = new stdClass();
                        $cm->id = $row->activityid;
                        $completion = $completioninfo->get_data($cm, false, $row->userid);
                        switch ($completion->completionstate) {
                            case COMPLETION_INCOMPLETE:
                                $completionstatus = get_string('in_complete', 'block_learnerscript');
                                break;
                            case COMPLETION_COMPLETE:
                                $completionstatus = get_string('completed', 'block_learnerscript');
                                break;
                            case COMPLETION_COMPLETE_PASS:
                                $completionstatus = get_string('completed_passgrade', 'block_learnerscript');
                                break;
                            case COMPLETION_COMPLETE_FAIL:
                                $completionstatus = get_string('fail', 'block_learnerscript');
                                break;
                        }
                    } catch (\exception $e) {
                        $completionstatus = get_string('not_yet_started', 'block_learnerscript');
                    }
                } else {
                    $submissionsql = "SELECT id FROM {assign_submission}
                                        WHERE assignment = :assignment AND status = 'submitted'
                                        AND userid = :userid $datesql";
                    $assignsubmission = $DB->get_record_sql($submissionsql,
                    ['assignment' => $row->id, 'userid' => $row->userid], IGNORE_MULTIPLE);
                    if (!empty($assignsubmission)) {
                        $completionstatus = html_writer::tag(
                            'span',
                            get_string('submitted', 'block_learnerscript'),
                            ['class' => 'completed']
                        );
                    } else {
                        $completionstatus = html_writer::tag(
                            'span',
                            get_string('not_yet_started', 'block_learnerscript'),
                            ['class' => 'notyetstart']
                        );
                    }
                }
                $row->{$data->column} = !empty($completionstatus) ? $completionstatus : '--';
                break;
            case 'noofdaysdelayed':
                $latedaydifference = $row->overduedate - $row->due_date;
                $latedaydays = format_time($latedaydifference);
                if ($latedaydifference > 0 && $row->submissionstatus == 'submitted' && $row->due_date != 0) {
                    $noofdaysdelayedstatus = get_string('assign_wassubmitted_by', 'block_learnerscript').$latedaydays.
                    get_string('late', 'block_learnerscript');
                    $row->{$data->column} = !empty($noofdaysdelayedstatus) ? $noofdaysdelayedstatus : '--';
                } else if ($latedaydifference < 0 && $row->submissionstatus == 'submitted' && $row->due_date != 0) {
                    $noofdaysdelayedstatus = 'NA';
                    $row->{$data->column} = !empty($noofdaysdelayedstatus) ? $noofdaysdelayedstatus : '--';
                } else if ($latedaydifference >= 0 && ($row->submissionstatus == 'new'
                || $row->submissionstatus == '') && $row->due_date != 0) {
                    $date = new DateTime();
                    $timestamp = $date->getTimestamp();
                    $latedaydifference = $timestamp - $row->due_date;
                    $noofdaysdelayedstatus = get_string('assign_overdue_by', 'block_learnerscript').format_time($latedaydifference).
                    get_string('late', 'block_learnerscript');
                    $row->{$data->column} = !empty($noofdaysdelayedstatus) ? $noofdaysdelayedstatus : '--';
                } else {
                    $row->{$data->column} = '--';
                }
                    break;
            case 'duedate':
                if ($row->due_date &&  $row->due_date != 0) {
                    $row->{$data->column} = $row->due_date ? userdate($row->due_date) : '--';
                } else {
                    $row->{$data->column} = '--';
                }
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }
}
