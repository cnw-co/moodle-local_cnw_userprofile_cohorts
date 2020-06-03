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
 * User profile cohorts
 *
 * @package     local_cnw_userprofile_cohorts
 * @copyright   CNW Rendszerintegrációs Zrt. <moodle@cnw.hu>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Class SmartCohortNotInstalledException
 */
class SmartCohortNotInstalledException extends Exception {}

/**
 * Check Smart Cohort plugin is installed
 *
 * @return bool
 */
function userprofile_cohorts_check_smartcohort_is_installed($plugins = null) {
    if(is_null($plugins)) {
        $plugins = core_plugin_manager::instance()->get_installed_plugins('local');
    }
    return array_key_exists('cnw_smartcohort', $plugins);
}

/**
 * Get Smart Cohort plugin version number
 *
 * @return mixed
 * @throws SmartCohortNotInstalledException
 */

function userprofile_cohorts_get_smartcohort_version($check = true) {
    if($check && userprofile_cohorts_check_smartcohort_is_installed()) {
        $plugins = core_plugin_manager::instance()->get_installed_plugins('local');
        return $plugins['cnw_smartcohort'];
    }

    throw new SmartCohortNotInstalledException();
}

/**
 * Get smartcohort memberships by user
 *
 * @param stdClass $user
 * @return array
 * @throws dml_exception
 */

function userprofile_cohorts_get_smartcohort_memberships(stdClass $user) {
    global $DB;

    try {
        $version = userprofile_cohorts_get_smartcohort_version();
        $smart_cohort_memberships = $DB->get_records('cnw_sc_user_cohort', array('user_id' => $user->id));
        return $smart_cohort_memberships;
    } catch (SmartCohortNotInstalledException $e) {
        return array();
    }
}

/**
 * Pluck array of array or object
 *
 * @param array $array
 * @param $key
 * @param bool $unique
 * @return array
 */

function userprofile_cohorts_pluck(array $array, $key, $unique = false) {
    $return = array();
    foreach($array as $item) {
        if (is_array($item)) {
            $return[] = $item[$key];
        } else if(is_object($item)) {
            $return[] = $item->$key;
        }
    }

    if($unique) {
        $return = array_unique($return);
    }

    return $return;
}

/**
 * Get cohorts by user. You can optionally pass excepts id array for filter
 *
 * @param stdClass $user
 * @param array $excepts optional
 * @return array
 * @throws dml_exception
 */

function userprofile_cohorts_get_cohorts(stdClass $user, array $excepts = array()) {
    global $DB;

    $cohorts = array();

    if(count($excepts) > 0) {
        $filter = array();
        $params = array();
        foreach($excepts as $e) {
            $filter[] = "cohortid != ?";
            $params[] = $e;
        }
        $filter[] = "userid = ?";
        $params[] = $user->id;
        $filter_sql = implode(" AND ", $filter);
        $memberships = $DB->get_records_sql('SELECT * FROM {cohort_members} WHERE ' . $filter_sql,
            $params);
    } else {
        $memberships = $DB->get_records('cohort_members', array('userid' => $user->id));
    }

    foreach($memberships as $membership) {
        $cohorts[] = $DB->get_record('cohort', array('id' => $membership->cohortid));
    }

    return $cohorts;
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 *
 * @return bool
 */
function local_cnw_userprofile_cohorts_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course)
{
    global $DB;

    $systemcontext = context_system::instance();
    $courseorsystemcontext = !empty($course) ? context_course::instance($course->id) : $systemcontext;

    if (isguestuser($user) || !has_capability('moodle/cohort:view', $courseorsystemcontext)) {
        return false;
    }

    $category = new \core_user\output\myprofile\category('cnw_cohorts',
        get_string('profile_category_title', 'local_cnw_userprofile_cohorts'));
    $tree->add_category($category);

    $smart_cohort_memberships = userprofile_cohorts_get_smartcohort_memberships($user);
    $cohorts = userprofile_cohorts_get_cohorts($user, userprofile_cohorts_pluck($smart_cohort_memberships, 'cohort_id'));
    $cohorts_smartcohort = userprofile_cohorts_get_cohorts($user, userprofile_cohorts_pluck($cohorts, 'id'));

    $links = array();

    if(count($cohorts) > 0) {
        foreach ($cohorts as $cohort) {
            if(has_capability('moodle/cohort:assign', $courseorsystemcontext)) {
                $postsurl = new moodle_url('/cohort/assign.php', array('id' => $cohort->id));
                $links[] = html_writer::link($postsurl, $cohort->name);
            } else {
                $links[] = $cohort->name;
            }
        }
    } else {
        $links[] = html_writer::span(get_string('not_in_any_cohort', 'local_cnw_userprofile_cohorts'), 'label label-info');
    }

    $node = new \core_user\output\myprofile\node('cnw_cohorts', 'manual_title',
        get_string('manual_title', 'local_cnw_userprofile_cohorts'), null, null,
        implode('<br/>', $links));
    $tree->add_node($node);

    //Smart Cohort filters
    $links = array();
    foreach ($smart_cohort_memberships as $membership) {
        $cohort = $DB->get_record('cohort', array('id' => $membership->cohort_id));
        $filter = $DB->get_record('cnw_sc_filters', array('id' => $membership->filter_id));
        if(has_capability('moodle/cohort:assign', $courseorsystemcontext)) {
            try {
                $version = userprofile_cohorts_get_smartcohort_version();
                if($version <= 2019050603) {
                    $filter_link = new moodle_url('local/cnw_smartcohort/edit.php?id=' . $filter->id);
                } else {
                    $filter_link = new moodle_url('local/cnw_smartcohort/edit.php?id=' . $filter->id . '&format=' . $filter->type);
                }
            } catch (SmartCohortNotInstalledException $e) {
                $filter_link = new moodle_url('local/cnw_smartcohort/edit.php?id=' . $filter->id);
            }
            $links[] = $cohort->name . " (" . get_string('filter', 'local_cnw_userprofile_cohorts') . ": " . html_writer::link($filter_link, $filter->name) . ')';
        } else {
            $links[] = $cohort->name . " (" . get_string('filter', 'local_cnw_userprofile_cohorts') . ": " . $filter->name . ")";
        }

    }

    if(!userprofile_cohorts_check_smartcohort_is_installed()) {
        $links[] = html_writer::span(get_string('smart_cohort_not_installed', 'local_cnw_userprofile_cohorts'), 'label label-danger');
        $links[] = get_string('smart_cohort_check_link', 'local_cnw_userprofile_cohorts') . ': ' . html_writer::link('https://moodle.org/plugins/local_cnw_smartcohort', 'https://moodle.org/plugins/local_cnw_smartcohort');
    } else if(count($links) == 0) {
        $links[] = html_writer::span(get_string('not_in_any_cohort_by_smart_cohort', 'local_cnw_userprofile_cohorts'), 'label label-info');
    }

    $node = new \core_user\output\myprofile\node('cnw_cohorts', 'smart_cohort_title',
        get_string('smart_cohort_title', 'local_cnw_userprofile_cohorts'), null, null, implode('<br/>', $links));
    $tree->add_node($node);

    return true;
}