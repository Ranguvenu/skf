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
use block_learnerscript\local\ls;
use context_system;
use moodle_url;
use html_writer;
/**
 * Course Overview Columns
 */
class plugin_coursesoverview extends pluginbase {

    /**
     * @var string $role User role
     */
    public $role;

    /**
     * @var string $reportinstance User role
     */
    public $reportinstance;

    /**
     * @var array $reportfilterparams User role
     */
    public $reportfilterparams;

    /**
     * Course overview init function
     */
    public function init() {
        $this->fullname = get_string('coursesoverview', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['coursesoverview'];
    }
    /**
     * Course overview column summary
     * @param object $data Course overview column name
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
     * @param string $reporttype Report type
     * @return object
     */
    public function execute($data, $row, $reporttype) {
        global $DB, $USER, $CFG, $OUTPUT;
        $systemcontext = context_system::instance();
        $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'courseprofile'], IGNORE_MULTIPLE);
        $courseprofilepermissions = empty($reportid) ? false :
        (new reportbase($reportid))->check_permissions($systemcontext, $USER->id);
        if (empty($reportid) || empty($courseprofilepermissions)) {
            $url = new moodle_url('/course/view.php', ['id' => $row->id]);
        } else {
            $url = new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $reportid, 'filter_courses' => $row->id]);
        }
        $activityinfoid = $DB->get_field('block_learnerscript', 'id', ['type' => 'useractivities'], IGNORE_MULTIPLE);
        $reportpermissions = empty($activityinfoid) ? false :
        (new reportbase($activityinfoid))->check_permissions($systemcontext, $USER->id);
        $this->reportfilterparams['filter_modules'] = isset($this->reportfilterparams['filter_modules']) ?
        $this->reportfilterparams['filter_modules'] : 0;
        $this->reportfilterparams['filter_users'] = isset($this->reportfilterparams['filter_users']) ?
        $this->reportfilterparams['filter_users'] : $row->userid;
        $allactivityurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                ['id' => $activityinfoid, 'filter_courses' => $row->id,
                'filter_modules' => $this->reportfilterparams['filter_modules'],
                'filter_users' => $this->reportfilterparams['filter_users'], ]);
        $inprogressactivityurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                ['id' => $activityinfoid, 'filter_courses' => $row->id,
                'filter_status' => get_string('notcompleted', 'block_learnerscript'),
                'filter_modules' => $this->reportfilterparams['filter_modules'],
                'filter_users' => $this->reportfilterparams['filter_users'], ]);
        $completedactivityurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                ['id' => $activityinfoid, 'filter_courses' => $row->id,
                'filter_status' => get_string('completed', 'block_learnerscript'),
                'filter_modules' => $this->reportfilterparams['filter_modules'],
                'filter_users' => $this->reportfilterparams['filter_users'], ]);
        $searchicon = $OUTPUT->pix_icon('search', '', 'block_learnerscript', ['class' => 'searchicon']);
        switch ($data->column) {
            case 'coursename':
                if (!isset($row->coursename)) {
                    $coursename = $DB->get_field_sql($data->subquery);
                } else {
                    $coursename = $row->{$data->column};
                }
                $row->{$data->column} = !empty($coursename) ? html_writer::tag('a', $coursename, ['href' => $url]) : '--';
                break;
            case 'totalactivities':
                if (!isset($row->totalactivities)) {
                    $totalactivities = $DB->get_field_sql($data->subquery);
                } else {
                    $totalactivities = $row->{$data->column};
                }
                if (empty($activityinfoid) || empty($reportpermissions)) {
                    $row->{$data->column} = !empty($totalactivities) ? $totalactivities.$searchicon : '--';
                } else {
                    $row->{$data->column} = !empty($totalactivities) ?
                    html_writer::tag('a', $totalactivities.$searchicon, ['href' => $allactivityurl]) : '--';
                }
                break;
            case 'inprogressactivities':
                if (!isset($row->inprogressactivities)) {
                    $inprogressactivities = $DB->get_field_sql($data->subquery);
                } else {
                    $inprogressactivities = $row->{$data->column};
                }
                if (empty($activityinfoid) || empty($reportpermissions)) {
                    $row->{$data->column} = !empty($inprogressactivities) ? $inprogressactivities.$searchicon : '--';
                } else {
                    $row->{$data->column} = !empty($inprogressactivities) ?
                    html_writer::tag('a', $inprogressactivities.$searchicon, ['href' => $inprogressactivityurl]) : '--';
                }
                break;
            case 'completedactivities':
                if (!isset($row->completedactivities)) {
                    $completedactivities = $DB->get_field_sql($data->subquery);
                } else {
                    $completedactivities = $row->{$data->column};
                }
                if (empty($activityinfoid) || empty($reportpermissions)) {
                    $row->{$data->column} = !empty($completedactivities) ? $completedactivities.$searchicon : '--';
                } else {
                    $row->{$data->column} = !empty($completedactivities) ?
                    html_writer::tag('a', $completedactivities.$searchicon, ['href' => $completedactivityurl]) : '--';
                }
                break;
            case 'grades':
                if (!isset($row->grades)) {
                    $grades = $DB->get_field_sql($data->subquery);
                } else {
                    $grades = $row->{$data->column};
                }

                if ($grades) {
                    $gradesgrades = [];
                    $gradeone = '';
                    $gradesgrades = explode('/', $grades);
                    if (is_numeric($gradesgrades[0])) {
                        if ($gradesgrades[0] == ($gradesgrades[1])) {
                            $gradeone = 1;
                        } else if ($gradesgrades[0] < ($gradesgrades[1] / 2)) {
                            $gradeone = 2;
                        } else {
                            $gradeone = 3;
                        }
                    }
                }
                if ($reporttype == 'table') {
                    if (!empty($grades)) {
                        if ($gradeone == 1) {
                            $row->{$data->column} = html_writer::tag('span', $grades, ['class' => 'text-success']);
                        } else if ($gradeone == 2) {
                            $row->{$data->column} = html_writer::tag('span', $grades, ['class' => 'text-danger']);
                        } else {
                            $row->{$data->column} = html_writer::tag('span', $grades, ['class' => 'text-info']);
                        }
                    } else {
                        $row->{$data->column} = 0;
                    }
                } else {
                    if (!empty($grades)) {
                        if ($gradeone == 1) {
                            $row->{$data->column} = html_writer::tag('span', $grades, ['class' => 'text-success']);
                        } else if ($gradeone == 2) {
                            $row->{$data->column} = html_writer::tag('span', $grades, ['class' => 'text-danger']);
                        } else {
                            $row->{$data->column} = html_writer::tag('span', $grades, ['class' => 'text-info']);
                        }
                    } else {
                        $row->{$data->column} = 0;
                    }
                }
                break;
            case 'totaltimespent':
                if (!isset($row->totaltimespent)) {
                    $totaltimespent = $DB->get_field_sql($data->subquery);
                } else {
                    $totaltimespent = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($totaltimespent) ? (new ls)->strtime($totaltimespent) : '--';
                } else {
                    $row->{$data->column} = !empty($totaltimespent) ? $totaltimespent : 0;
                }
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }
}
