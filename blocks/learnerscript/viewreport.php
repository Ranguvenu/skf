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

require_once("../../config.php");
use block_learnerscript\local\ls as ls;
$id = required_param('id', PARAM_INT);
$download = optional_param('download', false, PARAM_BOOL);
$format = optional_param('format', '', PARAM_ALPHA);
$courseid = optional_param('courseid', SITEID, PARAM_INT);
$status = optional_param('status', '', PARAM_TEXT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$userid = optional_param('userid', $USER->id, PARAM_INT);
$drillid = optional_param('_drillid', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$cid = optional_param('cid', '', PARAM_ALPHANUM);
$comp = optional_param('comp', '', PARAM_ALPHA);
$pname = optional_param('pname', '', PARAM_ALPHA);
$learnerscript = get_config('block_learnerscript', 'ls_serialkey');
global $USER, $CFG, $SESSION;

$lsreportconfigstatus = get_config('block_learnerscript', 'lsreportconfigstatus');

if (!$lsreportconfigstatus) {
    redirect(new moodle_url('/blocks/learnerscript/lsconfig.php', ['import' => 1]));
}

if (!is_siteadmin() && empty($SESSION->role)) {
    $rolelist = (new ls)->get_currentuser_roles();
    if (empty($SESSION->role) && !empty($rolelist)) {
        $role = empty($SESSION->role) ? array_shift($rolelist) : $SESSION->role;
    } else {
        $role = '';
    }
    $SESSION->role = $role;
}
if (!is_siteadmin()) {
    if (empty($SESSION->ls_contextlevel)) {
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
        $querysql = "SELECT DISTINCT ctx.contextlevel, r.shortname
                           FROM {role} r
                           JOIN {role_assignments} ra ON ra.roleid = r.id
                           JOIN {context} ctx ON ctx.id = ra.contextid
                           WHERE ra.userid = :userid ORDER BY ctx.contextlevel ASC";
        $contextlevels = $DB->get_record_sql($querysql, ['userid' => $USER->id], 0, 1);
        $SESSION->rolecontextlist = $rcontext;
        $SESSION->ls_contextlevel = $contextlevels->contextlevel;
        $SESSION->rolecontext = $SESSION->role . '_' . $SESSION->ls_contextlevel;
    }
}
$filterrequests = [];
$datefilterrequests = [];
$datefilterrequests['lsfstartdate'] = 0;
$datefilterrequests['lsfenddate'] = time();
$dir = $CFG->dirroot . '/blocks/learnerscript/components/filters/';

$folders = (new block_learnerscript\local\ls)->getsubdirectories($dir);
$customfilters = array_map('basename', $folders);
array_push($customfilters, 'status');
array_shift($customfilters);
$filterfolders = $customfilters;
array_push($customfilters, 'lsfstartdate', 'lsfenddate');

foreach ($customfilters as $k => $v) {
    if ($v == 'status') {
        $urlfilterparams['filter_'.$v] = optional_param('filter_'.$v, '', PARAM_TEXT);
    } else {
        if (strpos($v, 'date') !== false) {
            $datefilterrequests[$v] = optional_param($v, 0, PARAM_INT);
        } else {
            $urlfilterparams['filter_'.$v] = optional_param('filter_'.$v, 0, PARAM_INT);
        }
    }
}
$urlrequests = array_filter($urlfilterparams);
foreach ($urlrequests as $key => $val) {
    if (strpos($key, 'filter_') !== false) {
        if ($key == 'filter_status') {
            $filterrequests[$key] = optional_param($key, $val, PARAM_TEXT);
        } else {
            $filterrequests[$key] = optional_param($key, $val, PARAM_INT);
        }
    }
    if (strpos($key, 'date') !== false) {
        $datefilterrequests[$key] = optional_param($key, $val, PARAM_INT);
    }
}
if (!$report = $DB->get_record('block_learnerscript', ['id' => $id])) {
    throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
}

if ($courseid && $report->global) {
    $report->courseid = $courseid;
} else {
    $courseid = $report->courseid;
}
if ($userid > 0) {
    $report->userid = $userid;
}
if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    throw new moodle_exception(get_string('nocourseid', 'block_learnerscript'));
}

// Force user login in course (SITE or Course).
if ($course->id == SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course);
    $context = context_course::instance($course->id);
}
$PAGE->set_context($context);
$PAGE->set_title($report->name);
$PAGE->set_pagelayout('report');

