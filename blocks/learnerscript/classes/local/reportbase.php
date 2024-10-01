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
namespace block_learnerscript\local;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/lib/evalmath/evalmath.class.php');
require_once($CFG->dirroot . '/blocks/learnerscript/components/columns/component.class.php');
use stdclass;
use block_learnerscript\form\filter_form;
use html_table;
use EvalMath;
use EvalWise;
use component_columns;
use block_learnerscript\local\ls;
use context_system;
use moodle_url;

/**
 * Reportbase
 */
class reportbase {

    /**
     * @var int $id Report id
     */
    public $id = 0;

    /**
     * @var object $components Report components
     */
    public $components = [];

    /**
     * @var object $finalreport Final report data
     */
    public $finalreport;

    /**
     * @var array $finalelements Report final elements
     */
    public $finalelements;

    /**
     * @var int $totalrecords Total records
     */
    public $totalrecords = 0;

    /**
     * @var int $currentuser Current user id
     */
    public $currentuser = 0;

    /**
     * @var int $currentcourseid Current course id
     */
    public $currentcourseid = 1;

    /**
     * @var int $starttime Start time
     */
    public $starttime = 0;

    /**
     * @var int $endtime End time
     */
    public $endtime = 0;

    /**
     * @var string $sql Report SQL query
     */
    public $sql = '';

    /**
     * @var bool $designpage Design page
     */
    public $designpage = true;

    /**
     * @var array $tablehead Report table head
     */
    public $tablehead;

    /**
     * @var array $ordercolumn Report order column
     */
    public $ordercolumn;

    /**
     * @var array $sqlorder SQL order
     */
    public $sqlorder;

    /**
     * @var bool $exports
     */
    public $exports = true;

    /**
     * @var int $start Start count
     */
    public $start = 0;

    /**
     * @var int $length Reports length
     */
    public $length = 10;

    /**
     * @var string $search Search value
     */
    public $search;

    /**
     * @var int $courseid Course id
     */
    public $courseid;

    /**
     * @var int $cmid Course module id
     */
    public $cmid;

    /**
     * @var int $userid User id
     */
    public $userid;

    /**
     * @var string $status Report data status
     */
    public $status;

    /**
     * @var array $filters Report filters
     */
    public $filters;

    /**
     * @var array $columns Report columns
     */
    public $columns;

    /**
     * @var array $basicparams Basic params list
     */
    public $basicparams;

    /**
     * @var array $params Report params list
     */
    public $params;

    /**
     * @var array $filterdata Report filters data
     */
    public $filterdata;

    /**
     * @var string $role User role
     */
    public $role;

    /**
     * @var int $contextlevel User contextlevel
     */
    public $contextlevel;

    /**
     * @var bool $parent
     */
    public $parent = true;

    /**
     * @var bool $courselevel
     */
    public $courselevel = false;

    /**
     * @var bool $conditionsenabled
     */
    public $conditionsenabled = false;

    /**
     * @var string $reporttype
     */
    public $reporttype = 'table';

    /**
     * @var bool $scheduling
     */
    public $scheduling = false;

    /**
     * @var bool $colformat
     */
    public $colformat = false;

    /**
     * @var bool $calculations
     */
    public $calculations = false;

    /**
     * @var bool $singleplot
     */
    public $singleplot;

    /**
     * @var string $rolewisecourses
     */
    public $rolewisecourses = '';

    /**
     * @var object $componentdata
     */
    public $componentdata;

    /**
     * @var object $graphcolumns
     */
    private $graphcolumns;

    /**
     * @var array $userroles
     */
    public $userroles;

    /**
     * @var array $selectedcolumns
     */
    public $selectedcolumns;

    /**
     * @var array $selectedfilters
     */
    public $selectedfilters;

    /**
     * @var array $conditionfinalelements
     */
    public $conditionfinalelements = [];

    /**
     * @var stdClass $config
     */
    public $config;

    /**
     * @var string $lsstartdate
     */
    public $lsstartdate;

    /**
     * @var string $lsenddate
     */
    public $lsenddate;

    /**
     * @var array $moodleroles
     */
    public $moodleroles;

    /**
     * @var string $contextrole
     */
    public $contextrole;

    /**
     * @var int $instanceid
     */
    public $instanceid;

    /**
     * @var int $defaultcolumn
     */
    public $defaultcolumn;

    /**
     * @var bool $customheader Custom header
     */
    public $customheader;

    /**
     * @var string $reportcontenttype Report contenttype
     */
    public $reportcontenttype;
    /**
     * @var bool $singleselection
     */
    public $singleselection;

    /**
     * @var mixed $placeholder
     */
    public $placeholder;

    /**
     * @var int $maxlength
     */
    public $maxlength;
    /**
     * @var array $conditions
     */
    public $conditions;
    /**
     * @var array $searchable
     */
    public $searchable;
    /**
     * @var array $excludedroles
     */
    public $excludedroles;
    /**
     * @var array $preview
     */
    public $preview;
    /**
     * @var array $basicparamdata Basic params list
     */
    public $basicparamdata;
    /**
     * @var bool $downloading downloadfile
     */
    public $downloading = false;
    /**
     * @var string $currentrole Current loggedin user role
     */
    public $currentrole = '';

