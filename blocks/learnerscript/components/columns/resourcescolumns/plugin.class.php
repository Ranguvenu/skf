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
use moodle_url;
use html_writer;
/**
 * Resources columns
 */
class plugin_resourcescolumns extends pluginbase {

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
     * Resources column init function
     */
    public function init() {
        $this->fullname = get_string('resourcescolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['resources'];
    }
    /**
     * Resources column summary
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
        global $DB, $CFG, $OUTPUT, $USER;
        $context = context_system::instance();
        switch ($data->column) {
            case 'activity':
                $module = $DB->get_field('modules', 'name', ['id' => $row->module]);
                $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                $url = new moodle_url('/mod/'.$module.'/view.php',
                ['id' => $row->id, 'action' => 'grading']);
                $row->{$data->column} = $activityicon . html_writer::tag('a', $row->activity, ['href' => $url]);
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
                    $row->{$data->column} = html_writer::link(new moodle_url('blocks/learnerscript/viewreport.php',
                        ['id' => $reportid, 'filter_courses' => $row->course, 'filter_activities' => $row->id]),
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
