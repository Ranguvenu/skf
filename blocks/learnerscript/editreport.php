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
 * Learner Script - Report Creation
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
use block_learnerscript\local\ls;

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', SITEID, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$show = optional_param('show', 0, PARAM_BOOL);
$hide = optional_param('hide', 0, PARAM_BOOL);
$duplicate = optional_param('duplicate', 0, PARAM_BOOL);

$report = null;

$learnerscript = get_config('block_learnerscript', 'ls_serialkey');
if (empty($learnerscript)) {
    throw new moodle_exception(get_string('licencekeyrequired', 'block_learnerscript'));
}
$lsreportconfigstatus = get_config('block_learnerscript', 'lsreportconfigstatus');
if (!$lsreportconfigstatus) {
    redirect(new moodle_url('/blocks/learnerscript/lsconfig.php', ['import' => 1]));
}

if (!$course = $DB->get_record("course", ["id" => $courseid])) {
    throw new moodle_exception("nosuchcourseid", 'block_learnerscript');
}

// Force user login in course (SITE or Course).
if ($course->id == SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course->id);
    $context = context_course::instance($course->id);
}

if (!has_capability('block/learnerscript:managereports', $context)
&& !has_capability('block/learnerscript:manageownreports', $context)) {
    throw new moodle_exception('badpermissions', 'block_learnerscript');
}

$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

$PAGE->requires->data_for_js("M.cfg.accessls", $learnerscript , true);
$PAGE->requires->jquery_plugin('ui-css');

if ($id) {
    if (!$report = $DB->get_record('block_learnerscript', ['id' => $id])) {
        throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
    }

    if (!has_capability('block/learnerscript:managereports', $context) && $report->ownerid != $USER->id) {
        throw new moodle_exception('badpermissions', 'block_learnerscript');
    }

    $title = format_string($report->name);

    $courseid = $report->courseid;
    if (!$course = $DB->get_record("course", ["id" => $courseid])) {
        throw new moodle_exception("nosuchcourseid", 'block_learnerscript');
    }
    require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');

    $properties = new stdClass();
    $properties->courseid = $courseid;
    $properties->start = 0;
    $properties->length = 10;
    $properties->search = '';
    $properties->filters = [];
    $properties->lsstartdate = 0;
    $properties->lsenddate = time();

    $reportclassname = 'block_learnerscript\lsreports\report_' . $report->type;
    $reportclass = new $reportclassname($report->id, $properties);
    $PAGE->set_url('/blocks/learnerscript/editreport.php', ['id' => $id]);
} else {
    $title = get_string('newreport', 'block_learnerscript');
    $PAGE->set_url('/blocks/learnerscript/editreport.php', null);
}

if ($report) {
    $title = format_string($report->name);
} else {
    $title = get_string('report', 'block_learnerscript');
}

$courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
$PAGE->navbar->add($course->shortname, $courseurl);

if (!empty($report->courseid)) {
    $params = ['courseid' => $report->courseid];
} else {
    $params = ['courseid' => $courseid];
}

$managereporturl = new moodle_url('/blocks/learnerscript/managereport.php');
$PAGE->navbar->add(get_string('managereports', 'block_learnerscript'), $managereporturl);

$PAGE->navbar->add($title);

// Common actions.
if (($show || $hide) && confirm_sesskey()) {
    $visible = ($show) ? 1 : 0;
    if (!$DB->set_field('block_learnerscript', 'visible', $visible, ['id' => $report->id])) {
        throw new moodle_exception('cannotupdatereport', 'block_learnerscript');
    }
    header("Location: $CFG->wwwroot/blocks/learnerscript/managereport.php");
    die;
}

if ($duplicate && confirm_sesskey()) {
    $newreport = new stdclass();
    $newreport = $report;
    unset($newreport->id);
    $newreport->name = get_string('copyasnoun') . ' ' . $newreport->name;
    $newreport->summary = $newreport->summary;
    if (!$newreportid = $DB->insert_record('block_learnerscript', $newreport)) {
        throw new moodle_exception('cannotduplicate', 'block_learnerscript');
    }
    header("Location: $CFG->wwwroot/blocks/learnerscript/managereport.php");
    die;
}

