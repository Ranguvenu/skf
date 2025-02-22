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
use html_writer;
use moodle_url;
/**
 * User quizzess columns
 */
class plugin_userquizzes extends pluginbase {

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
     * User quizzess column init function
     */
    public function init() {
        $this->fullname = get_string('userquizzes', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['userquizzes'];
    }
    /**
     * User quizzess column summary
     * @param object $data User quizzess column name
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
        $myquizreport = $DB->get_field('block_learnerscript', 'id', ['type' => 'myquizs', 'visible' => 1], IGNORE_MULTIPLE);
        $myquizpermissions = empty($myquizreport) ? false :
        (new reportbase($myquizreport))->check_permissions($context, $USER->id);
        switch ($data->column) {
            case 'totalquizs':
                $total = html_writer::tag('a', 'Total', ['class' => 'btn',
                    'href' => new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $myquizreport,
                        'filter_users' => $row->userid, 'filter_courses' => $row->course])]);
                if (empty($myquizpermissions) || empty($myquizreport)) {
                    $row->{$data->column} = '--';
                } else {
                    $row->{$data->column} = $total;
                }
                break;
            case 'inprogressquizs':
                if (!isset($row->inprogressquizs) && isset($data->subquery)) {
                    $inprogressquizs = $DB->get_field_sql($data->subquery);
                } else {
                    $inprogressquizs = $row->{$data->column};
                }
                if (empty($myquizpermissions) || empty($myquizreport)) {
                        $row->{$data->column} = !empty($inprogressquizs) ? $inprogressquizs : '--';
                } else {
                    $row->{$data->column} = html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                        ['id' => $myquizreport, 'filter_users' => $row->userid, 'filter_courses' => $row->course,
                        'filter_status' => 'inprogress']), $inprogressquizs,
                    ['target' => '_blank']);
                }
                break;
            case 'finishedquizs':
                if (!isset($row->finishedquizs) && isset($data->subquery)) {
                    $finishedquizs = $DB->get_field_sql($data->subquery);
                } else {
                    $finishedquizs = $row->{$data->column};
                }
                $row->{$data->column} = $finishedquizs;
            break;
            case 'completedquizs':
                if (!isset($row->completedquizs) && isset($data->subquery)) {
                    $completedquizs = $DB->get_field_sql($data->subquery);
                } else {
                    $completedquizs = $row->{$data->column};
                }
                if (empty($myquizpermissions) || empty($myquizreport)) {
                    $row->{$data->column} = !empty($completedquizs) ? $completedquizs : '--';
                } else {
                    $row->{$data->column} = html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                        ['id' => $myquizreport,
                        'filter_users' => $row->userid, 'filter_courses' => $row->course,
                        'filter_status' => 'completed']), $completedquizs,
                    ['target' => '_blank']);
                }
            break;
            case 'notattemptedquizs':
                if (!isset($row->notattemptedquizs) && isset($data->subquery)) {
                    $notattemptedquizs = $DB->get_field_sql($data->subquery);
                } else {
                    $notattemptedquizs = $row->{$data->column};
                }
                if (empty($myquizpermissions) || empty($myquizreport)) {
                    $row->{$data->column} = !empty($notattemptedquizs) ? $notattemptedquizs : '--';
                } else {
                    $row->{$data->column} = html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                        ['id' => $myquizreport,
                        'filter_users' => $row->userid, 'filter_courses' => $row->course,
                        'filter_status' => 'notattempted']), $notattemptedquizs,
                    ['target' => '_blank']);
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
            case 'numviews':
                if (!isset($row->numviews) && isset($data->subquery)) {
                    $numviews = $DB->get_field_sql($data->subquery);
                } else {
                    $numviews = $row->{$data->column};
                }
                $row->{$data->column} = !empty($numviews) ? $numviews : '--';
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }
}
