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
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\pluginbase;
use html_writer;
use context_system;
use moodle_url;

/**
 * Course Field Columns
 */
class plugin_coursefield extends pluginbase {

    /** @var string $reportinstance  */
    public $reportinstance;

    /** @var string $role  */
    public $role;

    /** @var array $reportfilterparams  */
    public $reportfilterparams;

    /**
     * Course fields init function
     */
    public function init() {
        $this->fullname = get_string('coursefield', 'block_learnerscript');
        $this->type = 'advanced';
        $this->form = true;
        $this->reporttypes = ['courses', 'activitystatus',
            'popularresources', ];
    }
    /**
     * Course field column summary
     * @param object $data Course fields column name
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
        global $DB, $CFG, $SESSION;
        $courserecord = $DB->get_record('course', ['id' => $row->id]);
        $coursereportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'courseprofile'], IGNORE_MULTIPLE);
        if (isset($courserecord->{$data->column})) {
            switch ($data->column) {
                case 'enrolments':
                    $courserecord->{$data->column} = '--';
                    break;
                case 'enrolstartdate':
                case 'enrolenddate':
                    $courserecord->{$data->column} = ($courserecord->{$data->column}) ?
                    userdate($courserecord->{$data->column}) : '--';
                    break;
                case 'lang':
                case 'calendartype':
                case 'theme':
                case 'summary':
                case 'idnumber':
                    $courserecord->{$data->column} = ($courserecord->{$data->column}) ? ($courserecord->{$data->column}) : '--';
                    break;
                case 'visibleold':
                case 'visible':
                    $courserecord->{$data->column} = ($courserecord->{$data->column})
                    ? html_writer::tag('span', get_string('active'), ['class' => 'label label-success'])
                    : html_writer::tag('span', get_string('no'), ['class' => 'label label-warning']);
                    break;
                case 'enrollable':
                    $courserecord->{$data->column} = ($courserecord->{$data->column}) ?
                    html_writer::tag('span', get_string('yes'), ['class' => 'label label-success']) :
                    html_writer::tag('span', get_string('no'), ['class' => 'label label-warning']);
                    break;
                case 'enablecompletion':
                    $courserecord->{$data->column} = ($courserecord->{$data->column}) ?
                    html_writer::tag('span', get_string('enabled', 'block_learnerscript'), ['class' => 'label label-success']) :
                    html_writer::tag('span', get_string('disabled', 'block_learnerscript'), ['class' => 'label label-warning']);
                    break;
                case 'fullname':
                    if (is_siteadmin()) {
                        $courserecord->{$data->column} =
                                html_writer::link(new moodle_url('/blocks/reportdashboard/courseprofile.php',
                                ['filter_courses' => $courserecord->id]), $courserecord->{$data->column});
                    } else {
                        $courserecord->{$data->column} =
                                html_writer::link(new moodle_url('/blocks/reportdashboard/courseprofile.php',
                                ['filter_courses' => $courserecord->id,
                                'role' => $SESSION->role,
                                'contextlevel' => $SESSION->ls_contextlevel, ]), $courserecord->{$data->column});
                    }
                break;
                case 'category':
                    if ($courserecord->{$data->column} > 0) {
                        $courserecord->{$data->column} =
                        $DB->get_field('course_categories', 'name', ['id' => $courserecord->{$data->column}]);
                    } else {
                        $courserecord->{$data->column};
                    }
                break;
                case 'timecreated':
                case 'timemodified';
                case 'cacherev':
                    $courserecord->{$data->column} = ($courserecord->{$data->column}) ?
                    userdate($courserecord->{$data->column}) : '--';
                break;
                case 'groupmode':
                    if ($courserecord->{$data->column} == 0) {
                        $courserecord->{$data->column} = get_string('groupsnone');
                    } else if ($courserecord->{$data->column} == 1) {
                        $courserecord->{$data->column} = get_string('groupsseparate');
                    } else if ($courserecord->{$data->column} == 2) {
                        $courserecord->{$data->column} = get_string('groupsvisible');
                    }
                break;
                case 'startdate':
                case 'enddate':
                    if ($coursereportid) {
                        $courserecord->{$data->column} = ($courserecord->{$data->column}) ?
                        date("l, d F Y", $courserecord->{$data->column}) : '--';
                    } else {
                        $courserecord->{$data->column} = ($courserecord->{$data->column}) ?
                        userdate($courserecord->{$data->column}) : '--';
                    }
                break;
            }
        }
        return (isset($courserecord->{$data->column})) ?
        $courserecord->{$data->column} : '--';
    }

}
