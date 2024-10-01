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

namespace block_learnerscript\export;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/blocks/learnerscript/lib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/lib/excellib.class.php');
require_once("$CFG->libdir/odslib.class.php");
use MoodleODSWorkbook;

/**
 * Class export_ods
 *
 * @package    block_learnerscript
 * @copyright  2024 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_ods {
    /**
     * Export data in ODS format.
     * @package block_learnerscript
     * @param object $reportclass
     * @return mixed
     */
    public function export_report($reportclass, $id) {
        global $DB;
        $reportdata = $reportclass->finalreport;
        $table = $reportdata->table;
        $filename = $reportdata->name . "_" . date('d M Y H:i:s', time()) . '.ods';
        // Create a new Excel workbook
        $workbook = new MoodleODSWorkbook("-");
        // Sending HTTP headers.
        $workbook->send($filename);
        // Creating the first worksheet.
        $sheettitle = get_string('report', 'scorm');
        $worksheet = $workbook->add_worksheet($filename);

        // Add filters to the worksheet
        $row = 0;
        $col = 0;

        // Write 'Filters' title
        $worksheet->write($row, $col, 'Filters');
        $row++;
        if (!empty($reportclass->selectedfilters)) {
            foreach ($reportclass->selectedfilters as $k => $filter) {
                $worksheet->write($row, $col, $k);
                $worksheet->write($row, $col + 1, $filter);
                $row++;
            }
        }

        // Add column headers if applicable
        $reporttype = $DB->get_field('block_learnerscript', 'type', ['id' => $id]);
        if ($reporttype != 'courseprofile' && $reporttype != 'userprofile') {
            if (!empty($table->head)) {
                $col = 0;
                foreach ($table->head as $heading) {
                    // Ensure $key is an integer index for the column
                    $worksheet->write($row, $col, $heading);
                    $col++;
                }
                $row++;
            }
        }

        // Add data rows
        if (!empty($table->data)) {
            foreach ($table->data as $data) {
                $col = 0;
                foreach ($data as $value) {
                    $worksheet->write($row, $col, trim(strip_tags($value)));
                    $col++;
                }
                $row++;
            }
        }

        // Send the Excel file to the browser
        $workbook->send($filename);
        $workbook->close();
    }

    /**
     * ODS report export attachment
     * @param object $reportclass Report data
     * @param string $filename File name
     */
    public function export_ods_attachment($reportclass, $filename = null) {
        global $CFG;
        require_once($CFG->dirroot . '/lib/odslib.class.php');
        $report = $reportclass->finalreport;
        $table = $report->table;
        $matrix = [];
        $filename = $filename . '.ods';

        if (!empty($table->head)) {
            foreach ($table->head as $key => $heading) {
                $matrix[0][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($heading)),
                                    ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401));
            }
        }

        if (!empty($table->data)) {
            foreach ($table->data as $rkey => $row) {
                foreach ($row as $key => $item) {
                    $matrix[$rkey + 1][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($item)),
                                ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401));
                }
            }
        }
        $workbook = new \MoodleODSWorkbook($filename);

        $myxls = [];

        $myxls[0] = $workbook->add_worksheet('');
        foreach ($matrix as $ri => $col) {
            foreach ($col as $ci => $cv) {
                $myxls[0]->write($ri, $ci, $cv);
            }
        }
        $writer = new \MoodleODSWriter($myxls);
        $contents = $writer->get_file_content();
        $handle = fopen($filename, 'w');
        fwrite($handle, $contents);
        fclose($handle);
    }
}
