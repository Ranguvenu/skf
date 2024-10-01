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
 * User profile report
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\ls;
use html_table;
use stdClass;
use context_system;

/**
 * report_userprofile
 */
class report_userprofile extends reportbase {

    /** @var array $searchable  */
    public $searchable;

    /** @var string $defaultcolumn  */
    public $defaultcolumn;

    /** @var array $basicparamdata  */
    public $basicparamdata;

    /** @var array $orderable  */
    public $orderable;


    /**
     * Report construct function
     * @param object $report           User profile reportdata
     * @param object $reportproperties Report properties
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = ['columns', 'conditions', 'ordering', 'filters', 'permissions'];
        $this->parent = false;

        if ($this->role != $this->currentrole) {
            $this->basicparams = [['name' => 'users', 'singleselection' => false, 'placeholder' => false, 'maxlength' => 5]];
        }
        $this->columns = ['userfield' => ['userfield'], 'userprofile' => ['enrolled', 'inprogress',
            'completed', 'completedcoursesgrade', 'quizes', 'assignments', 'scorms', 'badges', 'progress', 'status', ], ];
        $this->exports = false;
        $this->orderable = [];
        $this->defaultcolumn = 'u.id';
    }

    /**
     * Report function initialize
     */
    public function init() {
        if (!$this->scheduling) {
            if ($this->role != $this->currentrole && !isset($this->params['filter_users'])) {
                $this->initial_basicparams('users');
                $fusers = array_keys($this->filterdata);
                $this->params['filter_users'] = array_shift($fusers);
            }
        }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
    }

    /**
     * Count SQL
     */
    public function count() {
        $this->sql  = " SELECT COUNT(DISTINCT u.id) ";
    }

