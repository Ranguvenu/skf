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
require_once($CFG->dirroot . '/lib/formslib.php');
use block_learnerscript\learnerscript;
/**
 * Formslib template for the new report form
 */
class scheduled_reports_form extends moodleform {

    /**
     * Form defination
     *
     */
    public function definition() {
        global $CFG;
        $mform = &$this->_form;
        $roleslist = [];
        $reportid = $this->_customdata['id'];
        $scheduledreportid = $this->_customdata['scheduleid'];
        foreach ($this->_customdata['roleslist'] as $role) {
            $roleslist[$role['key']] = $role['value'];
        }
        $schusers = $this->_customdata['schusers'];
        $schusersids = $this->_customdata['schusersids'];
        $exportoptions = $this->_customdata['exportoptions'];
        $schedulelist = $this->_customdata['schedulelist'];
        $frequencyselect = $this->_customdata['frequencyselect'];
        if (isset($this->_customdata['AjaxForm']) && $this->_customdata['AjaxForm']
        && isset($this->_customdata['instance'])) {
            $instance = $this->_customdata['instance'];
            $ajaxform = 'AjaxForm';
            $mform->_attributes['id'] = "schform$instance";
            $requireclass = "schformreq$instance";
            $reportinstance = $instance;
            $mform->_attributes['data-instanceid'] = $instance;
        } else {
            $ajaxform = '';
            $requireclass = 'schformelements';
            $reportinstance = $reportid;
            $instance = $reportid;
        }

        $mform->_attributes['class'] = "schform mform $ajaxform schforms$reportinstance";

        $mform->_attributes['data-reportid'] = $reportid;
        $mform->_attributes['data-scheduleid'] = $scheduledreportid;
        $exporttofilesystem = true;
        if (get_config('block_learnerscript', 'exportfilesystem') == 1) {
            $exporttofilesystem = true;
        }
        if ($scheduledreportid > 0) {
            $pagename = 'editscheduledreport';
        } else {
            $pagename = 'addschedulereport';
        }

        $mform->addElement('hidden', 'reportid', $reportid);
        $mform->setType('reportid', PARAM_INT);

        $mform->addElement('hidden', 'scheduleid', $scheduledreportid, ['id' => 'scheduleid']);
        $mform->setType('scheduleid', PARAM_INT);

        $mform->addElement('select', 'role', get_string('role', 'block_learnerscript'),
        $roleslist, [
            'data-select2' => true,
            'id' => 'id_role' . $reportinstance,
            'data-id' => $reportid,
            'data-class' => $requireclass,
            'data-element' => 'role',
            'data-reportid' => $reportid,
            'data-reportinstance' => $reportinstance,
            'data-placeholder' => get_string('selectroles', 'block_learnerscript'),
            'class' => 'schuserroleslist',
        ]);
        $mform->addRule('role', get_string('pleaseselectrole', 'block_learnerscript'),
        'required', null, 'client', false, false);

        $scheduleusers = $mform->addElement('select', 'users_data', get_string('fullname'),
        $schusers, ['data-select2-ajax' => true,
            'data-ajax-url' => new moodle_url('/blocks/learnerscript/ajax.php'),
            'id' => 'id_users_data' . $reportinstance,
            'data-reportid' => $reportid,
            'data-instanceid' => $instance,
            'data-reportinstance' => $reportinstance,
            'data-id' => $reportid,
            'class' => 'schusers_data',
            'data-class' => $requireclass,
            'data-element' => 'users_data',
            'data-placeholder' => get_string('selectusers', 'block_learnerscript'),
        ]);

        $mform->getElement('users_data')->setMultiple('true');
        $mform->addRule('users_data', get_string('PleaseSelectUser', 'block_learnerscript'), 'required', null, 'client');

        $mform->addElement('hidden', 'schuserslist', $schusersids,
        ['class' => 'schuserslist', 'id' => 'schuserslist' . $reportinstance]);
        $mform->setType('schuserslist', PARAM_TEXT);
        $mform->addElement('select', 'exportformat', get_string('export', 'block_learnerscript'), $exportoptions);

        if ($exporttofilesystem) {
            $exporttofilesystemarray = [];
            $exporttofilesystemarray[] = $mform->createElement('radio', 'exporttofilesystem', '',
            get_string('exporttoemail', 'block_learnerscript'), REPORT_EMAIL);
            $exporttofilesystemarray[] = $mform->createElement('radio', 'exporttofilesystem', '',
            get_string('exporttosave', 'block_learnerscript'), REPORT_EXPORT);
            $exporttofilesystemarray[] = $mform->createElement('radio', 'exporttofilesystem', '',
            get_string('exporttoemailandsave', 'block_learnerscript'), REPORT_EXPORT_AND_EMAIL);
            $mform->addGroup($exporttofilesystemarray, 'exporttofilesystem',
            get_string('exportfilesystemoptions', 'block_learnerscript'), ['<br/>'], false);
            $mform->setDefault('exporttofilesystem', REPORT_EXPORT_AND_EMAIL);
            $mform->setType('emailsaveorboth', PARAM_INT);
        } else {
            $mform->addElement('hidden', 'emailsaveorboth', REPORT_EXPORT_AND_EMAIL);
            $mform->setType('emailsaveorboth', PARAM_INT);
        }

        $newscheduledata = [];
        $newscheduledata[] = &$mform->createElement('select', 'frequency',
        get_string('schedule', 'block_learnerscript'), $frequencyselect,
        ['id' => 'id_frequency' . $reportinstance,
        'data-id' => $reportid,
        'data-class' => $requireclass,
        'data-element' => 'frequency',
        'data-reportinstance' => $reportinstance,
        'class' => 'frequencydate',
        ]);

        $newscheduledata[] = &$mform->createElement('select', 'schedule',
        get_string('updatefrequency', 'block_learnerscript'), $schedulelist,
        ['id' => 'id_updatefrequency' . $reportinstance, 'data-class' => $requireclass,
            'data-element' => 'schedule', ]);
        $mform->addGroup($newscheduledata, 'dependency', get_string('dependency', 'block_learnerscript'), [' '], false);

        $schfrequencyrules = [];
        $schfrequencyrules['frequency'][] = [get_string('err_required', 'form'), 'required', null, 'client'];
        $schfrequencyrules['schedule'][] = [get_string('err_required', 'form'), 'required', null, 'client'];
        $mform->addGroupRule('dependency', $schfrequencyrules);
        $btnstring = get_string('schedule', 'block_learnerscript');
        $btnstring1 = get_string('cancel');
        $this->add_action_buttons($btnstring1, $btnstring);
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
        return $errors;
    }

}
