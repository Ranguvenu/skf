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
use html_writer;
use moodle_url;
/**
 * Quiz field columns
 */
class plugin_quizfield extends pluginbase {

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
     * Quizfield column init function
     */
    public function init() {
        $this->fullname = get_string('quizfield', 'block_learnerscript');
        $this->type = 'advanced';
        $this->form = true;
        $this->reporttypes = ['myquizs', 'quizzes'];
    }
    /**
     * Quiz field column summary
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
        $quizrecord = $DB->get_record('quiz', ['id' => $row->id]);
        if (isset($quizrecord->{$data->column})) {
            switch ($data->column) {
                case 'name':
                    $module = $DB->get_field_sql('SELECT name
                    FROM {modules} m
                    JOIN {course_modules} cm ON m.id = cm.module
                    WHERE cm.id = :activityid', ['activityid' => $row->activityid]);
                    $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                    $quizrecord->{$data->column} = $activityicon . html_writer::link(new moodle_url('/mod/'.$module.'/view.php',
                    ['id' => $row->activityid]), $quizrecord->{$data->column}, ["target" => "_blank"]);
                break;
                case 'course':
                    $coursename = $DB->get_field('course', 'fullname', ['id' => $quizrecord->{$data->column}]);
                    $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'courseprofile'], IGNORE_MULTIPLE);
                    $checkpermissions = empty($reportid) ? false :
                    (new reportbase($reportid))->check_permissions($context, $USER->id);
                    if (empty($reportid) || empty($checkpermissions)) {
                        $quizrecord->{$data->column} = html_writer::link(new moodle_url('/course/view.php',
                            ['id' => $quizrecord->course]), $coursename,
                            ['target' => '_blank', 'class' => 'edit']);

                    } else {
                        $quizrecord->{$data->column} = html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                            ['id' => $reportid, 'filter_courses' => $quizrecord->course]),
                            $coursename,
                            ['target' => '_blank', 'class' => 'edit']
                        );
                    }
                break;
                case 'timecreated':
                    $quizrecord->{$data->column} = $quizrecord->{$data->column} ? userdate($quizrecord->{$data->column}) : 'N/A';
                break;
                case 'timemodified':
                    $quizrecord->{$data->column} = $quizrecord->{$data->column} ? userdate($quizrecord->{$data->column}) : 'N/A';
                break;
                case 'timeclose':
                    $quizrecord->{$data->column} = $quizrecord->{$data->column} ? userdate($quizrecord->{$data->column}) : 'N/A';
                break;
                case 'timeopen':
                    $quizrecord->{$data->column} = $quizrecord->{$data->column} ? userdate($quizrecord->{$data->column}) : 'N/A';
                break;
                case 'timelimit':
                    $quizrecord->{$data->column} = $quizrecord->{$data->column} ?
                    gmdate("H:i:s", $quizrecord->{$data->column}) : 'N/A';
                break;
            }
        }
        return (isset($quizrecord->{$data->column})) ? $quizrecord->{$data->column} : 'N/A';
    }

}
