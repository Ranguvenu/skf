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
 * Form for creating new report.
 * @package   block_reportdashboard
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
use block_learnerscript\local\ls as ls;
/**
 * Select report to create a new report
 */
class reportselect_form extends moodleform {
    /**
     * Form defination
     */
    public function definition() {
        global $DB, $CFG, $PAGE;

        $mform = $this->_form;
        $reportlist = $DB->get_records_select_menu('block_learnerscript',
            "global=1 AND visible=1 AND type !='statistics'", null, '', 'id,name');
        $reportsdata = $DB->get_field('config_plugins', 'value',
            ['name' => 'dashboardReports']);
        $reports = unserialize($reportsdata);
        $existingreports = [];
        if ($this->_customdata['coursels']) {
            if (!empty($reports)) {
                foreach ($reports as $key => $value) {
                    $existingreports[] = $value['report'];
                }
            }
        }
        ksort($reportlist);
        $mform->addElement('html', html_writer::start_div('dashboard_widgets'));
        $textgroup = [];
        $textgroup[] = &$mform->createElement('static', 'selecttext', '',
        html_writer::tag('span', '', ['class' => 'dashboard_checkbox']));
        $textgroup[] = &$mform->createElement('static', 'reportnametext', '',
        html_writer::tag('span', html_writer::tag('b', 'Report Name'), ['class' => 'dashboard_reportname']));
        $textgroup[] = &$mform->createElement('static', 'reporttypetext', '',
        html_writer::tag('span', html_writer::tag('b', get_string('report_type', 'block_reportdashboard')),
        ['class' => 'widget_reporttype']));
        $textgroup[] = &$mform->createElement('static', 'weighttext', '',
        html_writer::tag('span', html_writer::tag('b', get_string('position', 'block_reportdashboard')),
        ['class' => 'widget_position']));

        $mform->addGroup($textgroup, 'selectreporttext', '');

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
        $rolereports = (new ls)->listofreportsbyrole($this->_customdata['coursels'], false,
            $this->_customdata['parentcheck']);

        foreach ($rolereports as $key => $value) {
            $report = $DB->get_record('block_learnerscript', ['id' => $value['id']]);
            require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
            $reportclassname = 'block_learnerscript\lsreports\report_' . $report->type;
            $properties = new stdClass;
            $reportclass = new $reportclassname($report, $properties);
            $group = [];
            $reportcontenttypes = (new ls)->cr_listof_reporttypes($report->id);

            $group[] = &$mform->createElement('advcheckbox', 'report', null, '',
                ['group' => 1, 'class' => 'listofreports checkbox_name'],
                [null, $value['id']]);
                $group[] = &$mform->createElement('static', 'reportname' . $value['id'], '',
                html_writer::span(trim($value['name']), 'widgetreport_name'));

                $group[] = &$mform->createElement('select',
                get_string('reporttype', 'block_reportdashboard'),
                 get_string('reporttype', 'block_reportdashboard'), $reportcontenttypes,
                 ['class' => 'select-reporttype']);
            $group[] = &$mform->createElement('select', 'wieght' . $i . '', '', $weightoptions, ['class' => 'select-wieght']);
            $mform->addGroup($group, 'selectreport' . $i . '', '');
            if ( in_array($value['id'], $existingreports)) {
                $mform->setDefault('selectreport' . $i . '[report]', true);
            }
            $i++;
        }
        $submitlabel = get_string('addtodashboard', 'block_reportdashboard');
        $this->add_action_buttons(false, $submitlabel);
        $mform->addElement('html', html_writer::end_tag('div'));
    }
}
