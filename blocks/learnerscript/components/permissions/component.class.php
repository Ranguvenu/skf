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
use block_learnerscript\local\ls as ls;
/**
 * Component permissions
 */
class component_permissions extends componentbase {
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
     * Component permissions init function
     */
    public function init() {
        $this->plugins = true;
        $this->ordering = false;
        $this->form = true;
        $this->help = true;
    }
    /**
     * Component permissions form process
     * @param object $cform Form data
     */
    public function form_process_data(&$cform) {
        global $DB;

        if ($this->form) {
            $data = $cform->get_data();
            $components = (new ls)->cr_unserialize($this->config->components);
            $components->permissions->config = $data;
            if (isset($components->permissions->config->conditionexpr)) {
                $components->permissions->config->conditionexpr =
                $this->add_missing_conditions($components->permissions->config->conditionexpr);
            }

            $components->permissions->config->conditionexpr2 = $this->add_roleincourse_conditions();
            if (empty($components->permissions->config->conditionexpr1)) {
                $components->permissions->config->conditionexpr = $components->permissions->config->conditionexpr2;
            } else if (empty($components->permissions->config->conditionexpr2)) {
                $components->permissions->config->conditionexpr = $components->permissions->config->conditionexpr1;
            } else {
                $components->permissions->config->conditionexpr =
                $components->permissions->config->conditionexpr1 . ' and ' .
                $components->permissions->config->conditionexpr2;
            }

            $this->config->components = (new ls)->cr_serialize($components);
            $DB->update_record('block_learnerscript', $this->config);
        }
    }
    /**
     * Add missing conditions
     * @param string $cond
     * @return string|null
     */
    public function add_missing_conditions($cond) {
        $components = (new ls)->cr_unserialize($this->config->components);
        $k = 0;
        if (isset($components->permissions->elements)) {
            $elements = $components->permissions->elements;
            $elements = array_values($elements);
            $count = count($elements);
            if ($count == 0 ) {
                return '';
            }
            $j = 0;
            $k = 0;
            for ($i = $count; $i > 0; $i--) {
                $element = $elements[$i - 1];
                if (strpos($cond, 'c' . $i) === false && $element->pluginname != 'roleincourse') {
                    if ($count > 1 && $cond) {
                        $cond .= " and c$i";
                    } else {
                        $cond .= "c$i";
                    }
                }
                if ($element->pluginname == 'roleincourse') {
                    $j++;
                    $cond = preg_replace('/(\bc' . $i . '\b\s+\b(and|or|not)\b\s*)/i', '', $cond);
                    $cond = preg_replace('/(\s+\b(and|or|not)\b\s+\bc' . $i . '\b)/i', '', $cond);
                } else {
                    $k++;
                }
            }
            $cond = trim($cond);
            // Deleting extra conditions.
            for ($i = $count + 1; $i <= $count + 5; $i++) {
                $cond = preg_replace('/(\bc' . $i . '\b\s+\b(and|or|not)\b\s*)/i', '', $cond);
                $cond = preg_replace('/(\s+\b(and|or|not)\b\s+\bc' . $i . '\b)/i', '', $cond);
            }
        }
        if ($k == 1 && $j == 0) {
            return 'c1';
        }

        return $cond;
    }
    /**
     * Conditions form data
     * @param object $cform Form data
     */
    public function form_set_data(&$cform) {
        global $DB;
        if ($this->form) {
            $fdata = new stdclass;
            $fdata->conditionexpr = '';
            $components = (new ls)->cr_unserialize($this->config->components);
            $conditionsconfig = (isset($components->permissions->config)) ? $components->permissions->config : new stdclass;

            if (!isset($conditionsconfig->conditionexpr)) {
                $fdata->conditionexpr = '';
                $conditionsconfig->conditionexpr = '';
            }
            $conditionsconfig->conditionexpr1 = '';
            $conditionsconfig->conditionexpr1 = $this->add_missing_conditions($conditionsconfig->conditionexpr1);
            $fdata->conditionexpr1 = $conditionsconfig->conditionexpr1;

            $conditionsconfig->conditionexpr2 = $this->add_roleincourse_conditions();

            $fdata->conditionexpr2 = $conditionsconfig->conditionexpr2;

            if (empty($components->permissions)) {
                $components->permissions = new StdClass;
            }
            if (!property_exists($components->permissions, 'config')) {
                $components->permissions->config = new StdClass;
            }
            $components->permissions->config->conditionexpr1 = $fdata->conditionexpr1;
            $components->permissions->config->conditionexpr2 = $fdata->conditionexpr2;
            $components->permissions->config->conditionexpr = $fdata->conditionexpr;

            if (empty($components->permissions->config->conditionexpr1)) {
                $components->permissions->config->conditionexpr = $components->permissions->config->conditionexpr2;
            } else if (empty($components->permissions->config->conditionexpr2)) {
                $components->permissions->config->conditionexpr =
                $components->permissions->config->conditionexpr1;
            } else {
                $components->permissions->config->conditionexpr =
                $components->permissions->config->conditionexpr1 . ' and ' .
                $components->permissions->config->conditionexpr2;
            }

            $this->config->components = (new ls)->cr_serialize($components);
            $DB->update_record('block_learnerscript', $this->config);

            $cform->set_data($fdata);
        }
    }
    /**
     * Add role conditions
     * @return string
     */
    public function add_roleincourse_conditions() {
        global $DB;
        $components = (new ls)->cr_unserialize($this->config->components);
        $cond = '';
        $i = 0;
        $j = 0;
        $z = 0;
        if (isset($components->permissions->elements)) {
            $elements = $components->permissions->elements;
            $i = 0;
            $j = 0;
            $z = 0;
            $elements = array_values($elements);

            foreach ($elements as $k => $element) {
                $j++;
                if ($element->pluginname == 'roleincourse') {
                    if ($i > 0) {
                        $cond .= " or c$j";
                    } else {
                        $cond .= "(c$j";
                    }
                    $i++;
                } else {
                    $z++;
                }
            }
            if ($i > 0) {
                $cond .= ")";
            }
        }
        if ($z == 0 && ($i == 0)) {
            return '';
        }

        $cond = trim($cond);
        $count = count($elements);
        // Deleting extra conditions.
        for ($i = $count + 1; $i <= $count + 5; $i++) {
            $cond = preg_replace('/(\bc' . $i . '\b\s+\b(and|or|not)\b\s*)/i', '', $cond);
            $cond = preg_replace('/(\s+\b(and|or|not)\b\s+\bc' . $i . '\b)/i', '', $cond);
        }
        return $cond;
    }
}
