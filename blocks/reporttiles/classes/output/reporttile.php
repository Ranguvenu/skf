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
 * Report Tiles for dashboard block instances.
 * @package  block_reporttiles
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_reporttiles\output;
use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Reporttiles
 */
class reporttile implements renderable, templatable {
    /**
     * @var $data
     */
    public $data;
    /**
     * Construct function
     * @param object $data Reporttiles data
     */
    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     * @param renderer_base $output Data to display
     * @return stdClass $data
     */
    public function export_for_template(renderer_base $output) {
        $data = $this->data;
        return $data;
    }
}
