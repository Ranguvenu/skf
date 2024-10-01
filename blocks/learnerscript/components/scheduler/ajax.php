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
define('AJAX_SCRIPT', true);
require_once('../../../../config.php');
global $CFG, $DB, $USER, $OUTPUT, $PAGE;
use block_learnerscript\local\schedule;

$action = required_param('action', PARAM_TEXT);
$reportid = optional_param('reportid', 0, PARAM_INT);
$search = optional_param_array('search', [], PARAM_TEXT);
$start = optional_param('start', 0, PARAM_INT);
$length = optional_param('length', 5, PARAM_INT);
$courseid = optional_param('courseid', 1, PARAM_INT);
$schuserslist = optional_param('schuserslist', '', PARAM_TEXT);
$component = optional_param('component', $requests['component'], PARAM_TEXT);
$pname = optional_param('pname', $requests['pname'], PARAM_TEXT);

$context = context_system::instance();
require_login();
$PAGE->set_context($context);

$scheduling = new schedule();
$learnerscript = $PAGE->get_renderer('block_learnerscript');

switch ($action) {
    case 'scheduledtimings':
        if ((has_capability('block/learnerscript:managereports', $context)
        || has_capability('block/learnerscript:manageownreports', $context)
        || is_siteadmin()) && !empty($reportid)) {
            $return = $learnerscript->schedulereportsdata($reportid, $courseid,
            false, $start, $length, $search['value']);
        } else {
            $termsdata = [];
            $termsdata['error'] = true;
            $termsdata['type'] = 'Warning';
            if (empty($reportid)) {
                $termsdata['cap'] = false;
                $termsdata['msg'] = get_string('missingparam', 'block_learnerscript', 'Reportid');
            } else {
                $termsdata['cap'] = true;
                $termsdata['msg'] = get_string('badpermissions', 'block_learnerscript');
            }
            $return = $termsdata;
        }
        break;
    case 'viewschusersdata':
        if ((has_capability('block/learnerscript:managereports', $context)
        || has_capability('block/learnerscript:manageownreports', $context)
        || is_siteadmin()) && !empty($schuserslist)) {
            $stable = new stdClass();
            $stable->table = false;
            $stable->start = $start;
            $stable->length = $length;
            $stable->search = $search['value'];
            $return = $learnerscript->viewschusers($reportid, $scheduleid, $schuserslist, $stable);
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
        break;
    case 'plotform':
        $componentdata = $learnerscript->render_component_form($reportid, $component, $pname);
        echo $componentdata['html'].'<script>'.$componentdata['script'].'</script>';
    break;
}
echo json_encode($return, JSON_NUMERIC_CHECK);
