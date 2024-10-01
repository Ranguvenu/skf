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

namespace block_learnerscript\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;
use block_learnerscript\local\ls;

/**
 * Report columns design page
 *
 * @package    block_learnerscript
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class design implements renderable, templatable {

    /** @var string $reporttype  */
    public $reporttype;

    /** @var int $reportid  */
    public $reportid;

    /** @var object $report  */
    public $report;
    /**
     * Construct
     * @param  array $report Report
     * @param  int $reportid Report ID
     */
    public function __construct($report, $reportid) {
        $this->reporttype = $report->type;
        $this->reportid = $reportid;
        $this->report = $report;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param  \renderer_base $output
     *
     * @return stdclass
     */
    public function export_for_template(renderer_base $output) {
        global $OUTPUT, $CFG;
        $data = new stdClass();
        $comp = '';
        $data->sqlreporttype = $this->reporttype == 'sql' ? true : false;
        require_once($CFG->dirroot . '/blocks/learnerscript/components/conditions/component.class.php');
        $elements = (new ls)->cr_unserialize($this->report->components);
        $elements = isset($elements->$comp->elements) ? $elements->$comp->elements : [];
        $componentclassname = 'component_conditions';
        $compclass = new $componentclassname($this->report->id);
        $plugins = get_list_of_plugins('blocks/learnerscript/components/conditions');
        $optionsplugins = [];
        if ($compclass->plugins) {
            $currentplugins = [];
            if ($elements) {
                foreach ($elements as $e) {
                    $currentplugins[] = $e->pluginname;
                }
            }

            foreach ($plugins as $p) {
                require_once($CFG->dirroot . '/blocks/learnerscript/components/conditions/' . $p . '/plugin.class.php');
                $pluginclassname = 'block_learnerscript\lsreports\plugin_' . $p;
                $pluginclass = new $pluginclassname($this->report);
                if (in_array($this->reporttype, $pluginclass->reporttypes)) {
                    if ($pluginclass->unique && in_array($p, $currentplugins)) {
                        continue;
                    }
                    $optionsplugins[$p] = get_string($p, 'block_learnerscript');
                }
            }
        }
        $data->enableconditions = empty($optionsplugins) ? false : true;
        $data->loading = $OUTPUT->image_url('loading', 'block_learnerscript');
        $data->reportid = $this->reportid;
        $data->reporttype = $this->reporttype == 'userprofile' || $this->reporttype == 'courseprofile' ? false : true;
        $debug = optional_param('debug', false, PARAM_BOOL);
        if ($debug) {
            $data->debugdisplay = true;
        }
        $data->params = $_SERVER['QUERY_STRING'];
        return $data;
    }
}
