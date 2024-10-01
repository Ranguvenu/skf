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
 * Competency Columns
 */
class plugin_competencycolumns extends pluginbase {
    /**
     * Copetency columns init function
     */
    public function init() {
        $this->fullname = get_string('competencycolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['competency'];
    }
    /**
     * Competency column summary
     * @param object $data Competency column name
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
        global $DB, $CFG, $USER;
        $context = context_system::instance();
        $coursereportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'coursecompetency'], IGNORE_MULTIPLE);
        switch ($data->column) {
            case 'course':
                $checkpermissions = empty($coursereportid) ? false :
                (new reportbase($coursereportid))->check_permissions($context, $USER->id);
                $sql = " SELECT c.id, c.fullname
                FROM {course} c
                JOIN {competency_coursecomp} comc ON comc.courseid = c.id
                WHERE c.visible = :visible AND comc.competencyid = :rowid
                    AND c.id = :couresid";
                $courseslist = $DB->get_records_sql($sql, ['visible' => 1, 'rowid' => $row->id,
                                'courseid' => $row->courseid, ]);
                foreach ($courseslist as $course) {
                    if ($this->report->type == 'courseprofile' || empty($coursereportid) || empty($checkpermissions)) {
                        $row->{$data->column} .= html_writer::tag('li',
                        html_writer::link(new \moodle_url('/course/view.php',
                        ['id' => $course->id]), $course->fullname) . ', ');
                    } else if ($coursereportid) {
                        $row->{$data->column} .= html_writer::tag(
                            'li',
                            html_writer::link(
                                new moodle_url('/blocks/learnerscript/viewreport.php',
                                    ['id' => $coursereportid, 'filter_courses' => $course->id]
                                ),
                                $course->fullname
                            ) . ', '
                        );
                    }
                }
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : ' -- ';
    }
}
