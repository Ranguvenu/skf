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
use context_system;
use DateTime;
use moodle_url;
use html_writer;
/**
 * Need grading columns
 */
class plugin_needgrading extends pluginbase {
    /**
     * Need grading columns init function
     */
    public function init() {
        $this->fullname = get_string('needgrading', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['needgrading'];
    }
    /**
     * Need grading column summary
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
        global $OUTPUT;
        $date = new DateTime();
        $timestamp = $date->getTimestamp();
        switch ($data->column) {
            case 'module':
                $row->modulename = $row->module;
                $activityicon = $OUTPUT->pix_icon('icon', ucfirst($row->module), $row->module, ['class' => 'icon']);
                $row->{$data->column} = ($activityicon.get_string('pluginname', $row->module));
                break;
            case 'datesubmitted':
                $row->{$data->column} = $row->timecreated ? userdate($row->timecreated) : 'NA';
                break;
            case 'delay':
                $delay = $timestamp - $row->timecreated;
                $row->{$data->column} = $delay != 0 ? format_time($delay) : 'NA';
                break;
            case 'grade':
                $url = new moodle_url('/mod/'.$row->modulename.'/view.php',
                                  ['id' => $row->cmd, 'action' => 'grader', 'userid' => $row->userid]);
                $row->{$data->column} = html_writer::link(
                    $url,
                    html_writer::tag(
                        'button',
                        get_string('grade', 'block_learnerscript'),
                        ['type' => 'button', 'class' => 'btn btn-primary']
                    )
                );
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }
}