$PAGE->requires->data_for_js("M.cfg.accessls", $learnerscript , true);


if ($delete && confirm_sesskey()) {
    $components = (new block_learnerscript\local\ls)->cr_unserialize($report->components);
    $elements = isset($components->$comp->elements) ? $components->$comp->elements : [];
    foreach ($elements as $index => $e) {
        if ($e->id == $cid) {
            if ($delete) {
                unset($elements[$index]);
                break;
            }
            $newindex = ($moveup) ? $index - 1 : $index + 1;
            $tmp = $elements[$newindex];
            $elements[$newindex] = $e;
            $elements[$index] = $tmp;
            break;
        }
    }
    $components->$comp->elements = $elements;
    $report->components = (new block_learnerscript\local\ls)->cr_serialize($components);
    $DB->update_record('block_learnerscript', $report);
    redirect(new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $id, 'courseid' => $courseid]));
}

require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
$properties = new stdClass();
$reportclassname = 'block_learnerscript\lsreports\report_' . $report->type;
$reportclass = new $reportclassname($report, $properties);
$reportclass->courseid = $courseid;
if (!$download) {
    $reportclass->start = 0;
    $reportclass->length = 1;
} else {
    $reportclass->length = -1;
}
$reportclass->search = '';
$reportclass->filters = $filterrequests;
$reportclass->basicparamdata = $filterrequests;
$reportclass->status = $status;
$reportclass->lsstartdate = $datefilterrequests['lsfstartdate'];
$reportclass->lsenddate = $datefilterrequests['lsfenddate'];

$reportclass->cmid = $cmid;
$reportclass->userid = $userid;
if (!is_siteadmin() && !$reportclass->check_permissions($context, $USER->id)) {
    throw new moodle_exception("badpermissions", 'block_learnerscript');
}
$basicparamdata = new stdclass;
foreach ($filterfolders as $k => $v) {
    if ($v == 'status') {
        $urlparams['filter_'.$v] = optional_param('filter_'.$v, '', PARAM_TEXT);
    } else {
        $urlparams['filter_'.$v] = optional_param('filter_'.$v, 0, PARAM_INT);
    }
}
$request = array_filter($urlparams);
if ($request) {
    foreach ($request as $key => $val) {
        if (strpos($key, 'filter_') !== false) {
            $plugin = str_replace('filter_', '', $key);
            $basicparamdata->{$key} = $val;
            if (file_exists($CFG->dirroot . '/blocks/learnerscript/components/filters/' . $plugin . '/plugin.class.php')
            && !empty($val)) {
                require_once($CFG->dirroot . '/blocks/learnerscript/components/filters/' . $plugin . '/plugin.class.php');
                $classname = 'block_learnerscript\lsreports\plugin_' . $plugin;
                $class = new $classname($reportclass->config);
                $selected = get_string('selectedfilter', 'block_learnerscript', ucfirst($plugin));
                $reportclass->selectedfilters[$selected] = $class->selected_filter($val, $request);
            }
        }
    }
}
$reportclass->params = (array)$basicparamdata;
$reportname = format_string($report->name);

$PAGE->set_url('/blocks/learnerscript/viewreport.php', ['id' => $id]);

$download = ($download && $format && strpos($report->export, $format) !== false) ? true : false;

