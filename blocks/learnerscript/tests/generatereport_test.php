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

namespace block_learnerscript;

use block_learnerscript\local\ls;

/**
 * PHPUnit data generator testcase
 *
 * @package    block_learnerscript
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generatereport_test extends \advanced_testcase {
    /**
     * Test add report
     *
     */
    public function test_addreport() {
        global $DB, $USER;
        $this->resetAfterTest(true);

        // Turn off debugging.
        set_debugging(DEBUG_NONE);

        // Start event capturing.
        $eventsink = $this->redirectEvents();

        // Default no records.
        $this->assertEquals(0, $DB->count_records('block_learnerscript'));

        // Create a report with dummy data.
        $report1 = new \stdClass();
        $report1->name = get_string('report_one', 'block_learnerscript');
        $report1->type = 'courses';
        $report1->courseid = SITEID;
        $report1->ownerid = $USER->id;
        $recordid1 = (new ls)->add_report($report1, \context_system::instance());
        // Check if the records created.
        $this->assertTrue($DB->record_exists('block_learnerscript', ['id' => $recordid1]));

        $report2 = new \stdClass();
        $report2->name = get_string('report_sql', 'block_learnerscript');
        $report2->type = 'sql';
        $report2->courseid = SITEID;
        $report2->ownerid = $USER->id;
        $recordid2 = (new ls)->add_report($report2, \context_system::instance());
        // Check if the records created.
        $this->assertTrue($DB->record_exists('block_learnerscript', ['id' => $recordid2]));

        // Created 2 reports.
        $this->assertEquals(2, $DB->count_records('block_learnerscript'));

        // Stop event capturing and discard the events.
        $eventsink->close();
    }

    /**
     * Test update report
     *
     */
    public function test_updatereport() {
        global $DB, $USER;
        $this->resetAfterTest(true);

        // Turn off debugging.
        set_debugging(DEBUG_NONE);

        // Start event capturing.
        $eventsink = $this->redirectEvents();

        // Default no records.
        $this->assertEquals(0, $DB->count_records('block_learnerscript'));

        $context = \context_system::instance();
        // Create a report with dummy data.
        $report = new \stdClass();
        $report->name = 'Report 1';
        $report->type = 'courses';
        $report->courseid = SITEID;
        $report->ownerid = $USER->id;
        $report->id = (new ls)->add_report($report, $context);

        // Check if record creared with Report 1.
        $this->assertTrue($DB->record_exists('block_learnerscript', ['id' => $report->id, 'name' => 'Report 1']));

        // Update the report name.
        $report->name = get_string('report_two', 'block_learnerscript');
        $report->global = 1;
        (new ls)->update_report($report, $context);

        // Check if the record updated.
        $this->assertTrue($DB->record_exists('block_learnerscript', ['id' => $report->id, 'name' => 'Report 2']));

        // Obviousely count will be increased.
        $this->assertEquals(1, $DB->count_records('block_learnerscript'));

        // Stop event capturing and discard the events.
        $eventsink->close();
    }

    /**
     * Test delete report
     *
     */
    public function test_deletereport() {
        global $DB, $USER;
        $this->resetAfterTest(true);

        // Turn off debugging.
        set_debugging(DEBUG_NONE);

        // Start event capturing.
        $eventsink = $this->redirectEvents();

        // Default no records.
        $this->assertEquals(0, $DB->count_records('block_learnerscript'));

        // System context level.
        $context = \context_system::instance();

        // Create a report with dummy data.
        $report = new \stdClass();
        $report->name = get_string('report_one', 'block_learnerscript');
        $report->type = 'courses';
        $report->courseid = SITEID;
        $report->ownerid = $USER->id;
        $this->assertEquals(1, $DB->count_records('block_learnerscript'));

        (new ls)->delete_report($report, $context);
        $this->assertEquals(0, $DB->count_records('block_learnerscript'));

        // Stop event capturing and discard the events.
        $eventsink->close();
    }
}
