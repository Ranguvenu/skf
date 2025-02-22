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
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
/**
 * Condition form
 */
class conditions_form extends moodleform {
    /**
     * Conditions form defination
     */
    public function definition() {
        $mform = & $this->_form;

        $mform->addElement('static', 'help', '', get_string('conditionexprhelp', 'block_learnerscript'));
        $mform->addElement('text', 'conditionexpr', get_string('conditionexpr', 'block_learnerscript'), 'size="50"');
        $mform->setType('conditionexpr', PARAM_RAW);
        $mform->addHelpButton('conditionexpr', 'conditionexpr_conditions', 'block_learnerscript');
        $this->add_action_buttons(true, get_string('update'));
    }
    /**
     * Conditions form validation
     * @param object $data Form data
     * @param object $files Files details
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!preg_match("/(\(*\s*\bc\d{1,2}\b\s*\(*\)*\s*(\(|and|or|not)\s*)+\(*\s*\bc\d{1,2}\b\s*\(*\)*\s*$/i",
        $data['conditionexpr'])) {
            $errors['conditionexpr'] = get_string('badconditionexpr', 'block_learnerscript');
        }
        if (substr_count($data['conditionexpr'], '(') != substr_count($data['conditionexpr'], ')')) {
            $errors['conditionexpr'] = get_string('badconditionexpr', 'block_learnerscript');
        }

        if (isset($this->_customdata['elements']) && is_array($this->_customdata['elements'])) {
            $elements = $this->_customdata['elements'];
            $nel = count($elements);
            if (!empty($elements) && $nel > 1) {
                preg_match_all('/(\d+)/', $data['conditionexpr'], $matches, PREG_PATTERN_ORDER);
                foreach ($matches[0] as $num) {
                    if ($num > $nel) {
                        $errors['conditionexpr'] = get_string('badconditionexpr', 'block_learnerscript');
                        break;
                    }
                }
            }
        }

        return $errors;
    }

}
