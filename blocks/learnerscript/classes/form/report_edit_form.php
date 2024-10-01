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

namespace block_learnerscript\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
use moodleform;
use block_learnerscript\local\ls;

/**
 * A Moodle block to create customizable reports.
 *
 * @package    block_learnerscript
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_edit_form extends moodleform {

    /**
     * Form definition
     */
    public function definition() {
        global $CFG;

        $adminmode = optional_param('adminmode', null, PARAM_INT);

        $mform = &$this->_form;
        $mform->addElement('header', 'general', get_string('report'));
        $mform->addElement('text', 'name', get_string('name'), ['maxlength' => 60, 'size' => 58]);
        $mform->addRule('name', get_string('spacevalidation', 'block_learnerscript'), 'regex', "/\S{1}/", 'client');
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_NOTAGS);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $typeoptions = (new ls)->cr_get_report_plugins($this->_customdata['courseid']);

        $eloptions = [];
        if (isset($this->_customdata['report']->id) && $this->_customdata['report']->id) {
            $eloptions = ['disabled' => 'disabled'];
        }
        $select = $mform->addElement('select', 'type', get_string("typeofreport", 'block_learnerscript'), $typeoptions, $eloptions);
        $mform->addHelpButton('type', 'typeofreport', 'block_learnerscript');
        $select->setSelected('sql');

        $mform->addElement('textarea', 'querysql', get_string('querysql', 'block_learnerscript'), 'rows="15" cols="80"');
        $selectedoptions = ['sql', 'statistics'];
        $querysqloptions = array_diff(array_keys($typeoptions), $selectedoptions);
        $querysqloptions1 = implode('|', $querysqloptions);

        $mform->disabledIf('querysql', 'type', 'in', $querysqloptions1);
        $mform->addElement('header', 'advancedoptions', get_string('advanced'));
        $mform->addElement('editor', 'description', get_string('summary'));
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('checkbox', 'global', get_string('global', 'block_learnerscript'),
         get_string('enableglobal', 'block_learnerscript'));
        $mform->addHelpButton('global', 'global', 'block_learnerscript');
        $mform->setDefault('global', 1);

        $mform->addElement('checkbox', 'disabletable',
            get_string('disabletable', 'block_learnerscript'),
            get_string('enabletable', 'block_learnerscript'));
        $mform->setDefault('disabletable', 0);
        if (isset($this->_customdata['report']->id) && $this->_customdata['report']->id) {
            $mform->addElement('hidden', 'id', $this->_customdata['report']->id);
        }

        $mform->setType('id', PARAM_INT);
        if (!empty($adminmode)) {
            $mform->addElement('text', 'courseid', get_string("setcourseid", 'block_learnerscript'),
                $this->_customdata['courseid']);
            $mform->setType('courseid', PARAM_INT);
        } else {
            $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
            $mform->setType('courseid', PARAM_INT);
        }
        $mform->setExpanded('advancedoptions', false);

        $this->add_action_buttons(true, get_string('next'));
    }

    /**
     * Form validation
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['type'] === 'sql' || $data['type'] === 'statistics') {
            $errors['querysql'] = get_string('required');
        }
        if (get_config('block_learnerscript', 'sqlsecurity')) {
            return $this->validation_high_security($data, $files);
        } else {
            return $this->validation_low_security($data, $files);
        }
    }

    /**
     * Form high security validation
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation_high_security($data, $files) {
        global $CFG;

        $errors = parent::validation($data, $files);
        if ($data['type'] === 'sql' || $data['type'] === 'statistics') {
            $sql = $data['querysql'];
        } else {
            $sql = "";
        }
        $sql = trim($sql);
        if (empty($data['querysql']) && ($data['type'] == 'sql' || $data['type'] == 'statistics')) {
            $errors['querysql'] = get_string('required');
        }
        // Simple test to avoid evil stuff in the SQL.
        if (preg_match('/\b(ALTER|CREATE|DELETE|DROP|
        GRANT|INSERT|INTO|TRUNCATE|UPDATE|SET|VACUUM|REINDEX|DISCARD|LOCK)\b/i', $sql)) {
            $errors['querysql'] = get_string('notallowedwords', 'block_learnerscript');

            // Do not allow any semicolons.
        } else if (strpos($sql, ';') !== false) {
            $errors['querysql'] = get_string('nosemicolon', 'block_learnerscript');

            // Make sure prefix is prefix_, not explicit.
        } else if ($CFG->prefix != '' && preg_match('/\b' . $CFG->prefix . '\w+/i', $sql)) {
            $errors['querysql'] = get_string('noexplicitprefix', 'block_learnerscript');

            // Now try running the SQL, and ensure it runs without errors.
        }
        return $errors;
    }

    /**
     * Form low security validation
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation_low_security($data, $files) {
        $errors = parent::validation($data, $files);

        $sql = $data['querysql'];
        $sql = trim($sql);
        if (empty($this->_customdata['report']->runstatistics) || $this->_customdata['report']->runstatistics == 0) {
            // Simple test to avoid evil stuff in the SQL.
            if (preg_match('/\b(ALTER|DELETE|DROP|GRANT|TRUNCATE|UPDATE|SET|VACUUM|REINDEX|DISCARD|LOCK)\b/i', $sql)) {
                $errors['querysql'] = get_string('notallowedwords', 'block_learnerscript');
            }

            // Now try running the SQL, and ensure it runs without errors.
        }
        return $errors;
    }
}
