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
use block_learnerscript\local\ls;
use context_system;
use moodle_url;
use html_writer;
/**
 * Course Activities Columns
 */
class plugin_courseactivitiescolumns extends pluginbase {

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
     * Course activities columns init function
     */
    public function init() {
        $this->fullname = get_string('courseactivitiescolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['courseactivities'];
    }
    /**
     * Course activities column summary
     * @param object $data Course activities column name
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
     * @param object $reporttype Report type
     * @return object
     */
    public function execute($data, $row, $reporttype) {
        global $DB, $OUTPUT, $USER, $CFG;
        $context = context_system::instance();
        $searchicon = $OUTPUT->pix_icon('search', '', 'block_learnerscript', ['class' => 'searchicon']);
        $module = $DB->get_field('modules', 'name', ['id' => $row->moduleid]);
        switch($data->column){
            case 'activityname':
                $module = $DB->get_field('modules', 'name', ['id' => $row->moduleid]);
                $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                $url = new moodle_url('/mod/'.$module.'/view.php', ['id' => $row->id]);
                $row->{$data->column} = $activityicon . html_writer::tag('a', $row->activityname, ['href' => $url]);
            break;
            case 'highestgrade':
            case 'lowestgrade':
            case 'averagegrade':
            case 'grademax':
            case 'gradepass':
                if (!isset($row->{$data->column})) {
                    $grade = $DB->get_field_sql($data->subquery);
                } else {
                    $grade = $row->{$data->column};
                }
                $gradableactivity = $DB->get_field_sql("SELECT id FROM {grade_items} WHERE iteminstance = :instance
                                AND itemmodule = :modulename", ['instance' => $row->instance, 'modulename' => $module]);
                if ($reporttype == 'table') {
                    if (!empty($gradableactivity)) {
                        $row->{$data->column} = !empty($grade) ? round($grade, 2) : '--';
                    } else {
                        $row->{$data->column} = 'N/A';
                    }
                } else {
                    $row->{$data->column} = !empty($grade) ? round($grade, 2) : 0;
                }

            break;
            case 'learnerscompleted':
                if (!isset($row->{$data->column})) {
                    $learnerscompleted = $DB->get_field_sql($data->subquery);
                } else {
                    $learnerscompleted = $row->{$data->column};
                }
                $row->{$data->column} = $learnerscompleted;
            break;
            case 'progress':
                if (!isset($row->{$data->column})) {
                    $progress = $DB->get_field_sql($data->subquery);
                } else {
                    $progress = $row->{$data->column};
                }
                $progress = empty($progress) ? 0 : round($progress);
                $row->{$data->column} = html_writer::start_div('d-flex progresscontainer align-items-center').
                html_writer::start_div('mr-2 flex-grow-1 progress') .
                html_writer::div('', "progress-bar",
                ['role' => "progressbar", 'aria-valuenow' => $progress,
                'aria-valuemin' => "0", 'aria-valuemax' => "100", 'style' => (($progress == 0) ? '' : ("width:" . $progress . "%")),
                ]) .
                html_writer::end_div().
                html_writer::span($progress.'%', 'progressvalue').
                html_writer::end_div();
            break;
            case 'grades';
                $gradesreportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'grades'], IGNORE_MULTIPLE);
                $checkpermissions = empty($gradesreportid) ? false :
                (new reportbase($gradesreportid))->check_permissions($context, $USER->id);
                if (empty($gradesreportid) || empty($checkpermissions)) {
                    $row->{$data->column} = 'N/A';
                } else {
                    $row->{$data->column} = html_writer::link(new moodle_url("/blocks/learnerscript/viewreport.php",
                    ['id' => $gradesreportid, 'filter_courses' => $row->course, 'filter_activities' => $row->id]),
                    'Grades'.$searchicon);
                }
            break;
            case 'totaltimespent':
                if (!isset($row->{$data->column})) {
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
                if (!isset($row->{$data->column})) {
                    $numviews = $DB->get_record_sql($data->subquery);
                } else {
                    $numviews = $row->{$data->column};
                }
                $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'noofviews'], IGNORE_MULTIPLE);
                $checkpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($context, $USER->id);
                if (empty($reportid) || empty($checkpermissions)) {
                    $row->{$data->column} = get_string('numviews', 'report_outline', $numviews).$searchicon;
                } else {
                    $row->{$data->column} = html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                    ['id' => $reportid, 'filter_courses' => $row->course, 'filter_activities' => $row->id]),
                    get_string('numviews', 'report_outline', $numviews).$searchicon, ["target" => "_blank"]);
                }
                break;
            case 'description':
                $row->{$data->column} = $row->description ? $row->description : '--';
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }

}
