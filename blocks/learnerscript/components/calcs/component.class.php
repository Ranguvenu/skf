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
 * A Moodle block for creating customizable reports
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use block_learnerscript\local\componentbase;
use block_learnerscript\local\ls;
/**
 * Component calculation
 */
class component_calcs extends componentbase {
    /**
     * Component initialization
     */
    public function init() {
        $this->plugins = true;
        $this->ordering = false;
        $this->form = false;
        $this->help = true;
    }
    /**
     * Form elements
     * @param  object $mform Form data
     * @param  object $components Report components
     */
    public function add_form_elements(&$mform, $components) {
        global $CFG;

        $components = (new ls)->cr_unserialize($components);
        $options = [];

        if ($this->config->type != 'sql') {
            if (!is_array($components) || empty($components->columns->elements)) {
                throw new moodle_exception('nocolumns');
            }
            $columns = $components->columns->elements;

            $calcs = isset($components->calcs->elements) ? $components->calcs->elements : [];
            $columnsused = [];
            if ($calcs) {
                foreach ($calcs as $c) {
                    $columnsused[] = $c->formdata->column;
                }
            }

            $i = 0;
            foreach ($columns as $c) {
                if (!in_array($i, $columnsused)) {
                    $options[$i] = $c->summary;
                }
                $i++;
            }
        } else {
            require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $this->config->type . '/report.class.php');

            $reportclassname = 'block_learnerscript\lsreports\report_' . $this->config->type;
            $reportclass = new $reportclassname($this->config);

            $components = (new ls)->cr_unserialize($this->config->components);
            $config = (isset($components->customsql->config)) ? $components->customsql->config : new stdclass;

            if (isset($config->querysql)) {

                $sql = $config->querysql;
                $sql = $reportclass->prepare_sql($sql);
                if ($rs = $reportclass->execute_query($sql)) {
                    foreach ($rs['results'] as $row) {
                        $i = 0;
                        foreach ($row as $colname => $value) {
                            $options[$i] = str_replace('_', ' ', $colname);
                            $i++;
                        }
                        break;
                    }
                }
            }
        }

        $mform->addElement('header', 'crformheader', get_string('coursefield', 'block_learnerscript'), '');
        $mform->addElement('select', 'column', get_string('column', 'block_learnerscript'), $options);

    }

}
