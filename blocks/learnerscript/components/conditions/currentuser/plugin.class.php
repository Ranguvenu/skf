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
namespace block_learnerscript\lsreports;
use block_learnerscript\local\pluginbase;
/**
 * Current user plugin
 */
class plugin_currentuser extends pluginbase {

    /** @var bool $allowedops  */
    public $allowedops;
    /**
     * Current user plugin init function
     */
    public function init() {
        $this->fullname = get_string('currentuser', 'block_learnerscript');
        $this->reporttypes = ['users'];
        $this->form = false;
        $this->allowedops = false;
    }
    /**
     * Current user plugin summary
     * @param object $data Activity fields column name
     * @return string
     */
    public function summary($data) {
        return get_string('currentuser_summary', 'block_learnerscript');
    }
    /**
     * This function executes the columns data
     * @param object $user user data
     * @return array
     */
    public function execute($user) {
        return [$user->id];
    }

}
