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
use block_learnerscript\local\reportbase;
use context_system;
use html_writer;
use moodle_url;
/**
 * Quizzess columns
 */
class plugin_quizzes extends pluginbase {
    /**
     * Quizzess column init function
     */
    public function init() {
        $this->fullname = get_string('quizzes', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['userquizzes'];
    }
    /**
     * Quizzess column summary
     * @param object $data No. of views column name
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
        global $DB, $CFG, $USER;
        $context = context_system::instance();
        $quizzid = $DB->get_field('block_learnerscript', 'id', ['type' => 'quizzparticipation'], IGNORE_MULTIPLE);
        $checkpermissions = empty($quizzid) ? false : (new reportbase($quizzid))->check_permissions($context, $USER->id);
        switch ($data->column) {
            case 'notattemptedusers':
                if (!isset($row->notattemptedusers)) {
                    $notattemptedusers = $DB->get_field_sql($data->subquery);
                } else {
                    $notattemptedusers = $row->{$data->column};
                }
                if (empty($quizzid) || empty($checkpermissions)) {
                    $row->{$data->column} = !empty($notattemptedusers) ? $notattemptedusers : '--';
                } else {
                    $row->{$data->column} = !empty($notattemptedusers) ?
                    html_writer::link(new moodle_url("/blocks/learnerscript/viewreport.php",
                        ['id' => $quizzid, 'filter_courses' => $row->course,
                        'filter_quiz' => $row->id, 'filter_status' => 'notattempted']), $notattemptedusers) : '--';
                }
                break;
            case 'totalattempts':
                if (!isset($row->totalattempts)) {
                    $totalattempts = $DB->get_field_sql($data->subquery);
                } else {
                    $totalattempts = $row->{$data->column};
                }
                $row->{$data->column} = !empty($totalattempts) ? $totalattempts : '--';
            break;
            case 'inprogressusers':
                if (!isset($row->inprogressusers)) {
                    $inprogressusers = $DB->get_field_sql($data->subquery);
                } else {
                    $inprogressusers = $row->{$data->column};
                }
                if (empty($quizzid) || empty($checkpermissions)) {
                    $row->{$data->column} = !empty($inprogressusers) ? $inprogressusers : '--';
                } else {
                    $row->{$data->column} = !empty($inprogressusers) ?
                    html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $quizzid,
                        'filter_courses' => $row->course,
                        'filter_quiz' => $row->id,
                        'filter_status' => 'inprogress']), $inprogressusers) : '--';
                }
            break;
            case 'completedusers':
                if (!isset($row->completedusers)) {
                    $completedusers = $DB->get_field_sql($data->subquery);
                } else {
                    $completedusers = $row->{$data->column};
                }
                if (empty($quizzid) || empty($checkpermissions)) {
                    $row->{$data->column} = !empty($completedusers) ? $completedusers : '--';
                } else {
                    $row->{$data->column} = !empty($completedusers) ?
                    html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $quizzid,
                        'filter_courses' => $row->course,
                        'filter_quiz' => $row->id,
                        'filter_status' => 'completed']), $completedusers) : '--';
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
                    $row->{$data->column} = html_writer::link(new moodle_url("/blocks/learnerscript/viewreport.php",
                    ['id' => $reportid,
                    'filter_courses' => $row->course,
                    'filter_activities' => $row->activityid]),
                    get_string('numviews', 'report_outline', $numviews), ["target" => "_blank"]);
                }
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
            case 'noofcompletegradedfirstattempts':
                if (!isset($row->noofcompletegradedfirstattempts)) {
                    $noofcompletegradedfirstattempts = $DB->get_field_sql($data->subquery);
                } else {
                    $noofcompletegradedfirstattempts = $row->{$data->column};
                }
                $row->{$data->column} = !empty($noofcompletegradedfirstattempts) ? $noofcompletegradedfirstattempts : '--';
            break;
            case 'totalnoofcompletegradedattempts':
                if (!isset($row->totalnoofcompletegradedattempts)) {
                    $totalnoofcompletegradedattempts = $DB->get_field_sql($data->subquery);
                } else {
                    $totalnoofcompletegradedattempts = $row->{$data->column};
                }
                $row->{$data->column} = !empty($totalnoofcompletegradedattempts) ? $totalnoofcompletegradedattempts : '--';
            break;
            case 'avggradeofhighestgradedattempts':
                if (!isset($row->avggradeofhighestgradedattempts)) {
                    $avggradeofhighestgradedattempts = $DB->get_field_sql($data->subquery);
                } else {
                    $avggradeofhighestgradedattempts = $row->{$data->column};
                }
                $row->{$data->column} = !empty($avggradeofhighestgradedattempts) ?
                round($avggradeofhighestgradedattempts, 2) : '--';
            break;
            case 'avggradeofallattempts':
                if (!isset($row->avggradeofallattempts)) {
                    $avggradeofallattempts = $DB->get_field_sql($data->subquery);
                } else {
                    $avggradeofallattempts = $row->{$data->column};
                }
                $row->{$data->column} = !empty($avggradeofallattempts) ? round($avggradeofallattempts, 2) : '--';
            break;
            case 'avggradeoffirstattempts':
                if (!isset($row->avggradeoffirstattempts)) {
                    $avggradeoffirstattempts = $DB->get_field_sql($data->subquery);
                } else {
                    $avggradeoffirstattempts = $row->{$data->column};
                }
                $row->{$data->column} = !empty($avggradeoffirstattempts) ? round($avggradeoffirstattempts, 2) : '--';
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : ' ';
    }
}
