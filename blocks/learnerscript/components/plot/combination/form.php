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
 * Combination grpah form
 */
class combination_form extends moodleform {
    /**
     * Combination form defination
     *
     * @return void
     */
    public function definition() {
        global $OUTPUT;

        $mform = & $this->_form;
        $report = $this->_customdata['report'];
        $cid = $this->_customdata['cid'];

        $components = (new block_learnerscript\local\ls)->cr_unserialize($this->_customdata['report']->components);

        if (!is_object($components) || empty($components->columns->elements)) {
            throw new moodle_exception('nocolumns', 'block_learnerscript');
        }
        $columns = $components->columns->elements;
        foreach ($columns as $c) {
            if ($report->type != 'sql') {
                if ($c->formdata->column == 'numviews'
                && !in_array($c->formdata->column, $this->_customdata['reportclass']->orderable)) {
                    $views = "numviews";
                    array_push($this->_customdata['reportclass']->orderable, $views);
                }
                if (in_array($c->formdata->column, $this->_customdata['reportclass']->orderable)) {
                    $options[$c->formdata->column] = $c->formdata->columname;
                }
            } else {
                $options[$c['formdata']->column] = $c['formdata']->columname;
            }
        }
        $mform->addElement('text', 'chartname', get_string('chartname', 'block_learnerscript'));
        $mform->setType('chartname', PARAM_RAW);
        $mform->addRule('chartname', get_string('chartnamerequired', 'block_learnerscript'), 'required', null, 'client');

        $mform->addElement('select', 'serieid', get_string('serieid', 'block_learnerscript'),
        [null => get_string('choose')] + $options);
        $mform->addRule('serieid', null, 'required', null, 'client');

        $mform->addElement('select', 'yaxis_line', get_string('yaxis_line', 'block_learnerscript'),
        [null => get_string('choose')] + $options, ['data-select2' => true, 'multiple' => 'multiple',
        'onChange' => '(function(e) { require("block_learnerscript/report").AddExpressions(e, " ")})(event);', ]);
        $mform->addRule('yaxis_line', null, 'required', null, 'client');
        $mform->addElement('select', 'yaxis_bar', get_string('yaxis_bar', 'block_learnerscript'),
        [null => get_string('choose')] + $options,
        ['data-select2' => true, 'multiple' => 'multiple',
        'onChange' => '(function(e) { require("block_learnerscript/report").AddExpressions(e, "yaxisbarvalue")})(event);', ]);
        $mform->addRule('yaxis_bar', null, 'required', null, 'client');

        $sortby = [];
        $ajaxformdata = $this->_ajaxformdata;
        if (empty($ajaxformdata)) {
            $elements = isset($components->plot->elements) ?
                                $components->plot->elements : [];
            if ($elements) {
                foreach ($elements as $e) {
                    if ($e->id == $cid) {
                        $conditions = '';
                        $conditions1 = '';
                        $conditionsymbols = ["=", ">", "<", ">=", "<=", "<>"];
                        foreach ($e->formdata->yaxis_line as $k => $column) {
                            $columndata['name'] = $column;
                            $columndata['symbol'] = $e->formdata->yaxis_line[$k];
                            $columndata['conditionsymbols'] = [];
                            if (!isset($e->formdata->{$column})) {
                                $columndata['disabled'] = 'disabled';
                            } else {
                                $columndata['disabled'] = false;
                                $columndata['value'] = $e->formdata->{$column.'_value'};
                            }
                            foreach ($conditionsymbols as $value) {
                                if (!empty($e->formdata->{$column})) {
                                    if ($value == $e['formdata']->{$column}) {
                                        $columndata['conditionsymbols'][] = ['value' => $value, 'selected' => true];
                                    } else {
                                        $columndata['conditionsymbols'][] = ['value' => $value];
                                    }
                                } else {
                                    $columndata['conditionsymbols'][] = ['value' => $value];
                                }
                            }

                            $conditions1 .= $OUTPUT->render_from_template('block_learnerscript/plotconditions',
                                           ['column' => $columndata]);
                        }
                        $mform->addElement('html',  $conditions1);

                        foreach ($e->formdata->yaxis_bar as $k => $column) {
                            $columndata['name'] = $column;
                            $columndata['symbol'] = $e->formdata->yaxis_bar[$k];
                            $columndata['conditionsymbols'] = [];
                            if (!isset($e->formdata->{$column})) {
                                $columndata['disabled'] = 'disabled';
                            } else {
                                $columndata['disabled'] = false;
                                $columndata['value'] = $e->formdata->{$column.'_value'};
                            }
                            foreach ($conditionsymbols as $value) {
                                if (!empty($e->formdata->{$column})) {
                                    if ($value == $e['formdata']->{$column}) {
                                        $columndata['conditionsymbols'][] = ['value' => $value, 'selected' => true];
                                    } else {
                                        $columndata['conditionsymbols'][] = ['value' => $value];
                                    }
                                } else {
                                    $columndata['conditionsymbols'][] = ['value' => $value];
                                }
                            }
                            $conditions .= $OUTPUT->render_from_template('block_learnerscript/plotconditions',
                                           ['column' => $columndata]);
                        }
                        $mform->addElement('html',  $conditions);
                        break;
                    }
                }
            }
        }

        $mform->addElement('html', html_writer::div('', 'yaxis1'));
        $mform->addElement('html', html_writer::div('', 'yaxis_bar1'));

        $mform->addElement('advcheckbox', 'showlegend', get_string('showlegend', 'block_learnerscript'), '', null, [0, 1]);

        $mform->addElement('advcheckbox', 'datalabels', get_string('datalabels', 'block_learnerscript'), '', null, [0, 1]);
        $sortby[] = $mform->createElement('select', 'columnsort', '', $options);
        $mform->setType('columnsort', PARAM_RAW);
        $sortby[] = $mform->createElement('select', 'sorting', '', [null => '--SELECT--',
                                    'ASC' => 'ASC', 'DESC' => 'DESC', ]);
        $mform->setType('sorting', PARAM_RAW);
        $mform->addGroup($sortby, 'sortby', get_string('sortby', 'block_learnerscript'),
                                ['&nbsp;&nbsp;&nbsp;'], false);
        $mform->setType('sortby', PARAM_RAW);

        $mform->addElement('select', 'limit', get_string('limit', 'block_learnerscript'),
                        [null => '--SELECT--', 10 => 10, 20 => 20, 50 => 50, 100 => 100]);
        $this->add_action_buttons(true, get_string('add'));
    }
    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((isset($data['yaxis_bar']) && isset($data['yaxis_line'])) && array_intersect($data['yaxis_bar'], $data['yaxis_line'])) {
            $errors['yaxis_bar'] = get_string('barlinecolumnsequal', 'block_learnerscript');
        }
        return $errors;
    }
}
