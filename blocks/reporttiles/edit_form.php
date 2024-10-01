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
 * Form for editing HTML block instances.
 * @package   block_reporttiles
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_reporttiles_edit_form extends block_edit_form {
    /**
     * Reporttiles block form definition
     * @param object $mform Form object
     */
    protected function specific_definition($mform) {
        global $DB;
        $this->page->requires->js('/blocks/reporttiles/js/jscolor.js', true);

        // Fields for editing HTML block title and contents.

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));
        $reportlist = $DB->get_records_select_menu('block_learnerscript', "global=1 AND type='statistics'", null, '', 'id,name');
        $reportlist[0] = get_string('selectreport', 'block_reporttiles');
        ksort($reportlist);

        $mform->addElement('text', 'config_blocktitle', get_string('blocktitle', 'block_reporttiles'));
        $mform->setType('config_blocktitle', PARAM_TEXT);

        $mform->addElement('select', 'config_reportlist', get_string('listofreports', 'block_reporttiles'), $reportlist);
        $mform->addElement('select', 'config_reporttype', get_string('reporttype', 'block_reporttiles'),
                ['table' => get_string('table', 'block_learnerscript'),
                'bar' => get_string('bar', 'block_learnerscript'),
                'line' => get_string('line', 'block_learnerscript'),
                'column' => get_string('column', 'block_learnerscript'),
                'pie' => get_string('pie', 'block_learnerscript'), ]);
        $durations = ['all' => get_string('all', 'block_reportdashboard'),
        'week' => get_string('week', 'block_reportdashboard'),
        'month' => get_string('month', 'block_reportdashboard'),
        'year' => get_string('year', 'block_reportdashboard'), ];
        $mform->addElement('select', 'config_reportduration', get_string('reportduration', 'block_reportdashboard'), $durations);

        $properties = ['maxbytes' => 512000, 'maxfiles' => 1, 'accepted_types' => ['.jpg', '.jpeg', '.png', '.gif']];
        $mform->addElement('filemanager', 'config_logo', get_string('file'), null, $properties);
        $mform->addElement('select', 'config_tileformat', get_string('tileformat', 'block_reporttiles'),
            ['fill' => get_string('fill', 'block_reporttiles'), 'border' => get_string('border', 'block_reporttiles')]);
        $tilescolourpicker = get_string('tilesbackground', 'block_reporttiles');
        $mform->addElement('text', 'config_tilescolourpicker', $tilescolourpicker,
            ['data-class' => 'jscolor', 'value' => '12445f']);
        $tilestextcolour = get_string('tilestextcolour', 'block_reporttiles');
        $mform->addElement('text', 'config_tilescolour', $tilestextcolour,
            ['data-class' => 'jscolor', 'value' => '000000']);

        $mform->addElement('text', 'config_url', get_string('url', 'block_reporttiles'), ['size' => 100]);
        $mform->setType('config_url', PARAM_TEXT);

    }
    /**
     * Set data for reporttiles block
     * @param object $defaults Values set in the edit form
     */
    public function set_data($defaults) {

        if (!$this->block->user_can_edit() && !empty($this->block->config->title)) {
            // If a title has been set but the user cannot edit it format it nicely.
            $title = $this->block->config->title;
            $defaults->config_title = format_string($title, true, $this->page->context);
            // Remove the title from the config so that parent::set_data doesn't set it.
            unset($this->block->config->title);
        }
        parent::set_data($defaults);
        if (!isset($this->block->config)) {
            $this->block->config = new stdClass();
        }
        if (isset($title)) {
            // Reset the preserved title.
            $this->block->config->title = $title;
        }
    }
}
