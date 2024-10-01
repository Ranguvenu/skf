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

require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG, $DB;
$reportid = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', SITEID, PARAM_INT);
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url('/blocks/learnerscript/components/scheduler/help.php');
$PAGE->set_pagelayout('admin');
$strheading = get_string('pluginname', 'block_learnerscript') .' : '. get_string('manual', 'block_learnerscript');
$PAGE->set_title($strheading);
require_login();
if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
    throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
}
$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add($report->name, new moodle_url('/blocks/learnerscript/viewreport.php',
['id' => $reportid, 'courseid' => $courseid]));

$PAGE->navbar->add(get_string('uploadscheduletime', 'block_learnerscript'),
new moodle_url('/blocks/learnerscript/components/scheduler/sch_upload.php',
['id' => $reportid, 'courseid' => $courseid]));
$PAGE->navbar->add(get_string('manual', 'block_learnerscript'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manual', 'block_learnerscript'));
echo $OUTPUT->box(get_string('helpmanual', 'block_learnerscript'));
echo html_writer::div(
    html_writer::link(new moodle_url('/blocks/learnerscript/components/scheduler/sch_upload.php', ['id' => $reportid]),
        html_writer::tag('button', get_string('back_upload', 'block_learnerscript'))
    ),
    '',
    []
);

$helpinstance = New stdClass();
$roles = $DB->get_records_sql("SELECT id, shortname
FROM {role}
WHERE shortname NOT IN ('guest', 'user', 'frontpage')");
$rolelist  = [];
asort($roles);
foreach ($roles as $role) {
    switch ($role->shortname) {
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
            $original = $role->shortname;
            break;
    }
    $rolelist[] = $role->id.' : '.$original;
}
$helpinstance->rolelist = implode(', ', $rolelist);
echo get_string('help_1', 'block_learnerscript', $helpinstance);
echo $OUTPUT->footer();