if ($delete && confirm_sesskey()) {
    if (!$confirm) {
        $PAGE->set_title($title);
        $PAGE->set_heading($title);
        $PAGE->set_cacheable(true);
        echo $OUTPUT->header();
        $PAGE->requires->js(new moodle_url('https://learnerscript.com/wp-content/plugins/learnerscript/js/highcharts.js'));
        $message = get_string('confirmdeletereport', 'block_learnerscript');
        $optionsyes = ['id' => $report->id, 'delete' => $delete, 'sesskey' => sesskey(), 'confirm' => 1];
        $optionsno = [];
        $buttoncontinue = new single_button(new moodle_url('editreport.php', $optionsyes), get_string('yes'), 'get');
        $buttoncancel = new single_button(new moodle_url('managereport.php', $optionsno), get_string('no'), 'get');
        echo $OUTPUT->confirm($message, $buttoncontinue, $buttoncancel);
        echo $OUTPUT->footer();
    } else {
        (new ls)->delete_report($report, $context);
        header("Location: $CFG->wwwroot/blocks/learnerscript/managereport.php");
    }
}


if (!empty($report)) {
    $editform = new block_learnerscript\form\report_edit_form('editreport.php', compact('report', 'courseid', 'context'));
} else {
    $editform = new block_learnerscript\form\report_edit_form('editreport.php', compact('courseid', 'context'));
}

if (!empty($report)) {
    $components = (new ls)->cr_unserialize($reportclass->config->components);
    $sqlconfig = (isset($components->customsql->config)) ? $components->customsql->config : new stdClass();
    if (!empty($sqlconfig->querysql)) {
        $report->querysql = $sqlconfig->querysql;
    }
    $report->description['text'] = $report->summary;
    $editform->set_data($report);
}

if ($editform->is_cancelled()) {
    if (!empty($report)) {
        redirect(new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $report->id]));
    } else {
        redirect(new moodle_url('/blocks/learnerscript/managereport.php'));
    }

} else if ($data = $editform->get_data()) {
    if (empty($report)) {
        $data->ownerid = $USER->id;
        $data->courseid = $courseid;
        $data->visible = 1;
        if ($data->type == 'sql' && !has_capability('block/learnerscript:managesqlreports', $context)) {
            throw new moodle_exception('nosqlpermissions', 'block_learnerscript');
        }
        $data->id = (new ls)->add_report($data, $context);
    } else {
        $data->type = $report->type;
        (new ls)->update_report($data, $context);
    }
    if ($data->type == 'statistics') {
        redirect(new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $data->id]));
    } else {
        redirect(new moodle_url('/blocks/learnerscript/design.php', ['id' => $data->id]));
    }
}

$PAGE->set_context($context);

$PAGE->set_pagelayout('incourse');

$PAGE->set_title($title);

$PAGE->set_heading($title);

$PAGE->set_cacheable(true);

echo $OUTPUT->header();
$PAGE->requires->js(new moodle_url('https://learnerscript.com/wp-content/plugins/learnerscript/js/highcharts.js'));
echo html_writer::start_tag('div', ['id' => 'licenseresult', 'class' => 'lsaccess']);

if ($id) {
    $renderer = $PAGE->get_renderer('block_learnerscript');
    if (has_capability('block/learnerscript:managereports', $context) ||
    (has_capability('block/learnerscript:manageownreports', $context)) && $report->ownerid == $USER->id) {
        $plots = [];
        $calcbutton = false;
        $plotoptions = new \block_learnerscript\output\plotoption($plots, $report->id, $calcbutton, 'editicon');
        echo $renderer->render($plotoptions);
    }
}
$editform->display();

echo html_writer::end_tag('div');
echo $OUTPUT->footer();
