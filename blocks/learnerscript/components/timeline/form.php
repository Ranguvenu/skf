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
 * Time line form
 */
class timeline_form extends moodleform {
    /**
     * Time line form defination
     */
    public function definition() {
        $mform = & $this->_form;

        $options = ['previous' => get_string('previousdays', 'block_learnerscript'),
        'fixeddate' => get_string('fixeddate', 'block_learnerscript'), ];
        $mform->addElement('select', 'timemode', get_string('timemode', 'block_learnerscript'), $options);
        $mform->setDefault('timemode', 'previous');

        $mform->addElement('text', 'previousstart', get_string('previousstart', 'block_learnerscript'));
        $mform->setDefault('previousstart', 1);
        $mform->setType('previousstart', PARAM_INT);
        $mform->addRule('previousstart', null, 'numeric', null, 'client');
        $mform->disabledIf('previousstart', 'timemode', 'eq', 'fixeddate');

        $mform->addElement('text', 'previousend', get_string('previousend', 'block_learnerscript'));
        $mform->setDefault('previousend', 0);
        $mform->setType('previousend', PARAM_INT);
        $mform->addRule('previousend', null, 'numeric', null, 'client');
        $mform->disabledIf('previousend', 'timemode', 'eq', 'fixeddate');

        $mform->addElement('checkbox', 'forcemidnight', get_string('forcemidnight', 'block_learnerscript'));
        $mform->disabledIf('forcemidnight', 'timemode', 'eq', 'fixeddate');

        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'block_learnerscript'));
        $mform->setDefault('starttime', time() - 3600 * 48);
        $mform->disabledIf('starttime', 'timemode', 'eq', 'previous');

        $mform->addElement('date_time_selector', 'endtime', get_string('endtime', 'block_learnerscript'));
        $mform->setDefault('endtime', time() + 3600 * 24);
        $mform->disabledIf('endtime', 'timemode', 'eq', 'previous');

        $mform->addElement('text', 'interval', get_string('timeinterval', 'block_learnerscript'));
        $mform->setDefault('interval', 1);
        $mform->setType('interval', PARAM_INT);
        $mform->addRule('interval', null, 'numeric', null, 'client');
        $mform->addRule('interval', null, 'nonzero', null, 'client');

        $mform->addElement('select', 'ordering', get_string('ordering', 'block_learnerscript'),
        ['asc' => 'ASC', 'desc' => 'DESC']);

        $this->add_action_buttons();
    }
    /**
     * Timeline form validation
     * @param object $data Form data
     * @param object $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        return $errors;
    }

}
