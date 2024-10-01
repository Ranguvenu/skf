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
define('AJAX_SCRIPT', true);
require_once('../../../../config.php');
global $CFG, $PAGE, $COURSE, $USER;

$context = context_system::instance();
require_login();
$PAGE->set_context($context);

$reportid = optional_param('reportid', 0, PARAM_INT);
$draw = optional_param('draw', 1, PARAM_INT);
$start = optional_param('start', 0, PARAM_INT);
$length = optional_param('length', 10, PARAM_INT);
$search = optional_param_array('search', [], PARAM_RAW);
$order = $_REQUEST['order'][0];
$ordercolumn = clean_param_array($order, PARAM_RAW, true);
$cmid = optional_param('cmid', 0, PARAM_INT);
$courseid = optional_param('courseid', SITEID, PARAM_INT);
$status = optional_param('status', '', PARAM_TEXT);
$courses = optional_param('filter_courses', $courseid, PARAM_TEXT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$courseid = optional_param('courseid', SITEID, PARAM_INT);
$userid = optional_param('userid', $USER->id, PARAM_INT);
$lsstartdate = optional_param('lsfstartdate', '', PARAM_INT);
$lsenddate = optional_param('lsfenddate', '', PARAM_INT);
$filters = optional_param('filters', '', PARAM_TEXT);
$filters = json_decode($filters, true);
$basicparams = optional_param('basicparams', '', PARAM_TEXT);
$basicparams = json_decode($basicparams, true);
$reportclass = (new block_learnerscript\local\ls)->create_reportclass($reportid, $reportclass);

$reportclass->cmid = $cmid;
$reportclass->status = $status;
$reportclass->userid = $userid;
$reportclass->lsstartdate = 0;
$reportclass->lsenddate = time();
$reportclass->start = $start;
$reportclass->length = $length;
$reportclass->search = $search['value'];
$reportclass->params = array_merge($filters, $basicparams);
$reportclass->courseid = $reportclass->params['courseid'] > SITEID
? $reportclass->params['courseid'] : $reportclass->params['filter_courses'];
$reportclass->currentcourseid = $reportclass->courseid;
$reportclass->currentuser = $DB->get_record('user', ['id' => $userid]);
if (!empty($filters['lsfstartdate'])) {
    $reportclass->lsstartdate = $filters['lsfstartdate'];
} else {
    $reportclass->lsstartdate = 0;
}
if (!empty($filters['lsfstartdate'])) {
    $reportclass->lsenddate = $filters['lsfenddate'];
} else {
    $reportclass->lsenddate = time();
}
$reportclass->ordercolumn = $ordercolumn;
$reportclass->reporttype = 'table';
$reportclass->create_report(null);
$data = [];
foreach ($reportclass->finalreport->table->data as $key => $value) {
    $data[$key] = array_values($value);
}
echo json_encode(
    [
        "draw" => $draw,
        "recordsTotal" => $reportclass->totalrecords,
        "recordsFiltered" => $reportclass->totalrecords,
        "data" => $data,
    ]
);
