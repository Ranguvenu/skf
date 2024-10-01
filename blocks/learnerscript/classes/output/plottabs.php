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
class plottabs implements renderable, templatable {
    /**
     * @var $plottabs
     */
    public $plottabs;
    /**
     * @var $reportid
     */
    public $reportid;
    /**
     * @var $params
     */
    public $params;
    /**
     * @var $enableplots
     */
    public $enableplots;
    /**
     * @var $filterform
     */
    public $filterform;
    /**
     * Construct
     * @param array $plottabs    Graph plot tabs
     * @param int $reportid    Report ID
     * @param string $params    Report params
     * @param int $enableplots Report plot enabled
     * @param object $filterform  Report filter form object
     */
    public function __construct($plottabs, $reportid, $params, $enableplots, $filterform = false) {
        $this->plottabs = $plottabs;
        $this->reportid = $reportid;
        $this->params = $params;
        $this->enableplots = $enableplots;
        $this->filterform = $filterform;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     * @param  renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $OUTPUT;
        $data = new stdClass();
        $data->multiplot = true;
        $data->issiteadmin = is_siteadmin();
        $data->plottabs = $this->plottabs;
        $data->editicon = $OUTPUT->image_url('/t/edit');
        $data->deleteicon = $OUTPUT->image_url('/t/delete');
        $data->loading = $OUTPUT->image_url('loading', 'block_learnerscript');
        $data->reportid = $this->reportid;
        $data->params = $this->params;
        $data->enableplots = $this->enableplots;
        $data->filterform = $this->filterform;
        return $data;
    }
}
