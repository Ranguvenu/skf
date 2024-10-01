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
 * Course field plugin
 */
class plugin_ccoursefield extends pluginbase {

    /** @var bool $allowedops  */
    public $allowedops;
    /**
     * Course field plugin init function
     */
    public function init() {
        $this->fullname = get_string('ccoursefield', 'block_learnerscript');
        $this->reporttypes = ['courses'];
        $this->form = true;
        $this->allowedops = true;
    }
    /**
     * Course field plugin summary
     * @param object $data Activity fields column name
     */
    public function summary($data) {
        return get_string($data->field, 'block_learnerscript') . ' ' . $data->operator . ' ' . $data->value;
    }
    /**
     * This function executes the columns data
     * @param object $data Columns data
     * @return object
     */
    public function execute($data) {
        global $DB;
        $ilike = " LIKE ";

        switch ($data->operator) {
            case 'LIKE % %': $sql = "$data->field $ilike ?";
                $params = ["%$data->value%"];
                break;
            default: $sql = "$data->field $data->operator ?";
                $params = [$data->value];
        }

        $courses = $DB->get_records_select('course', $sql, $params);

        if ($courses) {
            return array_keys($courses);
        }
        return [];
    }
    /**
     * Course field columns
     * @return array
     */
    public function columns() {
        global $DB;

        $columns = $DB->get_columns('course');

        $coursecolumns = [];
        foreach ($columns as $c) {
            $coursecolumns[$c->name] = $c->name;
        }
        return $coursecolumns;
    }

}
