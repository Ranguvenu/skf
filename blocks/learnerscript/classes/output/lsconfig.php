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

namespace block_learnerscript\output;

use moodle_url;
use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * A Moodle block to create customizable reports.
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lsconfig implements renderable, templatable {
    /**
     * @var $status
     */
    public $status;
    /**
     * @var $importstatus
     */
    public $importstatus;
    /**
     * Construct
     * @param int $status       Status
     * @param int $importstatus Reports import status
     */
    public function __construct($status, $importstatus) {
        $this->status = $status;
        $this->importstatus = $importstatus;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     * @param  renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $PAGE, $CFG;
        $data = new stdClass();
        $lsconfigslideshowimages = $this->lsconfigslideshowimages();
        $data->slideshowimages = $lsconfigslideshowimages['slideshowimages'];
        $data->slideshowimagespath = $lsconfigslideshowimages['slideshowimagespath'];
        $data->importstatus = $this->status == 'import' ? true : false;

        $reportdashboardblockexists = $PAGE->blocks->is_known_block_type('reportdashboard', false);
        if ($reportdashboardblockexists) {
            $redirecturl = new moodle_url('/blocks/reportdashboard/dashboard.php');
        } else {
            $redirecturl = new moodle_url('/blocks/learnerscript/managereport.php');
        }

        $data->redirecturl = $redirecturl;
        $data->importstatus = $this->importstatus;
        $data->lsreportconfigstatus = get_config('block_learnerscript', 'lsreportconfigstatus');
        $data->configurestatus = (($this->status == 'import' && !$data->lsreportconfigstatus)
                                    || $this->status == 'reset') ? true : false;
        return $data;
    }

    /**
     * Learnerscript configuration slide images
     * @return array
     */
    public function lsconfigslideshowimages() {
        global $CFG;
        $slideshowimagespath = '/blocks/learnerscript/images/slideshow/';
        $slideshowimages = [];
        if (is_dir($CFG->dirroot . $slideshowimagespath)) {
            $slideshowimages = scandir($CFG->dirroot . $slideshowimagespath,
                                        SCANDIR_SORT_ASCENDING);
        }
        $slideshowimagelist = [];
        if (!empty($slideshowimages)) {
            foreach ($slideshowimages as $slideshowimage) {
                if ($slideshowimage == '.' || $slideshowimage == '..') {
                    echo '';
                } else {
                    $slideshowimagelist[] = $CFG->wwwroot . $slideshowimagespath . $slideshowimage;
                }
            }
        }
        $slideshowimagesdata = [];
        $slideshowimagesdata['slideshowimagespath'] = $slideshowimagespath;
        $slideshowimagesdata['slideshowimages'] = $slideshowimagelist;
        return $slideshowimagesdata;
    }
}
