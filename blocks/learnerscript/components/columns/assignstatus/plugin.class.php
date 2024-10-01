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
/**
 * Assign status columns
 */
class plugin_assignstatus extends pluginbase {
    /**
     * Assign status column init function
     */
    public function init() {
        $this->fullname = get_string('assignstatus', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['assignstatus'];
    }

    /**
     * Assign status column summary
     *
     * @param  object $data
     *
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
     * @return object
     */
    public function execute($data, $row) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        switch ($data->column) {
            case 'total':
            case 'completed':
            case 'pending':
            case 'overdue':
                if (!isset($row->{$data->column})) {
                    $discussions = $DB->get_field_sql($data->subquery);
                } else {
                    $discussions = $row->{$data->column};
                }
                $row->{$data->column} = $discussions == null ? '--' : $discussions;
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }
}
