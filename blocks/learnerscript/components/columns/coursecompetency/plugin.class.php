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
use html_writer;
use moodle_url;
/**
 * Course Competency
 */
class plugin_coursecompetency extends pluginbase {
    /**
     * Course competency init function
     */
    public function init() {
        $this->fullname = get_string('coursecompetency', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['coursecompetency'];
    }
    /**
     * Course competency column summary
     * @param object $data Course competency column name
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
        global $DB, $OUTPUT, $CFG;
        switch($data->column) {
            case 'competency':
                $compurl = html_writer::link(new moodle_url('/admin/tool/lp/user_competency_in_course.php',
                ['courseid' => $row->courseid, 'competencyid' => $row->id]), $row->competency);
                $competency = $compurl;
                $row->{$data->column} = !empty($competency) ? $competency : '--';
            break;
            case 'activity':
                $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
                foreach ($modules as $modulename) {
                    $aliases[] = $modulename;
                    $activities[] = "'$modulename'";
                    $fields1[] = "COALESCE($modulename.name,'')";
                }
                $activitynames = implode(',', $fields1);
                $sql = " SELECT cm.id, CONCAT($activitynames) AS activityname, m.id AS moduleid
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                LEFT JOIN {competency_modulecomp} mcom ON mcom.cmid = cm.id ";
                foreach ($aliases as $alias) {
                    $sql .= " LEFT JOIN {".$alias."} AS $alias ON $alias.id = cm.instance AND m.name = '$alias' ";
                }
                $sql .= " WHERE m.visible = :visible AND mcom.competencyid = :rowid
                        AND cm.visible = :cmvisible AND cm.deletioninprogress = :deletioninprogress
                        AND cm.course = :courseid";
                $activitieslist = $DB->get_records_sql($sql, ['visible' => 1, 'rowid' => $row->id,
                                    'cmvisible' => 1, 'deletioninprogress' => 0,
                                    'courseid' => $row->courseid, ]);
                foreach ($activitieslist as $activity) {
                    $module = $DB->get_field('modules', 'name', ['id' => $activity->moduleid]);
                    $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                    $url = new moodle_url('/mod/'.$module.'/view.php', ['id' => $activity->id]);
                    $activityname = $activityicon . html_writer::tag('a', $activity->activityname, ['href' => $url]);
                    $data1[] = $activityname;
                }
                $activitiesd = !empty($data1) ? implode(', ', $data1) : [];
                $row->{$data->column} = !empty($activitiesd) ? $activitiesd : '--';
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : ' -- ';
    }
}
