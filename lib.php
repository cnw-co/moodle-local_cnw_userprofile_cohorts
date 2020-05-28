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

    $plugins = core_plugin_manager::instance()->get_installed_plugins('local');
    $cnw_smart_cohort_is_installed = array_key_exists('cnw_smartcohort', $plugins);
    $smart_cohort_memberships = array();
    if($cnw_smart_cohort_is_installed) {
        $smart_cohort_memberships = $DB->get_records('cnw_sc_user_cohort', ['user_id' => $user->id]);
    }

    if ($cnw_smart_cohort_is_installed && count($smart_cohort_memberships) > 0) {
        $cohort_ids = array();
        foreach ($smart_cohort_memberships as $scm) {
            $cohort_ids[] = $scm->cohort_id;
        }

        $manual_assigned_cohorts = $DB->get_records_sql('SELECT * FROM {cohort_members} WHERE cohortid NOT IN (?) AND userid = ?',
            array(implode(", ", $cohort_ids), $user->id));
    } else {
        $manual_assigned_cohorts = $DB->get_records('cohort_members', ['userid' => $user->id]);
        $smart_cohort_memberships = array();
    }

    //Manual assigned
    $links = array();
    if(count($manual_assigned_cohorts) > 0) {
        foreach ($manual_assigned_cohorts as $cohort) {
            $cohort = $DB->get_record('cohort', array('id' => $cohort->cohortid));
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
    $links = [];
    foreach ($smart_cohort_memberships as $membership) {
        $cohort = $DB->get_record('cohort', ['id' => $membership->cohort_id]);
        $filter = $DB->get_record('cnw_sc_filters', ['id' => $membership->filter_id]);
        if(has_capability('moodle/cohort:assign', $courseorsystemcontext)) {
            if($plugins['cnw_smartcohort'] <= 2019050603) {
                $filter_link = new moodle_url('local/cnw_smartcohort/edit.php?id=' . $filter->id);
            } else {
                $filter_link = new moodle_url('local/cnw_smartcohort/edit.php?id=' . $filter->id . '&format=' . $filter->type);
            }
            $links[] = $cohort->name . " (" . get_string('filter', 'local_cnw_userprofile_cohorts') . ": " . html_writer::link($filter_link, $filter->name) . ')';
        } else {
            $links[] = $cohort->name . " (" . get_string('filter', 'local_cnw_userprofile_cohorts') . ": " . $filter->name . ")";
        }

    }

    if(!$cnw_smart_cohort_is_installed) {
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