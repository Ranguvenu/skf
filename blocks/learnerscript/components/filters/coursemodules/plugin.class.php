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
use block_learnerscript\local\ls as ls;
use stdClass;

/**
 * Course modules
 */
class plugin_coursemodules extends pluginbase {

    /** @var mixed $singleselection  */
    public $singleselection;

    /**
     * Course modules
     *
     */
    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->fullname = get_string('filtercoursemodules', 'block_learnerscript');
        $this->reporttypes = ['sql'];
    }

    /**
     * Summary
     * @param  object $data
     * @return string
     */
    public function summary($data) {
        return get_string('filtercoursemodules_summary', 'block_learnerscript');
    }

    /**
     * Execute
     * @param  string $finalelements Final elements
     * @param  object $data          Filter data
     * @return string
     */
    public function execute($finalelements, $data) {
        global $DB;

        $filtercoursemoduleid = optional_param('filter_coursemodules', 0, PARAM_INT);
        if (!$filtercoursemoduleid) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return [$filtercoursemoduleid];
        } else {
            if (preg_match("/%%FILTER_COURSEMODULEID:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtercoursemoduleid;
                $finalelements = str_replace('%%FILTER_COURSEMODULEID:' . $output[1] . '%%', $replace, $finalelements);
            }
            if (preg_match("/%%FILTER_COURSEMODULEFIELDS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' ' . $output[1] . ' ';
                $finalelements = str_replace('%%FILTER_COURSEMODULEFIELDS:' . $output[1] . '%%', $replace, $finalelements);
            }
            if (preg_match("/%%FILTER_COURSEMODULE:([^%]+)%%/i", $finalelements, $output)) {
                $module = $DB->get_record('modules', ['id' => $filtercoursemoduleid]);
                $replace = ' JOIN {' . $module->name . '} AS m ON m.id = ' . $output[1] . ' ';
                $finalelements = str_replace('%%FILTER_COURSEMODULE:' . $output[1] . '%%', $replace, $finalelements);
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
        global $DB;

        $reportclassname = 'block_learnerscript\lsreports\report_' . $this->report->type;
        $properties = new stdClass;
        $reportclass = new $reportclassname($this->report, $properties);
        if ($this->report->type != 'sql') {
            $components = (new ls)->cr_unserialize($this->report->components);
            $conditions = isset($components['conditions']) ? $components['conditions'] : [];

            $coursemodulelist = $reportclass->elements_by_conditions($conditions);
        } else {
            $coursemodulelist = array_keys($DB->get_records('modules'));
        }
        $elestr = get_string('filtercoursemodules', 'block_learnerscript');
        $courseoptions = [];
        if ($selectoption) {
            $courseoptions[0] = $this->singleselection ?
                        get_string('filter_module', 'block_learnerscript') : get_string('select') . $elestr;
        }
        if (!empty($coursemodulelist)) {
            list($usql, $params) = $DB->get_in_or_equal($coursemodulelist, SQL_PARAMS_NAMED);
            $coursemodules = $DB->get_records_select('modules', "id $usql", $params);

            foreach ($coursemodules as $c) {
                $courseoptions[$c->id] = get_string('pluginname', $c->name);
            }
        }
        return $courseoptions;
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
        $courseoptions = $this->filter_data();
        $elestr = get_string('filtercoursemodules', 'block_learnerscript');
        $select = $mform->addElement('select', 'filter_coursemodules', $elestr,
        $courseoptions, ['data-select2' => 1]);
        $select->setHiddenLabel(true);
        $mform->setType('filter_coursemodules', PARAM_INT);
    }

}
