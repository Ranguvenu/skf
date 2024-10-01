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
 * A Moodle block to create customizable columns.
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
/**
 * Forum field columns
 */
class forumfield_form extends moodleform {
    /**
     * From defination
     *
     */
    public function definition() {
        global $DB;

        $mform = & $this->_form;

        $mform->addElement('header', 'crformheader', get_string('forumfield', 'block_learnerscript'), '');

        $columns = $DB->get_columns('forum');

        $forumcolumns = [];
        foreach ($columns as $c) {
            $forumcolumns[$c->name] = ucfirst($c->name);
        }
        $mform->addElement('select', 'column', get_string('column', 'block_learnerscript'), $forumcolumns);

        $this->_customdata['compclass']->add_form_elements($mform, $this);

        // Buttons.
        $this->add_action_buttons(true, get_string('add'));
    }
    /**
     * From defination
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $errors = $this->_customdata['compclass']->validate_form_elements($data, $errors);
        return $errors;
    }
    /**
     * From advanced columns
     *
     * @return array
     */
    public function advanced_columns() {
        global $DB;
        $columns = $DB->get_columns('forum');
        $forumcolumns = [];
        foreach ($columns as $c) {
            $forumcolumns[$c->name] = ucfirst($c->name);
        }
        return $forumcolumns;
    }

}
