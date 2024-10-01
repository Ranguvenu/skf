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
use block_learnerscript\local\ls;
require_once($CFG->libdir . '/formslib.php');
/**
 * Parent category form
 */
class parentcategory_form extends moodleform {
    /**
     * Parent category form defination
     */
    public function definition() {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $mform = & $this->_form;

        $mform->addElement('header', 'crformheader', get_string('coursefield', 'block_learnerscript'), '');

        $options = [get_string('top')];
        $parents = [];
        (new ls)->cr_make_categories_list($options, $parents);
        $mform->addElement('select', 'categoryid', get_string('category'), $options);

        $mform->addElement('checkbox', 'includesubcats', get_string('includesubcats', 'block_learnerscript'));
        $this->add_action_buttons(true, get_string('add'));
    }

}
