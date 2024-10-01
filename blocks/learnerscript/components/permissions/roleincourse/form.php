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
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->dirroot . '/blocks/learnerscript/lib.php');

/**
 * Role in course form
 */
class roleincourse_form extends moodleform {

    /**
     * definition
     */
    public function definition() {
        global $DB, $USER, $CFG, $PAGE;
        $PAGE->requires->js_call_amd('block_learnerscript/helper', 'rolepermissions',
                                    [[],
                                ]);

        $mform = & $this->_form;
        $cid = $this->_customdata['cid'];
        $pluginclass = $this->_customdata['pluginclass'];
        $reportclass = $this->_customdata['reportclass'];
        $comp = $this->_customdata['comp'];
        $compclass = $this->_customdata['compclass'];

        if (!empty($reportclass->componentdata->$comp->elements)) {
            foreach ($reportclass->componentdata->$comp->elements as $p) {
                if ($p->id == $cid) {
                    $contextlevel = $p->formdata->contextlevel;
                }
            }
        }
        $mform->addElement('header', 'crformheader', get_string('roleincourse', 'block_learnerscript'), '');

        $levels = context_helper::get_all_levels();

        $validlevels = array_filter($levels, function($level){
            if ($level != CONTEXT_BLOCK && $level != CONTEXT_MODULE && $level != CONTEXT_USER) {
                return true;
            }
        }, ARRAY_FILTER_USE_KEY);

        foreach ($validlevels as $level => $classname) {
            $allcontextlevels[$level] = context_helper::get_level_name($level);
        }
        $reportid = $this->_customdata['pluginclass']->report->id;
        $mform->addElement('select', 'contextlevel', get_string('contextid', 'block_learnerscript'), $allcontextlevels,
            ['data-reportid' => $reportid,
            'class' => 'rolepermissionsform']
            );

        if ($cid) {
            $userroles = block_learnerscript_get_roles_in_context($contextlevel);
        } else {
            $userroles = block_learnerscript_get_roles_in_context(CONTEXT_SYSTEM);
        }
        $mform->addElement('select', 'roleid', get_string('roles'), $userroles);

        $this->add_action_buttons(true, get_string('add'));
    }

}
