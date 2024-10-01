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
use html_writer;
use moodle_url;
/**
 * Users scorm columns
 */
class plugin_usersscormcolumns extends pluginbase {

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
     * Users scorm columns init function
     */
    public function init() {
        $this->fullname = get_string('usersscormcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['usersscorm'];
    }
    /**
     * User scorm column summary
     * @param object $data User scorm column name
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
     * @param string $reporttype Row data
     * @return object
     */
    public function execute($data, $row, $reporttype) {
        global $DB, $USER;
        $context = context_system::instance();
        $myscormreportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'myscorm'], IGNORE_MULTIPLE);
        switch($data->column){
            case 'inprogress':
                if (!isset($row->inprogress)) {
                    $inprogress = $DB->get_field_sql($data->subquery);
                } else {
                    $inprogress = $row->{$data->column};
                }
                $row->{$data->column} = !empty($inprogress) ? $inprogress : '--';
                break;
            case 'notattempted':
                if (!isset($row->notattempted)) {
                    $notattempted = $DB->get_field_sql($data->subquery);
                } else {
                    $notattempted = $row->{$data->column};
                }
                $row->{$data->column} = !empty($notattempted) ? $notattempted : '--';
                break;
            case 'completed':
                if (!isset($row->completed)) {
                    $completed = $DB->get_field_sql($data->subquery);
                } else {
                    $completed = $row->{$data->column};
                }
                $row->{$data->column} = !empty($completed) ? $completed : '--';
                break;
            case 'firstaccess':
                if (!isset($row->firstaccess)) {
                    $firstaccess = $DB->get_field_sql($data->subquery);
                } else {
                    $firstaccess = $row->{$data->column};
                }
                $row->{$data->column} = !empty($firstaccess) ? userdate($firstaccess) : '--';
                break;
            case 'lastaccess':
                $attempt = $DB->get_field_sql("SELECT MAX(sst.attempt)
                FROM {scorm_attempt} sst
                JOIN {scorm} s ON s.id = sst.scormid
                WHERE sst.userid = :id AND s.course = :courseid ", ['id' => $row->id, 'courseid' => $row->course]);
                if (!empty($attempt)) {
                    $value = $DB->get_field_sql("SELECT ssv.timemodified
                    FROM {scorm_scoes_value} ssv
                    JOIN {scorm_element} se ON se.id = ssv.elementid
                    JOIN {scorm_attempt} sa ON ssv.attemptid = sa.id
                    JOIN {scorm} s ON s.id = sa.scormid
                    WHERE se.element = :element
                    AND sa.userid = :id AND s.course = :courseid
                    AND sa.attempt = :attempt", ['element' => 'cmi.core.total_time',
                    'id' => $row->id, 'courseid' => $row->course, 'attempt' => $attempt, ]);
                    if (empty($value)) {
                        $lastaccess = $DB->get_field_sql("SELECT ssv.timemodified
                        FROM {scorm_scoes_value} ssv
                        JOIN {scorm_element} se ON se.id = ssv.elementid
                        JOIN {scorm_attempt} sa ON ssv.attemptid = sa.id
                        JOIN {scorm} s ON s.id = sa.scormid
                        WHERE sa.userid = :id AND s.course = :courseid
                        AND se.element = :element AND sa.attempt = :attempt", ['id' => $row->id,
                        'courseid' => $row->course, 'element' => 'x.start.time', 'attempt' => $attempt, ]);
                        $row->{$data->column} = $lastaccess ? userdate($lastaccess) : '--';
                    } else {
                        $row->{$data->column} = $value ? userdate($value) : '--';
                    }
                }
            break;
            case 'total':
                $myscormpermissions = empty($myscormreportid) ? false :
                (new reportbase($myscormreportid))->check_permissions($context, $USER->id);
                $url = new moodle_url('/blocks/learnerscript/viewreport.php',
                ['id' => $myscormreportid, 'filter_users' => $row->id , 'filter_courses' => $row->course]);
                $total = html_writer::tag('a', 'Total', ['class' => 'btn', 'href' => $url]);

                if (empty($myscormpermissions) || empty($myscormreportid)) {
                    $row->{$data->column} = 'Total';
                } else {
                    $row->{$data->column} = $total;
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
            case 'numviews':
                if (!isset($row->numviews) && isset($data->subquery)) {
                    $numviews = $DB->get_field_sql($data->subquery);
                } else {
                    $numviews = $row->{$data->column};
                }
                $row->{$data->column} = !empty($numviews) ? $numviews : '--';
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}
