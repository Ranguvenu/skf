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
use context_system;
use moodle_url;
use html_writer;
use DateTime;
/**
 * Pending activities columns
 */
class plugin_pendingactivities extends pluginbase {
    /**
     * Pending activities column init function
     */
    public function init() {
        $this->fullname = get_string('pendingactivities', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['pendingactivities'];
    }
    /**
     * Page resources timespent column summary
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
     * @return object
     */
    public function execute($data, $row) {
        global $DB, $OUTPUT;
        $date = new DateTime();
        $timestamp = $date->getTimestamp();
        switch ($data->column) {
            case 'activityname':
                $row->activity = $row->activityname;
                $module = $DB->get_field('modules', 'name', ['id' => $row->moduleid]);
                $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                $url = new moodle_url('/mod/'.$module.'/view.php',
                ['id' => $row->id]);
                $row->{$data->column} = $activityicon . html_writer::tag('a', $row->activityname, ['href' => $url]);
                break;

            case 'course':
                $course = $DB->get_field('course', 'fullname', ['id' => $row->course]);
                $row->{$data->column} = $course ? $course : '--';
                break;

            case 'startdate':
                $row->{$data->column} = $row->{$data->column} ? userdate($row->{$data->column}) : 'NA';
                break;

            case 'enddate':
                $row->{$data->column} = $row->lastdate ? userdate($row->lastdate) : 'NA';
                break;
            case 'attempt':
                $module = $DB->get_field('modules', 'name', ['id' => $row->moduleid]);
                $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                $url = new moodle_url('/mod/'.$module.'/view.php',
                ['id' => $row->id]);
                $daydifference = $timestamp - $row->lastdate;
                $latedaydays = format_time($daydifference);
                $activity = html_writer::tag('b', $row->activity) .
                get_string('is_over_due_by', 'block_learnerscript') . $latedaydays;
                $row->{$data->column} = html_writer::tag(
                    'div',
                    html_writer::link(
                        $url,
                        html_writer::tag(
                            'button',
                            get_string('submit', 'block_learnerscript'),
                            ['type' => 'button', 'class' => 'btn btn-primary']
                        )
                    ) . '<br><br>' . $activity
                );
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }
}
