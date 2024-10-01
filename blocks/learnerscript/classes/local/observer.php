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
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . "/blocks/learnerscript/lib.php");
use stdClass;

/**
 * Class observer
 *
 * @package    block_learnerscript
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Store all actions about modules create/update/delete in own table.
     * @param \core\event\base $event Event data
     * @return void
     */
    public static function store(\core\event\base $event) {
        global $CFG, $DB, $USER;
        if (!is_siteadmin()) {
            $browscap = new \block_learnerscript_browscap($CFG->dataroot . '/cache/');
            $browscap->doAutoUpdate = false;
            $info = $browscap->getBrowser();
            $ipdata = file_get_contents('https://ipinfo.io/'.$_SERVER['REMOTE_ADDR'].'');
            $ipinfo = json_decode($ipdata, true);
            $accessip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $ipinfocountrycode = isset($ipinfo['country']) ? $ipinfo['country'] : '';
            $infobrowser = isset($info->Browser) ? $info->Browser : '';
            $ipinofregion = isset($ipinfo['region']) ? $ipinfo['region'] : '';
            $device = $DB->get_record('block_devicels',  ['userid' => $USER->id, 'accessip' => $accessip,
            'countrycode' => $ipinfocountrycode, 'browser' => $infobrowser, 'region' => $ipinofregion, ], '*', IGNORE_MULTIPLE);
            if (!$device) {
                if (!$DB->record_exists('block_devicels',  ['accessip' => $accessip, 'userid' => $USER->id])) {
                    $deviceinfo = new stdClass;
                    $deviceinfo->accessip = isset($ipinfo['ip']) ? $ipinfo['ip'] : '';
                    $deviceinfo->country = isset($ipinfo['country']) ? get_string($ipinfo['country'], 'countries') : '';
                    $deviceinfo->countrycode = strtoupper($ipinfocountrycode);
                    $deviceinfo->region = $ipinofregion;
                    $deviceinfo->regionName = isset($ipinfo['region']) ? $ipinfo['region'] : '';
                    $deviceinfo->city = isset($ipinfo['city']) ? $ipinfo['city'] : '';
                } else {
                    $deviceinfo = $DB->get_record_sql("SELECT accessip,country,countryCode,region,regionName,city
                    FROM {block_devicels}
                    WHERE accessip = :accessip", ['accessip' => $accessip]);
                    if (empty($deviceinfo)) {
                        $deviceinfo = new stdClass;
                    }
                }
                $deviceinfo->userid = $USER->id;
                $deviceinfo->browser = $infobrowser;
                $deviceinfo->browserparent = isset($info->Parent) ? $info->Parent : '';
                $deviceinfo->platform = isset($info->Platform) ? $info->Platform : '';
                $deviceinfo->browserversion = isset($info->Version) ? $info->Version : '';
                $deviceinfo->devicetype = isset($info->Device_Type) ? $info->Device_Type : '';
                $deviceinfo->pointingmethod = isset($info->Device_Pointing_Method) ? $info->Device_Pointing_Method : '';
                $deviceinfo->ismobiledevice = isset($info->isMobileDevice) ? $info->isMobileDevice : 0;
                $deviceinfo->istablet = isset($info->isTablet) ? $info->isTablet : 0;
                $deviceinfo->timemodified = time();
                $DB->insert_record('block_devicels',  $deviceinfo);
            } else {
                $deviceinfo = new stdClass;
                $deviceinfo->id = $device->id;
                $deviceinfo->timemodified = time();
                $DB->update_record('block_devicels',  $deviceinfo);
            }
        }
    }
    /**
     * Store all actions about modules create/update/delete in own table.
     *
     */
    public static function ls_timestats() {
        global $COURSE, $USER, $DB, $PAGE;
        $reluser = \core\session\manager::is_loggedinas() ? $USER->id : null;
        if ($USER && is_siteadmin($reluser) || $reluser) {
            return true;
        }
        $activityid = $PAGE->context->instanceid;
        if ($PAGE->context->contextlevel == 70 && $PAGE->context->instanceid > 0) {
            $modulename = $DB->get_field_sql("SELECT m.name
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            WHERE cm.id = (:activityid)
            AND cm.visible = :cmvisible
            AND cm.deletioninprogress = :deletioninprogress", ['activityid' => $activityid,
            'cmvisible' => 1, 'deletioninprogress' => 0, ]);
            if ($modulename == 'scorm' || $modulename == 'quiz') {
                return false;
            }
        }
        // Used $_SESSION to get loggedin user information to calculate the timespent.
        $insertdata = new \stdClass();
        $insertdata->userid = isset($_SESSION['USER']->id) ? $_SESSION['USER']->id : 0;
        $insertdata->courseid = isset($_SESSION['courseid']) ? $_SESSION['courseid'] : SITEID;
        $insertdata->instanceid = isset($_SESSION['instanceid']) ? $_SESSION['instanceid'] : 0;
        $insertdata->activityid = isset($_SESSION['activityid']) ? $_SESSION['activityid'] : 0;
        $insertdata->timespent = isset($_COOKIE['time_timeme']) ? round($_COOKIE['time_timeme']) : '';
        $insertdata1 = new \stdClass();
        $insertdata1->userid = isset($_SESSION['USER']->id) ? $_SESSION['USER']->id : 0;
        $insertdata1->courseid = isset($_SESSION['courseid']) ? $_SESSION['courseid'] : SITEID;
        $insertdata1->timespent = isset($_COOKIE['time_timeme']) ? round($_COOKIE['time_timeme']) : '';
        // print_r($insertdata1);exit;

        if (isset($_COOKIE['time_timeme']) && isset($_SESSION['pageurl_timeme']) &&
            $_COOKIE['time_timeme'] != 0) {
            // print_r($_COOKIE['time_timeme']);exit;

            $record1 = $DB->get_record('block_ls_coursetimestats',
                ['courseid' => $insertdata1->courseid,
                    'userid' => $insertdata1->userid, ], '*', IGNORE_MULTIPLE);
            if ($record1) {
                $insertdata1->id = $record1->id;
                $insertdata1->timespent += round($record1->timespent);
                $insertdata1->timemodified = time();
                $DB->update_record('block_ls_coursetimestats', $insertdata1);
            } else {
                $insertdata1->timecreated = time();
                $insertdata1->timemodified = 0;
                $DB->insert_record('block_ls_coursetimestats', $insertdata1);
            }
            if ($PAGE->context->contextlevel == 70 && $insertdata->instanceid <> 0) {
                $record = $DB->get_record('block_ls_modtimestats', ['courseid' => $insertdata->courseid,
                                                                        'activityid' => $insertdata->activityid,
                                                                        'instanceid' => $insertdata->instanceid,
                                                                        'userid' => $insertdata->userid, ], '*', IGNORE_MULTIPLE);
                if ($record) {
                    $insertdata->id = $record->id;
                    $insertdata->timespent += round($record->timespent);
                    $insertdata->timemodified = time();
                    $DB->update_record('block_ls_modtimestats', $insertdata);
                } else {
                    $insertdata->timecreated = time();
                    $insertdata->timemodified = 0;
                    $DB->insert_record('block_ls_modtimestats', $insertdata);
                }
            }
             $_COOKIE['time_timeme'] = 0;
             unset($_COOKIE['time_timeme']);
        } else {
            $_COOKIE['time_timeme'] = 0;
            $_SESSION['pageurl_timeme'] = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'])['path'] : '';
            $_SESSION['time_timeme'] = round($_COOKIE['time_timeme'], 0);

        }
        $instance = 0;
        if ($PAGE->context->contextlevel == 70) {
            $cm = get_coursemodule_from_id('', $PAGE->context->instanceid);
            $instance = $cm->instance;
        }
        $_SESSION['courseid'] = $COURSE->id;
        $_SESSION['pageurl_timeme'] = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'])['path'] : '';
        $_SESSION['instanceid'] = $instance;
        $_SESSION['activityid'] = $PAGE->context->instanceid;
        // print_r('sdsdsddddddddddddddddddd');
        $PAGE->requires->js_call_amd('block_learnerscript/track', 'timeme');
        $_COOKIE['time_timeme'] = 0;
        unset($_COOKIE['time_timeme']);
    }
}
