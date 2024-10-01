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
 * Report Tiles Renderer
 *
 * @package    block_reporttiles
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_reporttiles_renderer extends plugin_renderer_base {
    /**
     * This function displays the reporttiles/statistic reports data
     * @param object $page This page
     * @return string|bool
     */
    public function render_reporttile($page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('block_reporttiles/reporttile', $data);
    }
}
