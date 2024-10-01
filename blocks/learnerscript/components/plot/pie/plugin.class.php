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

/**
 * Pie graph
 */
class plugin_pie extends pluginbase {

    /** @var bool $ordering */
    public $ordering;

    /**
     * Graph init
     */
    public function init() {
        $this->fullname = get_string('pie', 'block_learnerscript');
        $this->form = true;
        $this->ordering = true;
        $this->reporttypes = ['courses', 'sql', 'assignment',
                            'courseactivities', 'courseparticipation', 'courses', 'coursesoverview',
                            'gradedactivity', 'myassignments', 'myquizs', 'myresources',
                            'quizzes', 'student_overall_performance',
                            'student_performance',  'useractivities', 'forum', 'myforums', 'assignstatus', 'bigbluebutton', ];
    }

    /**
     * Graph summary
     * @param  object $data Pie graph data
     * @return string
     */
    public function summary($data) {
        return get_string('piesummary', 'block_learnerscript');
    }

    /**
     * Execution function
     * @param  int $id          Graph id
     * @param  object $data        Graph data
     * @param  object $finalreport Final report data
     * @return string
     */
    public function execute($id, $data, $finalreport) {
        global $CFG;
        $series = [];
        if ($finalreport) {
            foreach ($finalreport as $r) {
                if ($data->areaname == $data->areavalue) {
                    $hash = md5(strtolower($r[$data->areaname]));
                    if (isset($series[0][$hash])) {
                        $series[1][$hash] += 1;
                    } else {
                        $series[0][$hash] = str_replace(',', '', $r[$data->areaname]);
                        $series[1][$hash] = 1;
                    }
                } else if (!isset($data->group) || !$data->group) {
                    $series[0][] = str_replace(',', '', $r[$data->areaname]);
                    $series[1][] = (isset($r[$data->areavalue]) && is_numeric($r[$data->areavalue])) ? $r[$data->areavalue] : 0;
                } else {
                    $hash = md5(strtolower($r[$data->areaname]));
                    if (isset($series[0][$hash])) {
                        $series[1][$hash] += (isset($r[$data->areavalue])
                        && is_numeric($r[$data->areavalue])) ? $r[$data->areavalue] : 0;
                    } else {
                        $series[0][$hash] = str_replace(',', '', $r[$data->areaname]);
                        $series[1][$hash] = (isset($r[$data->areavalue])
                        && is_numeric($r[$data->areavalue])) ? $r[$data->areavalue] : 0;
                    }
                }
            }
        }
        $serie0 = base64_encode(implode(',', $series[0]));
        $serie1 = base64_encode(implode(',', $series[1]));
        return new \moodle_url('/blocks/learnerscript/components/plot/pie/graph.php', [
            'reportid' => $this->report->id,
            'id' => $id,
            'serie0' => $serie0,
            'serie1' => $serie1,
        ]);
    }
}
