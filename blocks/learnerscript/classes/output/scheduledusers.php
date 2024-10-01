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
class scheduledusers implements renderable, templatable {
    /**
     * @var $reportid
     */
    public $reportid;
    /**
     * @var $reqimage
     */
    public $reqimage;
    /**
     * @var $roleslist
     */
    public $roleslist;
    /**
     * @var $selectedusers
     */
    public $selectedusers;
    /**
     * @var $scheduleid
     */
    public $scheduleid;
    /**
     * @var $reportinstance
     */
    public $reportinstance;
    /**
     * Construct
     * @param  int $reportid       Report id
     * @param  string $reqimage     Required image
     * @param  array $roleslist     Roles list
     * @param  string $selectedusers Selected users
     * @param  int $scheduleid     Schedule report id
     * @param  int $reportinstance Schedule report instance
     */
    public function __construct($reportid, $reqimage, $roleslist, $selectedusers, $scheduleid, $reportinstance) {
        $this->reportid = $reportid;
        $this->reqimage = $reqimage;
        $this->roleslist = $roleslist;
        $this->selectedusers = $selectedusers;
        $this->scheduleid = $scheduleid;
        $this->reportinstance = $reportinstance;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     * @param  renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->reportid = $this->reportid;
        $data->reqimage = $this->reqimage;
        $data->roleslist = $this->roleslist;
        $data->selectedusers = $this->selectedusers;
        $data->scheduleid = $this->scheduleid;
        $data->reportinstance = $this->reportinstance;
        return $data;
    }
}
