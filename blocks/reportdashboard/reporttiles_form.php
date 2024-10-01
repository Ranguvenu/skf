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
 * Form for create the statistic report
 * @package   block_reportdashboard
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
use block_learnerscript\local\ls as ls;
/**
 * Select report to create a statistic report
 */
class reporttiles_form extends moodleform {
    /**
     * Form defination
     */
    public function definition() {
        global $DB, $CFG, $PAGE;

        $mform = $this->_form;

        $reportsdata = $DB->get_field('config_plugins', 'value', ['name' => 'dashboardTiles']);
        $reports = unserialize($reportsdata);
        if ($this->_customdata['coursels']) {
            if (!empty($reports)) {
                foreach ($reports as $key => $value) {
                    $existingreports[] = $value['report'];
                }
            }
        }
        $mform->addElement('html', html_writer::start_tag('div', ['class' => 'dashboard_widgets']));
        $textgroup = [];
        $textgroup[] = &$mform->createElement('static', 'selecttext', '',
        html_writer::tag('span', '', ['class' => 'dashboard_checkbox']));
        $textgroup[] = &$mform->createElement('static', 'reportnametext', '',
        html_writer::tag('span', html_writer::tag('b', get_string('report_name', 'block_reportdashboard')),
        ['class' => 'dashboard_reportname']));
        $textgroup[] = &$mform->createElement('static', 'reporttypetext', '',
        html_writer::tag('span', html_writer::tag('b', get_string('report_type', 'block_reportdashboard')),
        ['class' => 'widget_reporttype']));
        $textgroup[] = &$mform->createElement('static', 'wieghttext', '',
        html_writer::tag('span', html_writer::tag('b', get_string('position', 'block_reportdashboard')),
        ['class' => 'widget_position']));
        $mform->addGroup($textgroup, 'selectreporttext', '');

        $group1 = [];
        $group1[] = &$mform->createElement('advcheckbox', 'tilesall', null, ' ',
           ['group' => 1, 'class' => 'tiles-all', 'title' => 'Select All'], [0, 1]);
        $group1[] = &$mform->createElement('static', 'all', null, 'All');
        $weightoptions = [];
        for ($i = -block_manager::MAX_WEIGHT; $i <= block_manager::MAX_WEIGHT; $i++) {
            $weightoptions[$i] = $i;
        }
        $first = -10;
        $weightoptions[$first] = get_string('bracketfirst', 'block', $first);
        $last = end($weightoptions);
        $weightoptions[$last] = get_string('bracketlast', 'block', $last);
        array_shift($weightoptions);
        $i = 0;
        $staticreportlist = (new ls)->listofreportsbyrole(false, $statistics = true);
        foreach ($staticreportlist as $key => $value) {
            $group = [];

            $group[] = &$mform->createElement('advcheckbox', 'report', null, '',
                ['group' => 1, 'class' => 'static_listofreports'],
                [null, $value['id']]);
            $group[] = &$mform->createElement('static', 'reportname' . $i . '', '',
                html_writer::tag('span', $value['name'], ['class' => 'titlereport_name']));

            $group[] = &$mform->createElement('select', get_string('reporttype',
                'block_reportdashboard'), get_string('reporttype', 'block_reportdashboard'),
            ['table' => 'Table', 'bar' => 'Bar', 'line' => 'Line', 'column' => 'Column',
                'solidgauge' => 'Solid Guage', 'pie' => 'Pie', ],
            ['class' => 'select-reporttype']);
            $group[] = &$mform->createElement('select', 'wieght' . $i . '',
                get_string('durationminutes', 'calendar'), $weightoptions,
                ['class' => 'select-wieght']);
            if ($this->_customdata['coursels'] && in_array($value['id'], $existingreports)) {
                $mform->setDefault('selectreport' . $i . '[report]', true);
            }
            $mform->addGroup($group, 'selectreport' . $i . '', '');
            $i++;
        }

        $submitlabel = get_string('addtodashboard', 'block_reportdashboard');
        $this->add_action_buttons(false, $submitlabel);

        $mform->addElement('html', html_writer::end_tag('div'));
    }
}
