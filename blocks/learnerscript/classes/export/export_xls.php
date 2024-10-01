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
require_once($CFG->dirroot . '/lib/excellib.class.php');
require_once($CFG->dirroot . '/blocks/learnerscript/lib.php');
require_once($CFG->libdir . '/adminlib.php');
use MoodleExcelWorkbook;

/**
 * Class export_xls
 *
 * @package    block_learnerscript
 * @copyright  2024 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_xls {
    /**
     * Export data in XLS format.
     * @package block_learnerscript
     * @param object $reportclass
     * @param int $id report id
     */
    public function export_report($reportclass, $id) {
        global $DB;

        // Prepare data for export
        $reportdata = $reportclass->finalreport;
        $table = $reportdata->table;
        $filename = $reportdata->name . "_" . date('d_M_Y_H_i_s', time()) . '.xls';

        // Create a new Excel workbook
        $workbook = new MoodleExcelWorkbook("-");
        $worksheet = $workbook->add_worksheet('Report');

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
     * XLS report export attachment
     * @param object $reportclass Report data
     * @param string $filename File name
     */
    public function export_xls_attachment($reportclass, $filename) {
        global $CFG;
        require_once("$CFG->libdir/phpexcel/PHPExcel.php");
        $report = $reportclass->finalreport;
        $table = $report->table;

        $filename = $filename . '.xls';

        // Creating a workbook.
        $workbook = new \PHPExcel();
        $workbook->getActiveSheet()->setTitle(get_string('listofusers', 'block_learnerscript'));
        $rownumber = 1;
        $col = 'A';
        foreach ($table->head as $key => $heading) {
            $workbook->getActiveSheet()->setCellValue($col . $rownumber,
            str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($heading)),
                        ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401)));
            $col++;
        }
        // Loop through the result set.
        $rownumber = 2;
        if (!empty($table->data)) {
            foreach ($table->data as $rkey => $row) {
                $col = 'A';
                foreach ($row as $key => $item) {
                    $workbook->getActiveSheet()->setCellValue($col . $rownumber,
                    str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($item)),
                            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401)));
                    $col++;
                }
                $rownumber++;
            }
        }

        // Freeze pane so that the heading line won't scroll.
        $workbook->getActiveSheet()->freezePane('A2');

        // Save as an Excel BIFF (xls) file.
        $objwriter = \PHPExcel_IOFactory::createWriter($workbook, 'Excel2007');

        $objwriter->save($filename);
    }
}
