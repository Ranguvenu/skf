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

namespace block_learnerscript\local;

use stdClass;

/**
 * A Moodle block to create customizable reports.
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pluginbase {

    /**
     * @var $fullname
     */
    public $fullname = '';

    /**
     * @var $type
     */
    public $type = '';

    /**
     * @var object $report
     */
    public $report = null;

    /**
     * @var $form
     */
    public $form = false;

    /**
     * @var $cache
     */
    public $cache = [];

    /**
     * @var $unique
     */
    public $unique = false;

    /**
     * @var $required
     */
    public $required = false;

    /**
     * @var $reporttypes
     */
    public $reporttypes = [];

    /**
     * @var $colformat
     */
    public $colformat = false;

    /**
     * @var object $reportclass
     */
    public $reportclass;

    /**
     * @var $rolewisecourses
     */
    public $rolewisecourses = '';

    /**
     * @var int $reportinstance
     */
    public $reportinstance;
    /**
     * @var object $reportfilterparams
     */
    public $reportfilterparams;
    /**
     * @var int $role
     */
    public $role;
    /**
     * @var bool $downloading
     */
    public $downloading;

    /**
     * Construct
     * @param  object $report Report data
     */
    public function __construct($report) {
        global $DB, $CFG;

        if (is_numeric($report)) {
            $this->report = $DB->get_record('block_learnerscript', ['id' => $report]);
        } else {
            $this->report = $report;
        }
        if (!empty($this->report->type) && $this->report->type) {
            require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $this->report->type . '/report.class.php');
            $reportclassname = 'block_learnerscript\lsreports\report_' . $this->report->type;
            $properties = new stdClass;
            $this->reportclass = new $reportclassname($this->report, $properties);
        }

        $this->init();

    }

    /**
     * Report columns summary
     * @param  object $data Report data
     * @return string
     */
    public function summary($data) {
        return '';
    }
    // Should be override.
    /**
     * Init function
     * @return string
     */
    public function init() {
        return '';
    }
}
