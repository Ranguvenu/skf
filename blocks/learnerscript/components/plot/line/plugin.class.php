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
 * A Moodle block to create customizable reports.
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\pluginbase;
use moodle_url;

/**
 * Line graph
 */
class plugin_line extends pluginbase {

    /** @var bool $ordering */
    public $ordering;

    /**
     * Graph init
     *
     */
    public function init() {
        $this->fullname = get_string('line', 'block_learnerscript');
        $this->form = true;
        $this->ordering = true;
        $this->reporttypes = ['timeline', 'sql', 'assignment',
                            'courseactivities', 'courseparticipation', 'courses', 'coursesoverview',
                            'gradedactivity', 'myassignments', 'myquizs', 'myresources', 'myscorm',
                            'quizzes', 'scorm', 'student_overall_performance',
                            'student_performance', 'useractivities', 'userassignments',
                            'usercourses', 'userquizzes', 'usersresources', 'usersscorm', 'forum', 'myforums',
                            'assignstatus', 'userattendance', 'attendanceoverview', 'resources', 'coursecompetency',
                            'bigbluebutton', 'monthlysessions', 'dailysessions', 'weeklysessions', 'courseviews', ];
    }

    /**
     * Graph summary
     * @param  object $data Report data
     * @return string
     */
    public function summary($data) {
        return get_string('linesummary', 'block_learnerscript');
    }

    /**
     * Execution function
     * @param  int $id          Report id
     * @param  object $data        Report graph data
     * @param  array $finalreport  Final reportdata
     * @return string
     */
    public function execute($id, $data, $finalreport) {
        global $CFG;

        $series = [];
        $data->yaxis[0] --;
        $data->serieid--;
        $minvalue = 0;
        $maxvalue = 0;

        if ($finalreport) {
            foreach ($finalreport as $r) {
                $hash = md5(strtolower($r[$data->serieid]));
                $sname[$hash] = $r[$data->serieid];
                $val = (isset($r[$data->yaxis[0]]) && is_numeric($r[$data->yaxis[0]])) ? $r[$data->yaxis[0]] : 0;
                $series[$hash][] = $val;
                $minvalue = ($val < $minvalue) ? $val : $minvalue;
                $maxvalue = ($val > $maxvalue) ? $val : $maxvalue;
            }
        }

        $params = '';

        $i = 0;
        foreach ($series as $h => $s) {
            $params .= "&amp;serie$i=" . base64_encode($sname[$h] . '||' . implode(',', $s));
            $i++;
        }

        return new moodle_url('/blocks/learnerscript/components/plot/line/graph.php', [
            'reportid' => $this->report->id,
            'id' => $id.$params,
            'min' => $minvalue,
            'max' => $maxvalue,
        ]);
    }
}
