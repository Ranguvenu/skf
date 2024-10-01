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

require_once('../../../../config.php');
global $CFG, $DB, $USER, $OUTPUT;

require_once($CFG->dirroot . '/blocks/learnerscript/components/scheduler/schedule_form.php');
use block_learnerscript\local\ls;
use block_learnerscript\local\schedule;
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->css('/blocks/learnerscript/css/select2/select2.min.css', true);
$PAGE->requires->css('/blocks/learnerscript/css/datatables/jquery.dataTables.min.css', true);

$reportid = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', SITEID, PARAM_INT);
$scheduledreportid = optional_param('scheduleid', -1, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();

$learnerscript = get_config('block_learnerscript', 'ls_serialkey');
if (empty($learnerscript)) {
    throw new moodle_exception(get_string('licencekeyrequired', 'block_learnerscript'));
}

if ($courseid == SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($courseid);
    $context = context_course::instance($courseid);
}
$PAGE->set_context($context);
$PAGE->set_url('/blocks/learnerscript/components/scheduler/schedule.php', ['id' => $reportid]);
$PAGE->set_pagelayout('report');

$PAGE->set_title(get_string('schedulereport', 'block_learnerscript'));
$PAGE->requires->js_call_amd('block_learnerscript/schedule', 'ScheduledTimings',
[['reportid' => $reportid, 'courseid' => $courseid, 'action' => 'scheduledtimings']]);

$PAGE->requires->data_for_js("M.cfg.accessls", $learnerscript, true);

if ($scheduledreportid > 0) {
    if (!($scheduledreport = $DB->get_record('block_ls_schedule',
    ['id' => $scheduledreportid]))) {
        throw new moodle_exception('invalidscheduledreportid', 'block_learnerscript');
    }
}

if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
    throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
}

$PAGE->navbar->add(get_string('managereports', 'block_learnerscript'),
new moodle_url('/blocks/learnerscript/managereport.php'));
$PAGE->navbar->add($report->name, new moodle_url('/blocks/learnerscript/viewreport.php',
                    ['id' => $reportid, 'courseid' => $courseid]));
$PAGE->navbar->add(get_string('schedulereport', 'block_learnerscript'));

if (!has_capability('block/learnerscript:managereports', $context)
&& !has_capability('block/learnerscript:manageownreports', $context)) {
    throw new moodle_exception('permissiondenied', 'block_learnerscript');
}

$renderer = $PAGE->get_renderer('block_learnerscript');
if ($report->type) {
    require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
} else {
    throw new moodle_exception('reporttypeerror', 'block_learnerscript');
}

$reportclassname = 'block_learnerscript\lsreports\report_' . $report->type;
$properties = new stdClass();
$reportclass = new $reportclassname($report, $properties);

if (!$reportclass->check_permissions($context, $USER->id)) {
    throw new moodle_exception("badpermissions", 'block_learnerscript');
}
$returnurl = new moodle_url('/blocks/learnerscript/components/scheduler/schedule.php',
['id' => $reportid, 'courseid' => $courseid]);

if ($delete) {
    $PAGE->url->param('delete', 1);
    if ($confirm && confirm_sesskey()) {
        $DB->delete_records('block_ls_schedule', ['id' => $scheduledreportid]);
        $SESSION->ls_ele_delete = $confirm;
        redirect($returnurl);
    }
    $strheading = get_string('deletescheduledreport', 'block_learnerscript');
    $PAGE->navbar->add($strheading);
    $PAGE->set_title($strheading);
    echo $OUTPUT->header();
    $PAGE->requires->js(new moodle_url('https://learnerscript.com/wp-content/plugins/learnerscript/js/highcharts.js'));
    echo $OUTPUT->heading($strheading);
    $yesurl = new moodle_url('/blocks/learnerscript/components/scheduler/schedule.php',
    ['id' => $reportid, 'courseid' => $courseid, 'scheduleid' => $scheduledreportid,
    'confirm' => 1, 'sesskey' => sesskey(), 'delete' => 1, ]);
    $message = get_string('delconfirm', 'block_learnerscript');
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
}
$scheduling = new schedule();
// Form data.
$roleslist = $scheduling->reportroles('', $reportid);
list($schusers, $schusersids) = $scheduling->userslist($reportid, $scheduledreportid);
$exportoptions = (new ls)->cr_get_export_plugins();
$frequencyselect = $scheduling->get_options();
if (!empty($scheduledreport)) {
    $schedulelist = $scheduling->getschedule($scheduledreport->frequency);
} else {
    $schedulelist = [null => get_string('selectschedule', 'block_learnerscript')];
}

$schrecords = $DB->get_records("block_ls_schedule",  ['reportid' => $reportid]);
if (empty($schrecords)) {
    $collapse = false;
} else {
    $collapse = true;
}

$mform = new scheduled_reports_form($returnurl, ['id' => $reportid,
                                                'schusers' => $schusers,
                                                'scheduleid' => $scheduledreportid,
                                                'roleslist' => $roleslist,
                                                'schusersids' => $schusersids,
                                                'exportoptions' => $exportoptions,
                                                'schedulelist' => $schedulelist,
                                                'frequencyselect' => $frequencyselect,
                                                'reportfilters' => $reportclass->basicparams, ]);
