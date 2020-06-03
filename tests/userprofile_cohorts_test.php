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

global $CFG;
require_once(__DIR__ . "/../lib.php");
require_once("$CFG->dirroot/cohort/lib.php");
require_once(__DIR__ . "/../lib.php");
require_once("$CFG->dirroot/user/lib.php");

class userprofile_cohorts_test extends advanced_testcase
{

    public function testuserprofile_cohorts_check_smartcohort_is_installed() {
        $plugins = core_plugin_manager::instance()->get_installed_plugins('local');
        $result = userprofile_cohorts_check_smartcohort_is_installed();
        $this->assertEquals(array_key_exists('cnw_smartcohort', $plugins), $result);
    }


    public function testuserprofile_cohorts_get_smartcohort_version() {
        $plugins = core_plugin_manager::instance()->get_installed_plugins('local');
        $result = userprofile_cohorts_check_smartcohort_is_installed();
        if($result) {
            $this->assertEquals($plugins['cnw_smartcohort'], userprofile_cohorts_get_smartcohort_version());
        }
        $this->expectException(SmartCohortNotInstalledException::class);
        userprofile_cohorts_get_smartcohort_version(false);
    }

    public function testuserprofile_cohorts_pluck() {

        $array = array(
            array('id' => 123, 'name' => 'Alma'),
            array('id' => 321, 'name' => 'AlmaKorte'),
        );

        $pluck = array();
        foreach($array as $ar) {
            $pluck[] = $ar['id'];
        }

        $result = userprofile_cohorts_pluck($array, 'id');
        $this->assertEquals($pluck, $result);

        $obj = new stdClass();
        $obj->id = 123;

        $obj1 = new stdClass();
        $obj1->id = 321;

        $arrayOfObject = array(
            $obj, $obj1
        );

        $pluck = array();
        foreach($arrayOfObject as $ar) {
            $pluck[] = $ar->id;
        }

        $result = userprofile_cohorts_pluck($arrayOfObject, 'id');
        $this->assertEquals($pluck, $result);


    }

    public function testuserprofile_cohorts_get_smartcohort_memberships() {
        global $DB;
        $this->resetAfterTest();
        if(userprofile_cohorts_check_smartcohort_is_installed()) {
            $cohort = $this->getDataGenerator()->create_cohort();
            $user = $this->getDataGenerator()->create_user();
            $user2 = $this->getDataGenerator()->create_user();

            //Create fake cnw_sc_user_cohort
            $record = new stdClass();
            $record->user_id = $user->id;
            $record->cohort_id = $cohort->id;
            $record->filter_id = 9999999;
            $DB->insert_record('cnw_sc_user_cohort', $record);

            $result = userprofile_cohorts_get_smartcohort_memberships($user);
            $this->assertEquals(count($result), 1);

            $result = userprofile_cohorts_get_smartcohort_memberships($user2);
            $this->assertEquals(count($result), 0);

        } else {
            $user = $this->getDataGenerator()->create_user();
            $result = userprofile_cohorts_get_smartcohort_memberships($user);
            $this->assertEquals(count($result), 0);
        }

    }

    public function testuserprofile_cohorts_get_cohorts() {
        global $DB;
        $this->resetAfterTest();

        //Without params
        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $result = userprofile_cohorts_get_cohorts($user);
        $this->assertEquals($result, array());
        cohort_add_member($cohort->id, $user->id);
        $result = userprofile_cohorts_get_cohorts($user);
        $this->assertEquals(count($result), 1);

        cohort_add_member($cohort2->id, $user->id);
        $result = userprofile_cohorts_get_cohorts($user);
        $this->assertEquals(count($result), 2);

        $result = userprofile_cohorts_get_cohorts($user, [$cohort->id]);
        $this->assertEquals(count($result), 1);

    }

}