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
use block_learnerscript\local\componentbase;
use block_learnerscript\local\ls as ls;
/**
 * Component conditions
 */
class component_conditions extends componentbase {
    /**
     * @var bool $plugins Plugins
     */
    public $plugins;
    /**
     * @var bool $ordering Ordering
     */
    public $ordering;
    /**
     * @var bool $form Form
     */
    public $form;
    /**
     * @var bool $help Help
     */
    public $help;
    /**
     * Component conditions init function
     */
    public function init() {
        $this->plugins = true;
        $this->ordering = false;
        $this->form = true;
        $this->help = true;
    }
    /**
     * Components conditions form data process
     * @param object $cform Form data
     */
    public function form_process_data(&$cform) {
        global $DB;

        if ($this->form) {
            $data = $cform->get_data();
            $components = (new ls)->cr_unserialize($this->config->components);
            $components['conditions']['config'] = $data;
            if (isset($components['conditions']['config']->conditionexpr)) {
                $components['conditions']['config']->conditionexpr =
                $this->add_missing_conditions($components['conditions']['config']->conditionexpr);
            }
            $this->config->components = (new ls)->cr_serialize($components);
            $DB->update_record('block_learnerscript', $this->config);
        }
    }
    /**
     * Add missing form conditions
     * @param array $cond Conditions
     * @return array|string
     */
    public function add_missing_conditions($cond) {
        $components = (new ls)->cr_unserialize($this->config->components);

        if (isset($components['conditions']['elements'])) {

            $elements = $components['conditions']['elements'];
            $count = count($elements);
            if ($count == 0 || $count == 1) {
                return '';
            }
            for ($i = $count; $i > 0; $i--) {
                if (strpos($cond, 'c' . $i) === false) {
                    if ($count > 1 && $cond) {
                        $cond .= " and c$i";
                    } else {
                        $cond .= "c$i";
                    }
                }
            }

            // Deleting extra conditions.

            for ($i = $count + 1; $i <= $count + 5; $i++) {
                $cond = preg_replace('/(\bc' . $i . '\b\s+\b(and|or|not)\b\s*)/i', '', $cond);
                $cond = preg_replace('/(\s+\b(and|or|not)\b\s+\bc' . $i . '\b)/i', '', $cond);
            }
        }

        return $cond;
    }
    /**
     * Set form data
     * @param object $cform data
     */
    public function form_set_data(&$cform) {
        global $DB;
        if ($this->form) {
            $fdata = new stdclass;
            $components = (new ls)->cr_unserialize($this->config->components);
            $conditionsconfig = (isset($components['conditions']['config'])) ? $components['conditions']['config'] : new stdclass;

            if (!isset($conditionsconfig->conditionexpr)) {
                $conditionsconfig->conditionexpr = '';
                $conditionsconfig->conditionexpr = '';
            }
            $conditionsconfig->conditionexpr = $this->add_missing_conditions($conditionsconfig->conditionexpr);
            $fdata->conditionexpr = $conditionsconfig->conditionexpr;

            if (empty($components['conditions'])) {
                $components['conditions'] = [];
            }

            $components['conditions']['config']->conditionexpr = $fdata->conditionexpr;
            $this->config->components = (new ls)->cr_serialize($components);
            $DB->update_record('block_learnerscript', $this->config);

            $cform->set_data($fdata);
        }
    }

}
