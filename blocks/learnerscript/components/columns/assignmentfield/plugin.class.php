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
 * Assignment field column
 */
class plugin_assignmentfield extends pluginbase {

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
     * Assignment field column init function
     */
    public function init() {
        $this->fullname = get_string('assignmentfield', 'block_learnerscript');
        $this->type = 'advanced';
        $this->form = true;
        $this->reporttypes = ['assignment', 'myassignments'];
    }
    /**
     * Assignment field column summary
     * @param object $data Assignment field column name
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
        global $DB, $CFG, $OUTPUT, $USER;
        $context = context_system::instance();
        $assignmentrecord = $DB->get_record('assign', ['id' => $row->id]);
        $activityid = $DB->get_field_sql("SELECT cm.id
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        AND m.name = 'assign' AND cm.instance = $row->id");
        switch($data->column){
            case 'name':
                $module = 'assign';
                $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                if (is_siteadmin()) {
                    $url = new moodle_url('/mod/'.$module.'/view.php',
                            ['id' => $activityid, 'action' => 'grading']);
                } else {
                    $url = new moodle_url('/mod/'.$module.'/view.php',
                            ['id' => $activityid]);
                }
                $assignmentrecord->{$data->column} = $activityicon . html_writer::tag('a', $assignmentrecord->name,
                ['href' => $url]);
            break;
            case 'course':
                $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'courseprofile'], IGNORE_MULTIPLE);
                $coursename = $DB->get_field('course', 'fullname', ['id' => $assignmentrecord->course]);
                $checkpermissions = empty($reportid) ? false :
                (new reportbase($reportid))->check_permissions($context, $USER->id);
                if (empty($reportid) || empty($checkpermissions)) {
                    $assignmentrecord->{$data->column} = html_writer::link(
                        new moodle_url('/course/view.php', ['id' => $assignmentrecord->course]),
                        $coursename
                    );
                } else {
                    $assignmentrecord->{$data->column} = html_writer::link(
                        new moodle_url('/blocks/learnerscript/viewreport.php',
                        ['id' => $reportid, 'filter_courses' => $assignmentrecord->course]),
                        $coursename
                    );
                }
            break;
            case 'duedate':
            case 'allowsubmissionsfromdate':
            case 'timemodified':
            case 'gradingduedate':
            case 'cutoffdate':
                $assignmentrecord->{$data->column} = ($assignmentrecord->{$data->column}) ?
                userdate($assignmentrecord->{$data->column}) : '--';
            break;
            case 'intro':
                $assignmentrecord->{$data->column} = !empty($assignmentrecord->{$data->column}) ?
                $assignmentrecord->{$data->column} : '--';
            break;
            case 'maxattempts':
                if ($assignmentrecord->{$data->column} == -1) {
                    $assignmentrecord->{$data->column} = get_string('unlimited');
                } else {
                    $assignmentrecord->{$data->column} = $assignmentrecord->{$data->column};
                }
                break;

        }
        return (isset($assignmentrecord->{$data->column})) ? $assignmentrecord->{$data->column} : '';
    }

}
