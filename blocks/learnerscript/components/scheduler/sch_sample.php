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
require_login();
require_once($CFG->libdir . '/adminlib.php');
$format = optional_param('format', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
if ($format) {
    $fields = [
        'email' => get_string('email', 'block_learnerscript'),
        'exportformat' => get_string('export_format', 'block_learnerscript'),
        'exporttofilesystem' => get_string('exporttofilesystem', 'block_learnerscript'),
        'frequency' => get_string('frequency', 'block_learnerscript'),
        'schedule' => get_string('schedule', 'block_learnerscript'),
        'roleid' => get_string('role', 'block_learnerscript'),
        'contextlevel' => get_string('contextlevel', 'block_learnerscript'),
    ];

    switch ($format) {
        case 'csv':
            user_download_csv($fields, $id);
            break;
    }
    die;
}

/**
 * script for downloading admissions
 * @param array $fields Fields data
 * @param int $reportid Report id
 */
function user_download_csv($fields, $reportid) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/csvlib.class.php');
    $reportname = $DB->get_field('block_learnerscript', 'name', ['id' => $reportid]);
    $filename = $reportname;
    $csvexport = new csv_export_writer();
    $csvexport->set_filename($filename);
    $csvexport->add_data($fields);
    $userprofiledatadaily = [get_string('dailysampleinfo', 'block_learnerscript')];
    $userprofiledata = ['user@mailinator.com', 'csv', get_string('send_reportmail', 'block_learnerscript'),
    get_string('daily', 'block_learnerscript'), '14', '2', '10', ];
    $userprofiledata1 = ['user@mailinator.com', 'pdf', get_string('exporttosave', 'block_learnerscript'),
    get_string('daily', 'block_learnerscript'), '15', '2', '40', ];
    $userprofiledata2 = ['user@mailinator.com', 'ods', get_string('saveto_fileandmail', 'block_learnerscript'),
    get_string('daily', 'block_learnerscript'), '2', '2', '50', ];
    $userprofiledataweekly = [get_string('weeklysampleinfo', 'block_learnerscript')];
    $userprofiledata3 = ['user@mailinator.com', 'xls', get_string('send_reportmail', 'block_learnerscript'),
    get_string('weekly', 'block_learnerscript'), get_string('sun', 'block_learnerscript'), '2', '10', ];
    $userprofiledata4 = ['user@mailinator.com', 'csv', get_string('exporttosave', 'block_learnerscript'),
    get_string('weekly', 'block_learnerscript'), get_string('mon', 'block_learnerscript'), '3', '40', ];
    $userprofiledata5 = ['user@mailinator.com', 'csv', get_string('saveto_fileandmail', 'block_learnerscript'),
    get_string('weekly', 'block_learnerscript'), get_string('tue', 'block_learnerscript'), '2', '10', ];
    $userprofiledata6 = ['user@mailinator.com', 'pdf', get_string('send_reportmail', 'block_learnerscript'),
    get_string('weekly', 'block_learnerscript'), get_string('wed', 'block_learnerscript'), '5', '50', ];
    $userprofiledata7 = ['user@mailinator.com', 'ods', get_string('exporttosave', 'block_learnerscript'),
    get_string('weekly', 'block_learnerscript'), get_string('thu', 'block_learnerscript'), '5', '40', ];
    $userprofiledata8 = ['user@mailinator.com', 'xls', get_string('send_reportmail', 'block_learnerscript'),
    get_string('weekly', 'block_learnerscript'),  get_string('fri', 'block_learnerscript'), '4', '40', ];
    $userprofiledata9 = ['user@mailinator.com', 'csv', get_string('exporttosave', 'block_learnerscript'),
    get_string('weekly', 'block_learnerscript'), get_string('sat', 'block_learnerscript'), '2', '50', ];
    $userprofiledatamonthly = [get_string('monthlysampleinfo', 'block_learnerscript')];
    $userprofiledata10 = ['user@mailinator.com', 'pdf', get_string('send_reportmail', 'block_learnerscript'),
    get_string('monthly', 'block_learnerscript'), '1', '3', ];
    $userprofiledata11 = ['user@mailinator.com', 'xls', get_string('saveto_fileandmail', 'block_learnerscript'),
    get_string('monthly', 'block_learnerscript'), '2', '2', '50', ];
    $userprofiledata12 = ['user@mailinator.com', 'ods', get_string('send_reportmail', 'block_learnerscript'),
    get_string('monthly', 'block_learnerscript'), '3', '3', '50', ];
    $userprofiledata13 = ['user@mailinator.com', 'csv', get_string('exporttosave', 'block_learnerscript'),
    get_string('monthly', 'block_learnerscript'), '17', '1', '40', ];
    $userprofiledata14 = ['user@mailinator.com', 'pdf', get_string('send_reportmail', 'block_learnerscript'),
    get_string('monthly', 'block_learnerscript'), '30', '2', '50', ];
    $userprofiledataexample = [get_string('mandatoryinfo', 'block_learnerscript')];

    // Sample data.
    $csvexport->add_data($userprofiledatadaily);
    $csvexport->add_data($userprofiledata);
    $csvexport->add_data($userprofiledata1);
    $csvexport->add_data($userprofiledata2);
    $csvexport->add_data($userprofiledataweekly);
    $csvexport->add_data($userprofiledata3);
    $csvexport->add_data($userprofiledata4);
    $csvexport->add_data($userprofiledata5);
    $csvexport->add_data($userprofiledata6);
    $csvexport->add_data($userprofiledata7);
    $csvexport->add_data($userprofiledata8);
    $csvexport->add_data($userprofiledata9);
    $csvexport->add_data($userprofiledatamonthly);
    $csvexport->add_data($userprofiledata10);
    $csvexport->add_data($userprofiledata11);
    $csvexport->add_data($userprofiledata12);
    $csvexport->add_data($userprofiledata13);
    $csvexport->add_data($userprofiledata14);
    $csvexport->add_data($userprofiledataexample);

    $csvexport->download_file();
    die;
}
