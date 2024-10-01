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
 * Forum field columns
 */
class plugin_forumfield extends pluginbase {
    /**
     * Forum field columns init function
     */
    public function init() {
        $this->fullname = get_string('forumfield', 'block_learnerscript');
        $this->type = 'advanced';
        $this->form = true;
        $this->reporttypes = ['forum'];
    }
    /**
     * Forum field column summary
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
        $forumrecord = $DB->get_record('forum', ['id' => $row->id]);
        $activityid = $DB->get_field_sql("SELECT cm.id
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        AND m.name = 'forum' AND cm.instance = $row->id");
        switch($data->column){
            case 'completionposts':
                 $forumrecord->{$data->column} = ($forumrecord->{$data->column}) ? $forumrecord->{$data->column} : '--';
            break;
            case 'forcesubscribe':
                 $forumrecord->{$data->column} = ($forumrecord->{$data->column}) ? $forumrecord->{$data->column} : '--';
            break;
            case 'grade_forum':
                $forumrecord->{$data->column} = ($forumrecord->{$data->column}) ? $forumrecord->{$data->column} : '--';
            break;
            case 'scale':
                $forumrecord->{$data->column} = ($forumrecord->{$data->column}) ? $forumrecord->{$data->column} : '--';
            break;
            case 'cutoffdate':
                $forumrecord->{$data->column} = ($forumrecord->{$data->column}) ? $forumrecord->{$data->column} : '--';
            break;
            case 'assesstimestart':
                $forumrecord->{$data->column} = ($forumrecord->{$data->column}) ? $forumrecord->{$data->column} : '--';
            break;
            case 'trackingtype':
                $forumrecord->{$data->column} = ($forumrecord->{$data->column}) ? $forumrecord->{$data->column} : '--';
            break;
            case 'name':
                $module = 'forum';
                $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                if (is_siteadmin()) {
                    $url = new moodle_url('/mod/'.$module.'/view.php', ['id' => $activityid, 'action' => 'grading']);
                } else {
                    $url = new moodle_url('/mod/'.$module.'/view.php',
                    ['id' => $activityid]);
                }
                $forumrecord->{$data->column} = $activityicon . html_writer::tag('a', $forumrecord->name,
                ['href' => $url]);
            break;
            case 'course':
                $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'courseprofile'], IGNORE_MULTIPLE);
                $coursename = $DB->get_field('course', 'fullname', ['id' => $forumrecord->course]);
                $checkpermissions = empty($reportid) ? false :
                (new reportbase($reportid))->check_permissions($context, $USER->id);
                if (empty($reportid) || empty($checkpermissions)) {
                    $forumrecord->{$data->column} = html_writer::link(
                        new moodle_url('/course/view.php', ['id' => $forumrecord->course],
                        $coursename
                    ));
                } else {
                    $forumrecord->{$data->column} = html_writer::link(
                        new moodle_url('/blocks/learnerscript/viewreport.php',
                        ['id' => $reportid, 'filter_courses' => $forumrecord->course], $coursename
                    ));
                }
            break;
            case 'timemodified':
                $forumrecord->{$data->column} = ($forumrecord->{$data->column}) ? userdate($forumrecord->{$data->column}) :
                    '--';
            break;

        }
        return (isset($forumrecord->{$data->column})) ? $forumrecord->{$data->column} : '';
    }

}
