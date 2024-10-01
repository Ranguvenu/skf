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
 * Event class to schedule report
 *
 * @package    block_learnerscript
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule_report extends \core\event\base {
    /**
     * Sets the report schedule event
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'block_ls_schedule';
    }
    /**
     * Gets the scheduled report name
     * @return string
     */
    public static function get_name() {
        return get_string('eventschedule_report', 'block_learnerscript');
    }
    /**
     * Gets the scheduled report description
     * @return string
     */
    public function get_description() {
        return "The user with id {$this->userid} Scheduled report with id {$this->objectid}.";
    }
    /**
     * Gets the scheduled report URL
     * @return string
     */
    public function get_url() {
        return new \moodle_url('/blocks/learnerscript/components/scheduler/schedule.php',
            ['id' => $this->objectid]);
    }

}
