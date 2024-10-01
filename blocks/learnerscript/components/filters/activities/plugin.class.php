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
 * Activities filter
 */
class plugin_activities extends pluginbase {

    /** @var mixed $singleselection  */
    public $singleselection;

    /** @var mixed $placeholder  */
    public $placeholder;

    /** @var mixed $filtertype  */
    public $filtertype;

    /** @var int $maxlength  */
    public $maxlength;

    /**
     * Activities filter init function
     *
     */
    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->fullname = get_string('filteractivities', 'block_learnerscript');
        $this->filtertype = 'custom';
        if (!empty($this->reportclass->basicparams)) {
            foreach ($this->reportclass->basicparams as $basicparam) {
                if ($basicparam['name'] == 'activities') {
                    $this->filtertype = 'basic';
                }
            }
        }
        $this->reporttypes = ['useractivities'];
    }

    /**
     * Summary
     * @param  object $data
     * @return string
     */
    public function summary($data) {
        return get_string('filteractivities_summary', 'block_learnerscript');
    }

    /**
     * Execute
     * @param  string $finalelements Final elements
     * @param  object $data          Filter data
     * @param  array $filters       Filter params
     * @return string
     */
    public function execute($finalelements, $data, $filters) {

        $filteractivities = isset($filters['filter_activities']) ? $filters['filter_activities'] : 0;
        if (!$filteractivities) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return [$filteractivities];
        }
        return $finalelements;
    }

    /**
     * Filter data
     * @param  boolean $selectoption Filter select option
     * @param array $request Filter params
     * @return array
     */
    public function filter_data($selectoption = true, $request = []) {
        global $DB, $CFG;
        $factivities = isset($request['filter_activities']) ? $request['filter_activities'] : 0;
        require_once($CFG->dirroot . '/course/lib.php');
        $courseid = optional_param('courseid', SITEID, PARAM_INT);
        if ($courseid <= SITEID) {
            $courseid = optional_param('filter_courses', SITEID, PARAM_INT);
            $courselist = $DB->get_record_sql("SELECT * FROM {course} WHERE id = $courseid");
        }
        $activities = [];
        if ($selectoption) {
            $activities[0] = $this->singleselection ?
            get_string('select_activity', 'block_learnerscript')
            : get_string('select') .' '. get_string('activities', 'block_learnerscript');
        }
        if (!empty($courselist) && isset($courselist)) {
            $activitieslist = \course_modinfo::get_array_of_activities($courselist);
            foreach ($activitieslist as $activity) {
                $activities[$activity->cm] = $activity->name;
            }
        }
        return $activities;
    }

    /**
     * Selected filter data
     * @param  boolean $selected Selected filter value
     * @param array $request Filter params
     * @return array
     */
    public function selected_filter($selected, $request = []) {
        $filterdata = $this->filter_data(false, $request);
        return $filterdata[$selected];
    }

    /**
     * Print filter
     * @param  object $mform Form data
     */
    public function print_filter(&$mform) {
        $ftcourses = optional_param('filter_courses', 0, PARAM_INT);
        $ftcoursecategories = optional_param('filter_coursecategories', 0, PARAM_INT);
        $ftusers = optional_param('filter_users', 0, PARAM_INT);
        $ftmodules = optional_param('filter_modules', 0, PARAM_INT);
        $ftactivities = optional_param('filter_activities', 0, PARAM_INT);
        $ftstatus = optional_param('filter_status', '', PARAM_TEXT);
        $urlparams = ['filter_courses' => $ftcourses, 'filter_coursecategories' => $ftcoursecategories,
                    'filter_users' => $ftusers, 'filter_modules' => $ftmodules,
                    'filter_activities' => $ftactivities, 'filter_status' => $ftstatus, ];
        $request = array_filter($urlparams);
        $activities = $this->filter_data(true, $request);
        if (!$this->placeholder || $this->filtertype == 'basic' && count($activities) > 1) {
            unset($activities[0]);
        }
        $select = $mform->addElement('select', 'filter_activities', get_string('activities', 'block_learnerscript'), $activities,
        ['data-select2' => 1]);
        $select->setHiddenLabel(true);
        $mform->setType('filter_activities', PARAM_INT);
    }

}
