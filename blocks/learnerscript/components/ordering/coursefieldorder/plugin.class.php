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
 * A Moodle block for creating customizable reports
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\pluginbase;
/**
 * Course field order
 */
class plugin_coursefieldorder extends pluginbase {
    /** @var bool $sql  */
    public $sql = true;
    /**
     * Course field order init function
     */
    public function init() {
        $this->fullname = get_string('coursefield', 'block_learnerscript');
        $this->form = true;
        $this->unique = true;
        $this->reporttypes = ['courses', 'activitystatus'];
        $this->sql = true;
    }
    /**
     * Summary
     * @param  object $data
     * @return string
     */
    public function summary($data) {
        return get_string($data->column) . ' ' . (strtoupper($data->direction));
    }
    /**
     * Execute
     * @param  object $data Filter data
     * @return string
     */
    public function execute($data) {
        global $DB;
        if (isset($data->direction)) {
            if ($data->direction == 'asc' || $data->direction == 'desc') {
                $direction = strtoupper($data->direction);
                $columns = $DB->get_columns('course');

                $coursecolumns = [];
                foreach ($columns as $c) {
                    $coursecolumns[$c->name] = $c->name;
                }
                if (isset($coursecolumns[$data->column])) {
                    return 'main.' . $data->column . ' ' . $direction;
                }
            }
        }

        return '';
    }
    /**
     * Course field columns
     * @return array
     */
    public function columns() {
        global $DB;
        return $DB->get_columns('course');
    }
}
