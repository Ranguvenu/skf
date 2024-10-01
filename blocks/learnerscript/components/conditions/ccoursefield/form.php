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
 * Course fields form
 */
class ccoursefield_form extends moodleform {
    /** @var array $allowedops */
    private $allowedops = ['=' => '=', '>' => '>', '<' => '<', '>=' => '>=', '<=' => '<=',
    '<>' => '<>', 'LIKE' => 'LIKE', 'NOT LIKE' => 'NOT LIKE', 'LIKE % %' => 'LIKE % %', ];
    /**
     * Course fields form defination
     */
    public function definition() {
        global $DB;

        $mform = & $this->_form;

        $mform->addElement('header', 'crformheader', get_string('coursefield', 'block_learnerscript'), '');

        $columns = $DB->get_columns('course');

        $coursecolumns = [];
        foreach ($columns as $c) {
            $coursecolumns[$c->name] = $c->name;
        }
        $mform->addElement('select', 'field', get_string('column', 'block_learnerscript'), $coursecolumns);

        $mform->addElement('select', 'operator', get_string('operator', 'block_learnerscript'), $this->allowedops);
        $mform->addElement('text', 'value', get_string('value', 'block_learnerscript'));
        $mform->setType('value', PARAM_RAW);
        $this->add_action_buttons(true, get_string('add'));
    }
    /**
     * Course fields form validation
     * @param object $data Form data
     * @param object $files Files data
     * @return array
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        if (!in_array($data['operator'], $this->allowedops)) {
            $errors['operator'] = get_string('error_operator', 'block_learnerscript');
        }

        $columns = $DB->get_columns('course');
        $coursecolumns = [];
        foreach ($columns as $c) {
            $coursecolumns[$c->name] = $c->name;
        }

        if (!in_array($data['field'], $coursecolumns)) {
            $errors['field'] = get_string('error_field', 'block_learnerscript');
        }

        if (!is_numeric($data['value']) && preg_match('/^(<|>)[^(<|>)]/i', $data['operator'])) {
            $errors['value'] = get_string('error_value_expected_integer', 'block_learnerscript');
        }

        return $errors;
    }

}
