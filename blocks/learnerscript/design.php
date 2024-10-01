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
 * Definition of Schedule Reports scheduled tasks.
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
global $OUTPUT, $PAGE, $CFG;
$id = required_param('id', PARAM_INT);
require_login();
$systemcontext = context_system::instance();
$PAGE->set_url('/blocks/learnerscript/design.php');
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('design', 'block_learnerscript'));

if (!$report = $DB->get_record('block_learnerscript', ['id' => $id])) {
    throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
}
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js('/blocks/learnerscript/js/angular.js');
$PAGE->requires->css('/blocks/learnerscript/css/angular-material.min.css');
$PAGE->requires->js('/blocks/learnerscript/js/smart-table.min.js');
$PAGE->requires->js('/blocks/learnerscript/js/angular-drag-and-drop-lists.min.js');
$PAGE->requires->js('/blocks/learnerscript/js/angular-animate.min.js');
$PAGE->requires->js('/blocks/learnerscript/js/angular-route.js');
$PAGE->requires->js('/blocks/learnerscript/js/angular-aria.min.js');
$PAGE->requires->js('/blocks/learnerscript/js/angular-material.min.js');
$PAGE->requires->js('/blocks/learnerscript/js/design.js');

$PAGE->set_heading($report->name);
$reporturl = new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $report->id]);
$PAGE->navbar->add($report->name, $reporturl);
$PAGE->navbar->add(get_string("design", 'block_learnerscript'));

$learnerscript = get_config('block_learnerscript', 'ls_serialkey');
if (empty($learnerscript)) {
    throw new moodle_exception(get_string('license_key_required', 'block_learnerscript'));
}
$PAGE->requires->data_for_js("M.cfg.accessls", $learnerscript, true);

$lsreportconfigstatus = get_config('block_learnerscript', 'lsreportconfigstatus');
if (!$lsreportconfigstatus) {
    redirect(new moodle_url('/blocks/learnerscript/lsconfig.php', ['import' => 1]));
    exit;
}

echo $OUTPUT->header();
$PAGE->requires->js(new moodle_url('https://learnerscript.com/wp-content/plugins/learnerscript/js/highcharts.js'));
echo html_writer::start_tag('div', ['id' => 'licenseresult', 'class' => 'lsaccess']);

$renderer = $PAGE->get_renderer('block_learnerscript');
if (!is_siteadmin($USER->id)) {
    require_capability('block/learnerscript:designreport', $systemcontext);
}
if (!$report = $DB->get_record('block_learnerscript', ['id' => $id])) {
    throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
}
$courseid = SITEID;
if (!$course = $DB->get_record("course", ["id" => $courseid])) {
    throw new moodle_exception(get_string('nocourseid', 'block_learnerscript'));
}
if (has_capability('block/learnerscript:managereports', $systemcontext) ||
    (has_capability('block/learnerscript:manageownreports', $systemcontext))
    && $report->ownerid == $USER->id) {
    $plots = [];
    $calcbutton = false;
    $plotoptions = new \block_learnerscript\output\plotoption($plots, $report->id, $calcbutton,
        'design');
    echo $renderer->render($plotoptions);
}
$reportsheadstart = get_config('block_reportdashboard', 'header_start');
$reportsheadend = get_config('block_reportdashboard', 'header_end');
$reportsheadstart = empty($reportsheadstart) ? '#0d3c56' : $reportsheadstart;
$reportsheadend = empty($reportsheadend) ? '#35779b' : $reportsheadend;
$designdata = new \block_learnerscript\output\design($report, $id);
echo $renderer->render($designdata);
echo html_writer::end_tag('div');
echo $OUTPUT->footer();