$PAGE->requires->css('/blocks/reportdashboard/css/radioslider/radios-to-slider.min.css');
$PAGE->requires->css('/blocks/reportdashboard/css/flatpickr.min.css');
$PAGE->requires->css('/blocks/learnerscript/css/datatables/fixedHeader.dataTables.min.css');
$PAGE->requires->css('/blocks/learnerscript/css/datatables/responsive.dataTables.min.css');
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->css('/blocks/learnerscript/css/select2/select2.min.css');
$PAGE->requires->css('/blocks/learnerscript/css/datatables/jquery.dataTables.min.css');
// No download, build navigation header etc.
if (!$download) {
    $reportsheadstart = get_config('block_reportdashboard', 'header_start');
    $reportsheadend = get_config('block_reportdashboard', 'header_end');
    $reportsheadstart = empty($reportsheadstart) ? '#0d3c56' : $reportsheadstart;
    $reportsheadend = empty($reportsheadend) ? '#35779b' : $reportsheadend;

    $columndata = (new ls)->column_definations($reportclass);
    $PAGE->requires->js_call_amd('block_learnerscript/report', 'init',
                                    [['reportid' => $id,
                                                'filterrequests' => $filterrequests,
                                                'cols' => $columndata['datacolumns'],
                                                'columnDefs' => $columndata['columndefs'],
                                                'basicparams' => $reportclass->basicparams,
                                            ],
                                ]);

    $reportclass->check_filters_request($_SERVER['REQUEST_URI']);

    $navlinks = [];
    if (has_capability('block/learnerscript:managereports', $context) ||
        (has_capability('block/learnerscript:manageownreports', $context)) &&
        $report->ownerid == $USER->id) {
        if (is_siteadmin()) {
            $managereporturl = new moodle_url('/blocks/learnerscript/managereport.php');
        } else {
            $managereporturl = new moodle_url('/blocks/learnerscript/managereport.php', ['role' => $SESSION->role,
            'contextlevel' => $SESSION->ls_contextlevel]);
        }
        $PAGE->navbar->add(get_string('managereports', 'block_learnerscript'), $managereporturl);
    } else {
        $dashboardurl = new moodle_url('/blocks/learnerscript/reports.php', ['role' =>
        $SESSION->role, 'contextlevel' => $SESSION->ls_contextlevel]);

        $PAGE->navbar->add(get_string("reports_view", 'block_learnerscript'), $dashboardurl);
    }
    if ($drillid > 0) {
        $drillreporturl = new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $drillid]);
        $drillreportname = $DB->get_field('block_learnerscript', 'name', ['id' => $drillid]);
        $PAGE->navbar->add($drillreportname, $drillreporturl);
    }
    $PAGE->navbar->add($report->name);
    $reportsfont = get_config('block_reportdashboard', 'reportsfont');
    if ($reportsfont == 2) { /*selected font as PT Sans*/
        $PAGE->requires->css('/blocks/reportdashboard/fonts/roboto.css');
    } else if ($reportsfont == 1) { /*selected font as Open Sans*/
        $PAGE->requires->css('/blocks/reportdashboard/fonts/roboto.css');
    }
    $PAGE->set_cacheable(true);
    $event = \block_learnerscript\event\view_report::create([
        'objectid' => $report->id,
        'context' => $context,
    ]);
    $event->trigger();

    echo $OUTPUT->header();
    $PAGE->requires->js(new moodle_url('https://learnerscript.com/wp-content/plugins/learnerscript/js/highcharts.js'));
    if ($report->type == 'sql' || $report->type == 'statistics') {
        echo $OUTPUT->heading($report->name."  ".
        html_writer::empty_tag('img', ['src' => $OUTPUT->image_url('help', 'core'),
                'title' => get_string('helpwith', 'block_learnerscript') . $report->name,
                'alt' => get_string('help'),
                'class' => 'statisticshelptext',
                'data-reportid' => $report->id ]));

    } else {
        echo $OUTPUT->heading($report->name.$OUTPUT->help_icon('report_' . $report->type,
            'block_learnerscript'));
    }
    echo html_writer::start_tag('div', ['id' => 'licenseresult', 'class' => 'lsaccess']);
    $renderer = $PAGE->get_renderer('block_learnerscript');
    if ($drillid > 0) {
        echo $OUTPUT->single_button($drillreporturl,
            get_string('goback', 'block_learnerscript') . $drillreportname);
    }
    $disabletable = !empty($report->disabletable) ? $report->disabletable : 0;
    $renderer->viewreport($report, $context, $reportclass);
    echo html_writer::tag('input', '', ['type' => 'hidden', 'name' => 'lsfstartdate',
            'id' => 'lsfstartdate', 'value' => 0, ]) .
        html_writer::tag('input', '', ['type' => 'hidden', 'name' => 'lsfenddate',
            'id' => 'lsfenddate', 'value' => time(), ]) .
        html_writer::tag('input', '', ['type' => 'hidden', 'name' => 'reportid',
            'value' => $report->id, ]) .
        html_writer::tag('input', '', ['type' => 'hidden', 'name' => 'disabletable',
            'id' => 'disabletable', 'value' => $disabletable, ]);
    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
} else {
    $reportclass->reporttype = 'table';
    $reportclass->downloading = true;
    $reportclass->create_report();
    $exportclass = 'block_learnerscript\export\export_' . $format;
    if (class_exists($exportclass)) {
        $reportclass->finalreport->name = $reportclass->config->name;
        (new $exportclass)->export_report($reportclass, $id);
    }
}
