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
use block_learnerscript\local\ls;
/**
 * Enrole users
 */
class plugin_usersincurrentcourse extends pluginbase {

    /** @var bool $allowedops  */
    public $allowedops;
    /**
     * Enrole users plugin init function
     */
    public function init() {
        $this->fullname = get_string('usersincurrentcourse', 'block_learnerscript');
        $this->reporttypes = [];
        $this->form = true;
        $this->allowedops = false;
    }
    /**
     * Enrole users plugin summary
     * @param object $data Cohort users data
     * @return array
     */
    public function summary($data) {
        return get_string('usersincurrentcourse_summary', 'block_learnerscript');
    }
    /**
     * This function executes the columns data
     * @param object $data enrole user data
     * @param int $courseid Course id
     * @return array
     */
    public function execute($data, $courseid) {
        global $DB;
        $context = (new ls)->cr_get_context(CONTEXT_COURSE, $courseid);
        if ($users = get_role_users($data->field, $context, false, 'u.id', 'u.id')) {
            return array_keys($users);
        }

        return [];
    }
    /**
     * Enrole users columns
     * @return array
     */
    public function columns() {
        global $DB;

        $roles = $DB->get_records('role');
        $userroles = [];
        foreach ($roles as $r) {
            $userroles[$r->id] = $r->shortname;
        }
        return $userroles;
    }

}