    /**
     * Select SQL
     */
    public function select() {
        $this->sql = " SELECT DISTINCT u.id , CONCAT(u.firstname,' ',u.lastname) AS fullname ";
        parent::select();
    }
    /**
     * From SQL
     */
    public function from() {
        $this->sql  .= "FROM {user} u";
    }
    /**
     * SQl Joins
     */
    public function joins() {
        $this->sql .= " JOIN {role_assignments} ra ON ra.userid = u.id ";
        parent::joins();
    }
    /**
     * Adding conditions to the query
     */
    public function where() {
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        if (is_array($userid)) {
            $userid = implode(',', $userid);
        }
        $this->params['userid'] = $userid;
        $this->sql .= " WHERE u.confirmed = 1 AND u.deleted = 0 AND u.id IN ($userid)
                        AND ra.timemodified BETWEEN :lsfstartdate AND :lsfenddate";

        parent::where();
    }
    /**
     * Concat search values to the query
     */
    public function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $this->searchable = ["CONCAT(u.firstname, ' ', u.lastname)", "u.email"];
            $statsql = [];
            foreach ($this->searchable as $value) {
                $statsql[] = $DB->sql_like($value, "'%" . $this->search . "%'", false,
                            true, false);
            }
            $fields = implode(" OR ", $statsql);
            $this->sql .= " AND ($fields) ";
        }
    }
    /**
     * Concat filters to the query
     */
    public function filters() {

    }

    /**
     * Concat groupby to SQL
     */
    public function groupby() {
        $this->sql .= " GROUP BY u.id";
    }
    /**
     * Get list of users list
     * @param  array  $users Users list
     * @return array
     */
    public function get_rows($users) {
        return $users;
    }

    /**
     * Report column queries
     * @param  string  $column  Report column name
     * @param  int  $userid User id
     * @return string
     */
    public function column_queries($column, $userid) {
        $context = context_system::instance();
        $where = " AND %placeholder% = $userid";
        if (!is_siteadmin($this->userid) && !has_capability('block/learnerscript:managereports', $context)) {
            if ($this->rolewisecourses != '') {
                $coursefilter = " AND c.id IN ($this->rolewisecourses) ";
            }
        } else {
            $coursefilter = "";
        }
        $query = " ";
        $identity = " ";
        switch ($column) {
            case 'enrolled':
                $identity = "ra.userid";
                $query = "SELECT COUNT(DISTINCT c.id) AS enrolled
                          FROM {role_assignments} ra
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                          JOIN {context} ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                          WHERE 1 =1 $where $coursefilter";
                break;
            case 'inprogress':
                $identity = "ra.userid";
                $query = "SELECT (COUNT(DISTINCT c.id) - COUNT(DISTINCT cc.id)) AS inprogress
                          FROM {role_assignments} ra
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                          JOIN {context} ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                     LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ra.userid
                     AND cc.timecompleted > 0
                         WHERE 1=1 $where $coursefilter ";
                break;
            case 'completed':
                $identity = "cc.userid";
                $query = "SELECT COUNT(DISTINCT cc.course) AS completed
                          FROM {role_assignments} ra
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                          JOIN {context} ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                          JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ra.userid
                          AND cc.timecompleted > 0
                          WHERE 1 =1 $where $coursefilter ";
                break;
            case 'progress':
                $identity = "ra.userid";
                $query = "SELECT
                ROUND((CAST(COUNT(DISTINCT cc.course) AS DECIMAL) / CAST(COUNT(DISTINCT c.id) AS DECIMAL)) * 100, 2)
                            as progress
                            FROM {role_assignments} ra
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                            JOIN {context} ctx ON ctx.id = ra.contextid
                            JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                       LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ra.userid
                             AND cc.timecompleted > 0 WHERE 1 =1 $where $coursefilter";
                break;
            case 'completedcoursesgrade':
                $identity = "gg.userid";
                $query = "SELECT CONCAT(ROUND(sum(gg.finalgrade), 2),' / ', ROUND(sum(gi.grademax), 2)) AS completedcoursesgrade
                           FROM {grade_grades} gg
                           JOIN {grade_items} gi ON gi.id = gg.itemid
                           JOIN {course_completions} cc ON cc.course = gi.courseid
                           JOIN {course} c ON cc.course = c.id AND c.visible=1
                          WHERE gi.itemtype = 'course' AND cc.course = gi.courseid
                            AND cc.timecompleted IS NOT NULL
                            AND gg.userid = cc.userid
                             $where $coursefilter ";
                break;
            case 'assignments':
                $identity = 'ra.userid';
                $query = "SELECT COUNT(cm.id) AS assignments
                                FROM {course_modules} cm
                                JOIN {modules} m ON m.id = cm.module
                                JOIN {course} c ON c.id = cm.course AND c.visible = 1
                                JOIN {context} ctx ON c.id = ctx.instanceid
                                JOIN {role_assignments} ra ON ctx.id = ra.contextid
                                JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                                WHERE m.name = 'assign'
                                AND cm.visible = 1 AND cm.deletioninprogress = 0 $where $coursefilter";
                break;
            case 'quizes':
                $identity = 'ra.userid';
                $query = "SELECT COUNT(cm.id) AS quizes
                                FROM {course_modules} cm
                                JOIN {modules} m ON m.id = cm.module
                                JOIN {course} c ON c.id = cm.course AND c.visible = 1
                                JOIN {context} ctx ON c.id = ctx.instanceid
                                JOIN {role_assignments} ra ON ctx.id = ra.contextid
                                JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                                WHERE m.name = 'quiz'
                                AND cm.visible = 1 AND cm.deletioninprogress = 0 $where $coursefilter";
                break;
            case 'scorms':
                $identity = 'ra.userid';
                $query = "SELECT COUNT(cm.id) AS scorms
                                FROM {course_modules} cm
                                JOIN {modules} m ON m.id = cm.module
                                JOIN {course} c ON c.id = cm.course AND c.visible = 1
                                JOIN {context} ctx ON c.id = ctx.instanceid
                                JOIN {role_assignments} ra ON ctx.id = ra.contextid
                                JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                                WHERE m.name = 'scorm'
                                AND cm.visible = 1 AND cm.deletioninprogress = 0 $where $coursefilter";
                break;
            case 'badges':
                $identity = "bi.userid";
                $query = "SELECT count(bi.id) AS badges FROM {badge_issued} bi
                          JOIN {badge} b ON b.id = bi.badgeid
                          JOIN {course} c ON c.id = b.courseid AND c.visible = 1
                         WHERE  bi.visible = 1 AND b.status != 0
                          AND b.status != 2 AND b.status != 4
                           $where $coursefilter";
                break;
        }
        $query = str_replace('%placeholder%', $identity, $query);
        return $query;
    }
    /**
     * Create report
     * @param int $blockinstanceid Block instance id
     */
    public function create_report($blockinstanceid = null) {
        global $DB, $CFG;
        $components = (new ls)->cr_unserialize($this->config->components);
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
            ? $this->params['filter_users'] : $this->userid;
        $conditions = (isset($components->conditions->elements)) ? $components->conditions->elements : [];
        $filters = (isset($components->filters->elements)) ? $components->filters->elements : [];
        $columns = (isset($components->columns->elements)) ? $components->columns->elements : [];
        $ordering = (isset($components->ordering->elements)) ? $components->ordering->elements : [];
        $columnnames  = [];

        foreach ($columns as $key => $column) {
            if (isset($column->formdata->column)) {
                $columnnames[$column->formdata->column] = $column->formdata->columname;
                $this->selectedcolumns[] = $column->formdata->column;
            }
        }
        $finalelements = [];
        $sqlorder = '';
        $orderingdata = [];

        if ($this->ordercolumn) {
            $this->sqlorder = $this->selectedcolumns[$this->ordercolumn['column']] . " " . $this->ordercolumn['dir'];
        }

        $this->build_query(true);
        try {
            $this->totalrecords = $DB->count_records_sql($this->sql, $this->params);
        } catch (\dml_exception $e) {
            $this->totalrecords = 0;
        }

        $orderingdata = [];

        if ($this->ordercolumn) {
            $this->sqlorder = $this->selectedcolumns[$this->ordercolumn['column']] . " " . $this->ordercolumn['dir'];
        } else if (!empty($ordering)) {
            foreach ($ordering as $o) {
                require_once($CFG->dirroot.'/blocks/learnerscript/components/ordering/'.$o['pluginname'].'/plugin.class.php');
                $classname = 'block_learnerscript\lsreports\plugin_'.$o['pluginname'];
                $classorder = new $classname($this->config);
                if ($classorder->sql) {
                    $orderingdata = $o['formdata'];
                    $this->sqlorder = $classorder->execute($orderingdata);
                }
            }
        }

        $conditionfinalelements = [];
        if (!empty($conditions)) {
            $conditionfinalelements = $this->elements_by_conditions($components['conditions']);
        }

        $this->build_query();
        if (is_array($this->sqlorder) && !empty($this->sqlorder)) {
            $this->sql .= " ORDER BY ". $this->sqlorder['column'] .' '. $this->sqlorder['dir'];
        } else {
            if (!empty($sqlorder)) {
                $this->sql .= " ORDER BY c.$sqlorder ";
            } else {
                $this->sql .= " ORDER BY $this->defaultcolumn DESC ";
            }
        }

        try {
            $finalelements = $DB->get_records_sql($this->sql, $this->params, $this->start, $this->length);
        } catch (\dml_exception $e) {
            $finalelements = [];
        }
        $rows = $this->get_rows($finalelements);
        $reporttable = [];
        $tablehead = [];
        $tablealign = [];
        $tablesize = [];
        $tablewrap = [];
        $firstrow = true;
        $pluginscache = [];
        if ($rows) {
            $tempcols = [];
            foreach ($rows as $r) {
                foreach ($columns as $c) {
                    $c = (array) $c;
                    if (empty($c)) {
                        continue;
                    }
                    require_once($CFG->dirroot . '/blocks/learnerscript/components/columns/' .
                                $c['pluginname'] . '/plugin.class.php');
                    $classname = 'block_learnerscript\lsreports\plugin_' . $c['pluginname'];

                    if (!isset($pluginscache[$classname])) {
                        $class = new $classname($this->config, $c);
                        $pluginscache[$classname] = $class;
                    } else {
                        $class = $pluginscache[$classname];
                    }
                    $class->reportfilterparams = $this->params;
                    $class->role = $this->role;
                    $class->reportinstance = $blockinstanceid ? $blockinstanceid : $this->config->id;
                    if (isset($c['formdata']->column)) {
                        if (method_exists($this, 'column_queries')) {
                            if (isset($r->course)) {
                                $c['formdata']->subquery = $this->column_queries($c['formdata']->column, $r->id, $r->course);
                                $this->currentcourseid = $r->course;
                            } else if (isset($r->user)) {
                                $c['formdata']->subquery = $this->column_queries($c['formdata']->column, $r->id, $r->user);
                            } else {
                                $c['formdata']->subquery = $this->column_queries($c['formdata']->column, $r->id);
                            }
                        }
                        $tempcols[$c['formdata']->columname][] = $class->execute($c['formdata'], $r,
                                                                                $this->userid,
                                                                                $this->currentcourseid,
                                                                                $this->starttime,
                                                                                $this->endtime,
                                                                                $this->reporttype);
                    }

                    if ($firstrow) {
                        if (isset($c['formdata']->column)) {
                            $columnheading = !empty($c['formdata']->columname) ? $c['formdata']->columname : $c['formdata']->column;
                            $tablehead[$c['formdata']->columname] = $columnheading;
                        }
                        list($align, $size, $wrap) = $class->colformat($c['formdata']);
                        $tablealign[] = $align;
                        $tablesize[] = $size ? $size . '%' : '';
                        $tablewrap[] = $wrap;
                    }
                }
                $firstrow = false;
            }
            $reporttable = $tempcols;
        }
        // EXPAND ROWS.
        $finaltable = [];
        $i = 0;
        foreach ($reporttable as $key => $row) {
            $r = array_values($row);
            $r[] = $key;

            $finaltable[] = array_reverse($r);
            $i++;
        }

        if ($blockinstanceid == null) {
            $blockinstanceid = $this->config->id;
        }

        // Make the table, head, columns, etc...

        $table = new html_table;
        $table->data = $finaltable;
        if (is_array($userid)) {
            for ($i = 0; $i < (count($userid) + 1); $i++) {
                $table->head[] = '';
            }
        } else {
            for ($i = 0; $i < 2; $i++) {
                $table->head[] = '';
            }
        }
        $table->size = $tablesize;
        $table->align = $tablealign;
        $table->wrap = $tablewrap;
        $table->width = (isset($components->columns->config)) ? $components->columns->config->tablewidth : '';
        $table->summary = $this->config->summary;
        $table->tablealign = (isset($components->columns->config)) ? $components->columns->config->tablealign : 'center';
        $table->cellpadding = (isset($components->columns->config)) ? $components->columns->config->cellpadding : '5';
        $table->cellspacing = (isset($components->columns->config)) ? $components->columns->config->cellspacing : '1';

        if (!$this->finalreport) {
            $this->finalreport = new stdClass;
        }
        $this->finalreport->table = $table;
        $this->finalreport->calcs = null;
        return true;
    }
}
