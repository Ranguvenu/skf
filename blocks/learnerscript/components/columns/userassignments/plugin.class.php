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
 * User assignments columns
 */
class plugin_userassignments extends pluginbase {

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
     * User assignments column init function
     */
    public function init() {
        $this->fullname = get_string('userassignments', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['userassignments'];
    }
    /**
     * User assignments column summary
     * @param object $data User activities column name
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
        global $DB, $USER;
        $context = context_system::instance();
        $myassignmentsreportid = $DB->get_field('block_learnerscript', 'id',
        ['type' => 'myassignments', 'visible' => 1], IGNORE_MULTIPLE);
        switch ($data->column) {
            case 'total':
                $url = new moodle_url('/blocks/learnerscript/viewreport.php',
                ['id' => $myassignmentsreportid, 'filter_users' => $row->userid, 'filter_courses' => $row->courseid ]);
                $checkpermissions = empty($myassignmentsreportid) ? false :
                (new reportbase($myassignmentsreportid))->check_permissions($context, $USER->id);
                if (empty($myassignmentsreportid) || empty($checkpermissions)) {
                    $total = '--';
                } else {
                    $total = html_writer::tag('a', 'Total', ['class' => 'btn', 'href' => $url]);
                }
                $row->{$data->column} = $total;
                break;
            case 'notyetstarted':
                if (!isset($row->notyetstarted) && isset($data->subquery)) {
                    $notyetstarted = $DB->get_field_sql($data->subquery);
                } else {
                    $notyetstarted = $row->{$data->column};
                }
                $row->{$data->column} = !empty($notyetstarted) ? $notyetstarted : '--';
                break;
            case 'inprogress':
                if (!isset($row->inprogress) && isset($data->subquery)) {
                    $inprogress = $DB->get_field_sql($data->subquery);
                } else {
                    $inprogress = $row->{$data->column};
                }
                $row->{$data->column} = !empty($inprogress) ? $inprogress : '--';
                break;
            case 'completed':
                if (!isset($row->completed) && isset($data->subquery)) {
                    $completed = $DB->get_field_sql($data->subquery);
                } else {
                    $completed = $row->{$data->column};
                }
                $row->{$data->column} = !empty($completed) ? $completed : '--';
                break;
            case 'submitted':
                if (!isset($row->submitted) && isset($data->subquery)) {
                    $submitted = $DB->get_field_sql($data->subquery);
                } else {
                    $submitted = $row->{$data->column};
                }
                $row->{$data->column} = !empty($submitted) ? $submitted : '--';
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
