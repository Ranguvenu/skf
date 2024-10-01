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
 * User field
 */
class plugin_puserfield extends pluginbase {
    /**
     * User field init function
     */
    public function init() {
        $this->form = true;
        $this->unique = true;
        $this->fullname = get_string('puserfield', 'block_learnerscript');
        $this->reporttypes = ['courses', 'sql', 'users', 'timeline', 'categories'];
    }
    /**
     * Summary
     * @param  object $data Columns data
     * @return string
     */
    public function summary($data) {
        global $DB;
        if (strpos($data->field, 'profile_') === 0) {
            $name = $DB->get_field('user_info_field', 'name', ['shortname' => str_replace('profile_', '', $data->field)]);
            return $name . ' = ' . $data->value;
        }
        return $data->field . ' = ' . $data->value;
    }
    /**
     * Execute
     * @param  int $userid  User id
     * @param  object $context User context
     * @param  object $data    Report columns data
     * @return bool
     */
    public function execute($userid, $context, $data) {
        global $DB, $CFG;
        if (!$user = $DB->get_record('user', ['id' => $userid])) {
            return false;
        }
        if (strpos($data->field, 'profile_') === 0) {
            if ($profiledata = $DB->get_records_sql("SELECT d.*, f.shortname, f.datatype
            FROM {user_info_data} d ,{user_info_field} f
			WHERE f.id = d.fieldid AND d.userid = ?", [$userid])) {
                foreach ($profiledata as $p) {
                    $user->{'profile_' . $p->shortname} = $p->data;
                }
            }
        }
        if (isset($user->{$data->field}) && $user->{$data->field} == $data->value) {
            return true;
        }

        return false;
    }

}
