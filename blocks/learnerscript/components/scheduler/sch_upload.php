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
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once('sch_upload_lib.php');
require_once('sch_upload_form.php');
use block_learnerscript\local\ls as ls;
use block_learnerscript\local\schedule;
$iid = optional_param('iid', 0, PARAM_INT);
$reportid = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', SITEID, PARAM_INT);
$previewrows = optional_param('previewrows', 10, PARAM_INT);

@set_time_limit(60 * 60); // 1 hour should be enough.
raise_memory_limit(MEMORY_HUGE);

require_login();

$learnerscript = get_config('block_learnerscript', 'ls_serialkey');
if (empty($learnerscript)) {
    throw new moodle_exception(get_string('licencekeyrequired', 'block_learnerscript'));
}

$errorstr = get_string('error');
$stryes = get_string('yes');
$strno = get_string('no');
$stryesnooptions = [0 => $strno, 1 => $stryes];

global $USER, $DB, $PAGE, $OUTPUT;
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);

$PAGE->set_pagelayout('admin');
$PAGE->set_url('/blocks/learnerscript/components/scheduler/sch_upload.php?id=' . $reportid);
$PAGE->set_heading($SITE->fullname);
$strheading = get_string('pluginname', 'block_learnerscript') . ' : ' . get_string('uploadusers', 'block_learnerscript');
$PAGE->set_title($strheading);

$PAGE->requires->data_for_js("M.cfg.accessls", $learnerscript, true);
$PAGE->requires->jquery_plugin('ui-css');

if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
    throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
}

$PAGE->navbar->add($report->name, new moodle_url('/blocks/learnerscript/viewreport.php',
['id' => $reportid, 'courseid' => $courseid]));
$PAGE->navbar->add(get_string('uploadusers', 'block_learnerscript'));
$returnurl = new moodle_url('/blocks/learnerscript/components/scheduler/schedule.php?id=' .
$reportid . '&courseid=' . $courseid);
$returnurl1 = new moodle_url('/blocks/learnerscript/components/scheduler/sch_upload.php?id=' .
$reportid . '&courseid=' . $courseid);
$stdfields = [get_string('email', 'block_learnerscript'),  get_string('export_format', 'block_learnerscript'),
get_string('export_filesystem', 'block_learnerscript'), get_string('frequency', 'block_learnerscript'),
get_string('schedule', 'block_learnerscript'), get_string('role', 'block_learnerscript'),
get_string('contextlevel', 'block_learnerscript'), ];
$prffields = [];
$samplefields = [
    'email' => get_string('email', 'block_learnerscript') ,
    'exportformat' => get_string('export_format', 'block_learnerscript'),
    'exporttofilesystem' => get_string('export_filesystem', 'block_learnerscript'),
    'frequency' => get_string('frequency', 'block_learnerscript'),
    'schedule' => get_string('schedule', 'block_learnerscript'),
    'roleid' => get_string('role', 'block_learnerscript'),
    'contextlevel' => get_string('contextlevel', 'block_learnerscript'),
];

