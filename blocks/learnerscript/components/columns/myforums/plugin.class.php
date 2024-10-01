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
use moodle_url;
use html_writer;
/**
 * My forums columns
 */
class plugin_myforums extends pluginbase {
    /**
     * My forums column init function
     */
    public function init() {
        $this->fullname = get_string('myforums', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['myforums'];
    }
    /**
     * My forums column summary
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
        global $DB, $CFG, $OUTPUT, $USER;
        $context = context_system::instance();
        require_once($CFG->libdir . '/completionlib.php');
        switch ($data->column) {
            case 'forumname':
                $module = 'forum';
                $forumicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                $url = new moodle_url('/mod/'.$module.'/view.php',
                ['id' => $row->activityid]);
                $row->{$data->column} = $forumicon . html_writer::tag('a', $row->forumname, ['href' => $url]);
            break;
            case 'coursename':
                if (!isset($row->{$data->column})) {
                    $coursename = $DB->get_field_sql($data->subquery);
                } else {
                    $coursename = $row->{$data->column};
                }
                $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'courseprofile'], IGNORE_MULTIPLE);
                $checkpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($context, $USER->id);
                if (empty($reportid) || empty($checkpermissions)) {
                    $row->{$data->column} = html_writer::link(
                        new moodle_url('/course/view.php', ['id' => $row->courseid]),
                        $row->{$data->column}
                    );
                } else {
                    $row->{$data->column} = html_writer::link(
                        new moodle_url('/blocks/learnerscript/viewreport.php',
                        ['id' => $reportid, 'filter_courses' => $row->courseid]),
                        $row->{$data->column}
                    );
                }
            break;
            case 'noofdisscussions':
            case 'noofreplies':
            case 'wordcount':
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
