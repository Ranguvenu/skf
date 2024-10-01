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
 * Search text
 */
class plugin_searchtext extends pluginbase {
    /**
     * Search text init function
     */
    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('filter_searchtext', 'block_learnerscript');
        $this->reporttypes = ['searchtext', 'sql'];
    }
    /**
     * Summary
     * @param  object $data
     * @return string
     */
    public function summary($data) {
        return get_string('filter_searchtext_summary', 'block_learnerscript');
    }
    /**
     * Execute
     * @param  string $finalelements Final elements
     * @param  object $data Filter data
     * @param  array $filters Filter data
     * @return string
     */
    public function execute($finalelements, $data, $filters) {
        $searchtext = isset($filters['filter_searchtext']) ? $filters['filter_searchtext'] : '';
        if (empty($searchtext)) {
            $search = optional_param('search', '' , PARAM_RAW);
            $searchtext = $search ? $search['value'] : '';
        }
        $filtersearchtext = optional_param('filter_searchtext', $searchtext, PARAM_RAW);
        $operators = ['=', '<', '>', '<=', '>=', '~', 'in'];

        if (!$filtersearchtext) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return $filtersearchtext;
        } else {
            if (preg_match("/%%FILTER_SEARCHTEXT:([^%]+)%%/i", $finalelements, $output)) {
                list($field, $operator) = preg_split('/:/', $output[1]);
                if ($operator != '' && !in_array($operator, $operators)) {
                    throw new \moodle_exception('nosuchoperator', 'block_learnerscript');
                }
                if ($operator == '' || $operator == '~') {
                    $replace = " AND " . $field . " LIKE '%" . $filtersearchtext . "%'";
                } else if ($operator == 'in') {
                    $processeditems = [];
                    // Accept comma-separated values, allowing for '\,' as a literal comma.
                    foreach (preg_split("/(?<!\\\\),/", $filtersearchtext) as $searchitem) {
                        // Strip leading/trailing whitespace and quotes (we'll add our own quotes later).
                        $searchitem = trim($searchitem);
                        $searchitem = trim($searchitem, '"\'');

                        // We can also safely remove escaped commas now.
                        $searchitem = str_replace('\\,', ',', $searchitem);

                        // Escape and quote strings...
                        if (!is_numeric($searchitem)) {
                            $searchitem = "'" . addslashes($searchitem) . "'";
                        }
                        $processeditems[] = "$field LIKE $searchitem";
                    }
                    // Despite the name, by not actually using in() we can support wildcards, and maybe be more portable as well.
                    $replace = " AND (" . implode(" OR ", $processeditems) . ")";
                } else {
                    $replace = ' AND ' . $field . ' ' . $operator . ' ' . $filtersearchtext;
                }
                return str_replace('%%FILTER_SEARCHTEXT:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
    /**
     * Print filter
     * @param  object $mform Form data
     */
    public function print_filter(&$mform) {
        $filtersearchtext = optional_param('filter_searchtext', '', PARAM_RAW);
        $mform->addElement('text', 'filter_searchtext', get_string('filter'));
        $mform->setType('filter_searchtext', PARAM_RAW);
        $mform->setDefault('filter_searchtext', $filtersearchtext);
    }

}
