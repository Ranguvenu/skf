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
use context_system;

/**
 * Course categories
 */
class plugin_coursecategories extends pluginbase {

    /** @var mixed $filtertype  */
    public $filtertype;

    /** @var mixed $singleselection  */
    public $singleselection;

    /**
     * Course categories filter init function
     *
     */
    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('filtercoursecategories', 'block_learnerscript');
        $this->reporttypes = ['courses'];
        $this->filtertype = 'custom';
        if (!empty($this->reportclass->basicparams)) {
            foreach ($this->reportclass->basicparams as $basicparam) {
                if ($basicparam['name'] == 'coursecategories') {
                    $this->filtertype = 'basic';
                }
            }
        }
    }

    /**
     * Summary
     * @param  object $data
     * @return string
     */
    public function summary($data) {
        return get_string('filtercoursecategories_summary', 'block_learnerscript');
    }

    /**
     * Execute
     * @param  array $finalelements Final elements
     * @param  object $data          Filter data
     * @return string
     */
    public function execute($finalelements, $data) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/course/lib.php");
        $category = optional_param('filter_coursecategories', 0, PARAM_INT);
        if (!$category) {
            return $finalelements;
        }

        $displaylist = [];
        $parents = [];
        (new ls)->cr_make_categories_list($displaylist, $parents);

        $coursecache = [];
        foreach ($finalelements as $key => $course) {
            if (empty($coursecache[$course])) {
                $coursecache[$course] = $DB->get_record('course', ['id' => $course]);
            }
            $course = $coursecache[$course];
            if ($category != $course->category && ! in_array($category, $parents[$course->id])) {
                unset($finalelements[$key]);
            }
        }

        return $finalelements;
    }

    /**
     * Filter data
     * @param  boolean $selectoption Filter select option
     * @return array
     */
    public function filter_data($selectoption = true) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/course/lib.php");

        $displaylist = [];
        $notused = [];
        if ($selectoption) {
            $displaylist[0] = get_string('filter_category', 'block_learnerscript');
        }
        $context = context_system::instance();
        if (!is_siteadmin($this->reportclass->userid) && !has_capability('block/learnerscript:managereports', $context)) {
            if (!empty($this->reportclass->rolewisecourses)) {
                $courses = $this->reportclass->rolewisecourses;
                list($sql, $params) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
                $categories = $DB->get_records_sql_menu("SELECT DISTINCT(cat.id), cat.name
                FROM {course_categories} cat
                JOIN {course} c ON cat.id = c.category
                    WHERE c.id $sql", $params);
                foreach ($categories as $key => $value) {
                    $displaylist[$key] = $value;
                }
            }
        } else {
            (new ls)->cr_make_categories_list($displaylist, $notused);
        }
        return $displaylist;
    }

    /**
     * Selected filter data
     * @param  boolean $selected Selected filter value
     * @return array
     */
    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

    /**
     * Print filter
     * @param  object $mform Form data
     */
    public function print_filter(&$mform) {
        $displaylist = $this->filter_data();

        if ($this->filtertype == 'basic') {
            unset($displaylist[0]);
        }
        $select = $mform->addElement('select', 'filter_coursecategories',
        get_string('category'), $displaylist, ['data-select2' => 1]);
        $select->setHiddenLabel(true);
        $mform->setDefault('filter_coursecategories', 0);
        $mform->setType('filter_coursecategories', PARAM_INT);
    }

}
