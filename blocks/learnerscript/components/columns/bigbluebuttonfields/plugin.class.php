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
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls;
use context_system;
use html_writer;
use moodle_url;
/**
 * Bibluebuttton field columns
 */
class plugin_bigbluebuttonfields extends pluginbase {
    /**
     * Bigbluebutton field column init function
     */
    public function init() {
        $this->fullname = get_string('bigbluebuttonfields', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['bigbluebutton'];
    }
    /**
     * Badges column summary
     * @param object $data Assignment participation report column name
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
     * @param string $reporttype Report type
     * @return object
     */
    public function execute($data, $row, $reporttype = null) {
        global $DB, $OUTPUT, $USER, $CFG;
        $context = context_system::instance();
        switch($data->column) {
            case 'session':
                $module = 'bigbluebuttonbn';
                $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                $url = new moodle_url('/mod/'.$module.'/view.php',
                ['id' => $row->activityid]);
                $row->{$data->column} = $activityicon . html_writer::tag('a', $row->session, ['href' => $url]);
            break;
            case 'course':
                $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'courseprofile'], IGNORE_MULTIPLE);
                $checkpermissions = empty($reportid) ? false :
                (new reportbase($reportid))->check_permissions($context, $USER->id);
                if (empty($reportid) || empty($checkpermissions)) {
                    $row->{$data->column} = html_writer::link(new moodle_url('/course/view.php',
                    ['id' => $row->courseid]), $row->course);
                } else {
                    $row->{$data->column} = html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                        ['id' => $reportid, 'filter_courses' => $row->courseid]), $row->course
                    );
                }
            break;
            case 'timestart':
                if (!isset($row->timestart)) {
                    $timestart = $DB->get_field_sql($data->subquery);
                } else {
                    $timestart = $row->{$data->column};
                }
                $row->{$data->column} = !empty($timestart) ? userdate($timestart) : '--';
            break;
            case 'sessionjoinedat':
                if (!isset($row->sessionjoinedat)) {
                    $sessionjoinedat = $DB->get_field_sql($data->subquery);
                } else {
                    $sessionjoinedat = $row->{$data->column};
                }
                $row->{$data->column} = !empty($sessionjoinedat) ? userdate($sessionjoinedat) : '--';
            break;
            case 'duration':
                if (!isset($row->duration)) {
                    $duration = $DB->get_field_sql($data->subquery);
                } else {
                    $duration = $row->{$data->column};
                }
                $duration = ($duration > 0) ? $duration : '';
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($duration) ? (new ls)->strTime($duration) : '--';
                } else {
                    $row->{$data->column} = !empty($duration) ? $duration : 0;
                }
                break;
            case 'inactivestudents':
                $learnersql  = (new querylib)->get_learners('', $row->courseid);
                list($learnsql, $learnparams) = $DB->get_in_or_equal($learnersql, SQL_PARAMS_NAMED);
                $learnparams['courseid'] = $row->courseid;
                $activeusers = $DB->get_record_sql("SELECT COUNT(DISTINCT bbbl.userid) AS active
                            FROM {user} u
                            JOIN {bigbluebuttonbn_logs} bbbl ON bbbl.userid = u.id
                            JOIN {bigbluebuttonbn} bbb ON bbb.id = bbbl.bigbluebuttonbnid
                            JOIN {course} as c ON c.id = bbb.course
                            JOIN {context} ct ON ct.instanceid = c.id
                            JOIN {role_assignments} ra ON ra.contextid = ct.id AND ra.userid = u.id
                            JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'
                            WHERE bbbl.bigbluebuttonbnid = $row->id AND ct.instanceid = c.id
                            AND u.confirmed = 1 AND u.deleted = 0 AND ra.userid $learnsql)
                            AND c.id = :courseid", $learnparams, IGNORE_MULTIPLE);
                $enrolledusers = $DB->get_records_sql($learnersql);
                $inactiveusers = count($enrolledusers) - $activeusers->active;
                $row->{$data->column} = !empty($inactiveusers) ? $inactiveusers : 0;
            break;
            case 'learner':
                $userprofilereport = $DB->get_field('block_learnerscript', 'id', ['type' => 'userprofile'], IGNORE_MULTIPLE);
                $checkpermissions = empty($userprofilereport) ? false :
                (new reportbase($userprofilereport))->check_permissions($context, $USER->id);
                if ($this->report->type == 'userprofile' || empty($userprofilereport) || empty($checkpermissions)) {
                    $row->{$data->column} = html_writer::tag('a', $row->learner,
                    ['href' => new moodle_url('/user/profile.php', ['id' => $row->id])]);
                } else {
                    $row->{$data->column} = html_writer::tag('a', $row->learner,
                    ['href' => new moodle_url('/blocks/learnerscript/viewreport.php',
                    ['id' => $userprofilereport, 'filter_users' => $row->id])]);
                }
            break;
            case 'activestudents':
                $userprofilereport = $DB->get_field('block_learnerscript', 'id',
                ['type' => 'activestudents'], IGNORE_MULTIPLE);
                $checkpermissions = empty($userprofilereport) ? false :
                (new reportbase($userprofilereport))->check_permissions($context, $USER->id);
                if (empty($userprofilereport) || empty($checkpermissions)) {
                    $row->{$data->column} = $row->{$data->column};
                } else {
                    $row->{$data->column} = html_writer::tag('a', $row->activestudents,
                    ['href' => new moodle_url('/blocks/learnerscript/viewreport.php',
                    ['id' => $userprofilereport, 'filter_session' => $row->id])]);
                }
            break;
            case 'sessionsduration':
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($row->sessionsduration) ? (new ls)->strtime($row->sessionsduration) : '--';
                } else {
                    $row->{$data->column} = !empty($row->{$data->column}) ? $row->{$data->column} : 0;
                }

            break;

        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : ' -- ';
    }
}
