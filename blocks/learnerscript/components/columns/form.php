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
 * Component columns form
 */
class columns_form extends moodleform {
    /**
     * Component columns form defination
     */
    public function definition() {
        global $DB, $USER, $CFG;

        $mform = & $this->_form;

        $mform->addElement('header', get_string('reporttable', 'block_learnerscript'), '');

        $mform->addElement('text', 'tablewidth', get_string('tablewidth', 'block_learnerscript'));
        $mform->setType('tablewidth', PARAM_CLEAN);
        $mform->setDefault('tablewidth', '100%');
        $mform->addHelpButton('tablewidth', 'reporttable', 'block_learnerscript');

        $options = ['center' => 'center', 'left' => 'left', 'right' => 'right'];

        $mform->addElement('SELECT', 'tablealign', get_string('tablealign', 'block_learnerscript'), $options);
        $mform->setType('tablealign', PARAM_CLEAN);
        $mform->setDefault('tablealign', 'center');

        $mform->addElement('text', 'cellspacing', get_string('tablecellspacing', 'block_learnerscript'));
        $mform->setType('cellspacing', PARAM_INT);
        $mform->setDefault('cellspacing', '3');
        $mform->setAdvanced('cellspacing');

        $mform->addElement('text', 'cellpadding', get_string('tablecellpadding', 'block_learnerscript'));
        $mform->setType('cellpadding', PARAM_INT);
        $mform->setDefault('cellpadding', '3');
        $mform->setAdvanced('cellpadding');

        $mform->addElement('text', 'class', get_string('tableclass', 'block_learnerscript'));
        $mform->setType('class', PARAM_CLEAN);
        $mform->setAdvanced('class');

        // Buttons.
        $this->add_action_buttons(true, get_string('update'));
    }
    /**
     * Component columns form validation
     * @param array $data table data
     * @param array $files table data
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!preg_match("/^\d+%?$/i", trim($data['tablewidth']))) {
            $errors['tablewidth'] = get_string('badtablewidth', 'block_learnerscript');
        }

        return $errors;
    }

}
