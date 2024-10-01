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
require_once($CFG->libdir . '/pdflib.php');
use html_writer;

/**
 * Class export_pdf
 *
 * @package    block_learnerscript
 * @copyright  2024 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_pdf {
    /**
     * This function export report in PDF format
     * @param object $reportclass Report class
     * @param int $id Report id
     */
    public function export_report($reportclass, $id) {
        global $DB;
        $reportdata = $reportclass->finalreport;
        if (!empty($reportclass->basicparams)) {
            $requestdata = array_merge($reportclass->params, $reportclass->basicparams);
        } else {
            $requestdata = $reportclass->params;
        }
        $reportname = $DB->get_record('block_learnerscript', ['id' => $id]);
        $table = $reportdata->table;
        $matrix = [];
        $reportname->name == $reportdata->name . "_" . date('d M Y H:i:s', time()) . '.pdf';

        $filters = [];
        foreach ($requestdata as $key => $val) {
            if (strpos($key, 'filter_') !== false) {
                $key = explode('_', $key, 2)[1];
                $filters[$key] = $val;
            }
        }
        $finalfilterdata = '';
        if (!empty($reportclass->selectedfilters)) {
            foreach ($reportclass->selectedfilters as $k => $filter) {
                $finalfilterdata .= html_writer::div($k . ' ' . $filter);
            }
        }
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

        $table = "";
        $table .= html_writer::start_tag('table', ['border' => '1', 'cellpadding' => '5']);
        reset($matrix);
        $firstkey = key($matrix);
        $reporttype = $DB->get_field('block_learnerscript',  'type',  ['id' => $id]);
        if ($matrix) {
            if ($reporttype != 'courseprofile' && $reporttype != 'userprofile') {
                $table .= html_writer::start_tag('thead') . html_writer::start_tag('tr');
                for ($i = $firstkey; $i < ($firstkey + 1); $i++) {
                    foreach ($matrix[$i] as $col) {
                        $table .= html_writer::tag('td', $col);
                    }
                }
                $table .= html_writer::end_tag('tr') . html_writer::end_tag('thead');
            }

            $table .= html_writer::start_tag('tbody');
            for ($i = ($firstkey + 1); $i < count($matrix); $i++) {
                $table .= html_writer::start_tag('tr');
                foreach ($matrix[$i] as $col) {
                    $table .= html_writer::tag('td', $col);
                }
                $table .= html_writer::end_tag('tr');
            }
        }
        $table .= html_writer::end_tag('tbody') . html_writer::end_tag('table');

        $doc = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT);

        $doc->setPrintHeader(true);
        $doc->setPrintFooter(true);

        // Set header and footer fonts.
        $doc->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $doc->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

        // Set default monospaced font.
        $doc->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Set margins.
        $doc->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $doc->SetHeaderMargin(PDF_MARGIN_HEADER);
        $doc->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Set auto page breaks.
        $doc->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // Set auto page breaks.
        $doc->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // Set image scale factor.
        $doc->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Add a page.
        $doc->AddPage();

        // Set JPEG quality.
        $doc->setJPEGQuality(75);

        $head = get_config('block_learnerscript', 'analytics_color');
        $header = $this->pdf_reportheader();

        $filename = $reportname->name;
        $doc->writeHTMLCell($w = 0, $h = 10, $x = '230', $y = '30', $header, $border = 0,
         $ln = 1, $fill = 0, $reseth = true, $align = '', $autopadding = true);
        $doc->writeHTMLCell($w = 100, $h = 10, $x = '120', $y = '20',
        html_writer::tag('h1', html_writer::tag('b', $reportname->name)), $border = 0,
        $ln = 1, $fill = 0, $reseth = true, $align = '', $autopadding = true);
        $doc->writeHTMLCell($w = 100, $h = 10, $x = '10', $y = '25',
        html_writer::tag('h4', get_string('filters', 'block_learnerscript')), $border = 0, $ln = 1, $fill = 0, $reseth =
        true, $align = '', $autopadding = true);
        $doc->writeHTMLCell($w = 100, $h = 10, $x = '10', $y = '30',
        $finalfilterdata, $border = 0, $ln = 1, $fill = 0, $reseth = true, $align = '',
         $autopadding = true);
        if (!empty($reportclass->selectedfilters) && count($reportclass->selectedfilters) <= 4) {
            $doc->writeHTMLCell($w = 0, $h = 0, $x = '10', $y = '70', $table, $border = 0,
            $ln = 1, $fill = 0, $reseth = true, $align = '', $autopadding = true);
        } else {
            $doc->writeHTMLCell($w = 0, $h = 0, $x = '10', $y = '70', $table, $border = 0,
            $ln = 1, $fill = 0, $reseth = true, $align = '', $autopadding = true);
        }
        $doc->Output($filename, 'I');
    }

    /**
     * PDF Report Export Header
     * @return string Report Header
     */
    private function pdf_reportheader() {
        $headerimagepath = block_learnerscript_get_reportheader_imagepath();
        $headerimgpath = "";
        if (isset($headerimagepath) && !empty($headerimagepath) && @getimagesize($headerimagepath)) {
            $headerimgpath = $headerimagepath;
        }
        if ($headerimgpath) {
            $reportlogoimage = html_writer::empty_tag('img', [
                'src' => $headerimgpath,
                'alt' => get_string("altreportimage", "block_learnerscript"),
                'height' => '80px'
            ]);
        } else {
            $reportlogoimage = "";
        }
        return $reportlogoimage;
    }

    /**
     * PDF report export attachment
     * @param object $reportclass Report data
     * @param string $fname File name
     */
    public function export_pdf_attachment($reportclass, $fname = '') {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        $report = $reportclass->finalreport;
        $table = $report->table;
        $matrix = [];
        $fname == '' ? $filename = 'report' : $filename = $fname . '.pdf';

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
        $table = "";

        $table .= html_writer::start_tag('table', ['border' => '1', 'cellpadding' => '5']) .
                    html_writer::start_tag('thead') . html_writer::start_tag('tr');
        reset($matrix);
        $fkey = key($matrix);
        for ($i = $fkey; $i < ($fkey + 1); $i++) {
            foreach ($matrix[$i] as $col) {
                $table .= html_writer::start_tag('td') . html_writer::tag('b', $col) . html_writer::end_tag('td');
            }
        }
        $table .= html_writer::end_tag('tr') . html_writer::end_tag('thead') . html_writer::start_tag('tbody');
        for ($i = ($fkey + 1); $i < count($matrix); $i++) {
            $table .= html_writer::start_tag('tr');
            foreach ($matrix[$i] as $col) {
                $table .= html_writer::tag('td', $col);
            }
            $table .= html_writer::end_tag('tr');
        }
        $table .= html_writer::end_tag('tbody') . html_writer::end_tag('table');
        $table .= "";
        $doc = new \pdf;
        $doc->setPrintHeader(false);
        $doc->setPrintFooter(false);
        $doc->AddPage();

        $doc->writeHTML($table);

        if ($fname == '') {
            $doc->Output();
            exit;
        } else {
            $doc->Output($filename, 'F');
        }
    }
}