if ($scheduledreportid > 0) {

    $collapse = false;

    $scheduledreport->users_data = explode(',', $scheduledreport->sendinguserid);
    if ($scheduledreport->roleid > 0) {
        $scheduledreport->role = $scheduledreport->roleid . '_' . $scheduledreport->contextlevel;
    } else {
        $scheduledreport->role = $scheduledreport->roleid;
    }
    if (count($scheduledreport->users_data) > 10) {
        $scheduledreport->users_data = $scheduledreport->users_data + [-1 => -1];
    }
    $scheduledreport->frequency = [$scheduledreport->frequency, $scheduledreport->schedule];
    $mform->set_data($scheduledreport);
}

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/blocks/learnerscript/viewreport.php',
    ['id' => $reportid, 'courseid' => $courseid]));
} else if ($fromform = $mform->get_data()) {
    $data = data_submitted();
    $role = explode('_', $data->role);
    $fromform->roleid = $role[0];
    $fromform->contextlevel = isset($role[1]) ? $role[1] : 10;
    $fromform->sendinguserid = $data->schuserslist;
    $fromform->exportformat = $data->exportformat;
    $fromform->frequency = $data->frequency;
    $fromform->schedule = $data->schedule;
    $fromform->userid = $USER->id;
    $fromform->nextschedule = $scheduling->next($fromform, null, false);
    if ($scheduledreportid > 0) {
        $fromform->timemodified = time();
        $fromform->id = $fromform->scheduleid;
        $schedule = $DB->update_record('block_ls_schedule', $fromform);
        $collapse = true;
    } else {
        $fromform->timecreated = time();
        $fromform->timemodified = 0;
        $schedule = $DB->insert_record('block_ls_schedule', $fromform);
        $event = \block_learnerscript\event\schedule_report::create([
                    'objectid' => $fromform->reportid,
                    'context' => $context,
                ]);
        $event->trigger();
    }

    if ($schedule) {
        if ($schedule == 1) {
            $SESSION->ls_ele_update = $schedule;
        } else {
            $SESSION->ls_ele_schedule = $schedule;
        }
        redirect($returnurl);
    }
}
$PAGE->set_heading($report->name);
echo $OUTPUT->header();
$PAGE->requires->js(new moodle_url('https://learnerscript.com/wp-content/plugins/learnerscript/js/highcharts.js'));
echo html_writer::start_tag('div', ['id' => 'licenseresult', 'class' => 'lsaccess']);

if (isset($SESSION->ls_ele_schedule) && $SESSION->ls_ele_schedule) {
    echo $OUTPUT->notification(get_string('reportschedule', 'block_learnerscript'), 'notifysuccess');
    unset($SESSION->ls_ele_schedule);
}
if (isset($SESSION->ls_ele_delete) && $SESSION->ls_ele_delete) {
    echo $OUTPUT->notification(get_string('deleteschedulereport', 'block_learnerscript'),
    'notifysuccess');
    unset($SESSION->ls_ele_delete);
}
if (isset($SESSION->ls_ele_update) && $SESSION->ls_ele_update) {
    echo $OUTPUT->notification(get_string('updateschedulereport', 'block_learnerscript'),
    'notifysuccess');
    unset($SESSION->ls_ele_update);
}

if (has_capability('block/learnerscript:managereports', $context) ||
    (has_capability('block/learnerscript:manageownreports', $context)) && $report->ownerid == $USER->id) {
    $plots = (new block_learnerscript\local\ls)->get_components_data($report->id, 'plot');
       $calcbutton = false;
    $plotoptions = new \block_learnerscript\output\plotoption(false, $report->id, $calcbutton,
    'schreportform');
    echo $renderer->render($plotoptions);
}
echo html_writer::tag(
    'div',
    html_writer::link(
        new moodle_url('/blocks/learnerscript/components/scheduler/sch_upload.php', ['id' => $reportid, 'courseid' => $courseid]),
        get_string('bulk_upload', 'block_learnerscript'), ['class' => 'btn btn-primary']
    ), ['class' => 'bulkupload mb-2']
);


if ($scheduledreportid > 0) {
    $schreport = get_string('editscheduledreport', 'block_learnerscript');
} else {
    $schreport = get_string('addschedulereport', 'block_learnerscript');
}

$heading = html_writer::tag('span', $schreport, ['class' => 'filter-lebel']);
echo html_writer::start_div('', ['id' => 'filters_form']);
echo html_writer::tag('a', $heading, ['data-toggle' => "collapse", 'href' => "#userfilterform_collapse",
    'role' => "button", 'aria-expanded' => "false", 'aria-controls' => "userfilterform_collapse"]);
echo html_writer::start_div('collapse show', ['id' => 'userfilterform_collapse']);
$mform->display();
echo html_writer::end_div();
echo html_writer::end_div();

echo $renderer->schedulereportsdata($reportid, $courseid, true);
echo html_writer::end_tag('div');
echo $OUTPUT->footer();
