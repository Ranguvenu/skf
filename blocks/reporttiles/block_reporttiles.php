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
 * Report Tiles for dashboard block instances.
 * @package  block_reporttiles
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use block_learnerscript\local\ls as ls;
/**
 * Report tiles block class
 */
class block_reporttiles extends block_base {
    /**
     * Initialize
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_reporttiles');
    }
    /**
     * Reporttiles configuration
     * @return bool
     */
    public function has_config() {
        return false;
    }
    /**
     * Tiles instance allow multiple configurations
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }
    /**
     * Get all required javascript files
     */
    public function get_required_javascript() {
        global $COURSE;
        $blockinstance = unserialize(base64_decode($this->instance->configdata));
        $styletilescolour = (isset($blockinstance->tilescolour)) ? "style=color:#$blockinstance->tilescolour" : '';
        $reportlist = isset($this->config->reportlist) ? $this->config->reportlist : '';
        $reporttype = isset($this->config->reporttype) ? $this->config->reporttype : '';
        $this->page->requires->js('/blocks/reporttiles/js/jscolor.min.js');
        $this->page->requires->js(new moodle_url('https://learnerscript.com/wp-content/plugins/learnerscript/js/highcharts.js'));
        $this->page->requires->js_call_amd('block_learnerscript/reportwidget', 'CreateDashboardTile',
                                               [['reportid' => $reportlist,
                                                            'reporttype' => $reporttype,
                                                            'blockinstanceid' => $this->instance->id,
                                                            'courseid' => $COURSE->id,
                                                            'styletilescolour' => $styletilescolour,  ], ]);
        $this->page->requires->css('/blocks/learnerscript/css/select2/select2.min.css');
    }
    /**
     * Displays in all applicable formats
     * @return array
     */
    public function applicable_formats() {
        if (is_siteadmin()) {
            return ['site' => true, 'my' => true];
        } else {
            return [];
        }
    }
    /**
     * Tile block specialization
     */
    public function specialization() {
        $newreportblockstring = get_string('newreporttileblock', 'block_reporttiles');
        $reporttile = get_string('reporttile', 'block_reporttiles');
        $this->title = isset($this->config->title) ? format_string($reporttile) : format_string($newreportblockstring);
    }
    /**
     * Hide header
     * @return bool
     */
    public function hide_header() {
        return true;
    }
    /**
     * Reporttile block instance configuration save
     * @param object $data Reporttiles data
     * @param bool $nolongerused
     */
    public function instance_config_save($data, $nolongerused = false) {
        global $DB;
        $blockcontext = context_block::instance($this->instance->id);
        file_save_draft_area_files($data->logo, $blockcontext->id, 'block_reporttiles', 'reporttiles',
            $data->logo, ['maxfiles' => 1]);
        $DB->set_field('block_instances', 'configdata', base64_encode(serialize($data)),
            ['id' => $this->instance->id]);
    }
    /**
     * Get content for reporttile block
     * @return stdclass
     */
    public function get_content() {
        global $CFG , $DB , $OUTPUT;

        require_once($CFG->dirroot . '/blocks/reporttiles/lib.php');

        $output = $this->page->get_renderer('block_reporttiles');
        $reporttileslib = new block_reporttiles_reporttiles();
        $ls = new ls;
        $this->page->requires->jquery_plugin('ui-css');
        if ($this->content !== null) {
            return $this->content;
        }

        $themename = '';
        $themename = $this->page->theme->name;
        $reporttileclass = '';
        $themelist = ['lambda', 'adaptable', 'academi', 'moove', 'boost', 'classic', 'remui'];
        if (in_array($themename, $themelist)) {
            $reporttileclass = $themename ? $themename."_reporttiles" : '';
        }
        $dashboardurl = optional_param('dashboardurl', '', PARAM_TEXT);
        if ($dashboardurl == 'Geographical') {
            $reporttileclass .= ' geographical_tiles';
        }
        $filteropt = new stdClass();
        $filteropt->overflowdiv = true;
        $this->content = new stdClass;
        $this->content->footer = '';
        $this->content->text = "";
        if (isset($this->config->reportlist) && $this->config->reportlist &&
            $DB->record_exists('block_learnerscript', ['id' => $this->config->reportlist, 'visible' => 1])) {

            $blockinstanceid = $this->instance->id;
            $blockinstance = unserialize(base64_decode($this->instance->configdata));
            $styletilescolour = (isset($blockinstance->tilescolour)) ? "style=color:#$blockinstance->tilescolour;" : '';
            $instanceurlcheck = (isset($blockinstance->url)) ? $blockinstance->url : '';

            if ($instanceurlcheck) {
                $tilewithlink = 'reporttile_with_link';
            } else {
                $tilewithlink = '';
            }
            $reportid = $this->config->reportlist;
            $reportclass = $ls->create_reportclass($reportid);
            if (!empty($blockinstance->logo)) {
                $logo = $reporttileslib->reporttiles_icon($blockinstance->logo, $this->instance->id, $reportclass->config->name);
            } else {
                $logo = $OUTPUT->image_url('sample_reporttile', 'block_reporttiles');
            }
            $report = $ls->cr_get_reportinstance($this->config->reportlist);

            if (isset($report) && !$report->global) {
                $this->content->text .= '';
            } else if (isset($this->config->reportlist)) {
                $pickedcolor = isset($blockinstance->tilescolourpicker) ? $blockinstance->tilescolourpicker : '#FFF';
                $configtitle = $DB->get_field('block_learnerscript', 'name', ['id' => $this->config->reportlist]);
                if (strlen($configtitle) > 35) {
                    $configtitlefullname = substr($configtitle, 0, 35) . '...';
                } else {
                    $configtitlefullname = $configtitle;
                }
            }
            $configtitlefullname = isset($configtitlefullname) ? $configtitlefullname : '';
            $configtitle = isset($configtitle) ? $configtitle : '';
            $this->config->reporttype == 'table' ? $tableformat = true : $tableformat = false;
            $reportduration = (isset($this->config->reportduration) && !empty($this->config->reportduration))
                                ? $this->config->reportduration : 'all';
            $blocktitle = !empty($this->config->blocktitle) ? $this->config->blocktitle : '';
            $startduration = 0;
            switch ($reportduration) {
                case 'week':
                    $startduration = strtotime("-1 week");
                    break;
                case 'month':
                    $startduration = strtotime("-1 month");
                    break;
                case 'year':
                    $startduration = strtotime("-1 year");
                    break;
                default:
                    $startduration = 0;
                    break;
            }

            $reporttileformat = isset($this->config->tileformat) ? $this->config->tileformat : '';
            $reporttileformat == 'fill' ? $colorformat = "style = background:#" . $pickedcolor :
            $colorformat = "style=border-bottom-color:#$pickedcolor";
            $helpimg = $CFG->wwwroot;
            $durations = !empty($durations) ? $durations : 0;
            $configtitlefullname = !empty($blocktitle) ? $blocktitle : $configtitlefullname;
            $reporttiles = new \block_reporttiles\output\reporttile(
                                                        ['styletilescolour' => $styletilescolour,
                                                        'tile_with_link' => $tilewithlink,
                                                        'instanceurlcheck' => $instanceurlcheck,
                                                        'tilelogo' => $logo,
                                                        'stylecolorpicker' => $pickedcolor,
                                                        'configtitle' => $configtitle,
                                                        'config_title_fullname' => $configtitlefullname,
                                                        'reportid' => $reportid,
                                                        'instanceid' => $blockinstanceid,
                                                        'loading' => $OUTPUT->image_url('loading-small', 'block_learnerscript'),
                                                        'reporttype' => $this->config->reporttype,
                                                        'tableformat' => $tableformat,
                                                        'tileformat' => $reporttileformat,
                                                        'colorformat' => $colorformat,
                                                        'helpimg' => $helpimg,
                                                        'durations' => $durations,
                                                        'startduration' => $startduration,
                                                        'endduration' => time(),
                                                        'reportduration' => ($reportduration == 'all') ? '' :
                                                                    get_string($reportduration, 'block_reportdashboard'),
                                                        'blocktitle' => $blocktitle,
                                                        'reporttileclass' => $reporttileclass, ]);
            $this->content->text = $output->render($reporttiles);
        } else {
            $this->content->text = get_string('configurationmessage', 'block_reporttiles');
        }
        return $this->content;
    }
}
