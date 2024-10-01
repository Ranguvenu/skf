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

use block_learnerscript\local\ls;

/**
 * Learnerscript, a Moodle block to create customizable reports.
 *
 * @package    block_learnerscript
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_learnerscript extends block_list {
    /**
     * @var int $rolecontextlevel User rolecontextlevel
     */
    public $rolecontextlevel;

    /**
     * Sets the block name and version number
     *
     * @return void
     * */
    public function init() {
        $this->title = get_string('pluginname', 'block_learnerscript');
    }

    /**
     * Sets the block configuration
     *
     * @return void
     * */
    public function specialization() {
        if (empty($this->config->title)) {
            $this->title = get_string('pluginname', 'block_learnerscript');
        } else {
            $this->title = $this->config->title;
        }
    }

    /**
     * Block allows each instance to be configured
     *
     * @return bool
     * */
    public function instance_allow_config() {
        return true;
    }

    /**
     * Where to add the block
     *
     * @return array
     * */
    public function applicable_formats() {
        return ['site' => true, 'course' => true, 'my' => true];
    }

    /**
     * Global Config?
     *
     * @return bool
     * */
    public function has_config() {
        return true;
    }

    /**
     * More than one instance per page?
     *
     * @return bool
     * */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Gets the contents of the block (course view)
     *
     * @return object An object with the contents
     * */
    public function get_content() {
        global $DB, $USER, $CFG, $COURSE;
        if ($this->content !== null) {
            return $this->content;
        }
        $roleshortname = 0;
        $rolecontextlevel = 0;
        $this->content = new stdClass;
        $this->content->footer = '';
        $this->content->icons = [];

        if (!isloggedin()) {
            return $this->content;
        }

        $course = $DB->get_record('course', ['id' => $COURSE->id]);

        if (!$course) {
            throw new moodle_exception(get_string('nocourseexist', 'block_learnerscript'));
        }

        $reportdashboardblockexists = $this->page->blocks->is_known_block_type('reportdashboard', false);

        $enrolledcourses = enrol_get_users_courses($USER->id, true, ['id', 'shortname']);
        if ($course->id == SITEID && (array_key_last($enrolledcourses) == SITEID || empty($enrolledcourses))) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance(array_key_last($enrolledcourses));
        }

        if (!is_siteadmin()) {
            $currentuserroleid = array_key_first($USER->access['ra'][$context->path]);
            $getcontextlevels = get_role_contextlevels($currentuserroleid);
            $currentcontextlevel = reset($getcontextlevels);
            if ($currentcontextlevel == CONTEXT_SYSTEM || $currentcontextlevel == CONTEXT_MODULE) {
                $rolecontextlevel = 0;
            } else {
                $rolecontextlevel = $currentcontextlevel;
            }
            $roleshortname = $DB->get_field('role', 'shortname', ['id' => $currentuserroleid]);
        }
        if ($reportdashboardblockexists) {
            if (!is_siteadmin()) {
                if (has_capability('block/learnerscript:learnerreportaccess', $context)) {
                    $this->content->items[] = html_writer::link(new moodle_url(
                    '/blocks/reportdashboard/profilepage.php',
                    ['filter_users' => $USER->id, 'role' => $roleshortname, 'contextlevel' => $rolecontextlevel]),
                    get_string('pluginname', 'block_learnerscript'), ['class' => 'ls-block_reportdashboard']);
                } else {
                    $this->content->items[] = html_writer::link(new moodle_url(
                    '/blocks/reportdashboard/dashboard.php',
                    ['role' => $roleshortname, 'contextlevel' => $rolecontextlevel]),
                    get_string('pluginname', 'block_learnerscript'), ['class' => 'ls-block_reportdashboard']);
                }

            } else {
                $this->content->items[] = html_writer::link(new moodle_url(
                '/blocks/reportdashboard/dashboard.php', []), get_string('pluginname', 'block_learnerscript'),
                ['class' => 'ls-block_reportdashboard']);
            }
        }
        // Site (Shared) reports.
        if (!empty($this->config->displayglobalreports)) {
            $reports = $DB->get_records('block_learnerscript', ['global' => 1], 'name ASC');

            if ($reports) {
                foreach ($reports as $report) {
                    if ($report->visible && (new ls)->cr_check_report_permissions($report,
                                                    $USER->id, $context)) {
                        $rname = format_string($report->name);

                        $this->content->items[] =
                        html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                        ['id' => $report->id, 'courseid' => $course->id, "alt" => $rname]), $rname,
                        ['class' => 'ls-block_reportlist_reportname']);
                    }
                }
            }
        }

        $reports = $DB->get_records('block_learnerscript', ['courseid' => $course->id], 'name ASC');

        if ($reports) {
            foreach ($reports as $report) {
                if (!$report->global && $report->visible && (new ls)->cr_check_report_permissions($report, $USER->id, $context)) {
                    $rname = format_string($report->name);
                    $this->content->items[] =
                    html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                    ['id' => $report->id, 'courseid' => $course->id, "alt" => $rname]), $rname,
                    ['class' => 'ls-block_reportlist_reportname']);;
                }
            }
        }

        if (has_capability('block/learnerscript:managereports', $context) ||
            has_capability('block/learnerscript:manageownreports', $context)) {
            if (is_siteadmin()) {
                $this->content->items[] =
                html_writer::link(new moodle_url('/blocks/learnerscript/managereport.php',
                        []), get_string('managereports', 'block_learnerscript'),
                        ['class' => 'ls-block_managereports']);
            } else {
                $this->content->items[] = html_writer::link(new moodle_url('/blocks/learnerscript/managereport.php',
                ['role' => $roleshortname, 'contextlevel' => $rolecontextlevel]),
                get_string('managereports', 'block_learnerscript'), ['class' => 'ls-block_managereports']);
            }
        }

        if (!has_capability('block/learnerscript:managereports', $context) ||
            !has_capability('block/learnerscript:manageownreports', $context)) {
            $this->content->items[] = html_writer::link(new moodle_url('/blocks/learnerscript/reports.php',
            ['role' => $roleshortname, 'contextlevel' => $rolecontextlevel]),
            get_string('managereports', 'block_learnerscript'), ['class' => 'ls-block_managereports']);
        }
        if (is_siteadmin()) {
            $this->content->items[] = html_writer::link(new moodle_url('/blocks/learnerscript/lsconfig.php?reset=1',
            []),
            get_string('lsresetconfig', 'block_learnerscript'), ['class' => 'ls-block_resetconfig']);
        }

        return $this->content;
    }
}
