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
class filtertoggleform implements renderable, templatable {
    /**
     * @var $filterform
     */
    public $filterform;
    /**
     * @var $plottabscontent
     */
    public $plottabscontent;

    /**
     * Construct
     * @param  boolean $filterform     Filter toggle form
     * @param  string $plottabscontent Plot tab content
     */
    public function __construct($filterform = false, $plottabscontent = false) {
        $this->filterform = $filterform;
        $this->plottabscontent = $plottabscontent;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     * @param  renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->filterform = $this->filterform;
        $data->plottabscontent = $this->plottabscontent;
        return $data;
    }
}
