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
use block_learnerscript\form\basicparams_form;
use block_learnerscript\local\ls;
use block_learnerscript\local\schedule;
use html_table;
use html_writer;
use moodle_url;
use plugin_renderer_base;
use tabobject;

/**
 * A Moodle block to create customizable reports.
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * generate_report_page description
     *
     * @param  index_page $page
     *
     * @return bool|string
     */
    public function render_index_page(index_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('core_group/index', $data);
    }

    /**
     * Render report table to display the data
     *
     * @param  reporttable $page
     *
     * @return bool|string
     */
    public function render_reporttable(reporttable $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('block_learnerscript/reporttable', $data);
    }
    /**
     * Render report plot options
     * @param \block_learnerscript\output\plotoption $page
     * @return bool|string
     */
    public function render_plotoption(\block_learnerscript\output\plotoption $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('block_learnerscript/plotoption', $data);
    }
    /**
     * Render report design
     * @param \block_learnerscript\output\design $page Columns data
     * @return bool|string
     */
    public function render_design(\block_learnerscript\output\design $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('block_learnerscript/design', $data);
    }
    /**
     * Render report scheduled users
     * @param  \block_learnerscript\output\scheduledusers $page
     * @return bool|string
     */
    public function render_scheduledusers(\block_learnerscript\output\scheduledusers $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('block_learnerscript/scheduledusers', $data);
    }
    /**
     * Render report graph tabs
     * @param  \block_learnerscript\output\plottabs $page
     * @return bool|string
     */
    public function render_plottabs(\block_learnerscript\output\plottabs $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('block_learnerscript/plottabs', $data);
    }
    /**
     * Render report filter toggle form
     * @param  \block_learnerscript\output\filtertoggleform $page
     * @return bool|string
     */
    public function render_filtertoggleform(\block_learnerscript\output\filtertoggleform $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('block_learnerscript/filtertoggleform', $data);
    }
    /**
     * This functiond displays the report
     * @param  object $report Report data
     * @param  object $context User context
     * @param  object $reportclass Report class
     */
    public function viewreport($report, $context, $reportclass) {
        global $USER;
        $calcbutton = false;
        $reportid = $report->id;
        $ls = new ls();

        if ($report->type !== 'statistics') {
            $plots = $ls->get_components_data($report->id, 'plot');
            $components = $ls->cr_unserialize($reportclass->config->components);
            $calcbutton = false;
            if (!empty($components->calculations->elements)) {
                $calcbutton = true;
            }
            if (has_capability('block/learnerscript:managereports', $context) ||
                (has_capability('block/learnerscript:manageownreports', $context)) && $report->ownerid == $USER->id) {
                $plotoptions = new \block_learnerscript\output\plotoption($plots, $report->id, $calcbutton, 'viewreport');
                echo $this->render($plotoptions);
            }
            $debug = optional_param('debug', false, PARAM_BOOL);
            if ($debug) {
                $debugsql = true;
            }
        }

        if (!empty($reportclass->basicparams)) {
            $basicparamsform = new basicparams_form(null, $reportclass);
            $basicparamsform->set_data($reportclass->params);
            echo $basicparamsform->display();
        }
        $plottabscontent = '';
        $plotdata = (new ls)->cr_listof_reporttypes($report->id, false, false);
        if (!empty($plotdata)) {
            $params = '';
            if (empty($reportclass->basicparams) || !empty($reportclass->params)) {
                $enableplots = 0;
            } else {
                $enableplots = 1;
            }
            $plottabs = new \block_learnerscript\output\plottabs($plotdata, $report->id, $params, $enableplots);
            $plottabscontent = $this->render($plottabs);
        }

        echo html_writer::start_div('', ['id' => 'viewreport'. $report->id]);
        $filterform = $reportclass->print_filters(true);
        $filterform = new \block_learnerscript\output\filtertoggleform($filterform, $plottabscontent);
        echo $this->render($filterform);

        if ($calcbutton) {
            echo html_writer::start_div('reportcalculation'.$report->id, null, []);
            echo html_writer::end_div();
        }
        $plotreportcontainer = '';
        if ($report->disabletable == 1 && empty($plotdata)) {
            $plotreportcontainer = html_writer::div(get_string('nodataavailable', 'block_learnerscript'), 'alert alert-info', []);
        }
        echo html_writer::div(html_writer::empty_tag('img', ['src' => $this->output->image_url('t/dockclose'),
        'alt' => get_string('closegraph', 'block_learnerscript'),
        'title' => get_string('closegraph', 'block_learnerscript'), 'class' => 'icon', ]),
        'plotgraphcontainer hide pull-right', ['data-reportid' => $report->id]) .
        html_writer::div($plotreportcontainer, 'ls-report_graph_container', ['id' => "plotreportcontainer$reportid"]);
        if (!empty($plotdata)) {
            echo '';
        }
        if (!empty($reportclass->config->export)) {
            $export = explode(',', $reportclass->config->export);
        }
        if ($report->disabletable == 0) {
            echo html_writer::start_div('', ['id' => "reportcontainer". $report->id]);
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }
    /**
     * Scheduled reports data to display in html table
     * @param  integer $reportid ReportID
     * @param  integer $courseid CourseID
     * @param  boolean $table    Table Head(true)/ Table Body (false)
     * @param  integer $start start from
     * @param  integer $length length
     * @param  string $search search value
     * @return  array $table => true, table head content
     *                     $table=> false, object with scheduled reports
     *                     if  records not found, dispalying info message.
     */
    public function schedulereportsdata($reportid, $courseid = 1, $table = true, $start = 0, $length = 5, $search = '') {

        $scheduledreports = (new schedule)->schedulereports($reportid, $table, $start, $length, $search);
        if ($table) {
            if (!$scheduledreports['totalschreports']) {
                $return = html_writer::tag('center', get_string('noschedule', 'block_learnerscript'),
                ['class' => 'alert alert-info']);
            } else {
                $table = new html_table();
                $table->head = [get_string('role', 'block_learnerscript'),
                    get_string('exportformat', 'block_learnerscript'),
                    get_string('schedule', 'block_learnerscript'),
                    get_string('action'), ];
                $table->size = ['40%', '15%', '35%', '10%'];
                $table->align = ['left', 'center', 'left', 'center'];
                $table->id = 'scheduledtimings';
                $table->attributes['data-reportid'] = $reportid;
                $table->attributes['data-courseid'] = $courseid;
                $return = html_writer::table($table);
            }
        } else {
            $data = [];
            foreach ($scheduledreports['schreports'] as $sreport) {
                $line = [];

                switch ($sreport->role) {
                    case 'admin':
                        $originalrole = get_string('admin');
                        break;
                    case 'manager':
                        $originalrole = get_string('manager', 'role');
                        break;
                    case 'coursecreator':
                        $originalrole = get_string('coursecreators');
                        break;
                    case 'editingteacher':
                        $originalrole = get_string('defaultcourseteacher');
                        break;
                    case 'teacher':
                        $originalrole = get_string('noneditingteacher');
                        break;
                    case 'student':
                        $originalrole = get_string('defaultcoursestudent');
                        break;
                    case 'guest':
                        $originalrole = get_string('guest');
                        break;
                    case 'user':
                        $originalrole = get_string('authenticateduser');
                        break;
                    case 'frontpage':
                        $originalrole = get_string('frontpageuser', 'role');
                        break;
                    // We should not get here, the role UI should require the name for custom roles!
                    default:
                        $originalrole = $sreport->role;
                        break;
                }

                $line[] = $originalrole;
                $line[] = strtoupper($sreport->exportformat);
                $line[] = (new schedule)->get_formatted($sreport->frequency, $sreport->schedule);
                $buttons = [];
                $buttons[] = html_writer::link(new moodle_url('/blocks/learnerscript/components/scheduler/schedule.php',
                ['id' => $reportid, 'courseid' => $courseid, 'scheduleid' => $sreport->id, 'sesskey' => sesskey()]),
                html_writer::empty_tag('img', ['src' => $this->output->image_url('t/edit'),
                'alt' => get_string('edit'), 'class' => 'iconsmall', 'title' => get_string('edit'), ]));
                $buttons[] = html_writer::link(new moodle_url('/blocks/learnerscript/components/scheduler/schedule.php',
                ['id' => $reportid, 'courseid' => $courseid,
                'scheduleid' => $sreport->id, 'sesskey' => sesskey(), 'delete' => 1, ]),
                html_writer::empty_tag('img', ['src' => $this->output->image_url('t/delete'),
                'alt' => get_string('delete'), 'class' => 'iconsmall', 'title' => get_string('delete'), ]));
                $line[] = implode(' ', $buttons);
                $data[] = $line;
            }
            $return = [
                "recordsTotal" => $scheduledreports['totalschreports'],
                "recordsFiltered" => $scheduledreports['totalschreports'],
                "data" => $data,
            ];
        }
        return $return;
    }

    /**
     * View schedule users
     * @param  int    $reportid
     * @param  int    $scheduleid
     * @param  string $schuserslist
     * @param  object $stable Schedule table list
     * @return array
     */
    public function viewschusers($reportid, $scheduleid, $schuserslist, $stable) {
        if ($stable->table) {
            $viewschuserscount = (new schedule)->viewschusers($reportid, $scheduleid, $schuserslist, $stable);
            if ($viewschuserscount > 0) {
                $table = new html_table();
                $table->head = [get_string('name'),
                    get_string('email'), ];
                $table->size = ['50%', '50%'];
                $table->id = 'scheduledusers';
                $table->attributes['data-reportid'] = $reportid;
                $table->attributes['data-courseid'] = isset($courseid) ? $courseid : SITEID;
                $return = html_writer::table($table);
            } else {
                $return = html_writer::div(get_string('usersnotfound', 'block_learnerscript'), "alert alert-info");
            }
        } else {
            $schedulingdata = (new schedule)->viewschusers($reportid, $scheduleid, $schuserslist, $stable);
            $data = [];
            foreach ($schedulingdata['schedulingdata'] as $sdata) {
                $line = [];
                $line[] = $sdata->fullname;
                $line[] = $sdata->email;
                $data[] = $line;
            }
            $return = [
                "recordsTotal" => $schedulingdata['viewschuserscount'],
                "recordsFiltered" => $schedulingdata['viewschuserscount'],
                "data" => $data,
            ];

        }

        return $return;
    }
    /**
     * This function displays the report tabs
     * @param  object $reportclass Current report class object
     * @param  string $currenttab Selected tab
     * @return array|null
     */
    public function print_tabs($reportclass, $currenttab) {
        global $COURSE;
        $top = [];
        $top[] = new tabobject('viewreport', new moodle_url('/blocks/learnerscript/viewreport.php',
            ['id' => $reportclass->config->id, 'courseid' => $COURSE->id]),
            get_string('viewreport', 'block_learnerscript'));
        $components = ['permissions'];
        foreach ($reportclass->components as $comptab) {
            if (!in_array($comptab, $components)) {
                continue;
            }
            $top[] = new tabobject($comptab, new moodle_url('/blocks/learnerscript/editcomp.php',
                ['id' => $reportclass->config->id,
                    'comp' => $comptab,
                    'courseid' => $COURSE->id, ]),
                get_string($comptab, 'block_learnerscript'));
        }
        $top[] = new tabobject('report', new moodle_url('/blocks/learnerscript/editreport.php',
            ['id' => $reportclass->config->id,
                'courseid' => $COURSE->id, ]),
            get_string('report', 'block_learnerscript'));
        $top[] = new tabobject('schedulereport', new moodle_url('/blocks/learnerscript/components/scheduler/schedule.php',
            ['id' => $reportclass->config->id,
                'courseid' => $COURSE->id, ]),
            get_string('schedulereport', 'block_learnerscript'));

        $top[] = new tabobject('managereports', new moodle_url('/blocks/learnerscript/managereport.php'),
            get_string('managereports', 'block_learnerscript'));

        $tabs = [$top];
        print_tabs($tabs, $currenttab);
    }

    /**
     * This function render the report component form
     * @param  int $reportid Report id
     * @param  string $component Report components
     * @param  string $pname Plugin name
     * @return array
     */
    public function render_component_form($reportid, $component, $pname) {
        global $CFG, $DB;

        if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
            throw new \moodle_exception(get_string('noreportexists', 'block_learnerscript'));
        }
        require_once($CFG->dirroot . '/blocks/learnerscript/components/' . $component . '/' . $pname . '/plugin.class.php');
        $pluginclassname = 'block_learnerscript\lsreports\plugin_' . $pname;
        $pluginclass = new $pluginclassname($report);

        require_once($CFG->dirroot . '/blocks/learnerscript/components/' . $component . '/component.class.php');
        $componentclassname = 'component_' . $component;
        $compclass = new $componentclassname($report->id);

        require_once($CFG->dirroot . '/blocks/learnerscript/components/' . $component . '/' . $pname . '/form.php');
        $classname = $pname . '_form';

        $formurlparams = ['id' => $reportid, 'comp' => $component, 'pname' => $pname];
        if ($cid) {
            $formurlparams['cid'] = $cid;
        }
        $formurl = new moodle_url('/blocks/learnerscript/editplugin.php', $formurlparams);
        $editform = new $classname($formurl, compact('comp', 'cid', 'id', 'pluginclass', 'compclass', 'report', 'reportclass'));
        $html = $editform->render();
        $headcode = $this->page->start_collecting_javascript_requirements();

        $loadpos = strpos($headcode, 'M.yui.loader');
        $cfgpos = strpos($headcode, 'M.cfg');
        $script .= substr($headcode, $loadpos, $cfgpos - $loadpos);
        // And finally the initalisation calls for those libraries.
        $endcode = $this->page->requires->get_end_code();
        $script .= preg_replace_callback(
                    '/<\/?(script|link)[^>]*>/',
                    function($matches) {
                        return '';
                    },
                    $endcode
                );

        return ['html' => $html, 'script' => $script];
    }

    /**
     * This function render the learnerscript configuration
     * @param  \block_learnerscript\output\lsconfig $page
     * @return bool
     */
    public function render_lsconfig(\block_learnerscript\output\lsconfig $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('block_learnerscript/lsconfig', $data);
    }
}
