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
use context_system;
use html_writer;
use completion_info;
use stdClass;
/**
 * Scorm participation columns
 */
class plugin_scormparticipationcolumns extends pluginbase {
    /**
     * Scorm participation column init function
     */
    public function init() {
        $this->fullname = get_string('scormparticipationcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['scormparticipation'];
    }
    /**
     * Scorm participation column summary
     * @param object $data Scorm participation column name
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
        $align = isset($data->align) ? $data->align : '';
        $size = isset($data->size) ? $data->size : '';
        $wrap = isset($data->wrap) ? $data->wrap : '';
        return [$align, $size, $wrap];
    }
    /**
     * This function executes the columns data
     * @param object $data Columns data
     * @param object $row Row data
     * @param string $reporttype Row data
     * @return object
     */
    public function execute($data, $row, $reporttype) {
        global $DB, $CFG, $OUTPUT, $USER;

        $context = context_system::instance();
        require_once($CFG->libdir . '/completionlib.php');

        switch ($data->column) {
            case 'username':
                $username = $DB->get_field('user', 'username', ['id' => $row->userid], IGNORE_MULTIPLE);
                $row->{$data->column} = $username ? $username : 'NA';
                break;

            case 'course':
                $reportid = $DB->get_field('block_learnerscript', 'id', ['type' => 'courseprofile'], IGNORE_MULTIPLE);
                $checkpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($context, $USER->id);
                $row->{$data->column} = !empty($row->{$data->column}) ? $row->{$data->column} : 'NA';
                break;

            case 'scormname':
                $module = $DB->get_field('modules', 'name', ['id' => $row->moduleid]);
                $activityicon = $OUTPUT->pix_icon('icon', ucfirst($module), $module, ['class' => 'icon']);
                $row->{$data->column} = $activityicon . $row->scormname;
                break;

            case 'activitystate':
                $courserecord = $DB->get_record('course', ['id' => $row->courseid]);
                $completioninfo = new completion_info($courserecord);

                $query = "SELECT ssv.value
                          FROM {scorm_scoes_value} ssv
                          JOIN {scorm_attempt} sa ON sa.id = ssv.attemptid
                          WHERE sa.scormid = :id AND sa.userid = :userid
                          ORDER BY ssv.id DESC LIMIT 1";

                $query1 = "SELECT id
                           FROM {course_modules_completion}
                           WHERE coursemoduleid = :cmid AND userid = :userid
                           AND completionstate <> :completionstate ORDER BY id DESC LIMIT 1";

                $scormattemptstatus = $DB->get_field_sql($query, ['id' => $row->id, 'userid' => $row->userid]);
                $scormcomppletion = $DB->get_field_sql($query1,
                ['cmid' => $row->cmid, 'userid' => $row->userid, 'completionstate' => 0]);

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
                    switch ($completion->completionstate) {
                        case COMPLETION_INCOMPLETE:
                            $completionstatus = get_string('inprogress', 'block_learnerscript');
                            break;
                        case COMPLETION_COMPLETE:
                            $completionstatus = get_string('completed', 'block_learnerscript');
                            break;
                        case COMPLETION_COMPLETE_PASS:
                            $completionstatus = get_string('completed_passgrade', 'block_learnerscript');
                            break;
                        case COMPLETION_COMPLETE_FAIL:
                            $completionstatus = get_string('fail', 'block_learnerscript');
                            break;
                    }
                }
                $row->{$data->column} = !empty($completionstatus) ? $completionstatus : '--';
                break;

            case 'attempt':
                $query = "SELECT  attempt
                    FROM {scorm_attempt}
                    WHERE 1 = 1 AND scormid = :scormid
                    AND userid = :userid ORDER BY id DESC LIMIT 1 ";

                $attempt = $DB->get_field_sql($query, ['userid' => $row->userid, 'scormid' => $row->scormid]);
                $row->{$data->column} = !empty($attempt) ? $attempt : 0;
                break;

            case 'finalgrade':
                $finalgrade = $DB->get_field_sql(
                    "SELECT gg.finalgrade
                     FROM {grade_grades} gg
                     JOIN {grade_items} gi ON gi.id = gg.itemid
                     WHERE gi.itemmodule = 'scorm' AND gg.userid = :userid AND gi.iteminstance = :scormid",
                     ['userid' => $row->userid, 'scormid' => $row->scormid]
                );
                $row->{$data->column} = !empty($finalgrade) ? round($finalgrade, 2) : '--';
                break;

            case 'firstaccess':
                $query = "SELECT ssv.value AS firstaccess
                          FROM {scorm_scoes_value} ssv
                          JOIN {scorm_element} se ON se.id = ssv.elementid
                          JOIN {scorm_attempt} sa ON sa.id = ssv.attemptid
                          WHERE se.element = 'x.start.time' AND sa.scormid = :scormid
                          AND sa.userid = :userid ORDER BY sa.attempt ASC LIMIT 1 ";

                $firstaccess = $DB->get_field_sql($query, ['userid' => $row->userid, 'scormid' => $row->scormid]);
                $row->{$data->column} = !empty($firstaccess) ? userdate($firstaccess) : '--';
                break;

            case 'lastaccess':
                if (!empty($row->attempt)) {
                    $value = $DB->get_field_sql(
                        "SELECT ssv.timemodified
                         FROM {scorm_scoes_value} ssv
                         JOIN {scorm_element} se ON se.id = ssv.elementid
                         JOIN {scorm_attempt} sa ON sa.id = ssv.attemptid
                         WHERE sa.attempt = :attempt
                         AND se.element = :element AND sa.scormid = :scormid AND sa.userid = :userid",
                         ['attempt' => $row->attempt, 'element' => 'cmi.core.total_time',
                         'scormid' => $row->scormid, 'userid' => $row->userid, ]
                    );

                    if (empty($value)) {
                        $lastaccess = $DB->get_field_sql(
                            "SELECT ssv.timemodified
                             FROM {scorm_scoes_value} ssv
                             JOIN {scorm_element} se ON se.id = ssv.elementid
                             JOIN {scorm_attempt} sa ON sa.id = ssv.attemptid
                             WHERE sa.attempt = :rowattempt
                             AND se.element = :element AND sa.scormid = :scormid AND sa.userid = :userid",
                             ['rowattempt' => $row->attempt, 'element' => 'x.start.time',
                             'scormid' => $row->scormid, 'userid' => $row->userid, ]
                        );
                        $row->{$data->column} = $lastaccess ? userdate($lastaccess) : '--';
                    } else {
                        $row->{$data->column} = $value ? userdate($value) : '--';
                    }
                }
                break;

            case 'totaltimespent':
                $totaltimespent = $DB->get_field_sql(
                    "SELECT SUM(mt.timespent) AS totaltimespent
                     FROM {block_ls_modtimestats} mt
                     JOIN {course_modules} cm ON cm.id = mt.activityid
                     JOIN {modules} m ON m.id = cm.module
                     WHERE m.name = 'scorm' AND cm.instance = :scormid AND mt.userid = :userid",
                     ['userid' => $row->userid, 'scormid' => $row->scormid]
                );

                if ($reporttype == 'table') {
                    $row->{$data->column} = !empty($totaltimespent) ? (new ls)->strtime($totaltimespent) : '--';
                } else {
                    $row->{$data->column} = !empty($totaltimespent) ? $totaltimespent : 0;
                }
                break;
        }

        return isset($row->{$data->column}) ? $row->{$data->column} : '--';
    }
}
