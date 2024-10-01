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
 * Scorm columns
 */
class plugin_scorm extends pluginbase {
    /**
     * Scorm column intit function
     */
    public function init() {
        $this->fullname = get_string('scormfield', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['courses', 'activitystatus', 'courseaverage',
        'popularresources', 'scorm', ];
    }
    /**
     * Scorm column summary
     * @param object $data Scorm column column name
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
        global $DB, $CFG, $OUTPUT, $USER;
        $context = context_system::instance();
        $scormreportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'scormparticipation'], IGNORE_MULTIPLE);
        $checkpermissions = empty($scormreportid) ? false :
        (new reportbase($scormreportid))->check_permissions($context, $USER->id);
        switch ($data->column) {
            case 'noofattempts':
                if (!isset($row->noofattempts)) {
                    $noofattempts = $DB->get_field_sql($data->subquery);
                } else {
                    $noofattempts = $row->{$data->column};
                }
                $row->{$data->column} = !empty($noofattempts) ? $noofattempts : '--';
                break;
            case 'noofcompletions':
                if (!isset($row->noofcompletions)) {
                    $noofcompletions = $DB->get_field_sql($data->subquery);
                } else {
                    $noofcompletions = $row->{$data->column};
                }
                if (empty($scormreportid) || empty($checkpermissions)) {
                    $row->{$data->column} = !empty($noofcompletions) ? $noofcompletions : '--';
                } else {
                    $row->{$data->column} = !empty($noofcompletions) ?
                    html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $scormreportid,
                        'filter_courses' => $row->course,
                        'filter_scorm' => $row->id,
                        'filter_activity' => $row->activityid,
                        'filter_status' => 'completed']), $noofcompletions) : '--';
                }
                break;
            case 'highestgrade':
                if (!isset($row->highestgrade)) {
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
            case 'lowestgrade':
                if (!isset($row->lowestgrade)) {
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
            case 'totaltimespent':
                if (!isset($row->totaltimespent)) {
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
            case 'numviews':
                if (!isset($row->numviews)) {
                    $numviews = $DB->get_record_sql($data->subquery);
                }
                $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'noofviews'], IGNORE_MULTIPLE);
                $checkpermissions = empty($reportid) ? false :
                (new reportbase($reportid))->check_permissions($context, $USER->id);
                if (empty($reportid) || empty($checkpermissions)) {
                    $row->{$data->column} = get_string('numviews', 'report_outline', $numviews);
                } else {
                    $row->{$data->column} = html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                    ['id' => $reportid,
                    'filter_courses' => $row->course,
                    'filter_activities' => $row->activityid]),
                    get_string('numviews', 'report_outline', $numviews), ["target" => "_blank"]);
                }
                break;
            case 'status':
                $row->{$data->column} = ($row->{$data->column}) ?
                html_writer::tag('span', get_string('active'), ['class' => 'label label-success']) :
                html_writer::tag('span', get_string('inactive'), ['class' => 'label label-warning']);
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }

}
