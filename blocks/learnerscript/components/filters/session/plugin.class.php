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
use block_learnerscript\local\querylib;
use stdClass;
/**
 * Session filter
 */
class plugin_session extends pluginbase {

    /** @var mixed $singleselection  */
    public $singleselection;

    /**
     * @var mixed $placeholder
     */
    public $placeholder;

    /**
     * @var int $maxlength
     */
    public $maxlength;
    /**
     * Session filter init function
     */
    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->fullname = get_string('filtersession', 'block_learnerscript');
        $this->reporttypes = [];
    }
    /**
     * Summary
     * @param  object $data
     * @return string
     */
    public function summary($data) {
        return get_string('filtersession_summary', 'block_learnerscript');
    }
    /**
     * Execute
     * @param  string $finalelements Final elements
     * @param  object $data Filter data
     * @param  array $filters Filters
     * @return string
     */
    public function execute($finalelements, $data, $filters) {
        $filtersession = isset($filters['filter_session']) ? $filters['filter_session'] : 0;
        if (!$filtersession) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return $filtersession;
        } else {
            if (preg_match("/%%FILTER_SESSION:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtersession;
                return str_replace('%%FILTER_SESSION:' . $output[1] . '%%', $replace, $finalelements);
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
        global $DB, $USER, $SESSION;
        $properties = new stdClass();
        $properties->courseid = SITEID;

        $reportclassname = 'block_learnerscript\lsreports\report_' . $this->report->type;
        $reportclass = new $reportclassname($this->report, $properties);
        if (!is_siteadmin()) {
            $courseoptions = (new querylib)->get_rolecourses($USER->id, $SESSION->role, $SESSION->ls_contextlevel );
            foreach ($courseoptions as $courseoption) {
                $courses[] = $courseoption->id;
            }
            list($coursesql, $courseparams) = $DB->get_in_or_equal($courses);
            $sql = "SELECT *
                    FROM {bigbluebuttonbn}
                    WHERE course $coursesql ";
            $sessionslist = array_keys($DB->get_records_sql($sql, $courseparams));
        } else {
            $sessionslist = array_keys($DB->get_records('bigbluebuttonbn'));
        }
        $sessionoptions = [];
        if ($selectoption && !empty($this->reportclass->basicparams[0]) &&
        !in_array('session', $this->reportclass->basicparams[0])) {
            $sessionoptions[0] = $this->singleselection ?
                get_string('filter_session', 'block_learnerscript') :
                get_string('select') .' '. get_string('session', 'block_learnerscript');
        }

        if (!empty($sessionslist)) {
            list($usql, $params) = $DB->get_in_or_equal($sessionslist);
            $sessions = $DB->get_records_select('bigbluebuttonbn', "id $usql", $params);

            foreach ($sessions as $s) {
                $sessionoptions[$s->id] = format_string($s->name);
            }
        } else {
            $sessionoptions[0] = $this->singleselection ?
                get_string('filter_session', 'block_learnerscript') :
                get_string('select') .' '. get_string('session', 'block_learnerscript');
        }
        return $sessionoptions;
    }
    /**
     * Selected filter data
     * @param  boolean $selected Selected filter value
     * @return string
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
        $sessionoptions = $this->filter_data();
        $select = $mform->addElement('select', 'filter_session', get_string('session', 'block_learnerscript'), $sessionoptions,
        ['data-select2' => 1]);
        $select->setHiddenLabel(true);
        $mform->setType('filter_session', PARAM_INT);
    }

}
