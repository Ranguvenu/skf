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
class reporttable implements renderable, templatable {

    /**
     * @var $reportclass
     */
    public $reportclass;
    /**
     * @var array $tableproperties
     */
    public $tableproperties;
    /**
     * @var $tablehead
     */
    public $tablehead;
    /**
     * @var $tableid
     */
    public $tableid;
    /**
     * @var $exports
     */
    public $exports;
    /**
     * @var $reportid
     */
    public $reportid;
    /**
     * @var $reportsql
     */
    public $reportsql;
    /**
     * @var $debugsql
     */
    public $debugsql;
    /**
     * @var $includeexport
     */
    public $includeexport;
    /**
     * @var $instanceid
     */
    public $instanceid;
    /**
     * @var $reporttype
     */
    public $reporttype;

    /**
     * Construct
     * @param  array $reportclass Report data
     * @param  array $tabledetails  Report table details
     * @param  int $tableid       Report table id
     * @param  array $exports       Export options
     * @param  int $reportid      Report id
     * @param  string $reportsql     Report sql query
     * @param  string $reporttype    Type of the report
     * @param  string $debugsql      Debug SQL query
     * @param  boolean $includeexport Export include option
     * @param  int $instanceid    Report instance id
     */
    public function __construct($reportclass, $tabledetails, $tableid, $exports, $reportid,
    $reportsql, $reporttype, $debugsql = false, $includeexport = false, $instanceid = null) {
        isset($tabledetails['tablehead']) ? $this->tablehead = $tabledetails['tablehead'] : null;
        $this->reportclass = $reportclass;
        $this->tableproperties = $tabledetails['tableproperties'];
        $this->tableid = $tableid;
        $this->exports = $exports;
        $this->reportid = $reportid;
        $this->reportsql = $reportsql;
        $this->debugsql = $debugsql;
        $this->includeexport = $includeexport;
        $this->instanceid = $instanceid;
        $this->reporttype = $reporttype;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     * @param  renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $OUTPUT;
        $data = new stdClass();
        $data->reportclass = $this->reportclass;
        $data->tablehead = $this->tablehead;
        $data->tableid = $this->tableid;
        $data->loading = $OUTPUT->image_url('loading', 'block_learnerscript');
        $data->exports = $this->exports;
        $data->reportid = $this->reportid;
        $data->reportsql = $this->reportsql;
        $data->debugsql = $this->debugsql;
        $data->includeexport = $this->includeexport;
        $data->download_icon = $OUTPUT->image_url('download_icon', 'block_learnerscript');
        $data->reportinstance = $this->instanceid ? $this->instanceid : $this->reportid;
        $data->reporttype = $this->reporttype;
        $exportparams = '';
        if (!empty($this->reportclass->basicparams)) {
            $exportfilters = array_merge($this->reportclass->params, $this->reportclass->basicparams);
        } else {
            $exportfilters = $this->reportclass->params;
        }
        if (!empty($exportfilters)) {
            foreach ($exportfilters as $key => $val) {
                if (strpos($key, 'date') !== false) {
                    $exportparams .= "&$key=$val";
                }
            }
        }
        $data->exportparams = $exportparams;
        $arraydata = (array)$data + $this->tableproperties;
        return $arraydata;
    }
}
