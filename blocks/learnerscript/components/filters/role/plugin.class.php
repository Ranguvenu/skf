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
 * Role filter
 */
class plugin_role extends pluginbase {
    /** @var mixed $singleselection  */
    public $singleselection;

    /**
     * Role filter init function
     */
    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->fullname = get_string('filterrole', 'block_learnerscript');
        $this->reporttypes = ['categories', 'sql'];
    }
    /**
     * Summary
     * @param  object $data
     * @return string
     */
    public function summary($data) {
        return get_string('filterrole_summary', 'block_learnerscript');
    }
    /**
     * Execute
     * @param  string $finalelements Final elements
     * @param  object $data          Filter data
     * @return string
     */
    public function execute($finalelements, $data) {

        $filterrole = optional_param('filter_role', 0, PARAM_INT);
        if (!$filterrole) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return $filterrole;
        } else {
            if (preg_match("/%%FILTER_ROLE:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterrole . ' ';
                return str_replace('%%FILTER_ROLE:' . $output[1] . '%%', $replace, $finalelements);
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

        $systemroles = $DB->get_records('role');
        $roles = [];
        foreach ($systemroles as $role) {
            $roles[$role->id] = $role->shortname;
        }

        if ($this->report->type != 'sql') {
            $components = (new ls)->cr_unserialize($this->report->components);
            $conditions = $components['conditions'];

            $rolelist = $reportclass->elements_by_conditions($conditions);
        } else {
            $rolelist = $roles;
        }

        $roleoptions = [];
        if ($selectoption) {
            $roleoptions[0] = $this->singleselection ?
            get_string('filter_role', 'block_learnerscript') :
            get_string('select') .' '. get_string('filterrole', 'block_learnerscript');
        }
        if (!empty($rolelist)) {
            // Todo: check that keys of role array items are available.
            foreach ($rolelist as $key => $role) {
                $roleoptions[$key] = $role;
            }
        }
        return $roleoptions;
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
        $roleoptions = $this->filter_data();
        $select = $mform->addElement('select', 'filter_role',
        get_string('filterrole', 'block_learnerscript'), $roleoptions, ['data-select2' => 1]);
        $select->setHiddenLabel(true);
        $mform->setType('filter_role', PARAM_INT);
    }

}
