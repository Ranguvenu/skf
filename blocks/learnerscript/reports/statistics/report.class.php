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
 * Statistics report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\ls as ls;
use block_learnerscript\local\reportbase;
use stdClass;
use html_table;
use context_system;
/**
 * Statistics reports class
 */
class report_statistics extends reportbase {

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /**
     * Report initialization
     */
    public function init() {
        $this->lsstartdate = 0;
        $this->lsenddate = time();
    }
    /**
     * Report construct
     * @param object $report           Statistic reports data
     * @param object $reportproperties Report properties
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->components = ['filters', 'permissions', 'plot'];
        $this->parent = true;
    }
    /**
     * This function prepares the SQL query for statistics report
     * @param string $sql          SQL query
     */
    public function prepare_sql($sql) {
        global $DB, $CFG, $COURSE, $SESSION;
        $sql = str_replace('%%LS_STARTDATE%%', $this->lsstartdate, $sql);
        $sql = str_replace('%%LS_ENDDATE%%', $this->lsenddate, $sql);
        $sql = str_replace('%%LS_ROLE%%', $this->role, $sql);

        // Enable debug mode from SQL query.
        $this->config->debug = (strpos($sql, '%%DEBUG%%') !== false) ? true : false;
        $sessiontimeout = $DB->get_field('config', 'value', ['name' => 'sessiontimeout']);

        $sql = str_replace('%%SESSIONTIMEOUT%%', $sessiontimeout, $sql);
        $sql = str_replace('%%USERID%%', $this->userid, $sql);
        $sql = str_replace('%%COURSEID%%', $this->courseid, $sql);
        $sql = str_replace('%%CATEGORYID%%', $COURSE->category, $sql);

        // Current timestamp.
        $date = new \DateTime();
        $timestamp = $date->getTimestamp();
        $sql = str_replace('%%UNIXTIME%%', $timestamp, $sql);

        $sql = str_replace('%%LIMIT%%', 'LIMIT 1', $sql);

        if (($this->courseid != SITEID) && preg_match("/%%LS_COURSEID:([^%]+)%%/i", $sql, $output)) {
            $replace = ' AND ' . $output[1] . ' = ' . $this->courseid;
            $sql = str_replace('%%LS_COURSEID:' . $output[1] . '%%', $replace, $sql);
        }
        $context = context_system::instance();
        if (!is_siteadmin() && !has_capability('block/learnerscript:managereports', $context)) {
            if (preg_match("/%%DASHBOARDROLE:([^%]+)%%/i", $sql, $output)) {
                $currentrole = "'".$SESSION->role."'";
                $replace = ' AND ' . $output[1] . ' =  ' . $currentrole . ' ';
                $sql = str_replace('%%DASHBOARDROLE:' . $output[1] . '%%', $replace, $sql);
            }
        }

        if (preg_match("/%%FILTER_COURSES:([^%]+)%%/i", $sql, $output) && $this->courseid > 1) {
            $replace = ' AND ' . $output[1] . ' = ' . $this->courseid;
            $sql = str_replace('%%FILTER_COURSES:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%ROLECOURSES:([^%]+)%%/i", $sql, $output) && !empty($this->rolewisecourses)) {
            $replace = ' AND ' . $output[1] . ' IN (' . $this->rolewisecourses . ')';
            $sql = str_replace('%%ROLECOURSES:' . $output[1] . '%%', $replace, $sql);
        }

        // Activities list.
        $activitiesquery = "";
        $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);
        foreach ($modules as $modulename) {
            $aliases[] = $modulename;
            $activitylist[] = $modulename.'.name';
            $fields1[] = "COALESCE($modulename.name,'')";
        }
        $activities = $DB->sql_concat(...$fields1);
        $sql = str_replace('%%ACTIVITIESLIST%%', $activities, $sql);
        foreach ($aliases as $alias) {
            $activitiesquery .= " LEFT JOIN {".$alias."} AS $alias ON $alias.id = cm.instance AND m.name = '$alias'";
        }
        $sql = str_replace('%%ACTIVITIESQUERY%%', $activitiesquery, $sql);
        $activity = implode(',', $activitylist);
        $sql = str_replace('%%ACTIVITIES%%', $activity, $sql);

        // See http://en.wikipedia.org/wiki/Year_2038_problem.
        $sql = str_replace(['%%STARTTIME%%', '%%ENDTIME%%'], ['0', '2145938400'], $sql);
        $sql = str_replace('%%WWWROOT%%', $CFG->wwwroot, $sql);
        $sql = preg_replace_callback(
                    '/%{2}[^%]+%{2}/i',
                    function($matches) {
                        return '';
                    },
                    $sql
                );

        $sql = str_replace('?', '[[QUESTIONMARK]]', $sql);

        return $sql;
    }
    /**
     * This function executes the statistics sql query
     * @param string $sql           SQL query
     */
    public function execute_query($sql) {
        global $DB, $CFG;

        $sql = preg_replace_callback(
                    '/\bprefix_(?=\w+)/i',
                    function($matches) use ($CFG) {
                        return $CFG->prefix;
                    },
                    $sql
                );
        $starttime = microtime(true);

        if (preg_match('/\b(INSERT|INTO|CREATE)\b/i', $sql)) {
            // Run special (dangerous) queries directly.
            $results = $DB->execute($sql);
        } else {
            $results = $DB->get_recordset_sql($sql, null, 0, 1);
        }
        $lastexecutiontime = round((microtime(true) - $starttime) * 1000);
        $this->config->lastexecutiontime = $lastexecutiontime;

        $DB->set_field('block_learnerscript', 'lastexecutiontime', $lastexecutiontime,  ['id' => $this->config->id]);
        return $results;
    }
    /**
     * This function creates the statistics report
     * @param int $blockinstanceid Block instance id
     * @param int $start Report data start value
     * @param int $length Length of the report
     * @param string $search Search value
     * @return bool
     */
    public function create_report($blockinstanceid = null, $start = 0, $length = -1, $search = '') {
        global $CFG, $PAGE;

        $PAGE->requires->jquery_plugin('ui-css');
        $components = (new ls)->cr_unserialize($this->config->components);

        $filters = (isset($components->filters->elements)) ? $components->filters->elements : [];

        $tablehead = [];
        $finalcalcs = [];
        $finaltable = [];
        $tablehead = [];

        $components = (new ls)->cr_unserialize($this->config->components);
        $config = (isset($components->customsql->config)) ? $components->customsql->config : new stdclass;
        $totalrecords = 0;

        $sql = '';
        if (isset($config->querysql)) {
            // FILTERS.
            $sql = $config->querysql;
            if (!empty($filters)) {
                foreach ($filters as $f) {
                    require_once($CFG->dirroot . '/blocks/learnerscript/components/filters/' .
                            $f['pluginname'] . '/plugin.class.php');
                    $classname = 'block_learnerscript\lsreports\plugin_' . $f['pluginname'];
                    $class = new $classname($this->config);
                    $sql = $class->execute($sql, $f['formdata']);
                }
            }

            $sql = $this->prepare_sql($sql);

            if ($rs = $this->execute_query($sql)) {
                foreach ($rs as $row) {
                    if (empty($finaltable)) {
                        foreach ($row as $colname => $value) {
                            $tablehead[] = ucfirst(str_replace('_', ' ', $colname));
                        }
                    }
                    $arrayrow = array_values((array) $row);
                    foreach ($arrayrow as $ii => $cell) {
                        $arrayrow[$ii] = str_replace('[[QUESTIONMARK]]', '?', $cell);
                    }
                    $totalrecords++;
                    if ($this->config->name == 'Total timespent') {
                        if ($arrayrow[0] > 0) {
                            $arrayrow[0] = (new ls)->strtime($arrayrow[0]);
                        }
                    }
                    $finaltable[] = $arrayrow;
                }
            }
        }
        $this->sql = $sql;
        $this->totalrecords = $totalrecords;
        if ($blockinstanceid == null) {
            $blockinstanceid = $this->config->id;
        }

        $table = new stdClass;
        $table->id = 'reporttable_' . $blockinstanceid . '';
        $table->data = $finaltable;
        $table->head = $tablehead;

        $calcs = new html_table();
        $calcs->id = 'calcstable';
        $calcs->data = [$finalcalcs];
        $calcs->head = $tablehead;

        if (!$this->finalreport) {
            $this->finalreport = new stdClass;
        }
        $this->finalreport->table = $table;
        $this->finalreport->calcs = $calcs;

        return true;
    }
}
