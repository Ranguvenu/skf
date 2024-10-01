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
 * Course category plugin
 */
class plugin_coursecategory extends pluginbase {

    /** @var bool $allowedops  */
    public $allowedops;
    /**
     * Course category plugin init function
     */
    public function init() {
        $this->fullname = get_string('coursecategory', 'block_learnerscript');
        $this->type = 'text';
        $this->form = true;
        $this->allowedops = false;
        $this->reporttypes = ['courses'];
    }
    /**
     * Course category plugin summary
     * @param object $data Activity fields column name
     * @return string
     */
    public function summary($data) {
        global $DB;

        $cat = $DB->get_record('course_categories', ['id' => $data->field]);
        if ($cat) {
            return get_string('category') . ' ' . $cat->name;
        } else {
            return get_string('category') . ' ' . get_string('top');
        }
    }
    /**
     * This function executes the columns data
     * @param object $data Columns data
     * @return array
     */
    public function execute($data) {
        global $DB;
        $courses = $DB->get_records('course', ['category' => $data->field]);
        if ($courses) {
            return array_keys($courses);
        }
        return [];
    }
    /**
     * Course category columns
     * @return array
     */
    public function columns() {
        $options = [get_string('top')];
        $parents = [];
        (new ls)->cr_make_categories_list($options, $parents);
        return $options;
    }

}
