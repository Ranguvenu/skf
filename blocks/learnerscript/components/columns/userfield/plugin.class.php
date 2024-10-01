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
use moodle_url;

/**
 * user field
 */
class plugin_userfield extends pluginbase {

    /**
     * @var string $role User role
     */
    public $role;

    /**
     * @var string $reportinstance User role
     */
    public $reportinstance;

    /**
     * @var array $reportfilterparams User role
     */
    public $reportfilterparams;

    /**
     * User fields init function
     */
    public function init() {
        $this->fullname = get_string('userfield', 'block_learnerscript');
        $this->type = 'advanced';
        $this->form = true;
        $this->reporttypes = ['users', 'usercourses', 'grades'];
    }
    /**
     * User field column summary
     * @param object $data User fields column name
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
     * @return object
     */
    public function execute($data, $row) {
        global $DB, $CFG, $OUTPUT, $USER, $SESSION;
        $context = context_system::instance();
        $row->id = isset($row->userid) ? $row->userid : $row->id;
        if (strpos($data->column, 'profile_') === 0) {
            $sql = "SELECT d.*, f.shortname, f.datatype
                      FROM {user_info_data} d ,{user_info_field} f
                     WHERE f.id = d.fieldid AND d.userid = ?";
            if ($profiledata = $DB->get_records_sql($sql, [$row->id])) {
                foreach ($profiledata as $p) {
                    if ($p->datatype == 'checkbox') {
                        $p->data = ($p->data) ? get_string('yes') : get_string('no');
                    }
                    if ($p->datatype == 'datetime') {
                        $p->data = userdate($p->data);
                    }
                    $row->{'profile_' . $p->shortname} = $p->data;
                }
            }
        }
        $userprofilereport = $DB->get_field('block_learnerscript', 'id', ['type' => 'userprofile'], IGNORE_MULTIPLE);
        $userrecord = $DB->get_record('user', ['id' => $row->id]);
        $userrecord->fullname = html_writer::start_span('userdp_name', []);
        $userrecord->fullname .= $OUTPUT->user_picture($userrecord);
        (new reportbase($userprofilereport))->check_permissions($context, $USER->id);
        if (is_siteadmin()) {
            $userrecord->fullname .= html_writer::tag('a', fullname($userrecord),
            ['href' => new moodle_url('/blocks/reportdashboard/profilepage.php', ['filter_users' => $row->id])]);
        } else {
            $userrecord->fullname .= html_writer::tag('a', fullname($userrecord),
            ['href' => new moodle_url('/blocks/reportdashboard/profilepage.php', ['filter_users' => $row->id,
            'role' => $SESSION->role, 'contextlevel' => $SESSION->ls_contextlevel])]);
        }
        $userrecord->fullname .= html_writer::end_span();
        $userfullname = $userrecord->fullname;
        if ($CFG->messaging) {
            $userrecord->fullname .= html_writer::start_tag('sup', ['id' => 'communicate']);
            $userrecord->fullname .= html_writer::start_span('ls icon sendsms', [
                'id' => "sendsms_" . $this->reportinstance . "_" . $row->id, 'class' => "sendusermsg",
                'data-userid' => $row->id,
                'data-reportinstance' => $this->reportinstance,
                'data-userfullname' => $userfullname,
            ]);
            $userrecord->fullname .= html_writer::end_span();
            $userrecord->fullname .= html_writer::end_tag('sup');
        }
        if (isset($userrecord->{$data->column})) {
            switch ($data->column) {
                case 'email':
                    $userrecord->{$data->column} = html_writer::tag('a', $userrecord->{$data->column},
                    ['href' => 'mailto:'.$userrecord->{$data->column}.'' ]);
                break;
                case 'firstaccess':
                case 'lastaccess':
                case 'currentlogin':
                case 'timemodified':
                case 'lastlogin':
                case 'timecreated':
                    $userrecord->{$data->column} = ($userrecord->{$data->column}) ? userdate($userrecord->{$data->column}) : '--';
                    break;
                case 'url':
                case 'description':
                case 'imagealt':
                case 'lastnamephonetic':
                case 'firstnamephonetic':
                case 'middlename':
                case 'alternatename':
                case 'secret':
                case 'lang':
                case 'theme':
                case 'icq':
                case 'skype':
                case 'yahoo':
                case 'aim':
                case 'msn':
                case 'phone1':
                case 'phone2':
                case 'department':
                case 'address':
                case 'institution':
                case 'idnumber':
                    if ($userrecord->{$data->column} == null) {
                        $userrecord->{$data->column} = "--";
                    } else if ($userrecord->{$data->column}) {
                        $userrecord->{$data->column} = $userrecord->{$data->column};
                    } else {
                        $userrecord->{$data->column} = "--";
                    }
                    break;
                case 'country':
                    if ($userrecord->{$data->column} == null) {
                        $userrecord->{$data->column} = "--";
                    } else if ($userrecord->{$data->column}) {
                        $userrecord->{$data->column} = get_string(strtoupper($userrecord->{$data->column}), 'countries');
                    } else {
                        $userrecord->{$data->column} = "--";
                    }
                    break;
                case 'confirmed':
                case 'policyagreed':
                case 'maildigest':
                case 'ajax':
                case 'autosubscribe':
                case 'trackforums':
                case 'screenreader':
                case 'emailstop':
                case 'picture':
                    $userrecord->{$data->column} = ($userrecord->{$data->column}) ? get_string('yes') : get_string('no');
                    break;
                case 'deleted':
                case 'suspended':
                    $userrecord->{$data->column} = $userrecord->{$data->column} > 0 ?
                    html_writer::tag('span', get_string('yes'), ['class' => 'label label-warning']) :
                    html_writer::tag('span', get_string('no'), ['class' => 'label label-success']);
                break;
            }
        } else {
            $columnshortname = str_replace("profile_", "", $data->column);
            $result = $DB->get_record_sql("SELECT uid.data, uif.datatype
                                FROM {user_info_data} uid
                                join {user_info_field} uif on uif.id = uid.fieldid
                                WHERE uif.shortname = :columnshortname and uid.userid = :userid",
                                ['columnshortname' => $columnshortname, 'userid' => $row->id]);
            if ($result->datatype == 'datetime' && $result->data > 0) {
                $advdata = userdate($result->data, get_string('strftimedaydate', 'core_langconfig'));
            } else {
                $advdata = $result->data;
            }
            $userrecord->{$data->column} = !empty($advdata) ? $advdata : '--';
        }
        return (isset($userrecord->{$data->column})) ? $userrecord->{$data->column} : '';
    }
}
