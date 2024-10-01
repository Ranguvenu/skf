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
use block_learnerscript\local\schedule;
use block_learnerscript\local\ls;
defined('MOODLE_INTERNAL') || die();

define('UU_USER_ADDNEW', 0);
define('UU_USER_ADDINC', 1);
define('UU_USER_ADD_UPDATE', 2);
define('UU_USER_UPDATE', 3);

define('UU_UPDATE_NOCHANGES', 0);
define('UU_UPDATE_FILEOVERRIDE', 1);
define('UU_UPDATE_ALLOVERRIDE', 2);
define('UU_UPDATE_MISSING', 3);

define('UU_BULK_NONE', 0);
define('UU_BULK_NEW', 1);
define('UU_BULK_UPDATED', 2);
define('UU_BULK_ALL', 3);

define('UU_PWRESET_NONE', 0);
define('UU_PWRESET_WEAK', 1);
define('UU_PWRESET_ALL', 2);
/**
 * Progress tracker
 */
class uu_progress_tracker {
    /** @var array $_row  */
    private $_row;
     /** @var array $columns  */
    public $columns = ['email', 'exportformat', 'exporttofilesystem', 'schedule', 'frequency', 'roleid'];

    /**
     * Print table header.
     * @return void
     */
    public function start() {
        $ci = 0;
        echo html_writer::start_tag('table', ['id' => 'uuresults', 'class' =>
        'generaltable boxaligncenter flexible-wrap', 'summary' =>
        get_string('uploadusersresult', 'tool_uploaduser'), ]);
        echo html_writer::start_tag('tr', ['class' => 'heading r0']);
        $ci = 0;
        echo html_writer::tag('th', get_string('email'),
        ['class' => 'header c' . $ci++, 'scope' => 'col']);
        echo html_writer::tag('th', get_string('exportformat'),
        ['class' => 'header c' . $ci++, 'scope' => 'col']);
        echo html_writer::tag('th', get_string('exporttofilesystem'),
        ['class' => 'header c' . $ci++, 'scope' => 'col']);
        echo html_writer::tag('th', get_string('schedule'),
        ['class' => 'header c' . $ci++, 'scope' => 'col']);
        echo html_writer::tag('th', get_string('frequency'),
        ['class' => 'header c' . $ci++, 'scope' => 'col']);
        echo html_writer::tag('th', get_string('roleid'),
        ['class' => 'header c' . $ci++, 'scope' => 'col']);
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('table');
        $this->_row = null;
    }

    /**
     * Flush previous line and start a new one.
     * @return void
     */
    public function flush() {
        if (empty($this->_row) || empty($this->_row['line']['normal'])) {
            // Nothing to print - each line has to have at least number.
            $this->_row = [];
            foreach ($this->columns as $col) {
                $this->_row[$col] = ['normal' => '', 'info' => '', 'warning' => '', 'error' => ''];
            }
            return;
        }
        $ci = 0;
        $ri = 1;
        echo html_writer::start_tag('tr', ['class' => 'r' . $ri]);
        foreach ($this->_row as $key => $field) {
            foreach ($field as $type => $content) {
                if ($field[$type] !== '') {
                    $field[$type] = html_writer::tag('span', $field[$type],
                     ['class' => 'uu' . $type]);
                } else {
                    unset($field[$type]);
                }
            }
            echo html_writer::start_tag('td', ['class' => 'cell c' . $ci++]);
            if (!empty($field)) {
                echo implode(html_writer::empty_tag('br'), $field);
            } else {
                echo '&nbsp;';
            }
            echo html_writer::end_tag('td');
        }
        echo html_writer::end_tag('tr');
        foreach ($this->columns as $col) {
            $this->_row[$col] = ['normal' => '', 'info' => '', 'warning' => '', 'error' => ''];
        }
    }