$scheduling = new schedule();
$mform1 = new bulkschreports(new moodle_url('/blocks/learnerscript/components/scheduler/sch_upload.php', ['id' => $reportid]),
['reportid' => $reportid]);
if ($mform1->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $mform1->get_data()) {
    echo $OUTPUT->header();
    $PAGE->requires->js(new moodle_url('https://learnerscript.com/wp-content/plugins/learnerscript/js/highcharts.js'));
    $iid = csv_import_reader::get_new_iid('uploaduser');
    $cir = new csv_import_reader($iid, 'uploaduser');
    $content = $mform1->get_file_content('userfile');
    $readcount = $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);
    unset($content);
    if ($readcount === false) {
        throw new moodle_exception('csvloaderror', '', $returnurl);
    } else if ($readcount == 0) {
        throw new moodle_exception('csvemptyfile', 'error', $returnurl);
    }
    $linenum = 1;
    $subline = 1;
    $errorscount = 0;
    $mfieldscount = 0;
    $successcreatedcount = 0;
    $reportclass = (new ls)->create_reportclass($reportid);
    // Test if columns ok(to validate the csv file content).
    $filecolumns = uu_validate_user_upload_columns($cir, $stdfields, $prffields, $returnurl);
    $upt = New uu_progress_tracker();
    $cir->init();
    while ($line = $cir->next()) {
        $upt->flush();
        $linenum++;
        $scheduledata = new stdClass();
        // Add fields to user object.
        foreach ($line as $keynum => $value) {
            if (!isset($filecolumns[$keynum])) {
                // This should not happen.
                continue;
            }
            $k = $filecolumns[$keynum];
            $key = array_search($k, $samplefields);
            $scheduledata->$key = $value;
        }

        // Add default values for remaining fields.
        $formdefaults = [];
        foreach ($stdfields as $field) {
            if (isset($scheduledata->$field)) {
                continue;
            }
            // All validation moved to form2.
            if (isset($formdata->$field)) {
                // Process templates.
                $formdefaults[$field] = true;
            }
        }
        foreach ($prffields as $field) {
            if (isset($scheduledata->$field)) {
                continue;
            }
            if (isset($formdata->$field)) {
                // Process templates.
                $formdefaults[$field] = true;
            }
        }
        $validations = formatdata_validation($reportid, $scheduledata, $linenum, $formatteddata, $reportclass);
        if (count($validations['errors']) > 0) {
            echo implode(' ', $validations['errors']);
        }
        if (!empty($validations['errors']) > 0 || !empty($validations['mfields']) > 0) {
            $errorscount++;
            $mfieldscount++;
        } else {
            $formatteddata->reportid = $reportid;
            $formatteddata->userid = $USER->id;
            $formatteddata->timecreated = time();
            $formatteddata->timemodified = time();
            $formatteddata->nextschedule = $scheduling->next($formatteddata);
            $uploadusers = $DB->insert_record('block_ls_schedule', $formatteddata);
            if ($uploadusers) {
                $successcreatedcount++;
            }
        }

    }

    $cir->cleanup(true);

    echo $OUTPUT->box_start('boxwidthnarrow boxaligncenter generalbox', 'uploadresults');
    echo html_writer::start_tag('div', ['class' => 'panel panel-primary']);
    if ($successcreatedcount > 0) {
        echo html_writer::tag('div',
        $successcreatedcount . get_string('records_successfullycreated', 'block_learnerscript'),
        ['class' => 'alert alert-success', 'role' => 'alert']
        );
        echo html_writer::tag('h6',
            ($linenum - 1) . get_string('user_uploaded', 'block_learnerscript'),
            []
        );
    }
    if ($mfieldscount > 0) {
        echo html_writer::tag('div',
        get_string('uploaderrors', 'block_learnerscript') . ': ' . $mfieldscount,
        ['class' => 'panel-body']
        );

    }

    echo html_writer::end_tag('div');
    if ($mfieldscount > 0) {
        echo html_writer::tag('h4', get_string('fill_without_error', 'block_learnerscript'));
    }

    echo $OUTPUT->box_end();

    echo html_writer::tag(
        'div',
        html_writer::link(
            new moodle_url('/blocks/learnerscript/components/scheduler/schedule.php', ['id' => $reportid, 'courseid' => $courseid]),
            html_writer::tag(
                'button',
                get_string('continue', 'block_learnerscript')
            )
        ),
        ['class' => 'text-center']
    ) . '<br />';

    echo $OUTPUT->footer();
    die;

    // Continue to form2.
} else {
    echo $OUTPUT->header();
    $PAGE->requires->js(new moodle_url('https://learnerscript.com/wp-content/plugins/learnerscript/js/highcharts.js'));
    echo $OUTPUT->heading(get_string('uploadusers', 'block_learnerscript'));
    echo html_writer::tag(
        'div',
        html_writer::link(
            new moodle_url('/blocks/learnerscript/components/scheduler/sch_sample.php', ['format' => 'csv', 'id' => $reportid]),
            html_writer::tag(
                'button',
                get_string('sample_csv', 'block_learnerscript')
            )
        ) . html_writer::link(
            new moodle_url('/blocks/learnerscript/components/scheduler/help.php', ['id' => $reportid]),
            html_writer::tag(
                'button',
                get_string('manual', 'block_learnerscript')
            )
        ),
        ['class' => 'samplecsv']
    );
    $mform1->display();

    echo $OUTPUT->footer();
    die;
}
