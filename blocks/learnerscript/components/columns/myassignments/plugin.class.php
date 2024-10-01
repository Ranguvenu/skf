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
use stdClass;
use html_writer;
/**
 * My asssignments report columns
 */
class plugin_myassignments extends pluginbase {

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
     * My assignments columns init function
     */
    public function init() {
        $this->fullname = get_string('myassignments', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['myassignments'];
    }
    /**
     * My assignments column summary
     * @param object $data Course views column name
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
        global $DB;
        switch ($data->column) {
            case 'gradepass':
                if (!isset($row->gradepass) && isset($data->subquery)) {
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
                if (!isset($row->grademax) && isset($data->subquery)) {
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
            case 'finalgrade':
                if (!isset($row->finalgrade) && isset($data->subquery)) {
                    $finalgrade = $DB->get_field_sql($data->subquery);
                } else {
                    $finalgrade = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($finalgrade) ? round($finalgrade, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($finalgrade) ? round($finalgrade, 2) : 0;
                }
                break;
            case 'lowestgrade':
                if (!isset($row->lowestgrade) && isset($data->subquery)) {
                    $lowestgrade = $DB->get_field_sql($data->subquery);
                } else {
                    $lowestgrade = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($lowestgrade) ? round($lowestgrade, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($lowestgrade) ? round($lowestgrade, 2) : 0;
                }
                break;
            case 'highestgrade':
                if (!isset($row->highestgrade) && isset($data->subquery)) {
                    $highestgrade = $DB->get_field_sql($data->subquery);
                } else {
                    $highestgrade = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($highestgrade) ? round($highestgrade, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($highestgrade) ? round($highestgrade, 2) : 0;
                }
                break;
            case 'noofsubmissions':
                if (!isset($row->noofsubmissions) && isset($data->subquery)) {
                    $noofsubmissions = $DB->get_field_sql($data->subquery);
                } else {
                    $noofsubmissions = $row->{$data->column};
                }
                $row->{$data->column} = !empty($noofsubmissions) ? $noofsubmissions : '0';
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
                    } catch (exception $e) {
                        $completionstatus = 'Not Yet Start';
                    }
                } else {
                    $submissionsql = "SELECT id FROM {assign_submission}
                                       WHERE assignment = :assignment AND status = 'submitted'
                                        AND userid = :userid";
                    $assignsubmission = $DB->get_record_sql($submissionsql,
                    ['assignment' => $row->id, 'userid' => $row->userid], IGNORE_MULTIPLE);
                    if (!empty($assignsubmission)) {
                        $completionstatus = html_writer::tag('span', 'Submitted', ['class' => 'completed']);
                    } else {
                        $completionstatus = html_writer::tag('span', 'Not Yet Start', ['class' => 'notyetstart']);
                    }
                }
                $row->{$data->column} = !empty($completionstatus) ? $completionstatus : '--';
            break;
            case 'noofdaysdelayed':
                $latedaydifference = $row->overduedate - $row->duedate;
                $latedaydays = floor($latedaydifference / (60 * 60 * 24));
                if ($latedaydays >= 0 && $row->submissionstatus == 'submitted' && $row->duedate != 0) {
                    if ($latedaydays == 1) {
                        $noofdaysdelayedstatus = get_string('assign_wassubmitted_by', 'block_learnerscript').' - '.$latedaydays.
                        get_string('daylate', 'block_learnerscript');
                    } else {
                        $noofdaysdelayedstatus = get_string('assign_overdue_by', 'block_learnerscript').' - '.$latedaydays.
                        get_string('daylate', 'block_learnerscript');
                    }

                    $row->{$data->column} = !empty($noofdaysdelayedstatus) ? $noofdaysdelayedstatus : '--';
                } else {
                    $row->{$data->column} = '--';
                }
                break;
            case 'totaltimespent':
                if (!isset($row->totaltimespent) && isset($data->subquery)) {
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
            case 'overduedate':
                if ($row->submissionstatus == 'submitted' &&  $row->duedate != 0) {
                    $row->{$data->column} = $row->overduedate > $row->duedate ? userdate($row->overduedate) : '--';
                } else {
                    $row->{$data->column} = '--';
                }
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }
}
