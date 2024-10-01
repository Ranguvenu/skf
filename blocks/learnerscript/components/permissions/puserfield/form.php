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
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
/**
 * User field form
 */
class puserfield_form extends moodleform {
    /**
     * User field form defination
     */
    public function definition() {
        global $DB, $USER, $CFG;

        $mform = & $this->_form;

        $mform->addElement('header', 'crformheader', get_string('coursefield', 'block_learnerscript'), '');

        $columns = $DB->get_columns('user');

        $usercolumns = [];
        foreach ($columns as $c) {
            $usercolumns[$c->name] = $c->name;
        }

        if ($profile = $DB->get_records('user_info_field')) {
            foreach ($profile as $p) {
                $usercolumns['profile_' . $p->shortname] = $p->name;
            }
        }
        unset($usercolumns['password']);
        unset($usercolumns['secret']);

        $mform->addElement('select', 'field', get_string('column', 'block_learnerscript'), $usercolumns);

        $mform->addElement('text', 'value', get_string('value', 'block_learnerscript'));
        $mform->setType('value', PARAM_RAW);
        $mform->addRule('value', get_string('required'), 'required');

        // Buttons.
        $this->add_action_buttons(true, get_string('add'));
    }
    /**
     * User field validation
     * @param object $data From data
     * @param object $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB, $db, $CFG;

        $errors = parent::validation($data, $files);

        $columns = $DB->get_columns('user');
        $usercolumns = [];
        foreach ($columns as $c) {
            $usercolumns[$c->name] = $c->name;
        }
        if ($profile = $DB->get_records('user_info_field')) {
            foreach ($profile as $p) {
                $usercolumns['profile_' . $p->shortname] = 'profile_' . $p->shortname;
            }
        }
        if (!in_array($data['field'], $usercolumns)) {
            $errors['field'] = get_string('error_field', 'block_learnerscript');
        }

        return $errors;
    }

}
