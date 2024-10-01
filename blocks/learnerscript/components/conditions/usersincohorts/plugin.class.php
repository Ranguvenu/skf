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
 * Cohort users plugin
 */
class plugin_usersincohorts extends pluginbase {

    /** @var bool $allowedops  */
    public $allowedops;
    /**
     * Cohort users plugin init function
     */
    public function init() {
        $this->fullname = get_string('usersincohorts', 'block_learnerscript');
        $this->reporttypes = [];
        $this->form = true;
        $this->allowedops = false;
    }
    /**
     * Cohort users plugin summary
     * @param object $data Cohort users data
     * @return string
     */
    public function summary($data) {
        return get_string('usersincohorts_summary', 'block_learnerscript');
    }
    /**
     * This function executes the columns data
     * @param object $data cohort user data
     * @return array
     */
    public function execute($data) {
        global $DB;

        if ($data->cohorts) {
            list($insql, $params) = $DB->get_in_or_equal($data->cohorts);

            $sql = "SELECT u.id
            FROM {user} u JOIN {cohort_members} c ON c.userid = u.id
            WHERE c.cohortid $insql ";

            return array_keys($DB->get_records_sql($sql, $params));
        }

        return [];
    }
    /**
     * Cohort user columns
     * @return array
     */
    public function columns() {
        global $DB;

        $cohorts = $DB->get_records('cohort');
        $usercohorts = [];
        foreach ($cohorts as $c) {
            $usercohorts[$c->id] = format_string($c->name);
        }
        return $usercohorts;
    }

}
