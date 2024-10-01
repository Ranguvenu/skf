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

namespace block_learnerscript\local;
use html_writer;
use moodle_url;

/**
 * Class license_setting
 *
 * @package    block_learnerscript
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class license_setting extends \admin_setting_configtext {
    /**
     * Get learnerscript settings
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }
    /**
     * Write learnerscript settings
     * @param string $data
     */
    public function write_setting($data) {
        GLOBAL $CFG, $PAGE;

        if (empty($data)) {
            set_config('ls_'.$this->name, $data, 'block_learnerscript');
            return '';
        }
        $validated = $this->validate($data);
        if ($validated !== true) {
            return $validated;
        }
        $learnerscript = md5($data);
        set_config('ls_'.$this->name, $learnerscript, 'block_learnerscript');
        $lsreportconfigstatus = get_config('block_learnerscript', 'lsreportconfigstatus');
        if (!$lsreportconfigstatus) {
            redirect(new moodle_url('/blocks/learnerscript/lsconfig.php', ['import' => 1]));
        } else {
            $reportdashboardblockexists = $PAGE->blocks->is_known_block_type('reportdashboard', false);
            if ($reportdashboardblockexists) {
                redirect(new moodle_url('/blocks/reportdashboard/dashboard.php'));
            } else {
                redirect(new moodle_url('/blocks/learnerscript/managereport.php'));
            }
        }
    }
    /**
     * Validate data before storage
     * @param string $data
     * @return mixed true if ok string if error found
     */
    public function validate($data) {
        GLOBAL $CFG;

        // Allow paramtype to be a custom regex if it is the form of /pattern/.
        if (preg_match('#^/.*/$#', $this->paramtype)) {
            if (preg_match($this->paramtype, $data)) {
                return true;
            } else {
                return get_string('validateerror', 'admin');
            }
        } else {
            $cleaned = clean_param($data, $this->paramtype);
            if ("$data" === "$cleaned") { // Implicit conversion to string is needed to do exact comparison.
                $curl = new \curl;
                $params['serial'] = $cleaned;
                $params['surl'] = $CFG->wwwroot;
                $param = json_encode($params);
                $json = $curl->post('https://learnerscript.com?wc-api=custom_validate_serial_key', $param);
                for ($i = 0; $i <= 31; ++$i) {
                    $json = str_replace(chr($i), "", $json);
                }
                $json = str_replace(chr(127), "", $json);

                // This is the most common part
                // Some file begins with 'efbbbf' to mark the beginning of the file. (binary level)
                // Here we detect it and we remove it, basically it's the first 3 characters.
                if (0 === strpos(bin2hex($json), 'efbbbf')) {
                    $json = substr($json, 3);
                }
                $jsondata = json_decode($json, true);
                if ($jsondata['success'] == 'true') {
                    return true;
                } else {
                    return $jsondata['message'];
                }
            } else {
                return get_string('validateerror', 'admin');
            }
        }
    }
    /**
     * Output HTML
     * @param string $data Import report data
     * @param string $query Import query
     * @return string
     */
    public function output_html($data, $query='') {
        global $CFG, $PAGE;
        $default = $this->get_defaultsetting();
        $pluginman = \core_plugin_manager::instance();
        $reportdashboardpluginfo = $pluginman->get_plugin_info('block_reportdashboard');
        $reporttilespluginfo = $pluginman->get_plugin_info('block_reporttiles');
        $error = false;
        $errordata = [];
        $reportdashboardblockexists = $PAGE->blocks->is_known_block_type('reportdashboard', false);
        // Make sure we know the plugin.
        if (is_null($reportdashboardpluginfo) || !$reportdashboardblockexists) {
            $error = true;
            $errordata[] = get_string('learnerscriptwidget', 'block_learnerscript');
        }
        $reporttilesblockexists = $PAGE->blocks->is_known_block_type('reporttiles', false);
        // Make sure we know the plugin.
        if (is_null($reporttilespluginfo) || !$reporttilesblockexists) {
            $error = true;
            $errordata[] = get_string('learnerscripttiles', 'block_learnerscript');
        }

        $return = '';
        $disabled = '';
        if ($error) {
            $errormsg = implode(', ', $errordata);
            $installlink = html_writer::link(
                new moodle_url('/admin/tool/installaddon/index.php'),
                get_string('installplugins', 'block_learnerscript'),
                ['title' => get_string('installplugin', 'block_learnerscript')]
            );
            $return = html_writer::div(
                get_string('installenable', 'block_learnerscript') . $errormsg . ' '.
                get_string('pluginclick', 'block_learnerscript') .' '. $installlink,
                'alert alert-notice'
            );
            $disabled = 'disabled';
        }

        $inputattributes = [
            'type' => 'text',
            'size' => $this->size,
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'value' => s($data),
            $disabled => $disabled,
        ];

        $input = html_writer::empty_tag('input', $inputattributes);
        $return .= format_admin_setting($this, $this->visiblename,
        html_writer::tag('div', $input, ['class' => 'form-text defaultsnext']),
        $this->description, true, '', $default, $query);

        return $return;
    }
}
