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
use block_learnerscript\local\ls;
/**
 * Worl graph form
 */
class worldmap_form extends moodleform {
    /**
     * Worl graph defination
     *
     * @return void
     */
    public function definition() {
        $mform = &$this->_form;
        $options = [];
        $report = $this->_customdata['report'];
        $components = (new ls)->cr_unserialize($this->_customdata['report']->components);

        if (!is_object($components) || empty($components->columns->elements)) {
            throw new moodle_exception('nocolumns', 'block_learnerscript');
        }

        $columns = $components->columns->elements;
        foreach ($columns as $c) {
            $options[$c->formdata->column] = $c->formdata->columname;
        }

        $mform->addElement('text', 'chartname', get_string('chartname', 'block_learnerscript'));
        $mform->addRule('chartname', get_string('chartnamerequired', 'block_learnerscript'), 'required', null, 'client');
        $mform->setType('chartname', PARAM_RAW);
        $mform->addElement('select', 'areaname', get_string('worldmapareaname', 'block_learnerscript'), $options);
        $mform->addElement('select', 'areavalue', get_string('worldmapareavalue', 'block_learnerscript'), $options);
        $mform->addElement('text', 'serieslabel', get_string('serieslabel', 'block_learnerscript'));
        $mform->setType('serieslabel', PARAM_RAW);
        $mform->addElement('advcheckbox', 'showlegend', get_string('showlegend', 'block_learnerscript'), '', null, [0, 1]);
        $mform->addElement('advcheckbox', 'datalabels', get_string('datalabels', 'block_learnerscript'), '', null, [0, 1]);
        $this->add_action_buttons(true, get_string('add'));
    }

}
