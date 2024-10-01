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

namespace block_learnerscript\event;

/**
 * Event class to create report
 *
 * @package    block_learnerscript
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_report extends \core\event\base {

    /** @var string $type */
    public $type;
    /**
     * Sets the report create event
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'block_learnerscript';
    }

    /**
     * Get the report name
     */
    public static function get_name() {
        return get_string('eventcreate_report', 'block_learnerscript');
    }

    /**
     * Get the report description
     */
    public function get_description() {
        return "The user with id {$this->userid} created New {$this->type} report with id {$this->objectid}.";
    }
    /**
     * Get the report URL
     */
    public function get_url() {
        return new \moodle_url('/blocks/learnerscript/editreport.php', ['id' => $this->objectid]);
    }

}