    /**
     * Construct
     *
     * @param  object $report     Report data
     * @param  object $properties Report properties
     */
    public function __construct($report, $properties = null) {
        global $DB, $SESSION, $USER;

        if (empty($report)) {
            return false;
        }
        if (is_numeric($report)) {
            $this->config = $DB->get_record('block_learnerscript', ['id' => $report]);
        } else {
            $this->config = $report;
        }
        $this->userid = isset($properties->userid) ? $properties->userid : $USER->id;
        $this->courseid = $this->config->courseid;
        if ($USER->id == $this->userid) {
            $this->currentuser = $USER;
        } else {
            $this->currentuser = $DB->get_record('user', ['id' => $this->userid]);
        }
        if (empty($this->role)) {
            $this->role = isset($SESSION->role) ? $SESSION->role : (isset($properties->role) ? $properties->role : '');
        }
        if (empty($this->contextlevel)) {
            $this->contextlevel = isset($SESSION->ls_contextlevel) ? $SESSION->ls_contextlevel :
            (isset($properties->contextlevel) ? $properties->contextlevel : '');
        }
        $this->lsstartdate = isset($properties->lsfstartdate) ? $properties->lsfstartdate : 0;
        $this->lsenddate = isset($properties->lsenddate) ? $properties->lsenddate : time();
        $this->componentdata = (new ls)->cr_unserialize($this->config->components);
        $this->rolewisecourses = $this->rolewisecourses();
        $rolecontexts = $DB->get_records_sql("SELECT DISTINCT CONCAT(r.id, '@', rcl.id),
        r.shortname, rcl.contextlevel
        FROM {role} r
        JOIN {role_context_levels} rcl ON rcl.roleid = r.id AND rcl.contextlevel NOT IN (70)
        WHERE 1 = 1
        ORDER BY rcl.contextlevel ASC");
        $rcontext = [];
        foreach ($rolecontexts as $rc) {
            if (has_capability('block/learnerscript:managereports', context_system::instance())) {
                continue;
            }
            $rcontext[] = get_string('rolecontexts', 'block_learnerscript', $rc);
        }
        $this->moodleroles = isset($SESSION->rolecontextlist) ? $SESSION->rolecontextlist : $rcontext;
        $this->contextrole = isset($SESSION->role) && isset($SESSION->ls_contextlevel)
        ? $SESSION->role . '_' . $SESSION->ls_contextlevel
        : $this->role .'_'.$this->contextlevel;
        $capabilityrole = get_roles_with_capability('block/learnerscript:learnerreportaccess');
        if (!empty($capabilityrole)) {
            $this->currentrole = current($capabilityrole)->shortname;
        }
    }

    /**
     * Initialize
     */
    public function init() {

    }

    /**
     * Check report permissions
     *
     * @param  object $context    User context
     * @param  int $userid     User ID
     * @return bool
     */
    public function check_permissions($context, $userid = null) {
        global $CFG, $USER;
        if ($userid == null) {
            $userid = $USER->id;
        }

        if (is_siteadmin($userid) || has_capability('block/learnerscript:managereports', $context, $userid)) {
            return true;
        }

        if (empty($this->config->visible)) {
            return false;
        }
        $permissions = (isset($this->componentdata->permissions)) ? $this->componentdata->permissions : [];
        if (empty($permissions->elements)) {
            return has_capability('block/learnerscript:viewreports', $context, $userid);
        } else {
            $i = 1;
            $cond = [];
            foreach ($permissions->elements as $p) {
                require_once($CFG->dirroot . '/blocks/learnerscript/components/permissions/' .
                    $p->pluginname . '/plugin.class.php');
                $classname = 'block_learnerscript\lsreports\plugin_' . $p->pluginname;
                $class = new $classname($this->config);
                $class->role = $this->role;
                $class->userroles = isset($this->userroles) ? $this->userroles : '';
                $cond[$i] = $class->execute($userid, $context, $p->formdata);
                $i++;
            }
            if (count($cond) == 1) {
                return $cond[1];
            } else {
                $m = new EvalMath;
                $orig = $dest = [];
                if (isset($permissions->config) && isset($permissions->config->conditionexpr)) {
                    $logic = trim($permissions->config->conditionexpr);
                    // Security.
                    // No more than: conditions * 10 chars.
                    $logic = substr($logic, 0, count($permissions->elements) * 10);
                    $logic = str_replace(['and', 'or'], ['&&', '||'], strtolower($logic));
                    // More Security Only allowed chars.
                    $logic = preg_replace_callback(
                            '/[^&c\d\s|()]/i',
                            function($matches) {
                                return '';
                            },
                            $logic
                        );
                    $logic = str_replace(['&&', '||'], ['*', '+'], $logic);

                    for ($j = $i - 1; $j > 0; $j--) {
                        $orig[] = 'c' . $j;
                        $dest[] = ($cond[$j]) ? 1 : 0;
                    }
                    return $m->evaluate(str_replace($orig, $dest, $logic));
                } else {
                    return false;
                }
            }
        }
    }

    /**
     * Add filter elements
     *
     * @param  object $mform Filter form
     */
    public function add_filter_elements(&$mform) {
        global $CFG;
        $filters = (isset($this->componentdata->filters)) ? $this->componentdata->filters : [];
        if (!empty($filters->elements)) {
            foreach ($filters->elements as $f) {
                if ($f->formdata->value) {
                    require_once($CFG->dirroot . '/blocks/learnerscript/components/filters/' .
                        $f->pluginname . '/plugin.class.php');
                    $classname = 'block_learnerscript\lsreports\plugin_' . $f->pluginname;
                    $class = new $classname($this->config);
                    $class->singleselection = true;
                    $this->finalelements = $class->print_filter($mform);
                }
            }
        }
    }

    /**
     * Initial basicparams
     *
     * @param  string $pluginname Mandatory filter plugin name
     */
    public function initial_basicparams($pluginname) {
        global $CFG;
         require_once($CFG->dirroot . '/blocks/learnerscript/components/filters/' .
            $pluginname . '/plugin.class.php');
        $classname = 'block_learnerscript\lsreports\plugin_' . $pluginname;
        $class = new $classname($this->config);
        $class->singleselection = false;
        $selectoption = false;
        $filterarray = $class->filter_data($selectoption);
        $this->filterdata = $filterarray;
    }

    /**
     * Add basicparams
     *
     * @param object $mform Basicparams form
     */
    public function add_basicparams_elements(&$mform) {
        global $CFG;
        $basicparams = (isset($this->basicparams)) ? $this->basicparams : [];
        if (!empty($basicparams)) {
            foreach ($basicparams as $f) {
                if ($f['name'] == 'status') {
                    if ($this->config->type == 'useractivities') {
                        $statuslist = ['all' => get_string('selectstatus', 'block_learnerscript'),
                        'notcompleted' => get_string('notcompleted', 'block_learnerscript'),
                        'completed' => get_string('completed', 'block_learnerscript'), ];
                    } else if ($this->config->type == 'coursesoverview') {
                        $statuslist = ['all' => get_string('selectstatus', 'block_learnerscript'),
                        'inprogress' => get_string('inprogress', 'block_learnerscript'),
                        'completed' => get_string('completed', 'block_learnerscript'), ];
                    } else {
                        $statuslist = ['all' => get_string('selectstatus', 'block_learnerscript'),
                        'inprogress' => get_string('inprogress', 'block_learnerscript'),
                        'notyetstarted' => get_string('notyetstarted', 'block_learnerscript'),
                        'completed' => get_string('completed', 'block_learnerscript'), ];
                    }
                    $this->finalelements = $mform->addElement('select', 'filter_status', '',
                    $statuslist, ['data-select2' => true]);
                } else {
                    require_once($CFG->dirroot . '/blocks/learnerscript/components/filters/' .
                        $f['name'] . '/plugin.class.php');
                    $classname = 'block_learnerscript\lsreports\plugin_' . $f['name'];
                    $class = new $classname($this->config);
                    $class->singleselection = isset($f['singleselection']) ? $f['singleselection'] : true;
                    $class->placeholder = isset($f['placeholder']) ? $f['placeholder'] : true;
                    $class->maxlength = isset($f['maxlength']) ? $f['maxlength'] : 0;
                    $class->required = true;
                    $this->finalelements = $class->print_filter($mform);
                }
            }
        }

    }

    /**
     * @var object $filterform Filter form
     */
    public $filterform = null;

    /**
     * Check filters request
     *
     * @param  object $action Form action
     */
    public function check_filters_request($action = null) {
        global $CFG;

        $filters = (isset($this->componentdata->filters)) ? $this->componentdata->filters : [];

        if (!empty($filters->elements)) {
            $formdata = new stdclass;
            $ftcourses = optional_param('filter_courses', 0, PARAM_INT);
            $ftcoursecategories = optional_param('filter_coursecategories', 0, PARAM_INT);
            $ftusers = optional_param('filter_users', 0, PARAM_INT);
            $ftmodules = optional_param('filter_modules', 0, PARAM_INT);
            $ftactivities = optional_param('filter_activities', 0, PARAM_INT);
            $ftstatus = optional_param('filter_status', '', PARAM_TEXT);
            $urlparams = ['filter_courses' => $ftcourses, 'filter_coursecategories' => $ftcoursecategories,
                        'filter_users' => $ftusers, 'filter_modules' => $ftmodules,
                        'filter_activities' => $ftactivities, 'filter_status' => $ftstatus, ];
            $request = array_filter($urlparams);
            if ($request) {
                foreach ($request as $key => $val) {
                    if (strpos($key, 'filter_') !== false) {
                        $formdata->{$key} = $val;
                    }
                }
            }
            $this->instanceid = $this->config->id;

            $filterform = new filter_form($action, $this);

            $filterform->set_data($formdata);
            if ($filterform->is_cancelled()) {
                if ($action) {
                    redirect($action);
                } else {
                    redirect(new moodle_url('/blocks/learnerscript/viewreport.php', ['id' =>
                        $this->config->id, 'courseid' => $this->config->courseid]));
                }
            }
            $this->filterform = $filterform;
        }
    }

    /**
     * Print filters
     *
     * @param  string $return Filters data
     */
    public function print_filters($return = false) {
        if (!is_null($this->filterform) && !$return) {
            $this->filterform->display();
        } else if (!is_null($this->filterform)) {
            return $this->filterform->render();
        }
    }
    /**
     * Evaluate report conditions
     *
     * @param  array $data Data
     * @param  string $logic Login
     * @return string
     */
    public function evaluate_conditions($data, $logic) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/blocks/learnerscript/reports/evalwise.class.php');
        $logic = trim(strtolower($logic));
        $logic = substr($logic, 0, count($data) * 10);
        $logic = str_replace(['or', 'and', 'not'], ['+', '*', '-'], $logic);
        $logic = preg_replace('/[^\*c\d\s\+\-()]/i', '', $logic);

        $orig = $dest = [];
        for ($j = count($data); $j > 0; $j--) {
            $orig[] = 'c' . $j;
            $dest[] = $j;
        }
        $logic = str_replace($orig, $dest, $logic);
        $m = new EvalWise();
        $m->set_data($data);
        $result = $m->evaluate($logic);
        return $result;
    }
    /**
     * Get report calculations
     *
     * @param  array $finaltable Data
     * @param  array $tablehead Login
     * @return stdclass
     */
    public function get_calcs($finaltable, $tablehead) {
        global $DB, $CFG;

        $calcs = (isset($this->componentdata->calculations->elements)) ? $this->componentdata->calculations->elements : [];
        // Calcs doesn't work with multi-rows so far.
        $columnscalcs = [];
        $calcstype = [];
        $calcsdatatype = [];
        $finalcalcs = [];
        if (!empty($calcs)) {
            foreach ($calcs as $calc) {
                $calc = (array) $calc;
                $calc['formdata'] = (object)$calc['formdata'];
                $calckey = $calc['formdata']->column;
                $columnscalcs[$calckey] = [];
                $calcstype[$calckey] = $calc['formdata']->columname;
                $calcsdatatype[$calc['id']] = $calc['pluginname'];
            }

            $columnstostore = array_keys($columnscalcs);
            foreach ($finaltable as $r) {
                foreach ($columnstostore as $c) {
                    if (isset($r[$c])) {
                        $columnscalcs[$c][] = strip_tags($r[$c]);
                    }
                }
            }
            foreach ($calcs as $calc) {
                $calc = (array) $calc;
                $calc['formdata'] = $calc['formdata'];
                require_once($CFG->dirroot . '/blocks/learnerscript/components/calcs/' . $calc['pluginname'] . '/plugin.class.php');
                $classname = 'block_learnerscript\lsreports\plugin_' . $calc['pluginname'];
                $class = new $classname($this->config);
                $calckey = urldecode($calc['formdata']->column);
                $class->columnname = $calckey;
                $result = $class->execute($columnscalcs[$calckey]);
                $datakey = $calckey.'-'.$calc['pluginname'];

                $finalcalcs[$datakey] = $result;
            }
        }
        $calcsclass = new stdClass();
        $calcsclass->head = $calcstype;
        $calcsclass->data = $finalcalcs;
        $calcsclass->calcdata = $calcsdatatype;
        return $calcsclass;
    }
    /**
     * Get report conditions
     *
     * @param  array|string $conditions conditions
     * @return array
     */
    public function elements_by_conditions($conditions) {
        global $DB, $CFG;
        if (empty($conditions->elements)) {
            $finalelements = $this->get_all_elements();
            return $finalelements;
        }
        $finalelements = [];
        $i = 1;
        foreach ($conditions['elements'] as $c) {
            require_once($CFG->dirroot.'/blocks/learnerscript/components/conditions/'.$c['pluginname'].'/plugin.class.php');
            $classname = 'block_learnerscript\lsreports\plugin_'.$c['pluginname'];
            $class = new $classname($this->config);
            $elements[$i] = $class->execute($c['formdata'], $this->currentuser, $this->currentcourseid);
            $i++;
        }
        if (count($conditions['elements']) == 1) {
            $finalelements = $elements[1];
        } else {
            $logic = $conditions['config']->conditionexpr;
            $finalelements = $this->evaluate_conditions($elements, $logic);
            if ($finalelements === false) {
                return false;
            }
        }
        return $finalelements;
    }
    /**
     * Build SQL query
     *
     * @param  int $count Count data
     */
    public function build_query($count = false) {
        $this->init();
        if ($count) {
            $this->count();
        } else {
            $this->select();
        }
        $this->from();
        $this->joins();
        $this->where();
        $this->search();
        $this->filters();
        if (!$count) {
            $this->groupby();
        }
    }

