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
 * Line graph
 */
class line_form extends moodleform {
    /**
     * Line graph defination
     *
     */
    public function definition() {
        $mform = & $this->_form;
        $cid = $this->_customdata['cid'];
        $components = (new ls)->cr_unserialize($this->_customdata['report']->components);

        if (!is_array($components) || empty($components['columns']['elements'])) {
            throw new moodle_exception('nocolumns', 'block_learnerscript');
        }
        $columns = $components['columns']['elements'];
        foreach ($columns as $c) {
            $options[$c['formdata']->column] = $c['formdata']->columname;
        }
        $mform->addElement('text', 'chartname', get_string('chartname', 'block_learnerscript'));
        $mform->setType('chartname', PARAM_RAW);
        $mform->addRule('chartname', get_string('chartnamerequired', 'block_learnerscript'),
                            'required', null, 'client');

        $mform->addElement('select', 'serieid', get_string('serieid', 'block_learnerscript'),
        [0 => get_string('choose')] + $options);
        $mform->addRule('serieid', null, 'required', null, 'client');

        $mform->addElement('select', 'yaxis', get_string('yaxis', 'block_learnerscript'),
                            $options, ['multiple' => 'multiple']);
        $mform->addRule('yaxis', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'showlegend', get_string('showlegend', 'block_learnerscript'), '', null, [0, 1]);

        $mform->addElement('advcheckbox', 'datalabels', get_string('datalabels', 'block_learnerscript'), '', null, [0, 1]);

        $mform->addElement('select', 'calcs', get_string('calcs', 'block_learnerscript'),
                            [null => '--SELECT--', 'average' => 'Average', 'max' => 'Max',
                                'min' => 'Min', 'sum' => 'Sum', ]);

        $sortby = [];
        $ajaxformdata = $this->_ajaxformdata;
        $selectedcalc = false;
        if (empty($ajaxformdata)) {
            $elements = isset($components['plot']['elements']) ?
                                $components['plot']['elements'] : [];
            if ($elements) {
                foreach ($elements as $e) {
                    if ($e['id'] == $cid) {
                        $selectedcalc = (isset($e['formdata']->calcs) && !empty($e['formdata']->calcs)) ? true : false;
                        break;
                    }
                }
            }
        } else {
            $selectedcalc = $ajaxformdata['calcs'];
        }
        if ($selectedcalc) {
            $sortby[] = $mform->createElement('select', 'columnsort', '', $options, ['disabled' => $disabled]);
            $mform->setType('columnsort', PARAM_RAW);
            $sortby[] = $mform->createElement('select', 'sorting', '', [null => '--SELECT--',
                                        'ASC' => 'ASC', 'DESC' => 'DESC', ], ['disabled' => $disabled]);
            $mform->setType('sorting', PARAM_RAW);
            $mform->addGroup($sortby, 'sortby', get_string('sortby', 'block_learnerscript'),
                                    ['&nbsp;&nbsp;&nbsp;'], false);
            $mform->setType('sortby', PARAM_RAW);

            $mform->addElement('select', 'limit', get_string('limit', 'block_learnerscript'),
                            [null => '--SELECT--', 10 => 10, 20 => 20, 50 => 50, 100 => 100], ['disabled' => $disabled]);
        } else {
            $sortby[] = $mform->createElement('select', 'columnsort', '', $options);
            $mform->setType('columnsort', PARAM_RAW);
            $sortby[] = $mform->createElement('select', 'sorting', '', [null => '--SELECT--', 'ASC' => 'ASC', 'DESC' => 'DESC']);
            $mform->setType('sorting', PARAM_RAW);
            $mform->addGroup($sortby, 'sortby', get_string('sortby', 'block_learnerscript'),
                                    ['&nbsp;&nbsp;&nbsp;'], false);
            $mform->setType('sortby', PARAM_RAW);

            $mform->addElement('select', 'limit', get_string('limit', 'block_learnerscript'),
                            [null => '--SELECT--', 10 => 10, 20 => 20, 50 => 50, 100 => 100]);
        }
        $this->add_action_buttons(true, get_string('add'));
    }
    /**
     * Line graph form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['serieid'])) {
            $errors['serieid'] = get_string('supplyvalue', 'block_learnerscript');
        }
        if (!empty($data['serieid']) && $data['serieid'] == $data['yaxis'][0]) {
            $errors['yaxis'] = get_string('xandynotequal', 'block_learnerscript');
        }
        return $errors;
    }
}
