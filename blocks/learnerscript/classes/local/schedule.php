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

namespace block_learnerscript\local;
use DateTime;
use DateTimeZone;
use context_system;
use context_course;
use stdClass;
use block_learnerscript\local\ls;
use context_helper;
use moodle_exception;
use html_writer;
use moodle_url;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/calendar/lib.php');

define('REPORT_EMAIL', 1);
define('REPORT_EXPORT', 2);
define('REPORT_EXPORT_AND_EMAIL', 3);
define('REPORT_EXPORT_FORMAT_ODS', 1);
define('REPORT_EXPORT_FORMAT_EXCEL', 2);
define('REPORT_EXPORT_FORMAT_CSV', 3);
define('REPORT_EXPORT_FORMAT_PDF', 4);

global $reportexportformats;
$reportexportformats = ['ods' => REPORT_EXPORT_FORMAT_ODS,
    'xls' => REPORT_EXPORT_FORMAT_EXCEL,
    'csv' => REPORT_EXPORT_FORMAT_CSV,
    'pdf' => REPORT_EXPORT_FORMAT_PDF, ];

/**
 * A Moodle block to create customizable reports.
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule {

    /**
     * @var DAILY
     */
    const DAILY = 1;
    /**
     * @var DAILY
     */
    const WEEKLY = 2;
    /**
     * @var DAILY
     */
    const MONTHLY = 3;

    /**
     * Get schedule task
     *
     * @param  int $frequency Schedule frequency
     * @return array
     */
    public function getschedule($frequency) {
        global $USER;
        $calendardays = calendar_get_days();
        // Daily selector.
        $data = [];
        if ($frequency == 1) {
            $dailyselect = [];
            for ($i = 0; $i < 24; $i++) {
                $dailyselect[$i] = date('G:i', mktime($i, 0, 0));
            }
            $data = $dailyselect;
        } else if ($frequency == 2) {
            // Weekly selector.
            $weeklyselect = [];
            for ($i = 0; $i < 7; $i++) {
                if (class_exists('\core_calendar\type_factory')) {
                    $weeklyselect[$i] = get_string(strtolower($calendardays[$i]['shortname']), 'calendar');
                } else {
                    $weeklyselect[$i] = get_string(strtolower($calendardays[$i]), 'calendar');
                }

            }
            $data = $weeklyselect;
        } else if ($frequency == 3) {
            $monthlyselect = [];
            $dateformat = ($USER->lang == 'en') ? 'jS' : 'j';
            for ($i = 1; $i <= 31; $i++) {
                $monthlyselect[$i] = date($dateformat, mktime(0, 0, 0, 0, $i));
            }
            $data = $monthlyselect;
        }

        return $data;
    }

    /**
     * Get available scheduler options
     *
     * @return array
     */
    public static function get_options() {

        return [0 => get_string('select', 'block_learnerscript'),
            self::DAILY => get_string('daily', 'block_learnerscript'),
            self::WEEKLY => get_string('weekly', 'block_learnerscript'),
            self::MONTHLY => get_string('monthly', 'block_learnerscript'), ];
    }

    /**
     * Get next schedule task timestamp
     *
     * @param  object $schedulereport Scheduled report data
     * @param  int $timestamp      Report schedule time
     * @param  boolean $iscron        Schedule cron
     * @return int|object
     */
    public function next($schedulereport, $timestamp = null, $iscron = true) {
        global $USER;
        if (!isset($schedulereport->frequency)) {
            return $this;
        }

        $frequency = $schedulereport->frequency;
        $schedule = $schedulereport->schedule;
        $usertz = \core_date::normalise_timezone($USER->timezone);
        if (is_null($timestamp)) {
            $datetime = new DateTime('now', new DateTimeZone($usertz));
            $timestamp = strtotime($datetime->format('Y-m-d H:i:s'));
        }
        is_null($timestamp) ? $time = time() : $time = $timestamp;
        $timeday = date('j', $time);
        $timemonth = date('n', $time);
        $timeyear = date('Y', $time);

        switch ($frequency) {
            case self::DAILY:
                $offset = (date('G', $time) < $schedule) ? 0 : DAYSECS;
                $nextschedule = mktime(0, 0, 0, $timemonth, $timeday, $timeyear) + $offset + ($schedule * 60 * 60);
                break;
            case self::WEEKLY:
                $calendardays = calendar_get_days();
                if ($schedule <= date('w')) {
                    $day = (7 - date('w')) + $schedule;
                } else {
                    $day = ($schedule - date('w')) + 7;
                }
                if ((($calendardays[$schedule]['fullname'] == strtolower(date('%A', time()))) && (!$iscron))) {
                    $nextschedule = mktime(9, 0, 0, $timemonth, $timeday, $timeyear);
                } else {
                    $nextschedule = mktime(9, 0, 0, $timemonth, $timeday + $day, $timeyear);
                }
                break;
            case self::MONTHLY:
                if (($timeday == $schedule) && (!$iscron)) {
                    $nextschedule = mktime(10, 0, 0, $timemonth, $timeday, $timeyear);
                } else {
                    $offset = ($timeday >= $schedule) ? 1 : 0;
                    $newmonth = $timemonth + $offset;
                    if ($newmonth < 13) {
                        $newyear = $timeyear;
                    } else {
                        $newyear = $timeyear + 1;
                        $newmonth = 1;
                    }

                    $daysinmonth = date('t', mktime(0, 0, 0, $newmonth, 3, $newyear));
                    $newday = ($schedule > $daysinmonth) ? $daysinmonth : $schedule;
                    $nextschedule = mktime(10, 0, 0, $newmonth, $newday, $newyear);
                }
                break;
        }
        // Make the appropriate conversion in case the user is using a different timezone from the server.
        $datetime = new DateTime(date('Y-m-d H:i:s', $nextschedule), new DateTimeZone($usertz));
        $datetime->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $nextschedule = strtotime($datetime->format('Y-m-d H:i:s'));

        return $nextschedule;
    }

    /**
     * Given scheduled report frequency and schedule data, output a human readable string.
     *
     * @param integer $frequent Frequency of the report scheduling
     * @param integer $schedule The scheduled date/time (either hour of day, day or week or day of month)
     * @param object $user User object belonging to the recipient (optional). Defaults to current user
     * @return string schedule desription
     */
    public function get_formatted($frequent, $schedule, $user = false) {
        global $USER;
        if (!$user) {
            $user = $USER;
        }

        $timemonth = date('n', time());
        $timeday = date('j', time());
        $timeyear = date('Y', time());
        $calendardays = calendar_get_days();
        $dateformat = ($USER->lang == 'en') ? 'jS' : 'j';
        $out = '';
        switch ($frequent) {

            case self::DAILY:
                $out .= get_string('daily', 'block_learnerscript') . ' ' . get_string('at', 'block_learnerscript') . ' ';
                $out .= date("h:i A", mktime($schedule, 0, 0, $timemonth, $timeday, $timeyear));
                break;
            case self::WEEKLY:
                $out .= get_string('weekly', 'block_learnerscript') . ' ' . get_string('on', 'block_learnerscript') . ' ';
                if (($calendardays[$schedule]['fullname'])) {
                    $out .= $calendardays[$schedule]['fullname'];
                }
                break;
            case self::MONTHLY:
                $out .= get_string('monthly', 'block_learnerscript') . ' ' . get_string('onthe', 'block_learnerscript') . ' ';
                $out .= date($dateformat, mktime(0, 0, 0, 0, $schedule, $timeyear));
                break;
        }
        return $out;
    }

    /**
     * THis function sends the schedules report in the selected format
     * @param object $schedule Object containing data from schedule table
     * @return bool True/False Email status
     */
    public function scheduledreport_send_scheduled_report($schedule) {
        global $CFG, $DB;

        switch ($schedule->exportformat) {
            case 'xls':
                $attachmentfilename = $schedule->name . '.xls';
                break;
            case 'csv':
                $attachmentfilename = $schedule->name . '.csv';
                break;
            case 'ods':
                $attachmentfilename = $schedule->name . '.ods';
                break;
            case 'pdf':
                $attachmentfilename = $schedule->name . '.pdf';
                break;
        }
        $sendinguserids = explode(',', $schedule->sendinguserid);
        foreach ($sendinguserids as $sendinguserid) {
            if (!$user = $DB->get_record('user', ['id' => $sendinguserid])) {
                debugging(get_string('invaliduserid', 'block_learnerscript'));
                return false;
            }

            $attachment = $this->scheduledreport_create_attachment($schedule, $user);
            if ($schedule->exporttofilesystem != REPORT_EXPORT) {
                $reporturl = $this->get_report_url($schedule->reportid);
                $messagedetails = new stdClass();
                $messagedetails->reportname = $schedule->name;
                $messagedetails->exporttype = get_string($schedule->exportformat . 'format', 'block_learnerscript');
                $messagedetails->reporturl = html_writer::link(new \moodle_url($reporturl), 'View Report');
                $messagedetails->scheduledreportsindex =
                new moodle_url('/blocks/learnerscript/components/scheduler/schedule.php', ['id' =>
                $schedule->reportid]);

                $messagedetails->schedule = $this->get_formatted($schedule->frequency,
                $schedule->schedule, $user);
                $messagedetails->admin = fullname(\core_user::get_user(2));
                $subject = $schedule->name . ' ' . get_string('report', 'block_learnerscript');

                $messagedetails->nodata = '';
                if (empty($attachment)) {
                    $messagedetails->nodata = html_writer::div(get_string('nodataavailable', 'block_learnerscript'),
                    "alert alert-info", []);
                }

                $message = get_string('scheduledreportmessage', 'block_learnerscript',
                $messagedetails);

                $fromaddress = !empty($CFG->noreplyaddress) ? $CFG->noreplyaddress : 'noreply@' . $_SERVER['HTTP_HOST'];

                $emailed = false;
                $messagetext = html_to_text($message);
                $emailed = email_to_user($user, $fromaddress, $subject, $messagetext, $message, $attachment, $attachmentfilename);
            }

            if ($schedule->exporttofilesystem == REPORT_EMAIL) {
                if ($attachment && !unlink($CFG->dataroot . '/' . $attachment)) {
                    mtrace(get_string('error:failedtoremovetempfile', 'block_learnerscript'));
                }
            }
        }
        if ($schedule->frequency == ONDEMAND) {
            $schedule->timemodified = time();
            $DB->update_record('block_ls_schedule', $schedule);
        }
        return true;
    }

    /**
     * Creates an export of a report in specified format (xls, csv or ods)
     *       for adding to email as attachment
     * @param object $schedule schedule record
     * @param object $user
     * @return string Filename of the created attachment
     */
    public function scheduledreport_create_attachment($schedule, $user) {
        global $CFG, $DB;

        $reportid = $schedule->reportid;
        $format = $schedule->exportformat;
        $contextlevel = $schedule->contextlevel;
        if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
            throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
        }
        $role = $DB->get_field('role', 'shortname', ['id' => $schedule->roleid]);
        $reportdata = $this->reportdata($reportid, $user, $role, $contextlevel);

        $reportfilepathname = $this->scheduledreport_get_export_filename($report, $user);
        $finalreportarray = [$reportdata->finalreport];
        if ($schedule->exporttofilesystem == REPORT_EMAIL) {
            if ($finalreportarray[0] == false) {
                return false;
            }
            if (empty($reportdata->finalreport->table->data)) {
                return false;
            }
        }
        switch ($format) {
            case 'ods':
                (new \block_learnerscript\export\export_ods)->export_ods_attachment($reportdata,
                    $CFG->dataroot . '/' . $reportfilepathname);
                $reportfilepathname = $reportfilepathname . '.ods';
                break;
            case 'xls':
                (new \block_learnerscript\export\export_xls)->export_xls_attachment($reportdata,
                    $CFG->dataroot . '/' . $reportfilepathname);
                $reportfilepathname = $reportfilepathname . '.xls';
                break;
            case 'csv':
                (new \block_learnerscript\export\export_csv)->export_csv_attachment($reportdata,
                    $CFG->dataroot . '/' . $reportfilepathname);
                $reportfilepathname = $reportfilepathname . '.csv';
                break;
            case 'pdf':
                (new \block_learnerscript\export\export_pdf)->export_pdf_attachment($reportdata,
                    $CFG->dataroot . '/' . $reportfilepathname);
                $reportfilepathname = $reportfilepathname . '.pdf';
                break;
        }

        get_config('block_learnerscript', 'exportfilesystempath') . DIRECTORY_SEPARATOR . $user->id;
        $reportfilename = $reportfilepathname;
        return $reportfilename;
    }

    /**
     * Checks if username directory under given path exists
     *       If it does not it creates it and returns fullpath with filename
     *       userdir + report fullname + time created + schedule id
     * @param object $report
     * @param object $user
     * @return string reportfullpathname
     */
    public function scheduledreport_get_export_filename($report, $user) {
        global $CFG;
        $reportfilename = format_string($report->name);
        $reportfilename = clean_param($reportfilename, PARAM_FILE) . time();
        $dir = get_config('block_learnerscript', 'exportfilesystempath') . DIRECTORY_SEPARATOR . $user->id;
        if (!file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . $dir)) {
            @mkdir($CFG->dataroot . DIRECTORY_SEPARATOR . $dir, 0777, true);
        }
        $reportfilepathname = $dir . DIRECTORY_SEPARATOR . $reportfilename;

        return $reportfilepathname;
    }

    /**
     * Generates report
     * @param int   $reportid Report ID
     * @param object $user
     * @param int   $role
     * @param int   $contextlevel
     * @return object|array Report data
     */
    public function reportdata($reportid, $user, $role, $contextlevel) {
        global $CFG, $DB;
        if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
            throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
        }

        require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');

        if ($report->courseid == SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance($report->courseid);
        }

        $report->userid = $user->id;
        $reportclassname = 'block_learnerscript\lsreports\report_' . $report->type;
        $properties = new stdClass();
        $properties->userid = $user->id;
        $rolecontexts = $DB->get_records_sql("SELECT DISTINCT CONCAT(r.id, '@', rcl.id),
         r.shortname, rcl.contextlevel
         FROM {role} r
         JOIN {role_context_levels} rcl ON rcl.roleid = r.id AND rcl.contextlevel NOT IN (70)
         WHERE 1 = 1
         ORDER BY rcl.contextlevel ASC");
        foreach ($rolecontexts as $rc) {
            if (has_capability('block/learnerscript:managereports', $context)) {
                continue;
            }
            $rcontext[] = get_string('rolecontexts', 'block_learnerscript', $rc);
        }

        $role = !empty($role) ? $role : 'editingteacher';
        $contextlevel = !empty($contextlevel) ? $contextlevel : 50;
        $contextroles = ['shortname' => $role, 'contextlevel' => $contextlevel];
        $properties->role = $role;
        $properties->contextlevel = $contextlevel;
        $properties->contextrole = get_string('rolecontexts', 'block_learnerscript', $contextroles);
        $properties->moodleroles = $rcontext;
        $reportclass = new $reportclassname($report, $properties);
        $reportclass->courseid = $report->courseid;
        $reportclass->start = 0;
        $reportclass->length = -1;
        $reportclass->search = '';
        $reportclass->lsstartdate = 0;
        $reportclass->lsenddate = time();
        $basicparamdata = new stdclass;
        $reportclass->params = (array)$basicparamdata;
        if (empty($role)) {
            $role = '';
            $rolelist = (new ls)->get_currentuser_roles($user->id);
            $components = (new ls)->cr_unserialize($reportclass->config->components);
            $permissions = (isset($components['permissions'])) ? $components['permissions'] : [];
            if (empty($permissions['elements'])) {
                $role = '';
            } else {
                $rolepermissions = [];
                foreach ($permissions['elements'] as $p) {
                    if ($p['pluginname'] == 'roleincourse') {
                         $rolepermissions[] = $p['formdata']->roleid;
                    }
                }
                sort($rolepermissions);
                $roleslistids = array_keys($rolelist);
                foreach ($rolepermissions as $rolepermission) {
                    if (in_array($rolepermission, $roleslistids)) {
                        $role = $rolelist[$rolepermission];
                        break;
                    }
                }
            }
        }
        $reportclass->scheduling = true;
        if (!$reportclass->check_permissions($context, $user->id)
        && (!has_capability('block/learnerscript:managereports', $context))) {
            return [[], false];
        }
        $reportclass->reporttype = 'table';
        $reportclass->create_report();
        return $reportclass;
    }

    /**
     * Get report URL
     * @todo MDL-7890 URL to view report
     * @param integer $reportid Report ID
     * @return string URL of the report provided or false
     */
    public function get_report_url($reportid) {
        return new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $reportid]);
    }

    /**
     * Getting users by using role wise and searching parameter string
     * @param  string   $roleid  List of roleids
     * @param  string  $search
     * @param  integer $page
     * @param  integer $reportid
     * @param  integer $contextlevel
     * @return array List of users with id and fullname
     */
    public function rolewiseusers($roleid, $search = '', $page = 0, $reportid = 0, $contextlevel = 10) {
        global $DB;
        if (empty($roleid)) {
            throw new moodle_exception(get_string('missingrolevalues', 'block_learnerscript'));
        }
        if ($search) {
            $searchsql = " AND " . $DB->sql_like("CONCAT(u.firstname, ' ', u.lastname)", ':search', false);;
        } else {
            $searchsql = " ";
        }
        $rolewiseuserssql = "SELECT DISTINCT u.id, CONCAT(u.firstname, ' ' , u.lastname) AS fullname
                               FROM {user} u
                               JOIN {role_assignments} ra ON u.id = ra.userid
                               JOIN {context} as ctx on ctx.id = ra.contextid AND ctx.contextlevel= $contextlevel
                               WHERE  ra.roleid = :roleid $searchsql AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0";

        $rolewiseusers = $DB->get_records_sql($rolewiseuserssql, ['roleid' => $roleid, 'search' => '%' . $search . '%']);
        $reportclass = (new ls)->create_reportclass($reportid);
        $reportclass->role = $DB->get_field('role', 'shortname', ['id' => $roleid]);
        $reportclass->courseid = $reportclass->config->courseid;

        if ($reportclass->config->courseid == SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance($reportclass->config->courseid);
        }
        $data = [];

        foreach ($rolewiseusers as $rolewiseuser) {
            if ($reportclass->check_permissions($context, $rolewiseuser->id) && !is_siteadmin($rolewiseuser->id)) {
                $data[] = ['id' => $rolewiseuser->id, 'text' => $rolewiseuser->fullname];
            }
        }
        return $data;
    }
    /**
     * Handling users for bulk selecting to schedule a report
     * Handling both condition to add or remove users to schedule report.
     * @param  integer $reportid           ReportID
     * @param  integer $scheduleid         ScheduleID
     * @param  string  $type               Type usually 'add' or 'remove'
     * @param  array   $roleid             Fetching users by using roles
     * @param  string  $search
     * @param  array  $bullkselectedusers List of users with comma seperatred value
     * @param  integer $contextlevel       User contextlevel
     * @return array List of users with id and fullname
     */
    public function schroleusers($reportid, $scheduleid, $type, $roleid,
    $search = '', $bullkselectedusers = [], $contextlevel = 10) {
        global $DB;

        if (!$reportid) {
            throw new moodle_exception(get_string('missingreportid', 'block_learnerscript'));
        }

        if (!$type) {
            throw new moodle_exception(get_string('missingtype', 'block_learnerscript'));
        }

        if (empty($roleid)) {
            throw new moodle_exception(get_string('missingrole', 'block_learnerscript'));
        }

        if ($search) {
            $searchsql = " AND " . $DB->sql_like("CONCAT(u.firstname, ' ', u.lastname)", ':search', false);
        } else {
            $searchsql = " ";
        }

        if ($bullkselectedusers) {
            $bullkselectedusers = implode(',', $bullkselectedusers);
            $escselsql = " AND u.id NOT IN ($bullkselectedusers) ";
        } else {
            $escselsql = " ";
        }

        switch ($type) {
            case 'add':
                $sql = " SELECT u.id,
                        CONCAT(u.firstname, ' ' , u.lastname) as fullname
                        FROM {user}  as u
                        JOIN {role_assignments} as ra
                        JOIN {context} as ctx ON ctx.id = ra.contextid AND ctx.contextlevel =:contextlevel
                        WHERE u.id = ra.userid  AND ra.roleid = :roleid $searchsql $escselsql
                        AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0";
                $users = $DB->get_records_sql($sql, ['contextlevel' => $contextlevel, 'roleid' => $roleid,
                            'search' => '%' . $search . '%', ]);
                break;

            case 'remove':
                $userslistsql = "SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) as fullname
                        FROM {user} as u
                        JOIN {block_ls_schedule} as bcs ON u.id = bcs.sendinguserid)
                        WHERE bcs.reportid = :reportid $searchsql  AND u.confirmed = 1
                        AND u.suspended = 0 AND u.deleted = 0 ";

                $users = $DB->get_records_sql($userslistsql, ['reportid' => $reportid,
                            'search' => '%' . $search . '%', ]);

                break;
        }
        $data = [];
        foreach ($users as $userdetail) {
            $data[] = ['id' => $userdetail->id, 'fullname' => $userdetail->fullname];
        }
        return $data;
    }
    /**
     * List Of scheduled users list
     * @param  integer $reportid   ReportID
     * @param  integer $scheduleid   ScheduledID
     * @param  string $schuserslist List of userids with comma seperated
     * @param  object $stable contains search value, start, length and table
     * @return array|int List of users with id and fullname
     */
    public function viewschusers($reportid, $scheduleid, $schuserslist, $stable) {
        global $DB;

        if (!$reportid) {
            throw new moodle_exception(get_string('missing_reportid', 'block_learnerscript'));
        }

        if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
            throw new moodle_exception(get_string('reportnotavailable', 'block_learnerscript'));
        }
        if ($stable->table) {
            $schuserscountsql = "SELECT COUNT(u.id) as count
            FROM {user} u
            WHERE u.id IN (".$schuserslist.") AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0";
        } else {
            $schuserscountsql = "SELECT COUNT(u.id) as count
            FROM {user} u
            WHERE u.id IN (".$schuserslist.") AND u.confirmed = 1 AND u.suspended = 0
            AND u.deleted = 0";
            $schuserssql = "SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) as fullname, u.email
            FROM {user} u
            WHERE u.id IN (".$schuserslist.") AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0";
        }

        if (!empty($stable->table)) {
            return $DB->count_records_sql($schuserscountsql);
        } else {
            $fields = ["CONCAT(u.firstname, ' ', u.lastname) ", "u.email"];
            $fields = implode(" LIKE '%" . $stable->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $stable->search . "%' ";
            if ($stable->search) {
                $schuserscountsql .= " AND ( $fields ) ";
                $schuserssql .= " AND ( $fields ) ";
            }
            $viewschuserscount = $DB->count_records_sql($schuserscountsql);
            $schuserssql .= ' ORDER BY u.id ASC';
            $schedulingdata = $DB->get_records_sql($schuserssql, [], $stable->start, $stable->length);
            return compact('schedulingdata', 'viewschuserscount');
        }
    }
    /**
     * Get List of scheduled reports and total count by using report ID
     * @param  integer  $reportid ReportID
     * @param  boolean $table table head (true)/ body (false)
     * @param  integer $start
     * @param  integer $length
     * @param  string  $search
     * @return array  [list of scheduled reports, total scheduled count of each report]
     */
    public function schedulereports($reportid, $table = true, $start = 0, $length = 5, $search = '') {
        global $DB;

        if (!$reportid) {
            throw new moodle_exception(get_string('reportnotavailable', 'block_learnerscript'));
        }

        if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
            throw new moodle_exception(get_string('reportnotavailable', 'block_learnerscript'));
        }

        $fields = " ";
        $params['role'] = '%' . $search . '%';
        $params['exportformat'] = '%' . $search . '%';
        $rolename = $DB->sql_like('sr.role', ":role", false);
        $exportformat = $DB->sql_like('sr.exportformat', ":exportformat", false);
        $fields .= " ($rolename OR $exportformat)";

        $fields1 = " ";
        $params1['rolename1'] = '%' . $search . '%';
        $params1['exportformat1'] = '%' . $search . '%';
        $rolename1 = $DB->sql_like('r.shortname', ':rolename1', false);
        $exportformat1 = $DB->sql_like('bcs.exportformat', ':exportformat1', false);
        $fields1 .= " ($rolename1 OR $exportformat1)";

        if (!$table) {
            $schreportssql = "SELECT * FROM (SELECT bcs.id, bcs.exportformat, bcs.frequency, bcs.schedule,
                                    bcr.name, CASE WHEN bcs.roleid> 0 THEN r.shortname ELSE 'admin' END as role
                                FROM {block_ls_schedule} bcs
                                  JOIN {block_learnerscript} bcr ON bcr.id = bcs.reportid
                           LEFT JOIN {role} r ON r.id = bcs.roleid
                               WHERE bcs.reportid = :reportid AND bcs.frequency > 0
                               GROUP BY bcs.id, r.shortname, bcr.name, bcs.exportformat,
                               bcs.frequency, bcs.schedule, bcs.roleid) sr WHERE 1=1";
        }
        $totalschreportssql = "SELECT * FROM (SELECT COUNT(bcs.id) as totalrecords
                                 FROM {block_ls_schedule} bcs
                                 JOIN {block_learnerscript} bcr ON bcr.id = bcs.reportid
                            LEFT JOIN {role} r ON r.id = bcs.roleid
                                WHERE bcs.reportid = :reportid AND bcs.frequency > 0
                                AND ( $fields1 )) sr WHERE 1=1";
        if ($search) {
            $schreportssql .= " AND ( $fields ) ";
        }
        $params1['reportid'] = $reportid;
        $schreportsdata = $DB->get_record_sql($totalschreportssql, $params1);
        $totalschreports = $schreportsdata->totalrecords;

        $params['reportid'] = $reportid;
        if (!$table) {
            $schreportssql .= ' ORDER BY sr.id DESC';
            $schreports = $DB->get_records_sql($schreportssql, $params, $start, $length);
        } else {
            $schreports = new stdClass;
        }
        return compact('schreports', 'totalschreports');
    }

    /**
     * Get report roles
     *
     * @param  int $selectedroleid Selected report id
     * @param  int $reportid Report ID
     * @return array
     */
    public function reportroles($selectedroleid = '', $reportid = 0) {
        global $DB;
        $selectedroleid = json_decode($selectedroleid, true);

        $reportinstance = (new ls)->cr_get_reportinstance($reportid);

        $components = (new ls)->cr_unserialize($reportinstance->components);
        $permissions = (isset($components->permissions)) ? $components->permissions : '';
        $roles[-1] = 'admin';
        if (!empty($permissions->elements)) {
            foreach ($permissions->elements as $p) {
                if ($p->pluginname == 'roleincourse') {
                    $contextname = context_helper::get_level_name($p->formdata->contextlevel);
                    $rolename = $DB->get_field('role', 'shortname', ['id' => $p->formdata->roleid]);
                    $roles[$p->formdata->roleid .'_'. $p->formdata->contextlevel] = $rolename . ' at '. $contextname .' level';
                }
            }
        } else {
            $roles = [-1 => 'admin'];
        }

        $selected = '';
        ksort($roles);
        $roleslist[] = ['key' => null, 'value' => get_string('selectrole', 'block_learnerscript')];
        foreach ($roles as $key => $value) {
            if ($key == $selectedroleid) {
                $selected = 'selected';
            } else {
                $selected = '';
            }
            $rolecontext = explode("-", $value);
            switch ($rolecontext[0]) {
                case 'admin':
                    $original = $value;
                    break;
                case 'manager':
                    $original = get_string('manager', 'role');
                    break;
                case 'coursecreator':
                    $original = get_string('coursecreators');
                    break;
                case 'editingteacher':
                    $original = get_string('defaultcourseteacher');
                    break;
                case 'teacher':
                    $original = get_string('noneditingteacher');
                    break;
                case 'student':
                    $original = get_string('defaultcoursestudent');
                    break;
                case 'guest':
                    $original = get_string('guest');
                    break;
                case 'user':
                    $original = get_string('authenticateduser');
                    break;
                case 'frontpage':
                    $original = get_string('frontpageuser', 'role');
                    break;
                default:
                    $original = $rolecontext[0];
                    break;
            }
            if (isset($rolecontext[1])) {
                $contextroles = ['shortname' => $original, 'contextlevel' => $rolecontext[1]];
                $rolecontextname = get_string('rolecontexts', 'block_learnerscript', $contextroles);
            } else {
                $rolecontextname = $original;
            }
            $roleslist[] = ['key' => $key, 'value' => $rolecontextname, 'selected' => $selected];
        }
        if (is_array($selectedroleid) && in_array(-1, $selectedroleid)) {
            $selected = get_string('selected', 'block_learnerscript');
        } else {
            $selected = '';
        }

        return $roleslist;
    }

    /**
     * Get list of users
     *
     * @param  integer $reportid
     * @param  integer $scheduledreportid
     * @param  string   $ajaxusers
     * @return array
     */
    public function userslist($reportid, $scheduledreportid, $ajaxusers = '') {
        global $DB;
        $userslist = $DB->get_field('block_ls_schedule', 'sendinguserid',
        [ 'id' => $scheduledreportid, 'reportid' => $reportid]);
        if (!$reportid) {
            throw new moodle_exception(get_string('missingreportid', 'block_learnerscript'));
        }

        if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
            throw new moodle_exception(get_string('reportnotavailable', 'block_learnerscript'));
        }

        if ($scheduledreportid > 0) {
            $schuserscountsql = "SELECT COUNT(u.id) as count
                                          FROM {user} u
                                              JOIN {block_ls_schedule} bcs ON u.id = :userid
                                              WHERE u.confirmed = 1 AND u.suspended = 0
                                              AND u.deleted = 0 AND bcs.reportid = :reportid
                                          AND bcs.id = :scheduledreportid";
            $schuserscount = $DB->count_records_sql($schuserscountsql, ['userid' => $userslist,
                                'reportid' => $reportid,
                                'scheduledreportid' => $scheduledreportid, ]);

            $schuserssql = "SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) as fullname
                                          FROM {user} u
                                          JOIN {block_ls_schedule} bcs ON u.id = :userid
                                          WHERE u.confirmed = 1 AND u.suspended = 0
                                          AND u.deleted = 0 AND bcs.reportid = :reportid
                                          AND bcs.id = :scheduledreportid";
            $schusers = $DB->get_records_sql_menu($schuserssql, ['userid' => $userslist,
                                'reportid' => $reportid,
                                'scheduledreportid' => $scheduledreportid, ], 0, 10);
            if ($schuserscount > 10) {
                $schusers = $schusers + [-1 => get_string('viewmore', 'block_learnerscript')];
            }
            $schusersidssql = "SELECT u.id
                                    FROM {user} u
                                  JOIN {block_ls_schedule} bcs ON u.id = :userid
                                 WHERE u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0
                                 AND bcs.reportid = :reportid AND bcs.id = :scheduledreportid ";
            $schusersids = $DB->get_fieldset_sql($schusersidssql, ['userid' => $userslist,
                                    'reportid' => $reportid,
                                    'scheduledreportid' => $scheduledreportid, ]);
            $schusersids = implode(',', $schusersids);

        } else {
            $schusers = [];
            $schusersids = '';
            if (!empty($ajaxusers)) {
                $schuserscount = $DB->count_records_sql("SELECT COUNT(u.id)
                FROM {user} as u
                WHERE u.id IN ($ajaxusers) ");
                $schusersids = $ajaxusers;
                $schusers = $DB->get_records_sql_menu("SELECT u.id, CONCAT(u.firstname, ' ',
                    u.lastname) as fullname
                                    FROM {user} as u WHERE u.id IN ($ajaxusers) ", [], 0, 10);
                if ($schuserscount > 10) {
                    $schusers = $schusers + [-1 => get_string('viewmore', 'block_learnerscript')];
                }
            }
        }

        return [$schusers, $schusersids];
    }

    /**
     * Select users list
     *
     * @param array $schuserslist Scheduled users list
     * @return array
     */
    public function selectesuserslist($schuserslist) {
        global $DB;
        if ($schuserslist) {
            $selecteduserssql = "SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) as fullname
            FROM {user} u
            WHERE u.id IN (".$schuserslist.")
            AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0";
            $selectedusers = $DB->get_records_sql_menu($selecteduserssql);
        } else {
            $selectedusers = false;
        }
        foreach ($selectedusers as $key => $value) {
            $scheduledusers[] = ['key' => $key, 'value' => $value];
        }
        return $scheduledusers;
    }

    /**
     * Get schedule reports list
     *
     * @param  int $frequency schedule frequerncy
     * @return array
     */
    public function getschedulelist($frequency) {
        if ($frequency == 1) {
            $i = 0;
            for ($i = 0; $i < 24; $i++) {
                if ($i < 10) {
                    $times[] = '0' . $i;
                } else {
                    $times[] = $i;
                }
            }
            $schedule = $times;
        } else if ($frequency == 2) {
            $weeks = [get_string('sun', 'block_learnerscript'), get_string('mon', 'block_learnerscript'),
            get_string('tue', 'block_learnerscript'), get_string('wed', 'block_learnerscript'),
            get_string('thu', 'block_learnerscript'), get_string('fri', 'block_learnerscript'),
            get_string('sat', 'block_learnerscript'), ];
            $schedule = $weeks;
        } else if ($frequency == 3) {
            $i = 0;
            for ($i = 1; $i <= 31; $i++) {
                $months[] = $i;
            }
            $schedule = $months;
        }
        return $schedule;
    }
}
