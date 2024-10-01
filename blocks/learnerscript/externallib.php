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
 * External lib functions
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/blocks/learnerscript/lib.php');
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use block_learnerscript\local\ls as ls;
use block_learnerscript\local\reportbase as reportbase;
use block_learnerscript\local\schedule;
use context_system as contextsystem;
use block_learnerscript\local\license_setting as lssetting;
use tool_usertours\tour;

/**
 * Learnescript external api
 */
class block_learnerscript_external extends external_api {
    /**
     * Roles wise users parameters description
     * @return external_function_parameters
     */
    public static function rolewiseusers_parameters() {
        return new external_function_parameters(
            [
                'roleid' => new external_value(PARAM_INT, 'role id of report', VALUE_DEFAULT),
                'term' => new external_value(PARAM_TEXT, 'Current search term in search box', VALUE_DEFAULT),
                'contextlevel' => new external_value(PARAM_INT, 'contextlevel of role', VALUE_DEFAULT),
                'page' => new external_value(PARAM_INT, 'Current page number to request', VALUE_DEFAULT),
                '_type' => new external_value(PARAM_TEXT, 'A "request type" will be usually a query', VALUE_DEFAULT),
                'reportid' => new external_value(PARAM_INT, 'Report id of report', VALUE_DEFAULT),
                'action' => new external_value(PARAM_TEXT, 'action', VALUE_DEFAULT),
                'maximumselectionlength' => new external_value(PARAM_INT, 'maximum selection length to search', VALUE_DEFAULT),
                'courses' => new external_value(PARAM_INT, 'Course id of report', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * Displaying list of users based on the selected role and search string.
     *
     * @param int $roleid Role ID
     * @param string $term Search text
     * @param int $contextlevel Role contextlevel
     * @param int $page Page
     * @param string $type Type of the filter
     * @param int $reportid Report ID
     * @param string $action Action
     * @param int $maximumselectionlength Maximum length of the entered string
     * @param array $courses Courses list
     * @return object
     */
    public static function rolewiseusers($roleid, $term, $contextlevel, $page,
    $type, $reportid, $action, $maximumselectionlength, $courses) {
        global $DB, $CFG;
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:managereports', $context);
        $roles = $roleid;
        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::rolewiseusers_parameters(), ['roleid' => $roleid, 'term' => $term,
        'contextlevel' => $contextlevel, 'page' => $page, '_type' => $type, 'reportid' => $reportid,
        'action' => $action, 'maximumselectionlength' => $maximumselectionlength, 'courses' => $courses, ]);

        if ((has_capability('block/learnerscript:managereports', $context) ||
            has_capability('block/learnerscript:manageownreports', $context) ||
            is_siteadmin()) && !empty($roles)) {
            if ($roles == -1) {
                $admins = get_admins();
                $userlist = [];
                foreach ($admins as $admin) {
                    $userlist[] = ['id' => $admin->id, 'text' => fullname($admin)];
                }
            } else {
                $userlist = (new schedule)->rolewiseusers($roles, $term, $page, $reportid, $contextlevel);
            }
            $termsdata = [];
            $termsdata['total_count'] = count($userlist);
            $termsdata['incomplete_results'] = false;
            $termsdata['items'] = $userlist;
            $return = $termsdata;
        } else {
            $termsdata = [];
            $termsdata['error'] = true;
            $termsdata['type'] = 'Warning';
            if (empty($roles)) {
                $termsdata['cap'] = false;
                $termsdata['msg'] = get_string('missingparam', 'block_learnerscript', 'Role');
            } else {
                $termsdata['cap'] = true;
                $termsdata['msg'] = get_string('badpermissions', 'block_learnerscript');
            }
            $termsdata['total_count'] = 0;
            $termsdata['incomplete_results'] = false;
            $termsdata['items'] = [];
            $return = $termsdata;
        }
        $data = json_encode($return);
        return $data;
    }
    /**
     * Roles wise users
     * @return external_description
     */
    public static function rolewiseusers_returns() {
        return new external_value(PARAM_TEXT, 'data');
    }
    /**
     * User roles parameters
     * @return external_function_parameters
     */
    public static function roleusers_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_INT, 'Report id of report', VALUE_DEFAULT),
                'scheduleid' => new external_value(PARAM_INT, 'selected schedule for report', VALUE_DEFAULT),
                'selectedroleid' => new external_value(PARAM_RAW, 'selected role for report', VALUE_DEFAULT),
                'roleid' => new external_value(PARAM_RAW, 'roleid for report', VALUE_DEFAULT),
                'contextlevel' => new external_value(PARAM_INT, 'contextlevel of role', VALUE_DEFAULT),
                'term' => new external_value(PARAM_TEXT, 'Current search term in search box', VALUE_DEFAULT),
                'type' => new external_value(PARAM_TEXT, 'A "request type" will be usually a query', VALUE_DEFAULT),
                'bullkselectedusers' => new external_value(PARAM_RAW, 'bulk users selected', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * Displays the list of users based on selected roles
     * @param int $reportid Scheduled report ID
     * @param int $scheduleid Schedule ID
     * @param int $selectedroleid Selected role id to share the scheduled report
     * @param int $roleid Roled ID
     * @param int $contextlevel Contextlevel of the selected role
     * @param string $term Search text
     * @param string $type Type of the report
     * @param string $bullkselectedusers Selected users
     * @return string
     */
    public static function roleusers($reportid, $scheduleid, $selectedroleid,
    $roleid, $contextlevel, $term, $type, $bullkselectedusers) {
        global $DB;
        $roleid = json_decode($roleid);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);
        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::roleusers_parameters(), ['reportid' => $reportid, 'scheduleid' => $scheduleid,
        'selectedroleid' => $selectedroleid, 'roleid' => $roleid, 'contextlevel' => $contextlevel, 'term' => $term,
        'type' => $type, 'bullkselectedusers' => $bullkselectedusers, ]);
        $bullkselectedusers = json_decode($bullkselectedusers);
        if ((has_capability('block/learnerscript:managereports', $context) ||
            has_capability('block/learnerscript:manageownreports', $context) ||
            is_siteadmin()) && !empty($reportid) && !empty($type) && !empty($roleid)) {
            if ($roleid == -1) {
                 $escselsql = "";
                if ($bullkselectedusers) {
                    $bullkselectedusersdata = implode(',', $bullkselectedusers);
                    $escselsql = " AND u.id NOT IN ($bullkselectedusersdata) ";
                }
                $siteadmins = array_keys(get_admins());
                list($adsql, $params) = $DB->get_in_or_equal($siteadmins, SQL_PARAMS_NAMED);
                $adminssql = "SELECT u.id, CONCAT(u.firstname, ' ' , u.lastname) AS fullname
                                    FROM {user} u
                                   WHERE 1 = 1
                                     AND u.id $adsql $escselsql";
                $admins = $DB->get_records_sql($adminssql, $params);
                $userslist = [];
                foreach ($admins as $admin) {
                    $userslist[] = ['id' => $admin->id, 'fullname' => $admin->fullname];
                }
            } else {
                $userslist = (new schedule)->schroleusers($reportid, $scheduleid, $type,
                                                    $roleid, $term, $bullkselectedusers, $contextlevel);
            }
            $termsdata = [];
            $termsdata['total_count'] = count($userslist);
            $termsdata['incomplete_results'] = false;
            $termsdata['items'] = $userslist;
            $return = $termsdata;
        } else {
            $termsdata = [];
            $termsdata['error'] = true;
            $termsdata['type'] = 'Warning';
            if (empty($reportid)) {
                $termsdata['cap'] = false;
                $termsdata['msg'] = get_string('missingparam', 'block_learnerscript', 'ReportID');
            } else if (empty($type)) {
                $termsdata['cap'] = false;
                $termsdata['msg'] = get_string('missingparam', 'block_learnerscript', 'Type');
            } else if (empty($roles)) {
                $termsdata['cap'] = false;
                $termsdata['msg'] = get_string('missingparam', 'block_learnerscript', 'Role');
            } else {
                $termsdata['cap'] = true;
                $termsdata['msg'] = get_string('badpermissions', 'block_learnerscript');
            }
            $return = $termsdata;
        }
        $data = json_encode($return);
        return $data;
    }
    /**
     * User roles
     * @return external_description
     */
    public static function roleusers_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    /**
     * View Schedule Users parameters description
     * @return external_function_parameters
     */
    public static function viewschuserstable_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_INT, 'Report id of report', VALUE_DEFAULT),
                'scheduleid' => new external_value(PARAM_INT, 'selected schedule for report', VALUE_DEFAULT),
                'schuserslist' => new external_value(PARAM_TEXT, 'list of scheduled users', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * View Schedule Users
     * @param int $reportid Report ID
     * @param int $scheduleid Report scheduled ID
     * @param string $schuserslist Scheduled users list
     * @return bool
     */
    public static function viewschuserstable($reportid, $scheduleid, $schuserslist) {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:managereports', $context);
        self::validate_parameters(self::viewschuserstable_parameters(), ['reportid' => $reportid,
        'scheduleid' => $scheduleid, 'schuserslist' => $schuserslist, ]);

        if ((has_capability('block/learnerscript:managereports', $context) ||
            has_capability('block/learnerscript:manageownreports', $context) ||
            is_siteadmin()) && !empty($schuserslist)) {
            $stable = new stdClass();
            $stable->table = true;
            $return = (new schedule)->viewschusers($reportid, $scheduleid, $schuserslist, $stable);
        } else {
            $termsdata = [];
            $termsdata['error'] = true;
            $termsdata['type'] = 'Warning';
            if (empty($schuserslist)) {
                $termsdata['cap'] = false;
                $termsdata['msg'] = get_string('missingparam', 'block_learnerscript', 'Schedule Users List');
            } else {
                $termsdata['cap'] = true;
                $termsdata['msg'] = get_string('badpermissions', 'block_learnerscript');
            }
            $return = $termsdata;
        }
        $data = json_encode($return);
        return $data;
    }
    /**
     * View Schedule Users
     * @return external_description
     */
    public static function viewschuserstable_returns() {
        return new external_value(PARAM_TEXT, 'data');
    }
    /**
     * Manage Schedule Users description
     */
    public static function manageschusers_is_allowed_from_ajax() {
        return true;
    }
    /**
     * Manage Schedule Users parameters description
     * @return external_function_parameters
     */
    public static function manageschusers_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_INT, 'report id of report', VALUE_DEFAULT),
                'scheduleid' => new external_value(PARAM_RAW, 'schedule id', VALUE_DEFAULT),
                'schuserslist' => new external_value(PARAM_RAW, '', VALUE_DEFAULT),
                'selectedroleid' => new external_value(PARAM_RAW, 'selected role id', VALUE_DEFAULT),
                'reportinstance' => new external_value(PARAM_INT, 'report instance', VALUE_DEFAULT),

            ]
        );
    }
    /**
     * Manage Schedule Users description
     * @param int $reportid Report ID
     * @param int $scheduleid Schedule ID
     * @param array $schuserslist Scheduled users list
     * @param int $selectedroleid Scheduled report ID
     * @param string $reportinstance Report instance type
     */
    public static function manageschusers($reportid, $scheduleid, $schuserslist, $selectedroleid, $reportinstance) {
        global $OUTPUT;
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);

        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::manageschusers_parameters(), ['reportid' => $reportid, 'scheduleid' => $scheduleid,
        'schuserslist' => $schuserslist, 'selectedroleid' => $selectedroleid, 'reportinstance' => $reportinstance, ]);

        if ((has_capability('block/learnerscript:managereports', $context) ||
            has_capability('block/learnerscript:manageownreports', $context) ||
            is_siteadmin()) && !empty($reportid)) {
            $roleslist = (new schedule)->reportroles($selectedroleid, $reportid);
            $selectedusers = (new schedule)->selectesuserslist($schuserslist);
            $reqimage = $OUTPUT->image_url('req');
            $scheduledata = new \block_learnerscript\output\scheduledusers($reportid,
            $reqimage, $roleslist, $selectedusers, $scheduleid, $reportinstance);
            $return = (new ls)->get_scheduleusersdata($scheduledata);
        } else {
            $termsdata = [];
            $termsdata['error'] = true;
            $termsdata['type'] = 'Warning';
            if (empty($reportid)) {
                $termsdata['cap'] = false;
                $termsdata['msg'] = get_string('missingparam', 'block_learnerscript', 'ReportID');
            } else {
                $termsdata['cap'] = true;
                $termsdata['msg'] = get_string('badpermissions', 'block_learnerscript');
            }
            $return = $termsdata;
        }
        $data = json_encode($return);
        return $data;
    }
    /**
     * Manage Schedule Users description returns
     * @return external_description
     */
    public static function manageschusers_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    /**
     * Schedule Report Form parameters description
     * @return external_function_parameters
     */
    public static function schreportform_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_INT, 'report id of report', VALUE_DEFAULT),
                'instance' => new external_value(PARAM_INT, 'Instance', VALUE_DEFAULT),
                'schuserslist' => new external_value(PARAM_TEXT, 'List of scheduled users', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * Schedule Report Form
     * @param int $reportid Report ID
     * @param int $instance Report instance
     * @param string $schuserslist Scheduled users list
     */
    public static function schreportform($reportid, $instance, $schuserslist) {
        global $CFG, $DB;
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:managereports', $context);

        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::schreportform_parameters(), ['reportid' => $reportid,
        'instance' => $instance, 'schuserslist' => $schuserslist, ]);

        if ((has_capability('block/learnerscript:managereports', $context) ||
            has_capability('block/learnerscript:manageownreports', $context) ||
            is_siteadmin()) && !empty($reportid)) {
            require_once($CFG->dirroot . '/blocks/learnerscript/components/scheduler/schedule_form.php');
            $roleslist = (new schedule)->reportroles('', $reportid);
            list($schusers, $schusersids) = (new schedule)->userslist($reportid, $scheduleid);
            $exportoptions = (new ls)->cr_get_export_plugins();
            $frequencyselect = (new schedule)->get_options();
            $scheduledreport = $DB->get_record('block_ls_schedule', ['id' => $scheduleid]);
            if (!empty($scheduledreport)) {
                $schedulelist = (new schedule)->getschedule($scheduledreport->frequency);
            } else {
                $schedulelist = [null => get_string('selectall', 'block_reportdashboard')];
            }
            $scheduleform = new scheduled_reports_form(new moodle_url('/blocks/learnerscript/components/scheduler/schedule.php',
            ['id' => $reportid, 'scheduleid' => $scheduleid, 'AjaxForm' => true, 'roleslist' => $roleslist,
                'schusers' => $schusers, 'schusersids' => $schusersids, 'exportoptions' => $exportoptions,
                'schedulelist' => $schedulelist, 'frequencyselect' => $frequencyselect, 'instance' => $instance, ]));
            $return = $scheduleform->render();
        } else {
            $termsdata = [];
            $termsdata['error'] = true;
            $termsdata['type'] = 'Warning';
            if (empty($reportid)) {
                $termsdata['cap'] = false;
                $termsdata['msg'] = get_string('missingparam', 'block_learnerscript', 'ReportID');
            } else {
                $termsdata['cap'] = true;
                $termsdata['msg'] = get_string('badpermissions', 'block_learnerscript');
            }
            $return = $termsdata;
        }
        $data = json_encode($return);
        return $data;
    }
    /**
     * Schedule Report Form description returns
     * @return external_description
     */
    public static function schreportform_returns() {
        return new external_value(PARAM_TEXT, 'data');
    }
    /**
     * Generate Plotgraph parameters description
     * @return external_function_parameters
     */
    public static function generate_plotgraph_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_INT, 'report id of report', VALUE_DEFAULT),
                'courseid' => new external_value(PARAM_INT, 'course id of course', VALUE_DEFAULT),
                'cmid' => new external_value(PARAM_INT, 'The course module id for the course', VALUE_DEFAULT),
                'status' => new external_value(PARAM_TEXT, 'status', VALUE_DEFAULT),
                'userid' => new external_value(PARAM_INT, 'user id', VALUE_DEFAULT),
                'lsfstartdate' => new external_value(PARAM_INT, 'start date for date filter', VALUE_DEFAULT),
                'lsfenddate' => new external_value(PARAM_INT, 'end date for date filter', VALUE_DEFAULT),
                'reporttype' => new external_value(PARAM_TEXT, 'type of report', VALUE_DEFAULT),
                'action' => new external_value(PARAM_TEXT, 'action', VALUE_DEFAULT),
                'singleplot' => new external_value(PARAM_INT, 'single plot', VALUE_DEFAULT),
                'cols' => new external_value(PARAM_RAW, 'columns', VALUE_DEFAULT),
                'instanceid' => new external_value(PARAM_RAW, 'id of instance', VALUE_DEFAULT),
                'container' => new external_value(PARAM_TEXT, 'container', VALUE_DEFAULT),
                'filters' => new external_value(PARAM_TEXT, 'applied filters', VALUE_DEFAULT),
                'basicparams' => new external_value(PARAM_TEXT, 'basic params required to generate graph', VALUE_DEFAULT),
                'columnDefs' => new external_value(PARAM_RAW, 'column definitions', VALUE_DEFAULT),
                'reportdashboard' => new external_value(PARAM_TEXT, 'report dashboard', VALUE_DEFAULT, true),
            ]
        );
    }
    /**
     * Generate Plotgraph description
     * @param int $reportid Report ID
     * @param int $courseid Course ID
     * @param int $cmid Course module ID
     * @param int $status Report status
     * @param int $userid User ID
     * @param int $lsfstartdate Start date
     * @param int $lsfenddate End date
     * @param string $reporttype Report type
     * @param string $action Action
     * @param int $singleplot Singleplot
     * @param array $cols Report columns
     * @param int $instanceid Report instance ID
     * @param string $container Report container
     * @param string $filters Report filters list
     * @param string $basicparams Mandatory filters list
     * @param array $columndefs Column definations
     * @param bool $reportdashboard Reportdashboard
     * @return string
     */
    public static function generate_plotgraph($reportid, $courseid, $cmid, $status, $userid,
        $lsfstartdate, $lsfenddate, $reporttype, $action, $singleplot, $cols, $instanceid,
        $container, $filters, $basicparams, $columndefs, $reportdashboard) {
        global $DB;
        $ls = new ls();
        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::generate_plotgraph_parameters(), ['reportid' => $reportid,
        'courseid' => $courseid, 'cmid' => $cmid, 'status' => $status, 'userid' => $userid,
        'lsfstartdate' => $lsfstartdate, 'lsfenddate' => $lsfenddate, 'reporttype' => $reporttype,
        'action' => $action, 'singleplot' => $singleplot, 'cols' => $cols, 'instanceid' => $instanceid,
        'container' => $container, 'filters' => $filters, 'basicparams' => $basicparams,
        'columnDefs' => $columndefs, 'reportdashboard' => $reportdashboard, ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);

        $filters = json_decode($filters, true);
        $basicparams = json_decode($basicparams, true);
        if (empty($basicparams)) {
            $basicparams = [];
        }

        if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
            throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
        }

        $properties = new stdClass();
        $properties->lsstartdate = !empty($filters['lsfstartdate']) ? $filters['lsfstartdate'] : 0;
        $properties->lsenddate   = !empty($filters['lsfenddate']) ? $filters['lsfenddate'] : time();
        $reportclass = $ls->create_reportclass($reportid, $properties);
        $reportclass->params = array_merge( $filters, (array)$basicparams);
        $reportclass->cmid = $cmid;
        $reportclass->courseid = isset($courseid) ? $courseid :
        (isset($reportclass->params['filter_courses']) ? $reportclass->params['filter_courses'] : SITEID);
        $reportclass->status = $status;
        $reporttype = !empty($reporttype) ? $reporttype : 'table';
        if ($reporttype != 'table') {
            $reportclass->start = 0;
            $reportclass->length = -1;
            $reportclass->reporttype = $reporttype;
        }
        if ($reportdashboard && $report->type == 'statistics') {
            $reportdatatable = false;
        } else {
            $reportdatatable = true;
        }

        $reportclass->create_report();

        if ($reportdatatable && $reporttype == 'table') {
            $datacolumns = [];
            $columndefs = [];
            $i = 0;
            $re = [];
            if (!empty($reportclass->orderable)) {
                $re = array_diff(array_keys($reportclass->finalreport->table->head), $reportclass->orderable);
            }
            if (empty($reportclass->finalreport->table->data)) {
                $return['tdata'] = html_writer::div(get_string("nodataavailable", "block_learnerscript"),
                                    'alert alert-info', []);
                $return['reporttype'] = 'table';
                $return['emptydata'] = 1;
                $reporttitle = get_string('report_' . $report->type, 'block_learnerscript');
                $return['reportname'] = (new ls)->get_reporttitle($reporttitle, $basicparams);
            } else {
                foreach ($reportclass->finalreport->table->head as $key => $value) {
                    $datacolumns[]['data'] = $value;
                    $columndef = new stdClass();
                    $align = isset($reportclass->finalreport->table->align[$i]) ?
                    $reportclass->finalreport->table->align[$i] : 'left';
                    $wrap = isset($reportclass->finalreport->table->wrap[$i])
                    && ($reportclass->finalreport->table->wrap[$i] == 'wrap') ? 'break-all' : 'normal';
                    $width = isset($reportclass->finalreport->table->size[$i])
                    ? $reportclass->finalreport->table->size[$i] : '';
                    $columndef->className = 'dt-body-' . $align;
                    $columndef->targets = $i;
                    $columndef->wrap = $wrap;
                    $columndef->width = $width;
                    if (!empty($re[$i]) && $re[$i]) {
                        $columndef->orderable = false;
                    } else {
                        $columndef->orderable = true;
                    }
                    $columndefs[] = $columndef;
                    $i++;
                }
                $export = explode(',', $reportclass->config->export);
                if (!empty($reportclass->finalreport->table->head)) {
                    $tablehead = (new ls)->report_tabledata($reportclass->finalreport->table);
                    $reporttable = new \block_learnerscript\output\reporttable($reportclass,
                        $tablehead,
                        $reportclass->finalreport->table->id,
                        $export,
                        $reportid,
                        $reportclass->sql,
                        $report->type,
                        false,
                        false,
                        $instanceid
                    );
                    $return = [];
                    foreach ($reportclass->finalreport->table->data as $key => $value) {
                        $data[$key] = array_values($value);
                    }
                    $return['tdata'] = (new ls)->get_viewreportdata($reporttable);
                    $return['data'] = [
                                            "draw" => true,
                                            "recordsTotal" => $reportclass->totalrecords,
                                            "recordsFiltered" => $reportclass->totalrecords,
                                            "data" => $data,
                        ];
                    $reporttitle = get_string('report_' . $report->type, 'block_learnerscript');
                    $return['reportname'] = (new ls)->get_reporttitle($reporttitle, $basicparams);
                    $return['columnDefs'] = $columndefs;
                    $return['reporttype'] = 'table';
                    $return['emptydata'] = 0;
                } else {
                    $return['emptydata'] = 1;
                    $return['reporttype'] = 'table';
                    $return['tdata'] = html_writer::div(get_string("nodataavailable", "block_learnerscript"),
                    'alert alert-info', []);
                }
            }
        } else {
            if ($report->type != 'statistics') {
                $seriesvalues = (isset($reportclass->componentdata->plot->elements)) ?
                $reportclass->componentdata->plot->elements : [];
                $i = 0;
                $reporttitle = get_string('report_' . $report->type, 'block_learnerscript');
                $return['reportname'] = (new ls)->get_reporttitle($reporttitle, $basicparams);
                foreach ($seriesvalues as $g) {
                    if (($reporttype != '' && $g->id == $reporttype) || $i == 0) {
                        $return['plot'] = (new ls)->generate_report_plot($reportclass, $g);
                        if ($reporttype != '' && $g->id == $reporttype) {
                            break;
                        }
                    }
                    $return['plotoptions'][] = ['id' => $g->id,
                    'title' => $g->formdata->chartname, 'pluginname' => $g->pluginname, ];
                    $i++;
                }
            } else {
                if ($reporttype == 'pie') {
                    foreach ($reportclass->finalreport->table->data[0] as $k => $r) {
                        $r = strip_tags($r);
                        if (is_numeric($r)) {
                            $piedata[] = ['name' => $reportclass->finalreport->table->head[$k], 'y' => $r];
                        }
                    }
                } else if ($reporttype == 'solidgauge') {
                    $radius = 112;
                    $innerradius = 88;
                    $colors = ['#90ed7d', 'rgb(67, 67, 72)', 'rgb(124, 181, 236)'];
                    foreach ($reportclass->finalreport->table->data[0] as $k => $r) {
                        $r = strip_tags($r);
                        $radius = $radius - 25;
                        $innerradius = $innerradius - 25;
                        if (is_numeric($r)) {
                            $piedata[] = ['name' => $reportclass->finalreport->table->head[$k],
                            'data' => [[ 'color' => $colors[$k], 'radius' => $radius.'%',
                            'innerradius' => $innerradius.'%' , 'y' => $r, ], ], ];
                        }
                    }
                } else {
                    $i = 0;
                    $categorydata = [];
                    if (!empty($reportclass->finalreport->table->data[0])) {
                        foreach ($reportclass->finalreport->table->data[0] as $k => $r) {
                                $r = strip_tags($r);
                                $r = is_numeric($r) ? $r : $r;
                                $seriesdata[] = $reportclass->finalreport->table->head[$k];
                                $graphdata[$i][] = $r;
                                $categorydata[] = $reportclass->finalreport->table->head[$k];
                                $i++;
                        }
                    }
                    $comdata = [];
                    $comdata['dataLabels'] = ['enabled' => 1];
                    $comdata['borderRadius'] = 5;
                    if (!empty($graphdata)) {
                        $i = 0;
                        foreach ($graphdata as $key => $value) {
                            if ($reporttype == 'table') {
                                $comdata['data'][] = [$value[0]];
                            } else {
                                $comdata['data'][] = ['y' => $value[0], 'label' => $value[0]];
                            }
                            $i++;
                        }
                        $piedata = [$comdata];
                    } else {
                        $piedata = $comdata;
                    }
                }
                $return['plot'] = ['type' => $reporttype,
                                    'containerid' => 'reportcontainer' . $instanceid . '',
                                    'name' => $report->name,
                                    'categorydata' => $categorydata,
                                    'tooltip' => '{point.y}',
                                    'datalabels' => 1,
                                    'showlegend' => 0,
                                    'id' => '{point.y}',
                                    'height' => '210',
                                    'data' => $piedata, ];
                $return['plotoptions'][] = ['id' => random_string(5), 'title' => $report->name, 'pluginname' => $reporttype];
            }
        }
        if ($reporttype == 'table') {
            $data = json_encode($return, JSON_PRESERVE_ZERO_FRACTION);
        } else {
            $data = json_encode($return, JSON_NUMERIC_CHECK);
        }
        return $data;
    }
    /**
     * Generate Plotgraph description
     * @return external_description
     */
    public static function generate_plotgraph_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    /**
     * Plugin license parameters description
     * @return external_function_parameters
     */
    public static function pluginlicence_parameters() {
        return new external_function_parameters(
            [
                'licencekey' => new external_value(PARAM_RAW, 'licencekey', VALUE_DEFAULT),
                'expireddate' => new external_value(PARAM_RAW, 'expiry date', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * Plugin licence description
     * @param string $licencekey License Key
     * @param mixed $expireddate Expiry Date
     */
    public static function pluginlicence($licencekey, $expireddate) {

        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::pluginlicence_parameters(), ['licencekey' => $licencekey,
            'expireddate' => $expireddate, ]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);
        if (!empty($expireddate) && !empty($licencekey)) {
            $explodedatetime = explode(' ', $expireddate);
            $explodedate = explode('-', $explodedatetime[0]);
            $explodetime = explode(':', $explodedatetime[1]);
            $expireddate = mktime($explodetime[0], $explodetime[1], $explodetime[2],
            $explodedate[1], $explodedate[2], $explodedate[0]);
            $return = (new schedule)->insert_licence($licencekey, $expireddate);
        } else {
            $termsdata = [];
            $termsdata['error'] = true;
            $termsdata['cap'] = false;
            $termsdata['type'] = 'Warning';
            $termsdata['msg'] = get_string('licencemissing', 'block_learnerscript');
            $return = $termsdata;
        }

        $data = json_encode($return);

        return $data;
    }
    /**
     * Plugin licence description
     * @return external_description
     */
    public static function pluginlicence_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
     /**
      * Frequency Schedule parameters description
      * @return external_function_parameters
      */
    public static function frequency_schedule_parameters() {
        return new external_function_parameters(
            [
                'frequency' => new external_value(PARAM_INT, 'schedule frequency', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * Frequency Schedule
     * @param string $frequency Report schedule frequency
     * @return string
     */
    public static function frequency_schedule($frequency) {
        $return = (new schedule)->getschedule($frequency);
        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::frequency_schedule_parameters(), ['frequency' => $frequency]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:managereports', $context);
        if (empty($return)) {
            $return = [null => get_string('selectall', 'block_reportdashboard')];
        }
        $data = json_encode($return);
        return $data;
    }
    /**
     * Frequency Schedule description
     * @return external_description
     */
    public static function frequency_schedule_returns() {
        return new external_value(PARAM_TEXT, 'data');
    }
    /**
     * Report Object paramerters description
     * @return external_function_parameters
     */
    public static function reportobject_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_INT, 'The context id for the course', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * Report Object
     * @param int $reportid Report ID
     * @return string
     */
    public static function reportobject($reportid) {
        global $DB, $CFG;

        self::validate_parameters(self::reportobject_parameters(), ['reportid' => $reportid]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);

        if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
            throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
        }
        require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
        $reportclassname = 'block_learnerscript\lsreports\report_' . $report->type;
        $reportclass = new $reportclassname($report);
        $reportclass->create_report();
        $return = (new ls)->cr_unserialize($reportclass->config->components);
        $data = json_encode($return);
        return $data;
    }
    /**
     * Report Object description
     * @return external_description
     */
    public static function reportobject_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    /**
     * Advanced columns description
     * @return external_function_parameters
     */
    public static function advancedcolumns_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_RAW, 'report id of the report', VALUE_DEFAULT),
                'component' => new external_value(PARAM_TEXT, 'available components', VALUE_DEFAULT),
                'advancedcolumn' => new external_value(PARAM_INT, 'advanced columns', VALUE_DEFAULT),
                'jsonformdata' => new external_value(PARAM_INT, 'json form data', VALUE_DEFAULT),
            ]
        );

    }
    /**
     * Advanced columns description
     * @param int $reportid Report ID
     * @param string $component Action
     * @param string $advancedcolumn Graph component
     * @param string $jsonformdata Plot name
     * @return string
     */
    public static function advancedcolumns($reportid, $component, $advancedcolumn, $jsonformdata) {

        self::validate_parameters(self::advancedcolumns_parameters(), ['reportid' => $reportid,
            'component' => $component, 'advancedcolumn' => $advancedcolumn, 'jsonformdata' => $jsonformdata, ]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);
        $advancedcolumn = "coursefield";
        $component = "columns";
        $args = new stdClass();
        $args->reportid = $reportid;
        $args->component = $component;
        $args->pname = $advancedcolumn;
        $args->jsonformdata = 'jsondata';

        $return = block_learnerscript_plotforms_ajaxform($args);

        $data = json_encode($return);

        return $data;
    }
    /**
     * Advanced columns description
     * @return external_description
     */
    public static function advancedcolumns_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    /**
     * Report calculations description
     * @return external_function_parameters
     */
    public static function reportcalculations_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_RAW, 'report id of report', VALUE_DEFAULT),
            ]
        );

    }
    /**
     * Advanced columns description
     * @param int $reportid Report ID
     * @param int $context Context ID
     * @return string
     */
    public static function reportcalculations($reportid, $context) {
        global $DB, $USER;
        $reportid = 1;
        self::validate_parameters(self::reportcalculations_parameters(), ['reportid' => $reportid,
            'context' => $context, ]);
        $context = contextsystem::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:managereports', $context);
        $checkpermissions = (new reportbase($reportid))->check_permissions($context, $USER->id);
        if ((has_capability('block/learnerscript:managereports', $context) ||
         has_capability('block/learnerscript:manageownreports', $context) || !empty($checkpermissions)) && !empty($reportid)) {
            $properties = new stdClass();
            $properties->start = 0;
            $properties->length = -1;
            $reportclass = (new ls)->create_reportclass($reportid, $properties);
            $reportclass->create_report();
            $table = html_writer::table($reportclass->finalreport->calcs);
            $reportname = $DB->get_field('block_learnerscript', 'name', ['id' => $reportid]);
            $return = ['table' => $table, 'reportname' => $reportname];
        } else {
            $termsdata = [];
            $termsdata['error'] = true;
            $termsdata['type'] = 'Warning';
            if (empty($reportid)) {
                $termsdata['cap'] = false;
                $termsdata['msg'] = get_string('missingparam', 'block_learnerscript', 'ReportID');
            } else {
                $termsdata['cap'] = true;
                $termsdata['msg'] = get_string('badpermissions', 'block_learnerscript');
            }
            $return = $termsdata;
        }
        $data = json_encode($return);
        return $data;
    }
    /**
     * Report calculations description
     * @return external_description
     */
    public static function reportcalculations_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    /**
     * Update report conditions description
     * @return external_function_parameters
     */
    public static function updatereport_conditions_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_RAW, 'report id of report', VALUE_DEFAULT),
                'conditionsdata' => new external_value(PARAM_RAW, 'conditions used in report', VALUE_DEFAULT),
            ]
        );

    }
    /**
     * Advanced columns description
     * @param int $reportid Report ID
     * @param array $conditionsdata Report conditions
     * @return string
     */
    public static function updatereport_conditions($reportid, $conditionsdata) {
        global $DB;
        if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
            throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
        }
        $context = contextsystem::instance();
        self::validate_parameters(self::updatereport_conditions_parameters(),
        ['reportid' => $reportid, $conditionsdata => $conditionsdata]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);
        $conditionsdata = json_decode($conditionsdata);
        $conditions = [];
        $conditions['elements'] = [];
        $sqlcon = [];
        $i = 1;
        foreach ($conditionsdata->selectedfields as $elementstr) {

            $element = explode(':', $elementstr);

            $columns = [];
            $columns['id'] = random_string();
            $columns['formdata'] = (object) ['field' => $element[1],
                'operator' => $conditionsdata->selectedcondition->{$elementstr},
                'value' => $conditionsdata->selectedvalue->{$elementstr},
                'submitbutton' => get_string('add'), ];
            $columns['pluginname'] = $element[0];
            $columns['pluginfullname'] = get_string($element[0], 'block_learnerscript');
            $columns['summary'] = get_string($element[0], 'block_learnerscript');
            $conditions['elements'][] = $columns;
            $sqlcon[] = 'c' . $i;
            $i++;
        }

        $conditions['config'] = (object) ['conditionexpr' => ($conditionsdata->sqlcondition) ?
        strtolower($conditionsdata->sqlcondition) : implode(' and ', $sqlcon),
            'submitbutton' => get_string('update'), ];

        $unserialize = (new ls)->cr_unserialize($report->components);
        $unserialize['conditions'] = $conditions;

        $unserialize = (new ls)->cr_serialize($unserialize);
        $DB->update_record('block_learnerscript', (object) ['id' => $reportid, 'components' => $unserialize]);
        $data = null;
        return $data;
    }
    /**
     * Update report conditions description
     * @return external_description
     */
    public static function updatereport_conditions_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    /**
     * Plotforms description
     * @return external_function_parameters
     */
    public static function plotforms_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_RAW, 'report id of report', VALUE_DEFAULT),
                'context' => new external_value(PARAM_RAW, 'The context id for the course', VALUE_DEFAULT),
                'component' => new external_value(PARAM_RAW, 'The context id for the course', VALUE_DEFAULT),
                'pname' => new external_value(PARAM_RAW, 'The context id for the course', VALUE_DEFAULT),
                'jsonformdata' => new external_value(PARAM_RAW, 'json form data', VALUE_DEFAULT),
                'cid' => new external_value(PARAM_RAW, 'The id for the course', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * Plotforms description
     * @param int $reportid Report ID
     * @param int $context Action
     * @param string $component Graph component
     * @param string $pname Plot name
     * @param string $jsonformdata Plot name
     * @param int $cid Plot name
     * @return string
     */
    public static function plotforms($reportid, $context, $component, $pname, $jsonformdata, $cid) {
        self::validate_parameters(self::plotforms_parameters(), ['reportid' => $reportid,
            'context' => $context, 'component' => $component, 'pname' => $pname,
            'jsonformdata' => $jsonformdata, 'cid' => $cid, ]);
        $context = contextsystem::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);

        $args = new stdClass();
        $args->context = $context;
        $args->reportid = $reportid;
        $args->component = $component;
        $args->pname = $pname;
        $args->cid = $cid;
        $args->jsonformdata = $jsonformdata;
        $return = block_learnerscript_plotforms_ajaxform($args);
        $data = json_encode($return);
        return $data;
    }
    /**
     * Plotforms description
     * @return external_description
     */
    public static function plotforms_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    /**
     * Design data description
     * @return external_function_parameters
     */
    public static function designdata_parameters() {
        return new external_function_parameters(
            [
                'frequency' => new external_value(PARAM_INT, 'The context id for the course', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * Design data description
     * @param int $reportid Report ID
     * @return string
     */
    public static function designdata($reportid) {
        global $DB, $CFG;
        $return = [];
        if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
            throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
        }
        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::designdata_parameters(), ['reportid' => $reportid]);

        $context = contextsystem::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);
        require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
        $reportclassname = 'block_learnerscript\lsreports\report_' . $report->type;
        $reportclass = new $reportclassname($report);
        $reportclass->create_report(null, 0, 10);
        $components = json_decode($reportclass->config->components);
        $starttime = microtime(true);
        if ($report->type == 'sql') {
            $rows = $reportclass->get_rows(0, 10);
            $return['rows'] = $rows['rows'];
            $reportclass->columns = get_object_vars($return['rows'][0]);
            $reportclass->columns = array_keys($reportclass->columns);
        } else {
            if (!isset($reportclass->columns)) {
                $availablecolumns = (new ls)->report_componentslist($report, 'columns');
            } else {
                $availablecolumns = $reportclass->columns;
            }
            $reporttable = $reportclass->get_all_elements(0, 10);
            $return['rows'] = $reportclass->get_rows($reporttable[0]);
        }

        $return['reportdata'] = json_encode($r, JSON_FORCE_OBJECT);
        $return['time'] = get_string('reportdata_time', 'block_learnerscript') .
        number_format((microtime(true) - $starttime), 4) . get_string('seconds', 'block_learnerscript') . " \n";
        /*
         *Calculations data
         */
        $comp = 'calcs';
        $plugins = get_list_of_plugins('blocks/learnerscript/components/' . $comp);
        $optionsplugins = [];
        foreach ($plugins as $p) {
            require_once($CFG->dirroot . '/blocks/learnerscript/components/' . $comp . '/' . $p . '/plugin.class.php');
            $pluginclassname = 'block_learnerscript\lsreports\plugin_' . $p;
            $pluginclass = new $pluginclassname($report);
            if (in_array($report->type, $pluginclass->reporttypes)) {
                if ($pluginclass->unique && in_array($p, $currentplugins)) {
                    continue;
                }
                $optionsplugins[get_string($p, 'block_learnerscript')] = $p;
            }
        }
        asort($optionsplugins);
        $return['calculations'] = $optionsplugins;
        $return['time'] .= get_string('calcluations_time', 'block_learnerscript') .
        number_format((microtime(true) - $starttime), 4) . get_string('seconds', 'block_learnerscript') ."\n";
        $activecolumns = [];

        if (isset($components->columns->elements)) {
            foreach ($components->columns->elements as $key => $value) {
                $value = (array) $value;
                $components->columns->elements[$key] = (array) $components->columns->elements[$key];

                $components->columns->elements[$key]['formdata']->columname = urldecode($value['formdata']->columname);
                $activecolumns[] = $value['formdata']->column;
            }
            $return['selectedcolumns'] = $components->columns->elements;
        } else {
            $return['selectedcolumns'] = [];
        }

        $return['time'] .= get_string('selected_columns_time', 'block_learnerscript') .
        number_format((microtime(true) - $starttime), 4) . get_string('seconds', 'block_learnerscript') . "\n";
        $conditionsdata = [];
        if (isset($components->conditions->elements)) {
            foreach ($components->conditions->elements as $key => $value) {
                $conditionsdata[] = $value['formdata'];
            }
        }

        $plugins = get_list_of_plugins('blocks/learnerscript/components/conditions');
        $conditionscolumns = [];
        $conditionscolumns['elements'] = [];
        $conditionscolumns['config'] = [];
        foreach ($plugins as $p) {
            require_once($CFG->dirroot . '/blocks/learnerscript/components/conditions/' . $p . '/plugin.class.php');
            $pluginclassname = 'block_learnerscript\lsreports\plugin_' . $p;
            $columns = [];
            $pluginclass = new $pluginclassname($report);
            if (in_array($report->type, $pluginclass->reporttypes)) {
                if ($pluginclass->unique && in_array($p, $currentplugins)) {
                    continue;
                }
                $uniqueid = random_string(15);
                while (strpos($reportclass->config->components, $uniqueid) !== false) {
                    $uniqueid = random_string(15);
                }
                $columns['id'] = $uniqueid;
                $columns['formdata'] = $conditionsdata;
                $columns['value'] = (in_array($p, $conditionsdata)) ? true : false;
                $columns['pluginname'] = $p;
                if (method_exists($pluginclass, 'columns')) {
                    $columns['plugincolumns'] = $pluginclass->columns();
                } else {
                    $columns['plugincolumns'] = [];
                }

                $columns['pluginfullname'] = get_string($p, 'block_learnerscript');
                $columns['summery'] = get_string($p, 'block_learnerscript');
                $conditionscolumns['elements'][$p] = $columns;
            }
        }
        $conditionscolumns['conditionssymbols'] = ["=", ">", "<", ">=", "<=", "<>", "LIKE", "NOT LIKE", "LIKE % %"];
        if (!empty($components['conditions']['elements'])) {
            $finalelements = [];
            $finalelements['elements'] = [];
            $finalelements['selectedfields'] = [];
            $finalelements['selectedcondition'] = [];
            $finalelements['selectedvalue'] = [];
            $finalelements['sqlcondition'] = urldecode($components['conditions']['config']->conditionexpr);
            foreach ($components['conditions']['elements'] as $element) {
                $finalelements['elements'][] = $element['pluginname'];
                $finalelements['selectedfields'][] = $element['pluginname'] . ':' . $element['formdata']->field;
                $finalelements['selectedcondition'][$element['pluginname'] . ':' . $element['formdata']->field] =
                urldecode($element['formdata']->operator);
                $finalelements['selectedvalue'][$element['pluginname'] . ':' . $element['formdata']->field] =
                urldecode($element['formdata']->value);
            }
            $conditionscolumns['finalelements'] = $finalelements;
        }
        $return['conditioncolumns'] = $conditionscolumns;
        $return['time'] .= "Conditions Time:  " . number_format((microtime(true) - $starttime), 4) . " Seconds\n";

        // Filters.
        $filterdata = [];
        if (isset($components['filters']['elements'])) {
            foreach ($components['filters']['elements'] as $key => $value) {
                $value = (array) $value;
                if ($value['formdata']->value) {
                    $filterdata[] = $value['pluginname'];
                }
            }
        }
        $filterplugins = get_list_of_plugins('blocks/learnerscript/components/filters');
        $filterplugins = $reportclass->filters;
        foreach ($filterplugins as $p) {
            require_once($CFG->dirroot . '/blocks/learnerscript/components/filters/' . $p . '/plugin.class.php');
            if (file_exists($CFG->dirroot . '/blocks/learnerscript/components/filters/' . $p . '/form.php')) {
                continue;
            }
            $pluginclassname = 'block_learnerscript\lsreports\plugin_' . $p;
            $pluginclass = new $pluginclassname($report);
            if (in_array($report->type, $pluginclass->reporttypes)) {
                $uniqueid = random_string(15);
                while (strpos($reportclass->config->components, $uniqueid) !== false) {
                    $uniqueid = random_string(15);
                }
                $filtercolumns = [];
                $filtercolumns['id'] = $uniqueid;
                $filtercolumns['pluginname'] = $p;
                $filtercolumns['pluginfullname'] = get_string($p, 'block_learnerscript');
                $filtercolumns['summary'] = '';
                $columnss['name'] = get_string($p, 'block_learnerscript');
                $columnss['type'] = 'filters';
                $columnss['value'] = (in_array($p, $filterdata)) ? true : false;
                $filtercolumns['formdata'] = $columnss;
                $filterelements[] = $filtercolumns;
            }
        }
        $return['filtercolumns'] = $filterelements;
        $return['time'] .= "Filters Time:  " . number_format((microtime(true) - $starttime), 4) . " Seconds\n";
        // Ordering.
        $comp = 'ordering';
        $plugins = get_list_of_plugins('blocks/learnerscript/components/' . $comp);
        $orderingplugin = [];
        foreach ($plugins as $p) {
            require_once($CFG->dirroot . '/blocks/learnerscript/components/' . $comp . '/' . $p . '/plugin.class.php');
            $pluginclassname = 'block_learnerscript\lsreports\plugin_' . $p;
            $pluginclass = new $pluginclassname($report);
            if (in_array($report->type, $pluginclass->reporttypes)) {
                $orderingplugin[$p] = get_string($p, 'block_learnerscript');
            }
        }
        asort($orderingplugin);
        $orderingdata = [];
        foreach ($orderingplugin as $key => $value) {
            $mstring = str_replace('fieldorder', '', $key);
            $tblcolumns = $DB->get_columns($mstring);
            $ordering = [];
            $ordering['column'] = $value;
            $ordering['type'] = 'Ordering';
            $ordering['ordercolumn'] = $key;
            $ordering['orderingcolumn'] = array_keys($tblcolumns);
            $orderingdata[] = $ordering;
        }
        $return['ordercolumns'] = (isset($components['ordering']['columns']) &&
            !empty($components['ordering']['columns'])) ? $components['ordering']['columns'] :
        $orderingdata;
        $return['time'] .= "Order columns Time:  " . number_format((microtime(true) - $starttime), 4) . " Seconds\n";
        // Columns.
        if ($report->type == 'sql') {
            $columns = [];
            foreach ($reportclass->columns as $value) {
                $c = [];
                $uniqueid = random_string(15);
                while (strpos($reportclass->config->components, $uniqueid) !== false) {
                    $uniqueid = random_string(15);
                }
                $c['id'] = $uniqueid;
                $c['pluginname'] = 'sql';
                $c['pluginfullname'] = get_string('sql', 'block_learnerscript');
                $c['summary'] = '';
                $c['type'] = 'columns';
                if (in_array($value, $activecolumns)) {
                    $columns['value'] = true;
                } else {
                    $columns['value'] = false;
                }
                $columns['columname'] = $value;
                $columns['column'] = $value;
                $columns['heading'] = '';
                $columns['wrap'] = '';
                $columns['align'] = '';
                $columns['size'] = '';
                $c['formdata'] = $columns;
                $elements[] = $c;
            }
        } else {
            $comp = 'columns';
            $cid = '';
            require_once($CFG->dirroot . '/blocks/learnerscript/components/columns/component.class.php');
            $compclass = new component_columns($report->id);
            $i = 0;
            foreach ($availablecolumns as $key => $values) {
                if (!isset($reportclass->columns)) {
                    $c = [];
                    $c['formdata']->column = $key;
                    $elements[] = $c;
                } else {
                    $columns = [];
                    foreach ($values as $value) {
                        $c = [];
                        $uniqueid = random_string(15);
                        while (strpos($reportclass->config->components, $uniqueid) !== false) {
                            $uniqueid = random_string(15);
                        }
                        $c['id'] = $uniqueid;
                        $c['pluginname'] = $key;
                        $c['pluginfullname'] = get_string($key, 'block_learnerscript');
                        $c['summary'] = '';
                        $c['type'] = 'columns';
                        if (in_array($value, $activecolumns)) {
                            $columns['value'] = true;
                        } else {
                            $columns['value'] = false;
                        }
                        $columns['columname'] = $value;
                        $columns['column'] = $value;
                        $columns['heading'] = $key;
                        $c['formdata'] = $columns;
                        $elements[] = $c;
                    }
                }
                $i++;
            }
        }
        $return['availablecolumns'] = $elements;
        $return['time'] .= "Available col Time:  " . number_format((microtime(true) - $starttime), 4) . " Seconds\n";
        if (!empty($components['calculations']['elements'])) {
            foreach ($components['calculations']['elements'] as $k => $ocalc) {
                $ocalc = (array) $ocalc;
                $calcpluginname[$ocalc['id']] = $ocalc['pluginname'];
            }
        } else {
            $components['calculations']['elements'] = [];
            $calcpluginname = [];
        }
        $return['calcpluginname'] = $calcpluginname;
        $return['calccolumns'] = $components['calculations']['elements'];
        // Exports.
        $exporttypes = ['pdf', 'csv', 'xls', 'ods'];
        $exportlists = [];
        foreach ($exporttypes as $key => $exporttype) {
            $list = [];
            $list['name'] = $exporttype;
            if (in_array($exporttype, explode(',', $report->export))) {
                $list['value'] = true;
            } else {
                $list['value'] = false;
            }
            $exportlists[] = $list;
        }
        $return['exportlist'] = $exportlists;
        $return['time'] .= "Export Time:  " . number_format((microtime(true) - $starttime), 4) . " Seconds\n";

        $data = json_encode($return);
        return $data;
    }
    /**
     * Design data description
     * @return external_description
     */
    public static function designdata_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    /**
     * Delete component parameters function
     * @return external_function_parameters
     */
    public static function deletecomponenet_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_INT, 'ReportID', VALUE_DEFAULT),
                'action' => new external_value(PARAM_TEXT, 'Action.', VALUE_DEFAULT),
                'comp' => new external_value(PARAM_TEXT, 'Report component', VALUE_DEFAULT),
                'pname' => new external_value(PARAM_TEXT, 'Plugin name', VALUE_DEFAULT),
                'cid' => new external_value(PARAM_TEXT, 'Component ID', VALUE_DEFAULT),
                'delete' => new external_value(PARAM_INT, 'Confirm Delete', VALUE_DEFAULT),
            ]
        );

    }
    /**
     * Delete report graph component
     * @param int $reportid Report ID
     * @param int $action Action
     * @param string $comp Graph component
     * @param string $pname Plot name
     * @param int $cid Graph element ID
     * @param int $delete Delete graph
     */
    public static function deletecomponenet($reportid, $action, $comp, $pname, $cid, $delete) {
        global $DB;
        if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
            throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
        }

        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::deletecomponenet_parameters(), ['reportid' => $reportid,
        'action' => $action, 'comp' => $comp, 'pname' => $pname, 'cid' => $cid, 'delete' => $delete, ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:managereports', $context);

        $components = (new ls)->cr_unserialize($report->components);
        $elements = isset($components->$comp->elements) ? $components->$comp->elements : [];
        $elements = (array) $elements;
        if (count($elements) == 1 && $report->disabletable == 1) {
            $success['success'] = true;
            $success['disabledelete'] = true;
        } else {
            foreach ($elements as $index => $e) {
                if ($e->id == $cid) {
                    if ($delete) {
                        unset($elements[$index]);
                        break;
                    }
                    $moveup = '';
                    $newindex = ($moveup) ? $index - 1 : $index + 1;
                    $tmp = $elements[$newindex];
                    $elements[$newindex] = $e;
                    $elements[$index] = $tmp;
                    break;
                }
            }
            $components->$comp->elements = $elements;
            $report->components = (new ls)->cr_serialize($components);
            try {
                $DB->update_record('block_learnerscript', $report);
                $success['success'] = true;
                $success['disabledelete'] = false;
            } catch (exception $e) {
                $success['success'] = false;
                $success['disabledelete'] = false;
            }
        }
        return $success;
    }
    /**
     * Delete component
     * @return external_description
     */
    public static function deletecomponenet_returns() {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'success message'),
                'disabledelete' => new external_value(PARAM_BOOL, 'message'),
            ]
        );
    }
    /**
     * Report Filter Form ajax
     */
    public static function reportfilterform_is_allowed_from_ajax() {
        return true;
    }
    /**
     * Report Filter Form parameters description
     * @return external_function_parameters
     */
    public static function reportfilterform_parameters() {
        return new external_function_parameters(
            [
                'action' => new external_value(PARAM_TEXT, 'The context id for the course', VALUE_DEFAULT),
                'reportid' => new external_value(PARAM_INT, 'ReportID', VALUE_DEFAULT),
                'instance' => new external_value(PARAM_INT, 'instanceID', VALUE_DEFAULT),
            ]
        );

    }
    /**
     * Report Filter Form
     * @param string $action Action
     * @param int $reportid Report ID
     * @param int $instance Report instance
     */
    public static function reportfilterform($action, $reportid, $instance) {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);
        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::reportfilterform_parameters(), ['action' => $action,
        'reportid' => $reportid, 'instance' => $instance, ]);

        $reportrecord = new reportbase($reportid);
        $reportrecord->customheader = true; // For not to display Form Header.
        $reportrecord->instanceid = $instance;
        $filterform = new block_learnerscript\form\filter_form(null, $reportrecord);
        $reportfilterform = $filterform->render();
        return $reportfilterform;
    }
    /**
     * Report Filter Form
     * @return external_description
     */
    public static function reportfilterform_returns() {
        return new external_value(PARAM_RAW, 'reportfilterform');
    }
    /**
     * Import Report parameters description
     * @return external_function_parameters
     */
    public static function importreports_parameters() {
        return new external_function_parameters(
            [
                'total' => new external_value(PARAM_INT, 'Total reports', VALUE_DEFAULT, 0),
                'current' => new external_value(PARAM_INT, 'Current Report Position', VALUE_DEFAULT, 0),
                'errorreportspositiondata' => new external_value(PARAM_TEXT, 'error report positions', VALUE_DEFAULT, 0),
                'lastreportposition' => new external_value(PARAM_INT, 'Last Report Position', VALUE_DEFAULT, 0),
            ]
        );
    }
    /**
     * Import Reports description
     * @param int $total Total reports count
     * @param int $current Report position
     * @param string $errorreportspositiondata Error in report position data
     * @param int $lastreportposition Last report position
     */
    public static function importreports($total, $current, $errorreportspositiondata, $lastreportposition = 0) {
        global $CFG, $DB;
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:managereports', $context);

        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::importreports_parameters(),
        ['total' => $total, 'current' => $current, 'errorreportspositiondata' => $errorreportspositiondata,
        'lastreportposition' => $lastreportposition, ]);

        $path = $CFG->dirroot . '/blocks/learnerscript/reportsbackup/';
        $learnerscriptreports = glob($path . '*.xml');
        $course = get_course(SITEID);
        if ($lastreportposition > 0) {
            $errorreportsposition = json_decode($errorreportspositiondata);
            foreach ($learnerscriptreports as $k => $learnerscriptreport) {
                if ((!empty($errorreportsposition) && in_array($k, $errorreportsposition)) || $k >= $lastreportposition) {
                    $finalreports[$k] = $learnerscriptreport;
                }
            }

            $position = $current;
            $importurl = $finalreports[$position];
            $data = [];
            if (file_exists($finalreports[$position])
                && pathinfo($finalreports[$position], PATHINFO_EXTENSION) == 'xml') {
                $filedata = file_get_contents($importurl);
                $status = (new ls)->cr_import_xml($filedata, $course, false, true);
                if ($status) {
                    $data['import'] = true;
                } else {
                    $data['import'] = false;
                }
                $event = \block_learnerscript\event\import_report::create([
                    'objectid' => $position,
                    'context' => $context,
                    'other' => ['reportid' => $status,
                                     'status' => $data['import'],
                                     'position' => $position, ],
                    ], );
                $event->trigger();
                $currentposition = array_search($position, array_keys($finalreports));
                $nextposition = $currentposition + 1;
                $percent = $nextposition / $total * 100;
                $data['percent'] = round($percent, 0);
                $data['current'] = array_keys($finalreports)[$nextposition];
            }
        } else {
            $position = $current - 1;
            $finalreports = $learnerscriptreports;
            $importurl = $finalreports[$position];
            $data = [];
            if (file_exists($finalreports[$position])
                && pathinfo($finalreports[$position], PATHINFO_EXTENSION) == 'xml') {
                $filedata = file_get_contents($importurl);
                $status = (new ls)->cr_import_xml($filedata, $course, false, true);
                if ($status) {
                    $data['import'] = true;
                } else {
                    $data['import'] = false;
                }
                $event = \block_learnerscript\event\import_report::create([
                    'objectid' => $position,
                    'context' => $context,
                    'other' => ['reportid' => $status,
                                     'status' => $data['import'],
                                     'position' => $position, ],
                ]);
                $event->trigger();

                $percent = $current / $total * 100;
                $data['percent'] = round($percent, 0);
            }
        }
        $pluginsettings = new lssetting('block_learnerscript/lsreportconfigstatus',
                'lsreportconfigstatus', get_string('lsreportconfigstatus', 'block_learnerscript'), '', PARAM_BOOL, 2);
        $totallsreports = $DB->count_records('block_learnerscript');
        if (count($learnerscriptreports) <= $totallsreports) {
            $pluginsettings->config_write('lsreportconfigstatus', true);
        } else {
            $pluginsettings->config_write('lsreportconfigstatus', false);
        }
        $data = json_encode($data);
        return $data;
    }
    /**
     * Import Reports
     * @return external_description
     */
    public static function importreports_returns() {
        return new external_value(PARAM_TEXT, 'data');
    }

    /**
     * Learnerscript reports configuration import params
     * @return external_function_parameters
     */
    public static function lsreportconfigimport_parameters() {
        return new external_function_parameters(
            []
        );
    }
    /**
     * Learnerscript reports configuration import
     */
    public static function lsreportconfigimport() {

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:managereports', $context);

        $pluginsettings = new lssetting('block_learnerscript/lsreportconfigimport',
                    'lsreportconfigimport', get_string('lsreportconfigimport', 'block_learnerscript'), '', PARAM_INT, 2);
        $return = $pluginsettings->config_write('lsreportconfigimport', 0);
        $data = json_encode($return);
        return $data;
    }

    /**
     * Learnerscript reports configuration import params
     * @return external_description
     */
    public static function lsreportconfigimport_returns() {
        return new external_value(PARAM_TEXT, 'data');
    }
    /**
     * Reset ls configuration
     */
    public static function resetlsconfig_parameters() {
        return new external_function_parameters(
            [
                'step' => new external_value(PARAM_INT, 'Step', 0),
            ]
        );
    }

    /**
     * Undocumented function
     *
     * @param  string $step
     *
     * @return string
     */
    public static function resetlsconfig($step) {
        global $CFG, $DB;
        $context = contextsystem::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);
        $search = $term;

        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::resetlsconfig_parameters(), ['step' => $step]);
        switch ($step) {
            case 1:
                $DB->delete_records('block_learnerscript');
                $DB->delete_records('block_ls_schedule');
                $return = ['next' => 2, 'percent' => 25];
            break;
            case 2:
                $blockinstancessql = "SELECT id
                                        FROM {block_instances}
                                        WHERE (pagetypepattern LIKE :pagetypepattern
                                        OR blockname = :blockname)";
                $blockinstances = $DB->get_fieldset_sql($blockinstancessql,
                ['pagetypepattern' => '%blocks-reportdashboard%', 'blockname' => 'coursels']);

                if (!empty($blockinstances)) {
                    blocks_delete_instances($blockinstances);
                }
                $return = ['next' => 3, 'percent' => 50];
            break;
            case 3:
                $usertours = $CFG->dirroot . '/blocks/learnerscript/usertours/';
                $usertoursjson = glob($usertours . '*.json');

                foreach ($usertoursjson as $usertour) {
                    $data = file_get_contents($usertour);
                    $tourconfig = json_decode($data);
                    $tourid = $DB->get_field('tool_usertours_tours', 'id', ['name' => $tourconfig->name]);
                    if ($tourid > 0) {
                        $tour = tour::instance($tourid);
                        $tour->remove();
                    }
                }
                $return = ['next' => 4, 'percent' => 75];
            break;
            case 4:
                set_config('lsreportconfigstatus', 0, 'block_learnerscript');
                set_config('lsreportconfigimport', 0, 'block_learnerscript');
                $return = ['next' => 0, 'percent' => 100];
            break;
            default:
                $return = ['next' => 0, 'percent' => 0];
            break;
        }
        $data = json_encode($return);
        return $data;
    }
    /**
     * Reset config
     * @return external_description
     */
    public static function resetlsconfig_returns() {
        return new external_value(PARAM_TEXT, 'data');
    }
    /**
     * Filter Courses parameters description
     * @return external_function_parameters
     */
    public static function filter_courses_parameters() {
        return new external_function_parameters(
            [
                'action' => new external_value(PARAM_TEXT, 'action', VALUE_DEFAULT),
                'maximumselectionlength' => new external_value(PARAM_INT, 'maximum selection length to search', VALUE_DEFAULT),
                'term' => new external_value(PARAM_TEXT, 'Current search term in search box', VALUE_DEFAULT),
                '_type' => new external_value(PARAM_TEXT, 'A "request type" will be usually a query', VALUE_DEFAULT),
                'fiterdata' => new external_value(PARAM_TEXT, 'fiterdata', VALUE_DEFAULT),
                'basicparamdata' => new external_value(PARAM_TEXT, 'basicparamdata', VALUE_DEFAULT),
                'reportinstanceid' => new external_value(PARAM_INT, 'reportid', VALUE_DEFAULT),
                'courses' => new external_value(PARAM_TEXT, 'Course id of report', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * Filter Courses description
     * @param int $action Action
     * @param int $maximumselectionlength Maximum selection length of coursename
     * @param boolean $term Search text
     * @param boolean $type Text type
     * @param string $fiterdata Reports filter data
     * @param string $basicparamdata Mandatory filters data
     * @param int $reportinstanceid Report instance ID
     * @param int $courses Courses list
     */
    public static function filter_courses($action, $maximumselectionlength,
    $term, $type, $fiterdata, $basicparamdata, $reportinstanceid, $courses) {
        global $DB, $CFG;
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);
        $search = $term;

        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::filter_courses_parameters(), ['action' => $action,
        'maximumselectionlength' => $maximumselectionlength, 'term' => $term, '_type' => $type,
        'fiterdata' => $fiterdata, 'basicparamdata' => $basicparamdata,
        'reportinstanceid' => $reportinstanceid, 'courses' => $courses, ]);

        $filters = json_decode($fiterdata, true);
        $basicparams = json_decode($basicparamdata, true);
        $filterdata = array_merge($filters, $basicparams);
        $report = $DB->get_record('block_learnerscript', ['id' => $reportinstanceid]);
        $reportclass = new stdClass();
        if (!empty($report) && $report->type) {
            require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
            $reportclassname = 'block_learnerscript\lsreports\report_' . $report->type;
            $properties = new stdClass;
            $reportclass = new $reportclassname($report, $properties);
        }
        $pluginclass = new stdClass();
        $pluginclass->report = new stdClass();
        $pluginclass->report->type = 'custom';
        $pluginclass->reportclass = $reportclass;
        $courseoptions = (new \block_learnerscript\local\querylib)->filter_get_courses($pluginclass, $courses, true, $search,
        $filterdata, $type, false);
        $termsdata = [];
        $termsdata['total_count'] = count($courseoptions);
        $termsdata['incomplete_results'] = false;
        $termsdata['items'] = $courseoptions;
        $return = $termsdata;
        $data = json_encode($return);
        return $data;
    }
    /**
     * Filter Courses
     * @return external_description
     */
    public static function filter_courses_returns() {
        return new external_value(PARAM_TEXT, 'data');
    }
    /**
     * Filter users parameters description
     * @return external_function_parameters
     */
    public static function filterusers_parameters() {
        return new external_function_parameters(
            [
                'action' => new external_value(PARAM_TEXT, 'action', VALUE_DEFAULT),
                'maximumselectionlength' => new external_value(PARAM_INT, 'maximum selection length to search', VALUE_DEFAULT),
                'term' => new external_value(PARAM_TEXT, 'Current search term in search box', VALUE_DEFAULT),
                '_type' => new external_value(PARAM_TEXT, 'A "request type" will be usually a query', VALUE_DEFAULT),
                'fiterdata' => new external_value(PARAM_TEXT, 'fiterdata', VALUE_DEFAULT),
                'basicparamdata' => new external_value(PARAM_TEXT, 'basicparamdata', VALUE_DEFAULT),
                'reportinstanceid' => new external_value(PARAM_INT, 'reportinstanceid', VALUE_DEFAULT),
                'courses' => new external_value(PARAM_INT, 'Course id of report', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * Filter users description
     * @param int $action Action
     * @param int $maximumselectionlength Maximum selection length of coursename
     * @param boolean $term Search text
     * @param boolean $type Text type
     * @param string $fiterdata Reports filter data
     * @param string $basicparamdata Mandatory filters data
     * @param int $reportinstanceid Report instance ID
     * @param int $courses Courses list
     * @return string
     */
    public static function filterusers($action, $maximumselectionlength, $term, $type,
    $fiterdata, $basicparamdata, $reportinstanceid, $courses) {
        global $DB, $CFG;
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/learnerscript:reportsaccess', $context);
        $search = $term;

        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::filterusers_parameters(), ['action' => $action,
        'maximumselectionlength' => $maximumselectionlength, 'term' => $term, '_type' => $type,
        'fiterdata' => $fiterdata, 'basicparamdata' => $basicparamdata,
        'reportinstanceid' => $reportinstanceid, 'courses' => $courses, ]);

        $filters = json_decode($fiterdata, true);
        $basicparams = json_decode($basicparamdata, true);

        $filterdata = array_merge($filters, $basicparams);

        $report = $DB->get_record('block_learnerscript', ['id' => $reportinstanceid]);
        $reportclass = new stdClass();
        if (!empty($report) && $report->type) {
            require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
            $reportclassname = 'block_learnerscript\lsreports\report_' . $report->type;
            $properties = new stdClass;
            $reportclass = new $reportclassname($report, $properties);
        }
        $pluginclass = new stdClass();
        $pluginclass->report = new stdClass();
        $pluginclass->report->type = 'custom';
        $pluginclass->reportclass = $reportclass;
        $courseoptions = (new \block_learnerscript\local\querylib)->filter_get_users($pluginclass,
        true, $search, $filterdata, SITEID, $type, $courses);
        $termsdata = [];
        $termsdata['total_count'] = count($courseoptions);
        $termsdata['incomplete_results'] = false;
        $termsdata['items'] = $courseoptions;
        $return = $termsdata;
        $data = json_encode($return);
        return $data;
    }
    /**
     * Filter Users
     * @return external_description
     */
    public static function filterusers_returns() {
        return new external_value(PARAM_TEXT, 'data');
    }
}