    /**
     * Add tracking info
     * @param string $col name of column
     * @param string $msg message
     * @param string $level 'normal', 'warning' or 'error'
     * @param bool $merge true means add as new line, false means override all previous text of the same type
     * @return void
     */
    public function track($col, $msg, $level = 'normal', $merge = true) {
        if (empty($this->_row)) {
            $this->flush();
        }
        if (!in_array($col, $this->columns)) {
            debugging(get_string('incorrect_column', 'block_learnerscript') . $col);
            return;
        }
        if ($merge) {
            if ($this->_row[$col][$level] != '') {
                $this->_row[$col][$level] .= html_writer::empty_tag('br');
            }
            $this->_row[$col][$level] .= $msg;
        } else {
            $this->_row[$col][$level] = $msg;
        }
    }

    /**
     * Print the table end
     * @return void
     */
    public function close() {
        $this->flush();
        echo html_writer::end_tag('table');
    }
}

/**
 * Validation callback function - verified the column line of csv file.
 * Converts standard column names to lowercase.
 * @param csv_import_reader $cir
 * @param array $stdfields standard user fields
 * @param array $profilefields custom profile fields
 * @param moodle_url $returnurl return url in case of any error
 * @return array list of fields
 */
function uu_validate_user_upload_columns(csv_import_reader $cir, $stdfields, $profilefields, $returnurl) {
    $columns = $cir->get_columns();

    if (empty($columns)) {
        $cir->close();
        $cir->cleanup();
        throw new moodle_exception('cannotreadtmpfile', 'error', $returnurl);
    }
    if (count($columns) < 1) {
        $cir->close();
        $cir->cleanup();
        throw new moodle_exception('csvfewcolumns', 'error', $returnurl);
    }
    $processed = [];
    foreach ($columns as $key => $unused) {
        $field = $columns[$key];
        if (in_array($field, $stdfields)) {
            $newfield = $field;

        } else {
            $cir->close();
            $cir->cleanup();
            throw new moodle_exception('invalidfieldname', 'error', $returnurl, $field);
        }
        if (in_array($newfield, $processed)) {
            $cir->close();
            $cir->cleanup();
            throw new moodle_exception('duplicatefieldname', 'error', $returnurl, $newfield);
        }
        $processed[$key] = $newfield;
    }

    return $processed;
}

/**
 * Format data validation
 * @param int $reportid Report id
 * @param stdclass $data report data
 * @param int $linenum Line number
 * @param stdclass $formatteddata Report fomat data
 * @param object $reportclass report data
 * @return object|array
 */
