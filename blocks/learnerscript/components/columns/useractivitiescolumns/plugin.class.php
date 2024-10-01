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
use moodle_url;
use html_writer;
/**
 * user activities columns
 */
class plugin_useractivitiescolumns extends pluginbase {

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
     * User activities init function
     */
    public function init() {
        $this->fullname = get_string('useractivities', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['useractivities', 'popularresources'];
    }

    /**
     * User activities column summary
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
        global $DB, $OUTPUT;
        switch($data->column) {
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
                $row->{$data->column} = !empty($numviews) ? $numviews : 0;
                break;
            case 'firstaccess':
            case 'lastaccess':
            case 'completedon':
                $row->{$data->column} = (isset($row->{$data->column})) ? userdate($row->{$data->column}) : '--';
            break;
            case 'modulename':
                $module = $DB->get_field('modules', 'name', ['id' => $row->module]);
                $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                $url = new moodle_url('/mod/'.$module.'/view.php', ['id' => $row->id]);
                $row->{$data->column} = $activityicon . html_writer::tag('a', $row->modulename, ['href' => $url]);
            break;
            case 'moduletype':
                $activityicon1 = $OUTPUT->pix_icon('icon', ucfirst($row->moduletype), $row->moduletype, ['class' => 'icon']);
                $row->{$data->column} = $activityicon1 . ucfirst($row->moduletype);
            break;
            case 'completionstatus':
                switch($row->completionstatus) {
                    case 0 :
                        $completiontype = get_string('completion-n', 'completion');
                        break;
                    case 1 :
                        $completiontype = get_string('completion-y', 'completion');
                        break;
                    case 2 :
                        $completiontype = get_string('pass', 'block_learnerscript');
                        break;
                    case 3 :
                        $completiontype = get_string('fail', 'block_learnerscript');
                        break;
                }
                $row->completionstatus = $completiontype ? $completiontype :
                get_string('na', 'block_learnerscript');
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}
