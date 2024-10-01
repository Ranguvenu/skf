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
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/lib/gradelib.php');
use completion_info;
use stdClass;
use html_writer;
/**
 * Myquizs columns
 */
class plugin_myquizs extends pluginbase {

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
     * Myquiz column init function
     */
    public function init() {
        $this->fullname = get_string('myquizs', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['myquizs'];
    }
    /**
     * My quiz column summary
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
     * @param string $reporttype Report type
     * @return object
     */
    public function execute($data, $row, $reporttype) {
        global $DB, $CFG;
        $query = "SELECT state
        FROM {quiz_attempts}
        WHERE quiz = :id AND userid = :userid
        ORDER BY id DESC LIMIT 1 ";

        $query1 = "SELECT id
        FROM {course_modules_completion}
        WHERE coursemoduleid = :activityid AND completionstate > :completionstate
        AND userid = :userid
        ORDER BY id DESC LIMIT 1 ";
        $quizattemptstatus = $DB->get_field_sql($query, ['id' => $row->id, 'userid' => $row->userid]);
        $quizcomppletion = $DB->get_field_sql($query1, ['activityid' => $row->activityid, 'completionstate' => 0,
        'userid' => $row->userid, ]);
        switch ($data->column) {
            case 'gradepass':
                if (!isset($row->gradepass) && isset($data->subquery)) {
                    $gradepass = $DB->get_field_sql($data->subquery);
                } else {
                    $gradepass = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($gradepass) ? round($gradepass, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($gradepass) ? round($gradepass, 2) : 0;
                }
                break;
            case 'finalgrade':
                if (!isset($row->finalgrade) && isset($data->subquery)) {
                    $finalgrade = $DB->get_field_sql($data->subquery);
                } else {
                    $finalgrade = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($finalgrade) ? round($finalgrade, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($finalgrade) ? round($finalgrade, 2) : 0;
                }
                break;
            case 'lowestgrade':
                if (!isset($row->lowestgrade) && isset($data->subquery)) {
                    $lowestgrade = $DB->get_field_sql($data->subquery);
                } else {
                    $lowestgrade = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($lowestgrade) ? round($lowestgrade, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($lowestgrade) ? round($lowestgrade, 2) : 0;
                }
                break;
            case 'highestgrade':
                if (!isset($row->highestgrade) && isset($data->subquery)) {
                    $highestgrade = $DB->get_field_sql($data->subquery);
                } else {
                    $highestgrade = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($highestgrade) ? round($highestgrade, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($highestgrade) ? round($highestgrade, 2) : 0;
                }
                break;
            case 'quizattempts':
                if (!isset($row->quizattempts) && isset($data->subquery)) {
                    $quizattempts = $DB->get_field_sql($data->subquery);
                } else {
                    $quizattempts = $row->{$data->column};
                }
                $row->{$data->column} = !empty($quizattempts) ? $quizattempts : '--';
                break;
            case 'grademax':
                if (!isset($row->grademax) && isset($data->subquery)) {
                    $grademax = $DB->get_field_sql($data->subquery);
                } else {
                    $grademax = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($grademax) ? round($grademax, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($grademax) ? round($grademax, 2) : 0;
                }
                break;
            case 'state':
                $courserecord = $DB->get_record('course', ['id' => $row->courseid]);
                $completioninfo = new completion_info($courserecord);
                if (empty($quizattemptstatus) && empty($quizcomppletion)) {
                    $completionstatus = html_writer::tag('span', get_string('notyetstarted',
                    'block_learnerscript'), ['class' => 'notyetstart']);
                } else if ($quizattemptstatus == 'inprogress' && empty($quizcomppletion)) {
                    $completionstatus = get_string('inprogress', 'block_learnerscript');
                } else if ($quizattemptstatus == 'finished' && empty($quizcomppletion)) {
                    $completionstatus = get_string('finished', 'block_learnerscript');
                } else if ($quizattemptstatus == 'finished' || !empty($quizcomppletion)) {
                    $cm = new stdClass();
                    $cm->id = $row->activityid;
                        $completion = $completioninfo->get_data($cm, false, $row->userid);
                    switch($completion->completionstate) {
                        case COMPLETION_INCOMPLETE :
                            $completionstatus = get_string('inprogress', 'block_learnerscript');
                        break;
                        case COMPLETION_COMPLETE :
                            $completionstatus = get_string('completed', 'block_learnerscript');
                        break;
                        case COMPLETION_COMPLETE_PASS :
                            $completionstatus = get_string('completed_passgrade', 'block_learnerscript');
                        break;
                        case COMPLETION_COMPLETE_FAIL :
                            $completionstatus = get_string('fail', 'block_learnerscript');
                        break;
                    }
                }
                $row->{$data->column} = !empty($completionstatus) ? $completionstatus : '--';
            break;
            case 'status':
                $userfinalgrade = $DB->get_field_sql("SELECT round(gg.finalgrade, 2) as finalgrade
                FROM {grade_grades} gg
                JOIN {grade_items} gi ON gg.itemid = gi.id
                WHERE 1 = 1 AND gi.itemmodule = :quiz AND gg.userid = :userid
                AND gi.iteminstance = :id", ['quiz' => 'quiz', 'userid' => $row->userid, 'id' => $row->id]);

                $usergradepass = $DB->get_field_sql("SELECT round(gi.gradepass, 2) as gradepass
                FROM {grade_items} gi
                WHERE gi.itemmodule = :quiz AND gi.iteminstance = :id",
                ['quiz' => 'quiz', 'id' => $row->id]);
                if (empty($quizattemptstatus) && empty($quizcomppletion) && empty($userfinalgrade)) {
                    $row->{$data->column} = '--';
                } else if ($userfinalgrade >= $usergradepass) {
                    $row->{$data->column} = get_string('pass', 'block_learnerscript');
                } else if (is_null($userfinalgrade) || $userfinalgrade == '--' || $usergradepass == 0
                || ($row->gradetype == GRADE_TYPE_SCALE && !grade_floats_different($usergradepass, 0.0))) {
                    $row->{$data->column} = '--';
                } else {
                    $row->{$data->column} = get_string('fail', 'block_learnerscript');
                }

            break;

        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }
}
