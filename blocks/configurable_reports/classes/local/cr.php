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

namespace block_configurable_reports\local;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/lib/evalmath/evalmath.class.php');
require_once($CFG->dirroot . "/course/lib.php");
use stdclass;
use DateTime;
use DateTimeZone;
use core_date;
use context_system;
use context_course;
use core_course_category;
use DateInterval;
use moodle_url;

define('DAILY', 1);
define('WEEKLY', 2);
define('MONTHLY', 3);
define('ONDEMAND', -1);

define('OPENSANS', 1);
define('PTSANS', 2);



class cr {
    public function userscormtimespent() {
        global $DB;
        $scormrecord = get_config('block_configurable_reports', 'userscormtimespent');
        if (empty($scormrecord)) {
            set_config('userscormtimespent', 0, 'block_configurable_reports');
        }
        $scormcrontime = get_config('block_configurable_reports', 'userscormtimespent');
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'scorm']);
        if ($scormcrontime == 0) {
            $scormdetails = $DB->get_records_sql("SELECT sst.id, sa.userid, sa.scormid, sst.value AS time
            FROM {scorm_scoes_value} sst
            JOIN {scorm_scoes} ss ON ss.id = sst.scoid
            JOIN {scorm_attempt} sa ON sa.id = sst.attemptid
            JOIN {scorm_element} se ON se.id = sst.elementid
            JOIN {scorm_scoes_value} sst1 ON sst1.scoid = sst.scoid AND sa.id = sst1.attemptid
            JOIN {scorm_element} se1 ON se1.id = sst1.elementid
            WHERE se.element LIKE 'cmi.core.total_time' AND sst1.value IN ('passed', 'completed', 'failed')
            AND sa.userid > 2 ");
            $time = time();
            set_config('userscormtimespent', $time, 'block_configurable_reports');
        } else if ($scormcrontime > 0) {
            $scormdetails = $DB->get_records_sql("SELECT sst.id, sa.userid, sa.scormid, sst.value AS time
            FROM {scorm_scoes_value} sst
            JOIN {scorm_scoes} ss ON ss.id = sst.scoid
            JOIN {scorm_attempt} sa ON sa.id = sst.attemptid
            JOIN {scorm_element} se ON se.id = sst.elementid
            JOIN {scorm_scoes_value} sst1 ON sst1.scoid = sst.scoid AND sa.id = sst1.attemptid
            JOIN {scorm_element} se1 ON se1.id = sst1.elementid
            WHERE se.element LIKE 'cmi.core.total_time' AND sst1.value IN ('passed', 'completed', 'failed')
            AND sa.userid > 2 AND sst.timemodified > :scormcrontime ",
            ['scormcrontime' => $scormcrontime]);
            $time = time();
            set_config('userscormtimespent', $time, 'block_configurable_reports');
        }
        if (empty($scormdetails)) {
            return true;
        }
        foreach ($scormdetails as $scormdetail) {
            $coursemoduleid = $DB->get_field('course_modules', 'id', ['module' => $moduleid,
            'instance' => $scormdetail->scormid, 'visible' => 1, 'deletioninprogress' => 0, ]);
            $courseid = $DB->get_field('scorm', 'course', ['id' => $scormdetail->scormid]);
            $insertdata = new stdClass();
            $insertdata->userid = $scormdetail->userid;
            $insertdata->courseid = $courseid;
            $insertdata->instanceid = $scormdetail->scormid;
            $insertdata->timespent = round($this->timetoseconds($scormdetail->time));
            $insertdata->activityid = $coursemoduleid;
            $insertdata->timecreated = time();
            $insertdata->timemodified = 0;
            $insertdata1 = new stdClass();
            $insertdata1->userid = $scormdetail->userid;
            $insertdata1->courseid = $courseid;
            $insertdata1->timespent = round($this->timetoseconds($scormdetail->time));
            $insertdata1->timecreated = time();
            $insertdata1->timemodified = 0;
            $records1 = $DB->get_records('block_cr_coursetimestats',
                        ['userid' => $insertdata1->userid,
                            'courseid' => $insertdata1->courseid, ]);
            if (!empty($records1)) {
                foreach ($records1 as $record1) {
                    $insertdata1->id = $record1->id;
                    $insertdata1->timespent += round($record1->timespent);
                    $insertdata1->timemodified = time();
                    $DB->update_record('block_cr_coursetimestats', $insertdata1);
                }
            } else {
                $insertdata1->timecreated = time();
                $insertdata1->timemodified = 0;
                $DB->insert_record('block_cr_coursetimestats', $insertdata1);
            }
            $records = $DB->get_records('block_cr_modtimestats',
                        ['courseid' => $insertdata->courseid,
                            'activityid' => $insertdata->activityid,
                            'instanceid' => $insertdata->instanceid,
                            'userid' => $insertdata->userid, ]);
            if ($insertdata->instanceid != 0) {
                if (!empty($records)) {
                    foreach ($records as $record) {
                        $insertdata->id = $record->id;
                        $insertdata->timespent += round($record->timespent);
                        $insertdata->timemodified = time();
                        $DB->update_record('block_cr_modtimestats', $insertdata);
                    }
                } else {
                    $insertdata->timecreated = time();
                    $insertdata->timemodified = 0;
                    $DB->insert_record('block_cr_modtimestats', $insertdata);
                }
            }
        }
    }
    public function timetoseconds($timevalue) {
        $strtime = $timevalue;
        $strtime = preg_replace_callback(
        "/^([\d]{1,2})\:([\d]{2})$/",
        function($matches) {
            return "00:{$matches[1]}:{$matches[2]}";
        },
        $strtime
        );
        sscanf($strtime, "%d:%d:%d", $hours, $minutes, $seconds);
        $timeseconds = $hours * 3600 + $minutes * 60 + $seconds;
        return $timeseconds;
    }
    public function userlmsaccess() {
        global $DB;
        $start    = new DateTime('monday last week');
        $end      = new DateTime('sunday last week');
        $interval = new \DateInterval('P1D');
        $period   = new \DatePeriod($start, $interval, $end);
        foreach ($period as $dt) {
            $weekdays = $dt->format("l");
            $weekdaysql = $dt->format('m/d/Y');
            $weekdaysdate[] = $weekdaysql;
            $weekdayslist[] = $weekdays;
            strtotime($weekdaysql . ' 09:00:00');
        }
        $timingslist = [get_string('firsthr', 'block_configurable_reports'),
                        get_string('secondhr', 'block_configurable_reports'),
                        get_string('thirdhr', 'block_configurable_reports'),
                        get_string('fourthhr', 'block_configurable_reports'),
                        get_string('fifthhr', 'block_configurable_reports'),
                        get_string('sixthhr', 'block_configurable_reports'),
                        get_string('seventhhr', 'block_configurable_reports'),
                        get_string('eighthr', 'block_configurable_reports')];
        $time = new DateTime("now", core_date::get_user_timezone_object());
        $time->add(new DateInterval("P1D"));
        $time->setTime(9, 0, 0);

        $sTime = new DateTime();
        $sTime->setTime(9, 0, 0);

        $eTime = new DateTime();
        $eTime->setTime(19, 0, 0);

        // Calculate the difference between the two times
        $interval = $sTime->diff($eTime);

        // Get the difference in hours
        $hours = $interval->h;

        $startTime = $time->getTimestamp();
        $timeslots = [];
        for($i = 0; $i < $hours; $i++){
            $startHour = date('H', $startTime);
            $endHour = date('H', strtotime('+1 hour', $startTime));
            $endtime = strtotime('+1 hour', $startTime);
            if ($startHour < 10) {
                $timeslots[] = date('H', $startTime) . '-' . $endHour;
                $endtimesec = strtotime('+1 second', $endtime);
            } else if($startHour > 11 && $startHour < 14) {
                $endtimesec = $endtime;
            } else {
                $timeslots[] = date('H:i:s', $startTime) . '-' . $endHour;
                $endtimesec = $endtime;
            }
             $startTime = $endtimesec;
        }
        $users = $DB->get_records_sql("SELECT DISTINCT ra.userid AS id
                        FROM {course} c
                        JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                        JOIN {role_assignments} ra ON ra.contextid = ctx.id
                        JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                        JOIN {user} u ON u.id = ra.userid
                        WHERE c.visible = 1");

        foreach ($users as $user) {
            $j = 0;
            $sessionsdata = [];
            foreach ($timeslots as $sessiontime) {
                $timestampdiff = [];
                for ($i = 0; $i <= count($weekdaysdate) - 1; $i++) {
                    $currenttime = explode('-', $sessiontime);
                    if (is_numeric($currenttime[0])) {
                        $starttime = strtotime($weekdaysdate[$i] . ' ' . $currenttime[0] . ':00:00');
                    } else {
                        $starttime = strtotime($weekdaysdate[$i] . ' ' . $currenttime[0]);
                    }
                    $endtime = strtotime($weekdaysdate[$i] . ' ' . $currenttime[1] . ':00:00');
                    $accesscount = $DB->get_records_sql("SELECT id FROM {logstore_standard_log} WHERE action = :action
                                AND userid = :userid AND timecreated BETWEEN :starttime AND :endtime",
                                    ['action' => 'loggedin', 'userid' => $user->id, 'starttime' => $starttime,
                                    'endtime' => $endtime, ]);
                    $timestampdiff[] = count($accesscount);
                }
                $sessionsdata[] = ['label' => $timingslist[$j], 'data' => $timestampdiff];
                $j++;
            }
            $options = ["type" => "radar",
                            "title" => get_string('lmsaccess', 'block_configurable_reports'),
                            "xAxis" => $weekdayslist,
                            "yAxis" => $timingslist,
                            "data" => $sessionsdata,
                            ];
            $logindata = json_encode($options, JSON_NUMERIC_CHECK);
            $insertdata = new stdClass();
            $record = $DB->get_field_sql("SELECT id FROM {block_cr_userlmsaccess} WHERE userid = :userid",
                                        ['userid' => $user->id]);
            // echo "<pre>";print_r($record);exit;
            if (empty($record)) {
                $insertdata->userid = $user->id;
                $insertdata->logindata = $logindata;
                $insertdata->timecreated = time();
                $insertdata->timemodified = 0;
                $DB->insert_record('block_cr_userlmsaccess', $insertdata);
            } else {
                $insertdata->id = $record;
                $insertdata->userid = $user->id;
                $insertdata->logindata = $logindata;
                $insertdata->timemodified = time();
                $DB->update_record('block_cr_userlmsaccess', $insertdata);
            }
        }
        echo get_string('taskcomplete', 'block_configurable_reports');
    }
    public function userquiztimespent() {
        global $DB;
        $quizrecord = get_config('block_configurable_reports', 'userquiztimespent');

        if (empty($quizrecord)) {
            set_config('userquiztimespent', 0, 'block_configurable_reports');
        }

        $quizcrontime = get_config('block_configurable_reports', 'userquiztimespent');
        // $quizcrontime = 1727671111;
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'quiz']);
        if ($quizcrontime == 0) {

            $quizdetails = $DB->get_records_sql("SELECT DISTINCT qa.id, qa.userid,
            SUM(qa.timefinish - qa.timestart) AS time1, qa.quiz AS quizid, q.course AS courseid
            FROM {user} u
            JOIN {quiz_attempts} qa ON qa.userid = u.id
            JOIN {role_assignments} ra ON ra.userid = qa.userid
            JOIN {quiz} q ON q.id = qa.quiz
            JOIN {enrol} e ON q.course = e.courseid
            WHERE qa.preview = 0 AND qa.state = (:finished) AND qa.userid > 2
            GROUP BY qa.userid, qa.quiz, q.course, qa.id", ['finished' => 'finished']);
            $time = time();
            set_config('userquiztimespent', $time, 'block_configurable_reports');
        } else if ($quizcrontime > 0) {

            $quizdetails = $DB->get_records_sql("SELECT DISTINCT qa.id, qa.userid,
            SUM(qa.timefinish - qa.timestart) AS time1, qa.quiz AS quizid, q.course AS courseid
            FROM {user} u
            JOIN {quiz_attempts} qa ON qa.userid = u.id
            JOIN {role_assignments} ra ON ra.userid = qa.userid
            JOIN {quiz} q ON q.id = qa.quiz
            JOIN {enrol} e ON q.course = e.courseid
            WHERE qa.preview = 0 AND qa.state = (:finished)
            AND qa.timemodified > :quizcrontime AND qa.userid > 2
            GROUP BY qa.userid, qa.quiz, q.course, qa.id", ['finished' => 'finished',
            'quizcrontime' => $quizcrontime, ]);
            $time = time();
            set_config('userquiztimespent', $time, 'block_configurable_reports');
        }

        if (empty($quizdetails)) {
            return true;
        }

        foreach ($quizdetails as $quizdetail) {
            $coursemoduleid = $DB->get_field('course_modules', 'id',
            ['module' => $moduleid, 'instance' => $quizdetail->quizid, 'visible' => 1,
            'deletioninprogress' => 0, ]);
            $courseid = $DB->get_field('quiz', 'course', ['id' => $quizdetail->quizid]);
            $insertdata = new stdClass();
            $insertdata->userid = $quizdetail->userid;
            $insertdata->courseid = $courseid;
            $insertdata->instanceid = $quizdetail->quizid;
            $insertdata->timespent = round($quizdetail->time1);
            $insertdata->activityid = $coursemoduleid;
            $insertdata->timecreated = time();
            $insertdata->timemodified = 0;
            $insertdata1 = new stdClass();
            $insertdata1->userid = $quizdetail->userid;
            $insertdata1->courseid = $courseid;
            $insertdata1->timespent = round($quizdetail->time1);
            $insertdata1->timecreated = time();
            $insertdata1->timemodified = 0;
            $records1 = $DB->get_records('block_cr_coursetimestats',
                        ['userid' => $insertdata1->userid,
                            'courseid' => $insertdata1->courseid, ]);
            // print_r($records1);exit;

            if (!empty($records1)) {
                foreach ($records1 as $record1) {
                    $insertdata1->id = $record1->id;
                    $insertdata1->timespent += round($record1->timespent);
                    $insertdata1->timemodified = time();
                    $DB->update_record('block_cr_coursetimestats', $insertdata1);
                }
            } else {
                $insertdata1->timecreated = time();
                $insertdata1->timemodified = 0;
                $hi = $DB->insert_record('block_cr_coursetimestats', $insertdata1);

            }
            $records = $DB->get_records('block_cr_modtimestats',
                        ['courseid' => $insertdata->courseid,
                            'activityid' => $insertdata->activityid,
                            'instanceid' => $insertdata->instanceid,
                            'userid' => $insertdata->userid, ]);
            // print_r($records);exit;
            if ($insertdata->instanceid != 0) {
                if (!empty($records)) {
                    foreach ($records as $record) {
                        $insertdata->id = $record->id;
                        $insertdata->timespent += round($record->timespent);
                        $insertdata->timemodified = time();
                        $DB->update_record('block_cr_modtimestats', $insertdata);
                    }
                } else {
                    $insertdata->timecreated = time();
                    $insertdata->timemodified = 0;
                    $DB->insert_record('block_cr_modtimestats', $insertdata);
                }
            }
        }
    }
    public function userbigbluebuttonbnspent() {
        global $DB;
        $taskname = '\block_configurable_reports\task\userbigbluebuttonbnspent';
        $task = \core\task\manager::get_scheduled_task($taskname);
        $bbrecord = get_config('block_configurable_reports', 'bbtimespent');
        if (empty($bbrecord)) {
            set_config('bbtimespent', 0, 'block_configurable_reports');
        }
        $bbcrontime = get_config('block_configurable_reports', 'bbtimespent');
        $bigbluebuttonbnleftdetails = $DB->get_records_sql("SELECT DISTINCT lsl.id, lsl.timecreated,
        lsl.userid, lsl.courseid, lsl.contextinstanceid
        FROM {logstore_standard_log} lsl
        JOIN {user} u ON u.id = lsl.userid
        JOIN {course} c ON c.id = lsl.courseid
        WHERE lsl.action = 'left' AND lsl.component = 'mod_bigbluebuttonbn'
        AND lsl.crud = 'r' AND u.confirmed = 1 AND u.deleted = 0 AND lsl.userid > 2
        AND lsl.userid > 2 AND lsl.timecreated > " . $bbcrontime. " ORDER BY lsl.id DESC ");
        if (empty($bigbluebuttonbnleftdetails)) {
            return true;
        }
        foreach ($bigbluebuttonbnleftdetails as $bigbluebuttonbnleftdetail) {
            $bigbuttonid = $DB->get_field_sql("SELECT bb.id
            FROM {bigbluebuttonbn} bb
            JOIN {course_modules} cm ON cm.instance = bb.id
            JOIN {modules} m ON m.id = cm.module
            WHERE m.name = 'bigbluebuttonbn' AND cm.id = " . $bigbluebuttonbnleftdetail->contextinstanceid . "
            AND cm.course = " . $bigbluebuttonbnleftdetail->courseid);
            $bigbluebuttonbnjoindetails = $DB->get_field_sql("SELECT lsl.timecreated
            FROM {logstore_standard_log} lsl
            JOIN {user} u ON u.id = lsl.userid
            JOIN {course} c ON c.id = lsl.courseid
            WHERE lsl.action = 'joined' AND lsl.crud = 'r' AND lsl.component = 'mod_bigbluebuttonbn'
            AND u.confirmed = 1 AND u.deleted = 0 AND lsl.timecreated > " . $bbcrontime. "
            AND lsl.contextinstanceid =".$bigbluebuttonbnleftdetail->contextinstanceid."
            AND lsl.userid =". $bigbluebuttonbnleftdetail->userid." AND lsl.courseid = ".$bigbluebuttonbnleftdetail->courseid."
            AND lsl.timecreated < ".$bigbluebuttonbnleftdetail->timecreated." ORDER BY lsl.id DESC LIMIT 0,1 ");
            if (empty($bigbluebuttonbnjoindetails)) {
                $bigbluebuttonbnjoindetails = $DB->get_field_sql("SELECT bb.closingtime
                FROM {bigbluebuttonbn} bb
                JOIN {course_modules} cm ON cm.instance = bb.id
                JOIN {modules} m ON m.id = cm.module
                WHERE m.name = 'bigbluebuttonbn' AND cm.id = " . $bigbluebuttonbnleftdetail->contextinstanceid . "
                AND cm.course = " . $bigbluebuttonbnleftdetail->courseid);
            }

            $insertdata = new stdClass();
            $insertdata->userid = $bigbluebuttonbnleftdetail->userid;
            $insertdata->courseid = $bigbluebuttonbnleftdetail->courseid;
            $insertdata->instanceid = $bigbuttonid;
            $insertdata->timespent = $bigbluebuttonbnleftdetail->timecreated - $bigbluebuttonbnjoindetails;
            $insertdata->activityid = $bigbluebuttonbnleftdetail->contextinstanceid;
            $insertdata->timecreated = time();
            $insertdata->timemodified = 0;
            $records = $DB->get_records('block_cr_modtimestats',
            ['courseid' => $insertdata->courseid,
            'activityid' => $insertdata->activityid,
            'instanceid' => $insertdata->instanceid,
            'userid' => $insertdata->userid, ]);
            if ($insertdata->instanceid != 0) {
                if (!empty($records)) {
                    foreach ($records as $record) {
                        $insertdata->id = $record->id;
                        $insertdata->timespent += $record->timespent;
                        $insertdata->timemodified = time();
                        $DB->update_record('block_cr_modtimestats', $insertdata);
                    }
                } else {
                    $insertdata->timecreated = time();
                    $insertdata->timemodified = 0;
                    $DB->insert_record('block_cr_modtimestats', $insertdata);
                }
            }
            $insertdata1 = new stdClass();
            $insertdata1->userid = $bigbluebuttonbnleftdetail->userid;
            $insertdata1->courseid = $bigbluebuttonbnleftdetail->courseid;
            $insertdata1->timespent = $bigbluebuttonbnleftdetail->timecreated - $bigbluebuttonbnjoindetails;
            $insertdata1->timecreated = time();
            $insertdata1->timemodified = 0;
            $records1 = $DB->get_records('block_cr_coursetimestats',
            ['userid' => $insertdata1->userid,
            'courseid' => $insertdata1->courseid, ]);
            if (!empty($records1)) {
                foreach ($records1 as $record1) {
                    $insertdata1->id = $record1->id;
                    $insertdata1->timespent += $record1->timespent;
                    $insertdata1->timemodified = time();
                    $DB->update_record('block_cr_coursetimestats', $insertdata1);
                }
            } else {
                $insertdata1->timecreated = time();
                $insertdata1->timemodified = 0;
                $DB->insert_record('block_cr_coursetimestats', $insertdata1);
            }
        }
        set_config('bbtimespent', time(), 'block_configurable_reports');
    }
    public function strtime($values) {
        global $OUTPUT;
        $totalval = $values;
        $day = intval($values / 86400);
        $values -= $day * 86400;
        $hours = intval($values / 3600);
        $values -= $hours * 3600;
        $minutes = intval($values / 60);
        $values -= $minutes * 60;
        $dateimage = $OUTPUT->pix_icon('courseprofile/date', '', 'block_reportdashboard', ['class' => 'dateicon']);
        if (!empty($hours)) {
            $hrs = $hours;
        } else {
            $hrs = 0;
        }
        if (!empty($minutes)) {
            $min = $minutes;
        } else {
            $min = 0;
        }
        if (!empty($values)) {
            $sec = $values;
        } else {
            $sec = 0;
        }
        if (!empty($day)) {
            $days = $day;
        } else {
            $days = 0;
        }
        $timeimage = '';
        if (empty($totalval)) {
            $timeimage = '';
        } else {
            $timeimage = $OUTPUT->pix_icon('courseprofile/time1', '', 'block_reportdashboard', ['class' => 'timeicon']);
        }
        $accesstimedata = new stdclass;
        $accesstimedata->dateimage = $dateimage;
        $accesstimedata->days = $days;
        $accesstimedata->timeimage = $timeimage;
        $accesstimedata->hours = $hrs;
        $accesstimedata->minutes = $min;
        $accesstimedata->seconds = $sec;
        $result = get_string('time', 'block_configurable_reports', $accesstimedata);
        return $result;
    }



}