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

/**
 * Class export_csv
 *
 * @package    block_learnerscript
 * @copyright  2024 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_csv {
    /**
     * Export data in csv format.
     * @package block_learnerscript
     * @param object $reportclass
     * @return mixed
     */
    public function export_report($reportclass) {
        // Retrieve report data
        $reportdata = $reportclass->finalreport;
        $table = $reportdata->table;
        $filename = $reportdata->name . "_" . date('d_M_Y_H_i_s', time()) . '.csv';

        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="' . $filename . '"');

        // Open output stream for writing
        $output = fopen('php://output', 'w');

        // Add filter row
        $filter = ['Filters'];
        fputcsv($output, $filter);

        if (isset($reportclass->selectedfilters) && !empty($reportclass->selectedfilters)) {
            foreach ($reportclass->selectedfilters as $k => $filter) {
                $k = substr($k, 0, -1);
                fputcsv($output, [$k, $filter]);
            }
        }

        // Add header row
        if (!empty($table->head)) {
            fputcsv($output, $table->head);
        }

        // Add data rows
        if (!empty($table->data)) {
            foreach ($table->data as $value) {
                // Clean up data
                $data = array_map(function ($v) {
                    return trim(strip_tags($v));
                }, $value);
                fputcsv($output, $data);
            }
        }

        // Close output stream
        fclose($output);
    }
    /**
     * CSV report export attachment
     * @param object $reportclass Report data
     * @param string $filename File name
     */
    public function export_csv_attachment($reportclass, $filename = '') {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');
        $report = $reportclass->finalreport;
        $table = $report->table;
        $matrix = [];
        $filename = '' ? $filename = 'report.csv' : $filename . '.csv';

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

        $csvexport = new \csv_export_writer();
        $csvexport->set_filename($filename);

        foreach ($matrix as $ri => $col) {
            $csvexport->add_data($col);
        }
        if ($filename) {
            $fp = fopen($filename, "w");
            fwrite($fp, $csvexport->print_csv_data(true));
            fclose($fp);
        } else {
            $csvexport->download_file();
        }
    }
}
