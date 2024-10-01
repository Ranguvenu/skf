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
use context_system;
use cache;
use context_helper;
use context_coursecat;

/**
 * A Moodle block to create customizable reports.
 *
 * @package   block_learnerscript
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permissionslib {

    /**
     * @var $contextlevel
     */
    private $contextlevel;

    /**
     * @var $roleid
     */
    private $roleid;

    /**
     * @var $archetype
     */
    private $archetype;

    /**
     * @var $userid
     */
    private $userid;

    /**
     * @var $moodleroles
     */
    public $moodleroles;

    /**
     * Construct
     * @param  int $contextlevel   User contextlevel
     * @param  int $roleid         User role ID
     * @param  int $userid         User ID
     */
    public function __construct($contextlevel, $roleid, $userid=null) {
        $this->set_context($contextlevel);
        $this->set_roleid($roleid);
        $this->set_userid($userid);
    }

    /**
     * Set context
     * @param int $contextlevel Set user contextlevel
     */
    private function set_context($contextlevel) {
        $this->contextlevel = $contextlevel;
    }

    /**
     * Set roleid
     * @param  int $roleid    User role ID
     */
    private function set_roleid($roleid) {
        $this->roleid = $roleid;
        $this->set_role_archtype();
    }

    /**
     * Set userid
     * @param  int $userid   User role ID
     */
    private function set_userid($userid) {
        global $USER;
        $this->userid = !is_null($userid) ? $userid : $USER->id;
    }

    /**
     * Set role archtype
     */
    private function set_role_archtype() {
        global $DB;
        $this->archetype = $DB->get_field('role', 'archetype', ['id' => $this->roleid]);
    }

    /**
     * Get rolewise courses
     */
    public function get_rolewise_courses() {
        global $DB;
        $context = context_system::instance();
        if (!has_capability('block/learnerscript:reportsaccess', $context)) {
            return false;
        }
        switch ($this->contextlevel) {
            case CONTEXT_SYSTEM:
                if (has_capability('block/learnerscript:managereports', $context)) {
                    return true;
                } else {
                    return $this->get_rolecourses();
                }
                break;
            case CONTEXT_COURSE:
                return $this->get_rolecourses();
                break;
            case CONTEXT_COURSECAT:
                if (has_capability('block/learnerscript:managereports', $context) && $this->contextlevel == CONTEXT_COURSECAT) {
                    $categories = $this->make_categories_list('moodle/category:manage');
                    list($csql, $params) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED);
                    return  $DB->get_fieldset_sql("SELECT id
                    FROM {course}
                    WHERE category $csql");
                } else {
                    $categories = $this->make_categories_list('moodle/category:viewhiddencategories');
                    list($csql, $params) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED);
                    $categorycourses = $DB->get_fieldset_sql("SELECT id FROM {course} WHERE category $csql", $params);
                     // Little trick here for categoty level course creator for child categories.
                     // Considering editing teacher role instead of coursecreator.
                    if ($this->roleid == $DB->get_field('role', 'id', ['shortname' => 'coursecreator'])) {
                        $this->roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
                        $this->contextlevel = CONTEXT_COURSE;
                        $assignedcourses = $this->get_rolecourses();
                        return array_intersect($categorycourses, $assignedcourses);
                    }
                }
            break;
            default:
                return false;
            break;
        }
    }

    /**
     * Get role courses
     */
    private function get_rolecourses() {
        global $DB;
        $params['contextlevel'] = $this->contextlevel;
        $params['userid'] = $this->userid;
        $params['userid1'] = $this->userid;
        $params['roleid'] = $this->roleid;
        $params['active'] = ENROL_USER_ACTIVE;
        $params['enabled'] = ENROL_INSTANCE_ENABLED;
        $params['now1'] = round(time(), -2); // Improves db caching.
        $params['now2'] = $params['now1'];
        $role = $DB->get_field_sql("SELECT shortname FROM {role} WHERE id = :roleid",
                        ['roleid' => $this->roleid]);
        $params['roleshortname'] = $role;

        $sql = " SELECT c.id
        FROM {course} c";
        if ($this->contextlevel == CONTEXT_SYSTEM) {
            $sql .= " LEFT JOIN {context} AS ctx ON ctx.instanceid = 0 AND ctx.contextlevel = :contextlevel";
        } else if ($this->contextlevel == CONTEXT_COURSECAT) {
            $sql .= " JOIN {course_categories} cc ON cc.id = c.category
            LEFT JOIN {context} AS ctx ON ctx.instanceid = cc.id AND ctx.contextlevel = :contextlevel";
        } else {
            $sql .= " LEFT JOIN {context} AS ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        }
        $sql .= " JOIN {role_assignments} ra ON ra.contextid = ctx.id
                 JOIN {role} r ON r.id = ra.roleid
                WHERE ra.userid = :userid AND r.shortname = :roleshortname";

        $courses = $DB->get_fieldset_sql($sql, $params);
        return $courses;
    }

    // Overriding default method - Just to make sure, schedule reports.
    // and emails will take userID based on configuration and not the current user.
    /**
     * Get categories list
     * @param  string $requiredcapability Capability required to access reports
     * @param  array $excludeid          Excluded ID
     * @param  string $separator         Separator
     * @return array
     */
    public function make_categories_list($requiredcapability = '', $excludeid = 0, $separator = ' / ') {
        global $DB;
        $coursecatcache = cache::make('core', 'coursecat');

        // Check if we cached the complete list of user-accessible category names ($baselist) or list of ids
        // with requried cap ($thislist).
        $currentlang = current_language();
        $basecachekey = $currentlang . '_catlist';
        $baselist = $coursecatcache->get($basecachekey);
        $thislist = false;
        $thiscachekey = null;
        if (!empty($requiredcapability)) {
            $requiredcapability = (array)$requiredcapability;
            $thiscachekey = 'catlist:'. json_encode($requiredcapability);
            if ($baselist !== false && ($thislist = $coursecatcache->get($thiscachekey)) !== false) {
                $thislist = preg_split('|,|', $thislist, -1, PREG_SPLIT_NO_EMPTY);
            }
        } else if ($baselist !== false) {
            $thislist = array_keys($baselist);
        }

        if ($baselist === false) {
            // We don't have $baselist cached, retrieve it. Retrieve $thislist again in any case.
            $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
            $sql = "SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent, cc.path, $ctxselect
                    FROM {course_categories} cc
                    JOIN {context} ctx ON cc.id = ctx.instanceid AND ctx.contextlevel = :contextcoursecat
                    WHERE cc.visible = 1
                    ORDER BY cc.sortorder";
            $rs = $DB->get_recordset_sql($sql, ['contextcoursecat' => CONTEXT_COURSECAT]);
            $baselist = [];
            $thislist = [];
            foreach ($rs as $record) {
                // If the category's parent is not visible to the user, it is not visible as well.
                if (!$record->parent || isset($baselist[$record->parent])) {
                    context_helper::preload_from_record($record);
                    $context = context_coursecat::instance($record->id);
                    if (!$record->visible && !has_capability('moodle/category:viewhiddencategories', $context, $this->userid)) {
                        // No cap to view category, added to neither $baselist nor $thislist.
                        continue;
                    }
                    $baselist[$record->id] = [
                        'name' => format_string($record->name, true, ['context' => $context]),
                        'path' => $record->path,
                    ];
                    if (!empty($requiredcapability) && !has_all_capabilities($requiredcapability, $context, $this->userid)) {
                        // No required capability, added to $baselist but not to $thislist.
                        continue;
                    }
                    $thislist[] = $record->id;
                }
            }
            $rs->close();
            $coursecatcache->set($basecachekey, $baselist);
            if (!empty($requiredcapability)) {
                $coursecatcache->set($thiscachekey, join(',', $thislist));
            }
        } else if ($thislist === false) {
            // We have $baselist cached but not $thislist. Simplier query is used to retrieve.
            $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
            $sql = "SELECT ctx.instanceid AS id, $ctxselect
                    FROM {context} ctx WHERE ctx.contextlevel = :contextcoursecat";
            $contexts = $DB->get_records_sql($sql, ['contextcoursecat' => CONTEXT_COURSECAT]);
            $thislist = [];
            foreach (array_keys($baselist) as $id) {
                context_helper::preload_from_record($contexts[$id]);
                if (has_all_capabilities($requiredcapability, context_coursecat::instance($id), $this->userid)) {
                    $thislist[] = $id;
                }
            }
            $coursecatcache->set($thiscachekey, join(',', $thislist));
        }

        // Now build the array of strings to return, mind $separator and $excludeid.
        $names = [];
        foreach ($thislist as $id) {
            $path = preg_split('|/|', $baselist[$id]['path'], -1, PREG_SPLIT_NO_EMPTY);
            if (!$excludeid || !in_array($excludeid, $path)) {
                $namechunks = [];
                foreach ($path as $parentid) {
                    $namechunks[] = $baselist[$parentid]['name'];
                }
                $names[$id] = join($separator, $namechunks);
            }
        }
        return $names;
    }
}