function formatdata_validation($reportid, $data, $linenum, &$formatteddata, $reportclass) {
    global $DB, $USER, $reportexportformats;
    $scheduling = new schedule();
    $warnings = []; // Warnings List.
    $errors = []; // Errors List.
    $mfields = []; // Mandatory Fields.
    $formatteddata = new stdClass(); // Formatted Data for inserting into DB.
    $exportformats = (new ls)->cr_get_export_options($reportid); // Export Formats.
    $exporttofilesystems = [get_string('report_tomail', 'block_learnerscript') => 1,
    get_string('save_to_filesystem', 'block_learnerscript') => 2,
    get_string('saveto_fileandmail', 'block_learnerscript') => 3, ]; // Export Filesystems.
    $frequencies = ['daily' => 1, 'weekly' => 2, 'monthly' => 3]; // Frequency.

    $reportclass->courseid = $reportclass->config->courseid;
    if ($reportclass->config->courseid == SITEID) {
        $context = context_system::instance();
    } else {
        $context = context_course::instance($reportclass->config->courseid);
    }

    if (empty($data->email)) {
        $mfields[] = 'email';
        $errors[] = html_writer::tag(
            'div',
            get_string('please_entermail', 'block_learnerscript')
            . $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    } else if (!validate_email($data->email)) {
        $errors[] = html_writer::tag(
            'div',
            get_string('invalid_mail_lineno', 'block_learnerscript')
            . $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    } else {
        $userid = $DB->get_field_sql("SELECT id FROM {user} WHERE email = '$data->email'");
    }

    if (empty($userid)) {
        $errors[] = html_writer::tag(
            'div',
            get_string('user_not_available', 'block_learnerscript')
             . $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    } else {
        $formatteddata->sendinguserid = $userid;
    }

    if (empty($data->exportformat)) {
        $mfields[] = get_string('exportformat', 'block_learnerscript');
        $errors[] = html_writer::tag(
            'div',
            get_string('export_format_inline', 'block_learnerscript')
            . $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    }

    if (!in_array($data->exportformat, array_keys($reportexportformats))) {
        $errors[] = html_writer::tag(
            'div',
            get_string('correct_exportformat', 'block_learnerscript')
            . $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    } else {
        $formatteddata->exportformat = $data->exportformat;
    }
    if (empty($data->exporttofilesystem)) {
        $mfields[] = get_string('exporttofilesystem', 'block_learnerscript');
        $errors[] = html_writer::tag(
            'div',
            get_string('export_filesystem_inline', 'block_learnerscript') .
             $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    }
    if (!isset($exporttofilesystems[$data->exporttofilesystem])) {
        $errors[] = html_writer::tag(
            'div',
            get_string('correct_exportfilesystem', 'block_learnerscript')
             . $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    } else {
        $formatteddata->exporttofilesystem = $exporttofilesystems[$data->exporttofilesystem];
    }
    if (empty($data->frequency)) {
        $mfields[] = get_string('frequency', 'block_learnerscript');
        $errors[] = html_writer::tag(
            'div',
            get_string('enter_frequesncyline', 'block_learnerscript') .
             $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    }
    $datafrequency = $data->frequency ? strtolower($data->frequency) : $data->frequency;
    if (!$frequency = isset($frequencies[$datafrequency])) {
        $errors[] = html_writer::tag(
            'div',
            get_string('enter_schedule', 'block_learnerscript') .
            $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    } else {
        $formatteddata->frequency = $frequencies[$datafrequency];
    }
    if (empty($data->schedule)) {
        $mfields[] = get_string('schedule', 'block_learnerscript');
        $errors[] = html_writer::tag(
            'div',
            get_string('enter_schedule', 'block_learnerscript') .
             $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    }
    $schedules = $scheduling->getschedulelist($formatteddata->frequency);

    $schedule = $data->schedule;
    if (!in_array($schedule, $schedules)) {
        $errors[] = html_writer::tag(
            'div',
            get_string('correct_scheduleline', 'block_learnerscript')
            . $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    } else {
        if ($formatteddata->frequency == 2) {
            $formatteddata->schedule = array_search($data->schedule, $schedules);
        } else {
            $formatteddata->schedule = $data->schedule;
        }
    }
    if (empty($data->roleid)) {
        $mfields[] = get_string('role', 'block_learnerscript');
        $errors[] = html_writer::tag(
            'div',
            get_string('enter_role', 'block_learnerscript') . $linenum
            . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    }
    if (!$DB->record_exists('role', ['id' => $data->roleid])) {
        $errors[] = html_writer::tag(
            'div',
            get_string('correct_role', 'block_learnerscript') . $linenum
            . get_string('uploaded_sheet', 'block_learnerscript'),
            ['class' => 'alert alert-error', 'role' => 'alert']
        );
    } else {
        $formatteddata->roleid = $data->roleid;
    }
    if ($data->roleid > 0) {
        if (!$DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $data->roleid])) {
            $mfields[] = get_string('nouserrole', 'block_learnerscript');
            $errors[] = html_writer::tag(
                'div',
                get_string('combo_isnotavailable', 'block_learnerscript')
                . $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
                ['class' => 'alert alert-error', 'role' => 'alert']
            );
        }
    }
    if ($userid > 0 && $data->roleid > 0) {
        $reportclass->userid = $userid;
        $reportclass->role = $DB->get_field('role', 'shortname', ['id' => $data->roleid]);
        if (!is_siteadmin($userid) && !$reportclass->check_permissions($context, $userid)) {
            $mfields[] = get_string('noreportpermission', 'block_learnerscript');
            $errors[] = html_writer::tag(
                'div',
                get_string('thisreport_notavailable', 'block_learnerscript')
                . $linenum . get_string('uploaded_sheet', 'block_learnerscript'),
                ['class' => 'alert alert-error', 'role' => 'alert']
            );
        }
    }
    $formatteddata->contextlevel = isset($data->contextlevel) ? $data->contextlevel : 10;
    return compact('mfields', 'errors');
}
