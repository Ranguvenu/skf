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
use block_learnerscript\local\ls;
/**
 * Average calculation
 */
class plugin_average extends pluginbase {
    /**
     * Plugin initialization
     */
    public function init() {
        $this->form = true;
        $this->unique = false;
        $this->fullname = get_string('average', 'block_learnerscript');
        $this->reporttypes = ['courses', 'users', 'timeline', 'categories',
        'assignment', 'courses', 'coursesoverview', 'gradedactivity', 'myassignments',
        'myquizs', 'quizzes', 'scorm', 'useractivities', 'userassignments', 'usercourses',
        'userquizzes', 'usersresources', 'usersscorm', 'courseactivities', 'myscorm', ];
    }
    /**
     * Plugin summary
     * @param  object $data data
     * @return string
     */
    public function summary($data) {
        global $DB, $CFG;
        if ($this->report->type != 'sql') {
            $components = (new ls)->cr_unserialize($this->report->components);
            if (!is_array($components) || empty($components['columns']['elements'])) {
                throw new moodle_exception('nocolumns');
            }
            $columns = $components['columns']['elements'];
            $i = 0;
            foreach ($columns as $c) {
                if ($i == $data->column) {
                    return $c['summary'];
                }
                $i++;
            }
        } else {
            require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $this->report->type . '/report.class.php');

            $reportclassname = 'block_learnerscript\lsreports\report_' . $this->report->type;
            $reportclass = new $reportclassname($this->report);

            $components = (new ls)->cr_unserialize($this->report->components);
            $config = (isset($components['customsql']['config'])) ? $components['customsql']['config'] : new stdclass;
            if (isset($config->querysql)) {

                $sql = $config->querysql;
                $sql = $reportclass->prepare_sql($sql);
                if ($rs = $reportclass->execute_query($sql)) {
                    foreach ($rs['results'] as $row) {
                        $i = 0;
                        foreach ($row as $colname => $value) {
                            if ($i == $data->column) {
                                return str_replace('_', ' ', $colname);
                            }
                            $i++;
                        }
                        break;
                    }
                }
            }
        }

        return '';
    }
    /**
     * Query execution
     * @param  object $rows Row data
     * @return float
     */
    public function execute($rows) {

        $result = 0;
        $els = 0;

        foreach ($rows as $r) {
            $result += (is_numeric(trim(strip_tags($r)))) ? $r : 0;
            $els++;
        }

        if ($els == 0) {
            $els = 1;
        }
        $finalresult = round($result / $els, 2);
        return $finalresult;
    }

}
