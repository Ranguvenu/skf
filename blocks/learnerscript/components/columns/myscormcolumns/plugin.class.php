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
 * A Moodle block for creating customizable reports
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_learnerscript\lsreports;
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\ls;
use context_system;
use moodle_url;
use html_writer;
use completion_info;
use stdClass;
/**
 * My scorm columns
 */
class plugin_myscormcolumns extends pluginbase {

    /** @var string $role  */
    public $role;

    /**
     * @var array $reportinstance User role
     */
    public $reportinstance;

    /**
     * @var string $reportfilterparams User role
     */
    public $reportfilterparams;
    /**
     * Myscorm columns init function
     */
    public function init() {
        $this->fullname = get_string('myscormcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['myscorm'];
    }
    /**
     * My scorm column summary
     * @param object $data Course views column name
     */
    public function summary($data) {
        return format_string($data->columname);
    }
    /**
     * This function return field column format
     * @param object $data Field data
     * @return array
     */
    public function colformat($data) {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return [$align, $size, $wrap];
    }
    /**
     * This function executes the columns data
     * @param object $data Columns data
     * @param object $row Row data
     * @param string $reporttype Report type
     * @return object
     */
    public function execute($data, $row, $reporttype) {
        global $DB, $CFG, $OUTPUT, $USER;
        $context = context_system::instance();
        require_once($CFG->libdir . '/completionlib.php');
        switch ($data->column) {
            case 'course':
                $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'courseprofile'], IGNORE_MULTIPLE);
                $checkpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($context, $USER->id);
                if (empty($reportid) || empty($checkpermissions)) {
                    $row->{$data->column} = html_writer::link(
                        new moodle_url('/course/view.php', ['id' => $row->courseid]),
                        $row->{$data->column}
                    );
                } else {
                    $row->{$data->column} = html_writer::link(
                        new moodle_url('/blocks/learnerscript/viewreport.php', ['id' => $reportid,
                         'filter_courses' => $row->courseid, ]),
                        $row->{$data->column}
                    );
                }
            break;
            case 'scormname':
                $module = $DB->get_field('modules', 'name', ['id' => $row->moduleid]);

                $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                if (is_siteadmin()) {
                    $url = new moodle_url('/mod/scorm/report.php', ['id' => $row->cmid]);
                } else {
                    $url = new moodle_url('/mod/'.$module.'/view.php', ['id' => $row->cmid]);
                }
                $row->{$data->column} = $activityicon . html_writer::tag('a', $row->scormname, ['href' => $url]);
            break;
            case 'lastaccess':
                if (!empty($row->attempt)) {
                    $value = $DB->get_field_sql("SELECT ssv.timemodified
                    FROM {scorm_scoes_value} ssv
                    JOIN {scorm_element} se ON se.id = ssv.elementid
                    JOIN {scorm_attempt} sa ON ssv.attemptid = sa.id
                    JOIN {scorm} s ON s.id = sa.scormid
                    WHERE se.element = :element
                    AND sa.userid = :userid AND s.id = :scormid
                    AND sa.attempt = :attempt", ['element' => 'cmi.core.total_time', 'userid' => $row->userid,
                    'scormid' => $row->scormid, 'attempt' => $row->attempt, ]);
                    if (empty($value)) {
                        $lastaccess = $DB->get_field_sql("SELECT ssv.timemodified
                        FROM {scorm_scoes_value} ssv
                        JOIN {scorm_element} se ON se.id = ssv.elementid
                        JOIN {scorm_attempt} sa ON ssv.attemptid = sa.id
                        JOIN {scorm} s ON s.id = sa.scormid
                        WHERE se.element = :element
                        AND sa.userid = :userid AND s.id = :scormid
                        AND sa.attempt = :attempt", ['element' => 'x.start.time', 'userid' => $row->userid,
                        'scormid' => $row->scormid, 'attempt' => $row->attempt, ]);
                        $row->{$data->column} = $lastaccess ? userdate($lastaccess) : '--';
                    } else {
                        $row->{$data->column} = $value ? userdate($value) : '--';
                    }
                }
            break;
            case 'activitystate':
                $courserecord = $DB->get_record('course', ['id' => $row->courseid]);
                $completioninfo = new completion_info($courserecord);
                if ($CFG->dbtype == 'sqlsrv') {
                    $scormattemptstatus = $DB->get_field_sql("SELECT TOP 1 ssv.value FROM {scorm_scoes_value} ssv
                    JOIN {scorm_attempt} sa ON sa.id = ssv.attemptid
                    WHERE sa.scormid = :id AND sa.userid = :userid
                    ORDER BY ssv.id DESC ",
                    ['id' => $row->id, 'userid' => $row->userid]);

                    $scormcomppletion = $DB->get_field_sql("SELECT TOP 1 id
                    FROM {course_modules_completion}
                    WHERE coursemoduleid = :cmid AND userid = :userid
                    AND completionstate <> :completionstate
                    ORDER BY id DESC ", ['cmid' => $row->cmid, 'userid' => $row->userid,
                    'completionstate' => 0, ]);
                } else {
                    $scormattemptstatus = $DB->get_field_sql("SELECT ssv.value FROM {scorm_scoes_value} ssv
                    JOIN {scorm_attempt} sa ON sa.id = ssv.attemptid
                    WHERE sa.scormid = :id AND sa.userid = :userid
                    ORDER BY ssv.id DESC LIMIT 1 ", ['id' => $row->id, 'userid' => $row->userid]);
                    $scormcomppletion = $DB->get_field_sql("SELECT id
                    FROM {course_modules_completion}
                    WHERE coursemoduleid = :cmid AND userid = :userid
                    AND completionstate <> :completionstate ORDER BY id DESC LIMIT 1 ",
                    ['cmid' => $row->cmid, 'userid' => $row->userid, 'completionstate' => 0]);
                }

                if (empty($scormattemptstatus) && empty($scormcomppletion)) {
                    $completionstatus = html_writer::tag(
                        'span',
                        get_string('notyetstarted', 'block_learnerscript'),
                        ['class' => 'notyetstart']
                    );
                } else if (empty($scormattemptstatus) && !empty($scormcomppletion)) {
                    $completionstatus = html_writer::tag(
                        'span',
                        get_string('completed', 'block_learnerscript'),
                        ['class' => 'finished']
                    );
                } else if (!empty($scormattemptstatus) && empty($scormcomppletion)) {
                    $completionstatus = html_writer::tag(
                        'span',
                        get_string('inprogress', 'block_learnerscript'),
                        ['class' => 'finished']
                    );
                } else if (!empty($scormcomppletion)) {
                    $cm = new stdClass();
                    $cm->id = $row->cmid;
                    $completion = $completioninfo->get_data($cm, false, $row->userid);
                    switch($completion->completionstate) {
                        case COMPLETION_INCOMPLETE :
                            $completionstatus = get_string('inprogress', 'block_learnerscript');
                        break;
                        case COMPLETION_COMPLETE :
                            $completionstatus = get_string('completed', 'block_learnerscript');
                        break;
                        case COMPLETION_COMPLETE_PASS :
                            $completionstatus = get_string('completed_passgrade', 'block_learnerscript');
                        break;
                        case COMPLETION_COMPLETE_FAIL :
                            $completionstatus = get_string('fail', 'block_learnerscript');
                        break;
                    }
                }
                $row->{$data->column} = !empty($completionstatus) ? $completionstatus : '--';
            break;
            case 'attempt':
                if (!isset($row->attempt) && isset($data->subquery)) {
                    $attempt = $DB->get_field_sql($data->subquery);
                } else {
                    $attempt = $row->{$data->column};
                }
                $row->{$data->column} = !empty($attempt) ? $attempt : 0;
                break;
            case 'finalgrade':
                if (!isset($row->finalgrade) && isset($data->subquery)) {
                    $finalgrade = $DB->get_field_sql($data->subquery);
                } else {
                    $finalgrade = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($finalgrade) ? round($finalgrade, 2) : '--';
                } else {
                    $row->{$data->column} = !empty($finalgrade) ? round($finalgrade, 2) : 0;
                }
                break;
            case 'firstaccess':
                if (!isset($row->firstaccess) && isset($data->subquery)) {
                    $firstaccess = $DB->get_field_sql($data->subquery);
                } else {
                    $firstaccess = $row->{$data->column};
                }
                $row->{$data->column} = !empty($firstaccess) ? userdate($firstaccess) : '--';
                break;
            case 'totaltimespent':
                if (!isset($row->totaltimespent) && isset($data->subquery)) {
                    $totaltimespent = $DB->get_field_sql($data->subquery);
                } else {
                    $totaltimespent = $row->{$data->column};
                }
                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($totaltimespent) ? (new ls)->strtime($totaltimespent) : '--';
                } else {
                    $row->{$data->column} = !empty($totaltimespent) ? $totaltimespent : 0;
                }
                break;
            case 'numviews':
                if (!isset($row->numviews) && isset($data->subquery)) {
                    $numviews = $DB->get_field_sql($data->subquery);
                } else {
                    $numviews = $row->{$data->column};
                }
                $row->{$data->column} = !empty($numviews) ? $numviews : 0;
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}
