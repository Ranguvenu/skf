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

require_once("../../config.php");
require_once($CFG->libdir.'/adminlib.php');
use html_writer;

$import = optional_param('import', 0, PARAM_INT);
$reset = optional_param('reset', 0, PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('block/learnerscript:managereports', $context);

$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->css('/blocks/learnerscript/css/slideshow.css');

$learnerscript = get_config('block_learnerscript', 'ls_serialkey');
if (empty($learnerscript)) {
    throw new moodle_exception('reqlicencekey', 'block_learnerscript');
}

$lsreportconfigstatus = get_config('block_learnerscript', 'lsreportconfigstatus');
$PAGE->set_url(new moodle_url('/blocks/learnerscript/import.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('maintenance');
$PAGE->set_title(get_string('importreports', 'block_learnerscript'));
$PAGE->set_heading(get_string('learnerscriptreportconfiguration', 'block_learnerscript'));
if ($reset) {
    $DB->delete_records('block_learnerscript');
    $DB->delete_records('block_ls_schedule');
    $blockinstancessql = "SELECT id
                            FROM {block_instances}
                           WHERE (pagetypepattern LIKE :pagetypepattern
                                    OR blockname = :blockname)";
    $blockinstances = $DB->get_fieldset_sql($blockinstancessql,
    ['pagetypepattern' => '%blocks-reportdashboard%', 'blockname' => 'coursels']);

    if (!empty($blockinstances)) {
        blocks_delete_instances($blockinstances);
    }
    set_config('lsreportconfigstatus', 0, 'block_learnerscript');
    set_config('lsreportconfigimport', 0, 'block_learnerscript');

    $usertours = $CFG->dirroot . '/blocks/learnerscript/usertours/';
    $usertoursjson = glob($usertours . '*.json');

    foreach ($usertoursjson as $usertour) {
        $data = file_get_contents($usertour);
        $tourconfig = json_decode($data);
        $DB->delete_records('tool_usertours_tours', ['name' => $tourconfig->name]);
    }
    redirect(new moodle_url('/blocks/learnerscript/import.php', ['import' => 1]));
}

$lsreportconfigimport = get_config('block_learnerscript', 'lsreportconfigimport');
if ($lsreportconfigimport) {
    throw new moodle_exception(get_string('alreadyimportstarted', 'block_learnerscript'));
}

$path = $CFG->dirroot . '/blocks/learnerscript/reportsbackup/';
$learnerscriptreports = glob($path . '*.xml');
$lsreportscount = $DB->count_records('block_learnerscript');
$lsimportlogs = [];
$lastreport = 0;
foreach ($lsimportlogs as $lsimportlog) {
    $lslog = json_decode($lsimportlog);
    if ($lslog['status'] == false) {
        $errorreportsposition[$lslog['position']] = $lslog['position'];
    }

    if ($lslog['status'] == true) {
        $lastreportposition = $lslog['position'];
    }
}

$importstatus = false;
if (empty($lsimportlogs) || $lsreportscount < 1) {
    $total = count($learnerscriptreports);

    $current = 1;
    $percentwidth = $current / $total * 100;
    $importstatus = true;
    $errorreportsposition = [];
    $lastreportposition = 0;
} else {
    $total = 0;
    foreach ($learnerscriptreports as $position => $learnerscriptreport) {
        if ((!empty($errorreportsposition) && in_array($position, $errorreportsposition)) || $position >= $lastreportposition) {
            $total++;
        }
    }
    if (empty($errorreportsposition)) {
        $current = $lastreportposition + 1;
        $errorreportsposition = [];
    } else {
        $occuredpositions = array_merge($errorreportsposition, [$lastreportposition]);
        $current = min($occuredpositions);
    }
    if ($total > 0) {
        $importstatus = true;
    }
}
$errorreportspositiondata = json_encode($errorreportsposition);

echo $OUTPUT->header();
$slideshowimagespath = '/blocks/learnerscript/images/slideshow/';
$slideshowimages = scandir($CFG->dirroot . $slideshowimagespath, SCANDIR_SORT_ASCENDING);
$slideshowcount = 0;
echo html_writer::start_tag('div', ['class' => 'lsoverviewimageslider']);
if (!empty($slideshowimages)) {
    foreach ($slideshowimages as $slideshowimage) {
        if (exif_imagetype($CFG->wwwroot . $slideshowimagespath . $slideshowimage)) {
            $slideshowcount++;
            echo html_writer::div(html_writer::div(html_writer::empty_tag('img',
            ['src' => $CFG->wwwroot . $slideshowimagespath . $slideshowimage, 'class' => "lsoverviewimages"]), "",
            []), "mySlides");
        }
    }
}

$reportdashboardblockexists = $PAGE->blocks->is_known_block_type('reportdashboard', false);
if ($reportdashboardblockexists) {
    $redirecturl = new moodle_url('/blocks/reportdashboard/dashboard.php');
} else {
    $redirecturl = new moodle_url('/blocks/learnerscript/managereport.php');
}

if ($importstatus && !$lsreportconfigstatus) {
    $pluginsettings = new block_learnerscript\local\license_setting('block_learnerscript/lsreportconfigimport',
                'lsreportconfigimport', get_string('lsreportconfigimport', 'block_learnerscript'), '', PARAM_INT, 2);
    $pluginsettings->config_write('lsreportconfigimport', 1);

    echo html_writer::div('', "", ['id' => 'progressbar']);
    echo html_writer::tag(
        'center',
        html_writer::div(
            html_writer::link(
                new moodle_url($redirecturl),
                html_writer::tag(
                    'button',
                    get_string('continue', 'block_learnerscript')
                )
            ),
            '',
            ['id' => 'reportdashboardnav']
        ),
        ['style' => 'display:none']
    );
    $usertours = $CFG->dirroot . '/blocks/learnerscript/usertours/';
    $totalusertours = count(glob($usertours . '*.json'));
    $usertoursjson = glob($usertours . '*.json');
    $pluginmanager = new \tool_usertours\manager();
    for ($i = 0; $i < $totalusertours; $i++) {
        $importurl = $usertoursjson[$i];
        if (file_exists($usertoursjson[$i])
                && pathinfo($usertoursjson[$i], PATHINFO_EXTENSION) == 'json') {
            $data = file_get_contents($importurl);
            $tourconfig = json_decode($data);
            $tourexists = $DB->record_exists('tool_usertours_tours', ['name' => $tourconfig->name]);
            if (!$tourexists) {
                $tour = $pluginmanager->import_tour_from_json($data);
            }
        }
    }
} else {
    echo html_writer::div(get_string('lsreportsconfigdone', 'block_learnerscript') .
    html_writer::link(new moodle_url($redirecturl),
    get_string('clickhere', 'block_learnerscript')) .get_string('tocontinue', 'block_learnerscript'),
    "alert alert-info");
}
echo html_writer::end_tag('center') . html_writer::end_tag('div');

if ($importstatus && !$lsreportconfigstatus) {
    $PAGE->requires->js_call_amd('block_learnerscript/lsreportconfig', 'init',
                                    [['total' => $total,
                                                'current' => $current,
                                                'errorreportspositiondata' => $errorreportspositiondata,
                                                'lastreportposition' => $lastreportposition,
                                            ],
                                    ]);

}
echo $OUTPUT->footer();
