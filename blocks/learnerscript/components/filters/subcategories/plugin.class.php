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
use stdClass;
/**
 * Sub categories filter
 */
class plugin_subcategories extends pluginbase {
    /** @var mixed $singleselection  */
    public $singleselection;

    /**
     * Sub categories filter init function
     */
    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->fullname = get_string('filtersubcategories', 'block_learnerscript');
        $this->reporttypes = ['categories', 'sql'];
    }
    /**
     * Summary
     * @param  object $data
     * @return string
     */
    public function summary($data) {
        return get_string('filtersubcategories_summary', 'block_learnerscript');
    }
    /**
     * Execute
     * @param  string $finalelements Final elements
     * @param  object $data          Filter data
     * @return string
     */
    public function execute($finalelements, $data) {
        $filtersubcategories = optional_param('filter_subcategories', 0, PARAM_INT);
        if (!$filtersubcategories) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return $filtersubcategories;
        } else {
            if (preg_match("/%%FILTER_SUBCATEGORIES:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' LIKE CONCAT( \'%/\', ' . $filtersubcategories . ', \'%\' ) ';
                return str_replace('%%FILTER_SUBCATEGORIES:' . $output[1] . '%%', $replace, $finalelements);
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
            $conditions = $components['conditions'];

            $subcategorieslist = $reportclass->elements_by_conditions($conditions);
        } else {
            $subcategorieslist = array_keys($DB->get_records('course_categories'));
        }

        $courseoptions = [];
        if ($selectoption) {
            $courseoptions[0] = $this->singleselection ?
                        get_string('filter_category', 'block_learnerscript') : get_string('select') .' '. get_string('category');
        }

        if (!empty($subcategorieslist)) {
            list($usql, $params) = $DB->get_in_or_equal($subcategorieslist);
            $subcategories = $DB->get_records_select('course_categories', "id $usql", $params);

            foreach ($subcategories as $c) {
                $courseoptions[$c->id] = format_string($c->name);
            }
        }
        return $courseoptions;
    }
    /**
     * Print filter
     * @param  object $mform Form data
     */
    public function print_filter(&$mform) {

        $courseoptions = $this->filter_data();
        $select = $mform->addElement('select', 'filter_subcategories', get_string('category'), $courseoptions);
        $select->setHiddenLabel(true);
        $mform->setType('filter_subcategories', PARAM_INT);
    }

}
