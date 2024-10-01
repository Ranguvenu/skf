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
 * Courses filter
 */
class plugin_courses extends pluginbase {

    /** @var mixed $singleselection  */
    public $singleselection;

    /** @var mixed $placeholder  */
    public $placeholder;

    /** @var mixed $filtertype  */
    public $filtertype;

    /** @var mixed $maxlength  */
    public $maxlength;

    /**
     * Courses filter init function
     *
     */
    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->filtertype = 'custom';
        if (!empty($this->reportclass->basicparams)) {
            foreach ($this->reportclass->basicparams as $basicparam) {
                if ($basicparam['name'] == 'courses') {
                    $this->filtertype = 'basic';
                }
            }
        }
        $this->fullname = get_string('filter_courses', 'block_learnerscript');
        $this->reporttypes = ['courses', 'sql', 'coursesoverview', 'userbadges'];
    }

    /**
     * Summary
     * @param  object $data
     * @return string
     */
    public function summary($data) {
        return get_string('filter_courses_summary', 'block_learnerscript');
    }

    /**
     * Execute
     * @param  string $finalelements Final elements
     * @param  object $data          Filter data
     * @param  array $filters       Filter params
     * @return string
     */
    public function execute($finalelements, $data, $filters) {
        $fcourse = isset($filters['filter_courses']) ? $filters['filter_courses'] : null;
        $filtercourses = optional_param('filter_courses', $fcourse, PARAM_INT);
        if (!$filtercourses) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return [$filtercourses];
        } else {
            if (preg_match("/%%FILTER_COURSES:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtercourses;
                return str_replace('%%FILTER_COURSES:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    /**
     * Filter data
     * @param  boolean $selectoption Filter select option
     * @param  array $request Filter request params
     * @return array
     */
    public function filter_data($selectoption = true, $request = []) {
        $filtercourses = '';
        $fcourses = isset($request['filter_courses']) ? $request['filter_courses'] : 0;
        $filtercourses = optional_param('filter_courses', $fcourses, PARAM_INT);
        if (empty($this->reportclass->basicparams)) {
            $courseoptions = [get_string('filter_courses', 'block_learnerscript')];
        }
        $filtercourse = $this->reportclass->filters;
        if ($this->reportclass->basicparams) {
            $basicparams = array_column($this->reportclass->basicparams, 'name');
            if (in_array('users', $basicparams) && in_array('courses', $basicparams)
            && isset($basicparams[2]) && isset($basicparams[3])
            && $basicparams[2] == 'users' && $basicparams[3] != 'courses') {
                $useroptions = (new \block_learnerscript\local\querylib)->filter_get_users($this, false, false, [], false,
                false);
                $userids = array_keys($useroptions);
                if (empty($request['filter_users'])) {
                    $courseuserid = array_shift($userids);
                } else {
                    $courseuserid = $request['filter_users'];
                }
            } else {
                $courseuserid = 0;
            }
        } else {
            $courseuserid = 0;
        }
        $courseoptions = (new \block_learnerscript\local\querylib)->filter_get_courses($this, $filtercourses,
        $selectoption, false, $filtercourse, false, $courseuserid);
        return $courseoptions;
    }

    /**
     * Selected filter data
     * @param  boolean $selected Selected filter value
     * @param  array $request Filter request params
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
        if ($this->report->type == 'courseprofile' || $this->report->type == 'userprofile') {
            $selectoption = false;
        } else {
            $selectoption = true;
        }
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
        $courseoptions = $this->filter_data(true, $request);
        if ((!$this->placeholder || $this->filtertype == 'basic')
        && count($courseoptions) > 1) {
            unset($courseoptions[0]);
        }

        $select = $mform->addElement('select', 'filter_courses', null,
            $courseoptions,
            ['data-select2-ajax' => true,
                  'data-maximum-selection-length' => $this->maxlength,
                  'data-action' => 'filter_courses',
                  'data-instanceid' => $this->reportclass->config->id, ]);
        $select->setHiddenLabel(true);
        if (!$this->singleselection) {
            $select->setMultiple(true);
        }
        if ($this->required) {
            $select->setSelected(current(array_keys($courseoptions)));
        }
        $mform->setType('filter_courses', PARAM_INT);
        $mform->addElement('hidden', 'filter_courses_type', $this->filtertype);
        $mform->setType('filter_courses_type', PARAM_TEXT);

    }
}
