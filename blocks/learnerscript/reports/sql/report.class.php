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
 * Sql report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
define('REPORT_CUSTOMSQL_MAX_RECORDS', 5000);
use block_learnerscript\local\ls;
use block_learnerscript\local\reportbase;
use stdclass;
use html_table;

/**
 * report_sql
 */
class report_sql extends reportbase {
    /**
     * @var $tablehead
     */
    public $tablehead;
    /**
     * @var $columns
     */
    public $columns;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /** @var array $orderable  */
    public $orderable;

    /**
     * SQL report construct
     * @param object $report           Report data
     * @param object $reportproperties Report properties
     */
    public function __construct($report, $reportproperties=[]) {
        parent::__construct($report, $reportproperties);
        $this->parent = true;
        $this->components = ['customsql', 'filters', 'permissions', 'plot'];
        $this->orderable = [''];
    }

    /**
     * SQL query initialization
     */
    public function init() {

    }

    /**
     * This function prepares the SQL query for the reports
     * @param string $sql SQL query
     * @return string
     */
    public function prepare_sql($sql) {
        global $DB, $CFG, $COURSE;
        $filtersearchtext = '';
        $operators = [];
        $sql = str_replace('%%LS_STARTDATE%%', $this->lsstartdate, $sql);
        $sql = str_replace('%%LS_ENDDATE%%', $this->lsenddate, $sql);
        if (!empty($this->search) && preg_match("/%%SEARCH:([^%]+)%%/i", $sql, $output)) {
            list($field, $operator) = preg_split('/:/', $output[1]);
            if ($operator != '' && !in_array($operator, $operators)) {
                throw new \moodle_exception('nosuchoperator', 'block_learnerscript');
            }
            if ($operator == '' || $operator == '~') {
                $replace = " AND " . $field . " LIKE '%" . $this->search . "%'";
            } else if ($operator == 'in') {
                $processeditems = [];
                // Accept comma-separated values, allowing for '\,' as a literal comma.
                foreach (preg_split("/(?<!\\\\),/", $this->search) as $searchitem) {
                    // Strip leading/trailing whitespace and quotes (we'll add our own quotes later).
                    $searchitem = trim($searchitem);
                    $searchitem = trim($searchitem, '"\'');

                    // We can also safely remove escaped commas now.
                    $searchitem = str_replace('\\,', ',', $searchitem);

                    // Escape and quote strings...
                    if (!is_numeric($searchitem)) {
                        $searchitem = "'" . addslashes($searchitem) . "'";
                    }
                    $processeditems[] = "$field like $searchitem";
                }
                // Despite the name, by not actually using in() we can support wildcards, and maybe be more portable as well.
                $replace = " AND (" . implode(" OR ", $processeditems) . ")";
            } else {
                $replace = ' AND ' . $field . ' ' . $operator . ' ' . $filtersearchtext;
            }
            $sql = str_replace('%%SEARCH:' . $output[1] . '%%', $replace, $sql);
        }
        // Enable debug mode from SQL query.
        $this->config->debug = (strpos($sql, '%%DEBUG%%') !== false) ? true : false;

        $sql = str_replace('%%USERID%%', $this->userid, $sql);
        $sql = str_replace('%%COURSEID%%', $COURSE->id, $sql);
        $sql = str_replace('%%CATEGORYID%%', $COURSE->category, $sql);

        // Current timestamp.
        $date = new \DateTime();
        $timestamp = $date->getTimestamp();
        $sql = str_replace('%%UNIXTIME%%', $timestamp, $sql);

        $sql = str_replace('%%LIMIT%%', 'LIMIT 10', $sql);

        if (preg_match("/%%FILTER_USER:([^%]+)%%/i", $sql, $output) && $this->params['filter_users'] > 1) {
            $replace = ' AND ' . $output[1] . ' = ' . $this->params['filter_users'];
            $sql = str_replace('%%FILTER_USER:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%FILTERUSER:([^%]+)%%/i", $sql, $output) && $this->params['filter_users'] > 1) {
            $replace = ' AND ' . $output[1] . ' = ' . $this->params['filter_users'];
            $sql = str_replace('%%FILTERUSER:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%FILTER_CATEGORIES:([^%]+)%%/i", $sql, $output) && $this->params['filter_categories'] >= 0) {
            $replace = ' AND ' . $output[1] . ' = ' . $this->params['filter_categories'];
            $sql = str_replace('%%FILTER_CATEGORIES:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%FILTER_COURSES:([^%]+)%%/i", $sql, $output) && $this->params['filter_courses'] > 0) {
            $replace = ' AND ' . $output[1] . ' = ' . $this->params['filter_courses'];
            $sql = str_replace('%%FILTER_COURSES:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%FILTER_ACTIVITIES:([^%]+)%%/i", $sql, $output) && $this->params['filter_activities'] > 0) {
            $replace = ' AND ' . $output[1] . ' = ' . $this->params['filter_activities'];
            $sql = str_replace('%%FILTER_ACTIVITIES:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%FILTER_MODULE:([^%]+)%%/i", $sql, $output) && $this->params['filter_modules'] > 0) {
            $replace = ' AND ' . $output[1] . ' = ' . $this->params['filter_modules'];
            $sql = str_replace('%%FILTER_MODULE:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%FILTER_COHORT:([^%]+)%%/i", $sql, $output) && $this->params['filter_cohort'] > 0) {
            $replace = ' AND ' . $output[1] . ' = ' . $this->params['filter_cohort'];
            $sql = str_replace('%%FILTER_COHORT:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%ROLECOURSES:([^%]+)%%/i", $sql, $output) && !empty($this->rolewisecourses)) {
            $replace = ' AND ' . $output[1] . ' IN (' . $this->rolewisecourses . ')';
            $sql = str_replace('%%ROLECOURSES:' . $output[1] . '%%', $replace, $sql);
        }

        $userfullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
        $sql = str_replace('%%USERFULLNAME%%', $userfullname, $sql);

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
     * This function gets all the elements of the report
     * @return object|array
     */
    public function get_all_elements() {
        global $DB, $CFG;

        $this->sql = preg_replace_callback(
                        '/\bprefix_(?=\w+)/i',
                        function($matches) use ($CFG) {
                            return $CFG->prefix;
                        },
                        $this->sql
                    );

        $reportlimit = get_config('block_learnerscript', 'reportlimit');
        if (empty($reportlimit) || $reportlimit == '0') {
            $reportlimit = REPORT_CUSTOMSQL_MAX_RECORDS;
        }
        preg_match("/(GROUP BY|group by)[\r\t|\r\n|\r\s]+(.*)/", $this->sql, $groupmatch, PREG_OFFSET_CAPTURE, 0);
        preg_match("/^(SELECT|select)[\r\t|\r\n|\r\s]+(.*)/", $this->sql, $matches, PREG_OFFSET_CAPTURE, 0);
        $selectcount = explode(',', $matches[2][0]);
        $nolimitrecords = $DB->get_recordset_sql($this->sql);
        $totalrecords = [];
        foreach ($nolimitrecords as $row) {
            $totalrecords[] = $row;
        }
        $totalrecords = count($totalrecords);
        $starttime = microtime(true);

        if (preg_match('/\b(INSERT|INTO|CREATE)\b/i', $this->sql)) {
            // Run special (dangerous) queries directly.
            $results = $DB->execute($this->sql);
        } else {
            $results = $DB->get_recordset_sql($this->sql, null, $this->start, $this->length);
        }

        $lastexecutiontime = round((microtime(true) - $starttime) * 1000);
        $this->config->lastexecutiontime = $lastexecutiontime;

        $DB->set_field('block_learnerscript', 'lastexecutiontime', $lastexecutiontime,  ['id' => $this->config->id]);
        return compact('results', 'totalrecords');
    }

    /**
     * THis function gets the report data rows
     * @return array
     */
    public function get_rows() {
        global $CFG, $DB;

        $components = (new ls)->cr_unserialize($this->config->components);

        $config = (isset($components->customsql->config)) ? $components->customsql->config : new stdclass;
        $reportfilters = (isset($components->filters->elements)) ? $components->filters->elements : [];

        $sql = '';

        if (isset($config->querysql)) {
            // FILTERS.
            $sql = $config->querysql;
            if (!empty($reportfilters)) {
                foreach ($reportfilters as $f) {
                    if ($f->formdata->value) {
                        require_once($CFG->dirroot . '/blocks/learnerscript/components/filters/' .
                                $f->pluginname . '/plugin.class.php');
                        $classname = 'block_learnerscript\lsreports\plugin_' . $f->pluginname;
                        $class = new $classname($this->config);

                        $sql = $class->execute($sql, $f->formdata, $this->params);
                    }
                }
            }

            $this->sql = $this->prepare_sql($sql);
            $columns = (isset($components->columns->elements)) ? $components->columns->elements : [];
            $selectedcolumns = [];
            $tablehead = [];
            $tablesize = [];
            $tablewrap = [];
            $tablealign = [];
            $finaltable = [];
            foreach ($columns as $c) {
                $selectedcolumns[$c->formdata->column] = $c->formdata->column;
                $tablehead[$c->formdata->column] = $c->formdata->columname;

                $tablealign[] = $c->formdata->align;
                $tablesize[] = $c->formdata->size;
                $tablewrap[] = $c->formdata->wrap;
            }

            if ($rs = $this->get_all_elements()) {

                foreach ($rs['results'] as $row) {
                    $i = 0;
                    $r = [];
                    foreach ($selectedcolumns as $k => $v) {
                        if (!empty($v)) {
                            if (in_array($v, $selectedcolumns)) {
                                if ($this->config->name == 'Timespent each course'
                                    && $this->reporttype == 'table') {
                                    if ($v == 'totaltimespent' && $row->$v != 0) {
                                        $row->$v = (new ls)->strtime(round($row->$v, 2));
                                    }
                                }
                                $row->$v = format_text($row->$v, FORMAT_HTML,
                                            ['trusted' => true, 'noclean' => true, 'para' => false]);
                                $r[$k] = str_replace('[[QUESTIONMARK]]', '?', $row->$v);
                                $i++;
                            }
                        }
                    }
                    $rows[] = $row;
                    $finaltable[] = $r;
                }
                $totalrecords = $rs['totalrecords'];
            }
        }
        return compact('finaltable', 'totalrecords', 'tablehead', 'rows', 'tablealign', 'tablewrap', 'tablesize');
    }

    /**
     * This function creates the SQL reports
     * @param array $blockinstanceid Instance id
     * @return bool
     */
    public function create_report($blockinstanceid = null) {
        $this->check_filters_request();
        $components = (new ls)->cr_unserialize($this->config->components);

        $tablehead = [];
        $finalcalcs = [];
        $tablehead = [];

        $finaldata = $this->get_rows();

        $this->totalrecords = $finaldata['totalrecords'];
        if ($blockinstanceid == null) {
            $blockinstanceid = $this->config->id;
        }

        $table = new stdClass;
        $table->id = 'reporttable_' . $blockinstanceid . '';
        $table->data = $finaldata['finaltable'];
        $table->head = $finaldata['tablehead'];
        $table->align = $finaldata['tablealign'];
        $table->wrap = $finaldata['tablewrap'];
        $table->size = $finaldata['tablesize'];
        $this->tablehead = $finaldata['tablehead'];
        if (!$this->finalreport) {
            $this->finalreport = new stdClass;
        }
        $this->finalreport->table = $table;

        return true;
    }
}
