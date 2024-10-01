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
use block_learnerscript\local\ls;
use block_learnerscript\local\querylib;
/**
 * Forum field columns
 */
class plugin_forum extends pluginbase {
    /**
     * Forum field columns init function
     */
    public function init() {
        $this->fullname = get_string('forum', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['forum'];
    }
    /**
     * Course views column summary
     * @param object $data Course views column name
     * @return string
     */
    public function summary($data) {
        return format_string($data->columname);
    }
    /**
     * This function return field column format
     * @param object $data Field data
     * @return array
     */
    public function colformat($data) {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return [$align, $size, $wrap];
    }
    /**
     * This function executes the columns data
     * @param object $data Columns data
     * @param object $row Row data
     * @return object
     */
    public function execute($data, $row) {
        global $DB;
        switch ($data->column) {
            case 'discussionscount':
                if (!isset($row->discussionscount)) {
                    $discussionscount = $DB->get_field_sql($data->subquery);
                } else {
                    $discussionscount = $row->{$data->column};
                }
                $row->{$data->column} = !empty($discussionscount) ? $discussionscount : '--';
                break;
            case 'posts':
                if (!isset($row->posts)) {
                    $posts = $DB->get_field_sql($data->subquery);
                } else {
                    $posts = $row->{$data->column};
                }
                $row->{$data->column} = !empty($posts) ? $posts : '--';
                break;
            case 'replies':
                if (!isset($row->replies)) {
                    $replies = $DB->get_field_sql($data->subquery);
                } else {
                    $replies = $row->{$data->column};
                }
                $row->{$data->column} = !empty($replies) ? $replies : '--';
                break;
            case 'wordscount':
                if (!isset($row->wordscount)) {
                    $wordscount = $DB->get_field_sql($data->subquery);
                } else {
                    $wordscount = $row->{$data->column};
                }
                $row->{$data->column} = !empty($wordscount) ? $wordscount : '--';
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : ' ';
    }
}