    /**
     * SQL query where conditions
     */
    public function where() {
        if ($this->reporttype != 'table'  &&  isset($this->selectedcolumns)) {
             $plot = (isset($this->componentdata->plot->elements))
             ? $this->componentdata->plot->elements : [];
            foreach ($plot as $e) {
                if ($e->id == $this->reporttype) {
                    if ($e->pluginname == 'combination') {
                        foreach ($e->formdata->yaxis_bar as $key) {
                            if (!empty($e->formdata->{$key}) && method_exists($this, 'column_queries')) {
                                $this->sql .= ' AND (' . $this->column_queries($key, $this->defaultcolumn) . ')'
                                .$e->formdata->{$key}.''.$e->formdata->{$key .'_value'}.'';
                            }
                        }
                        foreach ($e->formdata->yaxis_line as $key) {
                            if (!empty($e->formdata->{$key}) && method_exists($this, 'column_queries')) {
                                $this->sql .= ' AND (' . $this->column_queries($key, $this->defaultcolumn) . ')'
                                .$e->formdata->{$key}.''.$e->formdata->{$key .'_value'}.'';
                            }
                        }
                    } else {
                        if (isset($e->formdata->yaxis)) {
                            foreach ($e->formdata->yaxis as $key) {
                                if (!empty($e->formdata->{$key}) && method_exists($this, 'column_queries')) {
                                    $this->sql .= ' AND (' . $this->column_queries($key, $this->defaultcolumn) . ')'
                                    .$e->formdata->{$key}.''.$e->formdata->{$key .'_value'}.'';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * SQL query select
     */
    public function select() {
        if (isset($this->sqlorder['column'])) {
            if (method_exists($this, 'column_queries') && $this->column_queries($this->sqlorder['column'], $this->defaultcolumn)) {
                if ($this->column_queries($this->sqlorder['column'], $this->defaultcolumn) !== " ") {
                    $this->sql .= ' ,(' . $this->column_queries($this->sqlorder['column'],
                        $this->defaultcolumn) . ') as '. $this->sqlorder['column'].'';
                }
            }
        }
    }

    /**
     * Joins in SQL query
     */
    public function joins() {
    }

    /**
     * Get all elements
     */
    public function get_all_elements() {
        global $DB;
        try {
            $finalelements = $DB->get_records_sql($this->sql, $this->params, $this->start, $this->length);
        } catch (\dml_exception $e) {
            $finalelements = [];
        }
        return $finalelements;
    }

    /**
     * Function to create report
     *
     * @param  int $blockinstanceid Report block instance id
     */
    public function create_report($blockinstanceid = null) {
        global $DB, $CFG;
        $context = context_system::instance();
        $this->check_permissions($context, $this->userid);
        $columns = (isset($this->componentdata->columns->elements))
        ? $this->componentdata->columns->elements : [];
        $ordering = (isset($this->componentdata->ordering->elements))
        ? $this->componentdata->ordering->elements : [];
        $plot = (isset($this->componentdata->plot->elements))
        ? $this->componentdata->plot->elements : [];

        if ($this->reporttype !== 'table') {
            $this->graphcolumns = [];
            foreach ($plot as $column) {
                if ($column->id == $this->reporttype) {
                    $this->graphcolumns = $column;
                }
            }
            if (!empty($this->graphcolumns->formdata->columnsort)
            && $this->graphcolumns->formdata->columnsort
            && $this->graphcolumns->formdata->sorting) {
                $this->sqlorder['column'] = $this->graphcolumns->formdata->columnsort;
                $this->sqlorder['dir'] = $this->graphcolumns->formdata->sorting;
            }
            if (!empty($this->graphcolumns->formdata->limit)
            && $this->graphcolumns->formdata->limit) {
                $this->length = $this->graphcolumns->formdata->limit;
            }

            if ($this->graphcolumns->pluginname == 'combination') {
                $this->selectedcolumns = array_merge([$this->graphcolumns->formdata->serieid] ,
                $this->graphcolumns->formdata->yaxis_line,
                $this->graphcolumns->formdata->yaxis_bar);
            } else if ($this->graphcolumns->pluginname == 'pie') {
                $this->selectedcolumns = [$this->graphcolumns->formdata->areaname ,
                $this->graphcolumns->formdata->areavalue, ];
            } else {
                 $this->selectedcolumns = !empty($this->graphcolumns->formdata->yaxis) ?
                                        array_merge([$this->graphcolumns->formdata->serieid] ,
                 $this->graphcolumns->formdata->yaxis, ) : $this->graphcolumns->formdata->serieid;
            }

        } else {
            if ($this->preview && empty($columns)) {
                $columns = $this->preview_data();
            }
            $columnnames  = [];
            foreach ($columns as $key => $column) {
                if (isset($column->formdata->column)) {
                    $columnnames[$column->formdata->column] = $column->formdata->columname;
                    $this->selectedcolumns[] = $column->formdata->column;
                }
            }
        }
        $finalelements = [];
        $sqlorder = '';
        $orderingdata = [];
        if (!empty($this->ordercolumn)) {
            $this->sqlorder['column'] = $this->selectedcolumns[$this->ordercolumn['column']];
            $this->sqlorder['dir'] = $this->ordercolumn['dir'];
        } else if (!empty($ordering)) {
            foreach ($ordering as $o) {
                require_once($CFG->dirroot.'/blocks/learnerscript/components/ordering/' .
                    $o->pluginname . '/plugin.class.php');
                $classname = 'block_learnerscript\lsreports\plugin_'.$o->pluginname;
                $classorder = new $classname($this->config);
                if ($classorder->sql) {
                    $orderingdata = $o->formdata;
                    $sqlorder = $classorder->execute($orderingdata);
                }
            }
        }

        if (!empty($conditions)) {
            $this->conditionsenabled = true;
            $this->conditionfinalelements = $this->elements_by_conditions($this->componentdata['conditions']);
        }
        $this->params['siteid'] = SITEID;
        $this->build_query(true);

        if ($this->reporttype == 'table') {
            if (is_siteadmin($this->userid) || has_capability('block/learnerscript:managereports', $context)) {
                try {
                    $this->totalrecords = $DB->count_records_sql($this->sql, $this->params);
                } catch (\dml_exception $e) {
                    $this->totalrecords = 0;
                }
            } else {
                if ($this->rolewisecourses != '') {
                    try {
                        $this->totalrecords = $DB->count_records_sql($this->sql, $this->params);
                    } catch (\dml_exception $e) {
                        $this->totalrecords = 0;
                    }
                } else {
                    $this->totalrecords = 0;
                }
            }
        }
        $this->build_query();
        $groupcolumn = isset($this->groupcolumn) ? $this->groupcolumn : $this->defaultcolumn;
        if ($this->config->type != 'userattendance' && $this->config->type != 'attendanceoverview'
        && $this->config->type != 'monthlysessions' && $this->config->type != 'weeklysessions'
        && $this->config->type != 'dailysessions' && $this->config->type != 'upcomingactivities'
        && $this->config->type != 'pendingactivities') {
            if (is_array($this->sqlorder) && !empty($this->sqlorder)) {
                $this->sql .= " ORDER BY ". $this->sqlorder['column'] .' '. $this->sqlorder['dir'];
            } else {
                if (!empty($sqlorder)) {
                    $this->sql .= " ORDER BY $sqlorder ";
                } else {
                    $this->sql .= " ORDER BY $this->defaultcolumn DESC ";
                }
            }
        }
        if (is_siteadmin($this->userid)
        || has_capability('block/learnerscript:managereports', $context)) {
            $finalelements = $this->get_all_elements();
            $rows = $this->get_rows($finalelements);
        } else {
            if ($this->rolewisecourses != '') {
                $finalelements = $this->get_all_elements();
                $rows = $this->get_rows($finalelements);
            } else {
                $rows = [];
            }
        }
        $reporttable = [];
        $tablehead = [];
        $tablealign = [];
        $tablesize = [];
        $tablewrap = [];
        $firstrow = true;
        $pluginscache = [];
        if ($this->config->type == "topic_wise_performance" || $this->config->type == 'cohortusers') {
            $columns = (new ls)->learnerscript_sections_dynamic_columns($columns, $this->config,
                $this->params);
        }
        if ($rows) {
            foreach ($rows as $r) {
                $tempcols = [];
                foreach ($columns as $c) {
                    $c = (object) $c;
                    if (empty($c)) {
                        continue;
                    }
                    require_once($CFG->dirroot . '/blocks/learnerscript/components/columns/' .
                    $c->pluginname . '/plugin.class.php');
                    $classname = 'block_learnerscript\lsreports\plugin_' . $c->pluginname;

                    if (!isset($pluginscache[$classname])) {
                        $class = new $classname($this->config, $c);
                        $pluginscache[$classname] = $class;
                    } else {
                        $class = $pluginscache[$classname];
                    }
                    $class->role = $this->role;
                    $class->colformat = $this->colformat;
                    $class->reportinstance = $blockinstanceid ? $blockinstanceid : $this->config->id;
                    $class->reportfilterparams = $this->params;
                    $class->downloading = $this->downloading;
                    $rid = isset($r->id) ? $r->id : 0;
                    if (isset($c->formdata->column) &&
                        (($this->config->type == "topic_wise_performance" || $this->config->type == 'cohortusers')
                        || (!empty($this->selectedcolumns) && in_array($c->formdata->column, $this->selectedcolumns)))) {
                        if (!empty($this->params['filter_users'])) {
                            $this->currentuser = $this->params['filter_users'];
                        }
                        if (method_exists($this, 'column_queries')) {
                            if (isset($r->course)) {
                                $c->formdata->subquery = $this->column_queries($c->formdata->column, $rid, $r->course);
                                $this->currentcourseid = $r->course;
                            } else if (isset($r->user)) {
                                $c->formdata->subquery = $this->column_queries($c->formdata->column, $rid, $r->user);
                            } else {
                                $c->formdata->subquery = $this->column_queries($c->formdata->column, $rid);
                            }
                        }
                        $columndata = $class->execute($c->formdata, $r, $this->reporttype);
                        $tempcols[$c->formdata->column] = $columndata;
                    }
                    if ($firstrow) {
                        if (isset($c->formdata->column)) {
                            $columnheading = !empty($c->formdata->columname) ? $c->formdata->columname : $c->formdata->column;
                            $tablehead[$c->formdata->column] = $columnheading;
                        }
                        list($align, $size, $wrap) = $class->colformat($c->formdata);
                        $tablealign[] = $align;
                        $tablesize[] = $size ? $size . '%' : '';
                        $tablewrap[] = $wrap;
                    }
                }

                $firstrow = false;
                $reporttable[] = $tempcols;
            }
        }

        // EXPAND ROWS.
        $finaltable = [];
        foreach ($reporttable as $row) {
            $col = [];
            $multiple = false;
            $nrows = 0;
            $mrowsi = [];
            foreach ($row as $key => $cell) {
                if (!is_array($cell)) {
                    $col[$key] = $cell;
                } else {
                    $multiple = true;
                    $nrows = count($cell);
                    $mrowsi[] = $key;
                }
            }
            if ($multiple) {
                $newrows = [];
                for ($i = 0; $i < $nrows; $i++) {
                    $newrows[$i] = $row;
                    foreach ($mrowsi as $index) {
                        $newrows[$i][$index] = $row[$index][$i];
                    }
                }
                foreach ($newrows as $r) {
                    $finaltable[] = $r;
                }
            } else {
                $finaltable[] = $col;
            }
        }

        if ($blockinstanceid == null) {
            $blockinstanceid = $this->config->id;
        }

        // Make the table, head, columns, etc...

        $table = new stdClass;
        $table->id = 'reporttable_' . $blockinstanceid . '';
        $table->data = $finaltable;
        $table->head = $tablehead;
        $table->size = $tablesize;
        $table->align = $tablealign;
        $table->wrap = $tablewrap;
        $table->width = (isset($this->componentdata->columns->config))
        ? $this->componentdata->columns->config->tablewidth : '';
        $table->summary = $this->config->summary;
        $table->tablealign = (isset($this->componentdata->columns->config))
        ? $this->componentdata->columns->config->tablealign : 'center';
        $table->cellpadding = (isset($this->componentdata->columns->config))
        ? $this->componentdata->columns->config->cellpadding : '5';
        $table->cellspacing = (isset($this->componentdata->columns->config))
        ? $this->componentdata->columns->config->cellspacing : '1';
        $table->class = (isset($this->componentdata->columns->config))
        ? $this->componentdata->columns->config->class : 'generaltable';
                // CALCS.
        if ($this->calculations) {
            $finalheadcalcs = $this->get_calcs($finaltable, $tablehead);
            $finalcalcs = $finalheadcalcs->data;
            $calcs = new html_table();
            $calcshead = [];
            $calcshead[] = 'Column Name';

            foreach ($finalheadcalcs->calcdata as $key => $head) {
                    $calcshead[$head] = ucfirst(get_string($head, 'block_learnerscript'));
                    $calcshead1[$head] = $key;
            }
            $calcsdata = [];
            foreach ($finalheadcalcs->head as $key => $head) {
                $row = [];
                $row[] = $columnnames[$key];
                foreach ($calcshead1 as $key1 => $value) {
                    if (array_key_exists($key.'-'.$key1, $finalcalcs)) {
                        $row[] = $finalcalcs[$key.'-'.$key1];
                    } else {
                        $row[] = 'N/A';
                    }
                }
                $calcsdata[] = $row;
            }

            $calcs->data = $calcsdata;
            $calcs->head = $calcshead;
            $calcs->size = $tablesize;
            $calcs->align = $tablealign;
            $calcs->wrap = $tablewrap;
            $calcs->summary = $this->config->summary;
            $calcs->attributes['class'] = (isset($this->componentdata->columns->config))
            ? $this->componentdata->columns->config->class : 'generaltable';
            $this->finalreport = new stdClass();
            $this->finalreport->calcs = $calcs;
        }
        if (!$this->finalreport) {
            $this->finalreport = new stdClass;
        }
        $this->finalreport->table = $table;
        return true;
    }

    /**
     * utf8_strrev
     *
     * @param  string $str
     * @return string
     */
    public function utf8_strrev($str) {
        preg_match_all('/./us', $str, $ar);
        return join('', array_reverse($ar[0]));
    }
    /**
     * Report preview
     * @return array
     */
    public function preview_data() {
        global $CFG, $DB;
        $allcolumns = $this->columns;
        $columns = [];
        $componentcolumns = get_list_of_plugins('blocks/learnerscript/components/columns');
        foreach ($allcolumns as $key => $c) {
            if (in_array($key, array_values($componentcolumns))) {
                require_once($CFG->dirroot . '/blocks/learnerscript/components/columns/' . $key . '/plugin.class.php');
                $pluginclassname = 'block_learnerscript\lsreports\plugin_' . $key;
                $pluginclass = new $pluginclassname(null);

                if ($pluginclass->type == 'advanced') {
                    require_once($CFG->dirroot . '/blocks/learnerscript/components/columns/' . $key . '/form.php');
                    $pluginclassformname = $key . '_form';
                    $compclass = new component_columns($this->config->id);
                    $pluginclassform = new $pluginclassformname(null, ['compclass' => $compclass]);
                    $previewcolumns = $pluginclassform->advanced_columns();
                    foreach ($previewcolumns as $preview => $previewcolumn) {
                        $data = [];
                        $data['id'] = random_string(15);
                        $data['pluginname'] = $key;
                        $data['pluginfullname'] = get_string($key, 'block_learnerscript');
                        $data['summary'] = '';
                        $data['type'] = 'selectedcolumns';
                        $list = new stdClass;
                        $list->value = 0;
                        $list->columname = $previewcolumn;
                        $list->column = $preview;
                        $list->heading = $key;
                        $data['formdata'] = $list;
                        $columns[] = $data;
                    }
                } else {
                    foreach ($c as $value) {
                        $data = [];
                        $data['id'] = random_string(15);
                        $data['pluginname'] = $key;
                        $data['pluginfullname'] = get_string($key, 'block_learnerscript');
                        $data['summary'] = '';
                        $data['type'] = 'selectedcolumns';
                        $list = new stdClass;
                        $list->value = 0;
                        $list->columname = $value;
                        $list->column = $value;
                        $list->heading = $key;
                        $data['formdata'] = $list;
                        $columns[] = $data;
                    }
                }
            }
        }
        return $columns;
    }
    /**
     * Report conditions
     * @return array
     */
    public function setup_conditions() {
        global $CFG, $DB;
        $conditionsdata = [];
        if (isset($this->components->conditions->elements)) {
            foreach ($this->components->conditions->elements as $key => $value) {
                $conditionsdata[] = $value['formdata'];
            }
        }

        $plugins = get_list_of_plugins('blocks/learnerscript/components/conditions');

        $conditionscolumns = [];
        $conditionscolumns['elements'] = [];
        $conditionscolumns['config'] = [];
        foreach ($plugins as $p) {
            require_once($CFG->dirroot . '/blocks/learnerscript/components/conditions/' . $p . '/plugin.class.php');
            $pluginclassname = 'block_learnerscript\lsreports\plugin_' . $p;
            $columns = [];
            $pluginclass = new $pluginclassname($this->config->type);
            if (in_array($this->config->type, $pluginclass->reporttypes)) {
                if ($pluginclass->unique && in_array($p, $currentplugins)) {
                    echo '';
                }
                $uniqueid = random_string(15);
                $reportclassname = 'block_learnerscript\lsreports\report_' . $this->config->type;
                $report = $DB->get_record_sql("SELECT *
                FROM {block_learnerscript}
                WHERE id = :configid", ['configid' => $this->config->id]);
                $properties = new stdClass();
                $reportclass = new $reportclassname($report, $properties);
                while (strpos($reportclass->config->components, $uniqueid) !== false) {
                    $uniqueid = random_string(15);
                }
                $columns['id'] = $uniqueid;
                $columns['formdata'] = $conditionsdata;
                $columns['value'] = (in_array($p, $conditionsdata)) ? true : false;
                $columns['pluginname'] = $p;
                if (method_exists($pluginclass, 'columns')) {
                    $columns['plugincolumns'] = $pluginclass->columns();
                } else {
                    $columns['plugincolumns'] = [];
                }
                $columns['form'] = $pluginclass->form;
                $columns['allowedops'] = $pluginclass->allowedops;
                $columns['pluginfullname'] = get_string($p, 'block_learnerscript');
                $columns['summery'] = get_string($p, 'block_learnerscript');
                $conditionscolumns['elements'][$p] = $columns;
            }
        }
        $conditionscolumns['conditionssymbols'] = ["=", ">", "<", ">=", "<=", "<>", "LIKE", "NOT LIKE", "LIKE % %"];

        if (!empty($this->componentdata->conditions->elements)) {
            $finalelements = [];
            $finalelements['elements'] = [];
            $finalelements['selectedfields'] = [];
            $finalelements['selectedcondition'] = [];
            $finalelements['selectedvalue'] = [];
            $finalelements['sqlcondition'] = urldecode($this->componentdata['conditions']['config']->conditionexpr);
            foreach ($this->componentdata['conditions']['elements'] as $element) {
                $finalelements['elements'][] = $element['pluginname'];
                $finalelements['selectedfields'][] = $element['pluginname'] .
                ':' . $element['formdata']->field;
                $finalelements['selectedcondition'][$element['pluginname'] .
                ':' . $element['formdata']->field] = urldecode($element['formdata']->operator);
                $finalelements['selectedvalue'][$element['pluginname'] .
                ':' . $element['formdata']->field] = urldecode($element['formdata']->value);
            }
            $conditionscolumns['finalelements'] = $finalelements;
        }
        return $conditionscolumns;
    }
    /**
     * Rolewise courses
     * @return array|string
     */
    public function rolewisecourses() {
        global $DB;

        $context = context_system::instance();

        if (!is_siteadmin($this->userid) && !has_capability('block/learnerscript:managereports', $context)) {
            if (!empty($this->componentdata->permissions->elements)) {
                $roleincourse = array_filter($this->componentdata->permissions->elements, function($permission) {
                    // Role in course permission.
                    if ($permission->pluginname == 'roleincourse') {
                        return true;
                    }
                });
            }
            if (!empty($roleincourse)) {
                $currentroleid = $DB->get_field('role', 'id', ['shortname' => $this->role]);

                foreach ($roleincourse as $role) {
                    if (!empty($this->role) && (!isset($role->formdata->contextlevel)
                    || $role->formdata->roleid != $currentroleid)) {
                        continue;
                    }
                    $permissionslib = new permissionslib($role->formdata->contextlevel,
                    $role->formdata->roleid,
                    $this->userid);
                    $rolecontexts = $DB->get_records_sql("SELECT DISTINCT CONCAT(r.id, '@', rcl.id),
                    r.shortname, rcl.contextlevel
                    FROM {role} r
                    JOIN {role_context_levels} rcl ON rcl.roleid = r.id AND rcl.contextlevel NOT IN (70)
                    WHERE 1 = 1
                    ORDER BY rcl.contextlevel ASC");
                    foreach ($rolecontexts as $rc) {
                        if (has_capability('block/learnerscript:managereports', $context)) {
                            continue;
                        }
                        $rcontext[] = get_string('rolecontexts', 'block_learnerscript', $rc);
                    }
                    $permissionslib->moodleroles = $rcontext;
                    if (has_capability('block/learnerscript:reportsaccess', $context)) {
                        return implode(',', $permissionslib->get_rolewise_courses());
                    }
                }
            }
        }
    }
}
