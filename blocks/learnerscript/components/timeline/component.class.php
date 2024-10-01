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
 * A Moodle block to create customizable reports.
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_learnerscript\local\componentbase;
use block_learnerscript\local\ls;
/**
 * Component timeline
 */
class component_timeline extends componentbase {
    /**
     * Component timeline init function
     */
    public function init() {
        $this->plugins = false;
        $this->ordering = false;
        $this->form = true;
        $this->help = true;
    }
    /**
     * Component timeline form data process
     * @param object $cform Form data
     */
    public function form_process_data(&$cform) {
        global $DB;
        if ($this->form) {
            $data = $cform->get_data();
            $components = (new ls)->cr_unserialize($this->config->components);
            $components['timeline']['config'] = $data;
            $this->config->components = (new ls)->cr_serialize($components);
            $DB->update_record('block_learnerscript', $this->config);
        }
    }
    /**
     * Component timeline form data
     * @param object $cform Form data
     */
    public function form_set_data(&$cform) {
        if ($this->form) {
            $fdata = new stdclass;
            $components = (new ls)->cr_unserialize($this->config->components);
            $compconfig = (isset($components['timeline']['config'])) ? $components['timeline']['config'] : new stdclass;
            $cform->set_data($compconfig);
        }
    }

}
