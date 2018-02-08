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
 * This file contains the class for restore of this gradebookservices plugin
 *
 * @package    ltiservice_gradebookservices
 * @copyright  2017 Cengage Learning http://www.cengage.com
 * @author     Dirk Singels, Diego del Blanco, Claude Vervoort
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/mod/lti/locallib.php');
/**
 * Restore subplugin class.
 *
 * Provides the necessary information
 * needed to restore the lineitems related with the lti activity (coupled),
 * and all the uncoupled ones from the course.
 *
 * @package    ltiservice_gradebookservices
 * @copyright  2017 Cengage Learning http://www.cengage.com
 * @author     Dirk Singels, Diego del Blanco, Claude Vervoort
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_ltiservice_gradebookservices_subplugin extends restore_subplugin {

    protected function define_lti_subplugin_structure() {

        $userinfo = $this->get_setting_value('users');

        $paths = array();
        $elename = $this->get_namefor('coupledgradeitemlti2');
        $elepath = $this->get_pathfor('/thisactivitylineitems/thisactivitylineitemslti2/coupled_grade_item_lti2');
        $paths[] = new restore_path_element($elename, $elepath);
        $elename = $this->get_namefor('coupledgradeitemltiad');
        $elepath = $this->get_pathfor('/thisactivitylineitems/thisactivitylineitemsltiad/coupled_grade_item_ltiad');
        $paths[] = new restore_path_element($elename, $elepath);
        $elename = $this->get_namefor('uncoupledgradeitemlti2');
        $elepath = $this->get_pathfor('/nonactivitylineitems/nonactivitylineitemslti2/uncoupled_grade_item_lti2');
        $paths[] = new restore_path_element($elename, $elepath);
        $elename = $this->get_namefor('uncoupledgradeitemltiad');
        $elepath = $this->get_pathfor('/nonactivitylineitems/nonactivitylineitemsltiad/uncoupled_grade_item_ltiad');
        $paths[] = new restore_path_element($elename, $elepath);
        if ($userinfo) {
            $elename = $this->get_namefor('gradegradelti2');
            $elepath = $this->get_pathfor('/nonactivitylineitems/nonactivitylineitemslti2/'.
                    'uncoupled_grade_item_lti2/grade_grades_lti2/grade_grade_lti2');
            $paths[] = new restore_path_element($elename, $elepath);
            $elename = $this->get_namefor('gradegradeltiad');
            $elepath = $this->get_pathfor('/nonactivitylineitems/nonactivitylineitemsltiad/'.
                    'uncoupled_grade_item_ltiad/grade_grades_ltiad/grade_grade_ltiad');
            $paths[] = new restore_path_element($elename, $elepath);
        }
        return $paths;
    }

    /**
     * Processes one coupled lineitem element
     * @param mixed $data
     * @return void
     */
    public function process_ltiservice_gradebookservices_coupledgradeitemlti2($data) {
        global $DB;
        $data = (object)$data;
        // The coupled lineitems are restored as any other grade item
        // so we will only create the entry in the ltiservice_gradebookservices table.
        // We will try to find a valid toolproxy in the system.
        // If it has been found before... we use it.
        /* cache parent property to account for missing PHPDoc type specification */
        /** @var backup_activity_task $activitytask */
        $activitytask = $this->task;
        $courseid = $activitytask->get_courseid();
        $ltitoolproxy = $this->get_mappingid('ltitoolproxy', $data->toolproxyid);
        if ($ltitoolproxy && $ltitoolproxy != 0) {
            $newtoolproxyid = $ltitoolproxy;
        } else { // If not, then we will call our own function to find it.
            $newtoolproxyid = $this->find_proxy_id($data);
        }
        try {
            $newgbsid = $DB->insert_record('ltiservice_gradebookservices', (object) array(
                    'gradeitemid' => 0,
                    'courseid' => $courseid,
                    'toolproxyid' => $newtoolproxyid,
                    'ltilinkid' => $this->get_new_parentid('lti'),
                    'typeid' => null,
                    'baseurl' => $data->baseurl,
                    'tag' => $data->tag
            ));
            $this->set_mapping('gbsgradeitemoldid', $newgbsid, $data->id);
            $oldgradeitemid = $this->get_mappingid('gbsgradeitemoldid', $newgbsid, 0);
        } catch (\Exception $e) {
            debugging('Error restoring the lti gradebookservicescreating: ' . $e->getTraceAsString());
        }
    }

    /**
     * Processes one coupled lineitem element
     * @param mixed $data
     * @return void
     */
    public function process_ltiservice_gradebookservices_coupledgradeitemltiad($data) {
        global $DB;
        $data = (object)$data;
        // The coupled lineitems are restored as any other grade item
        // so we will only create the entry in the ltiservice_gradebookservices table.
        // We will try to find a valid type in the system.
        // If it has been done before we don't need to check it.
        /* cache parent property to account for missing PHPDoc type specification */
        /** @var backup_activity_task $activitytask */
        $activitytask = $this->task;
        $courseid = $activitytask->get_courseid();
        if ($ltitypeid = $this->get_mappingid('ltitype', $data->typeid)) {
            $newtypeid = $ltitypeid;
        } else { // If not, then we will call our own function to find it.
            $newtypeid = $this->find_typeid($data, $courseid);
        }
        try {
            $newgbsid = $DB->insert_record('ltiservice_gradebookservices', (object) array(
                    'gradeitemid' => 0,
                    'courseid' => $courseid,
                    'toolproxyid' => null,
                    'ltilinkid' => $this->get_new_parentid('lti'),
                    'typeid' => $newtypeid,
                    'baseurl' => $data->baseurl,
                    'tag' => $data->tag
            ));
            $this->set_mapping('gbsgradeitemoldid', $newgbsid, $data->id);
            $oldgradeitemid = $this->get_mappingid('gbsgradeitemoldid', $newgbsid, 0);
        } catch (\Exception $e) {
            debugging('Error restoring the lti gradebookservicescreating: ' . $e->getTraceAsString());
        }
    }

    /**
     * Processes one uncoupled lineitem element
     * @param mixed $data
     * @return void
     */
    public function process_ltiservice_gradebookservices_uncoupledgradeitemlti2($data) {
        global $DB;
        $data = (object)$data;
        // We will try to find a valid toolproxy in the system.It should have be done before
        // so we just need to find it in the mapping.
        $ltitoolproxy = $this->get_mappingid('ltitoolproxy', $data->toolproxyid);
        if ($ltitoolproxy && $ltitoolproxy != 0) {
            $newtoolproxyid = $ltitoolproxy;
        } else {
            $newtoolproxyid = $this->find_proxy_id($data);
        }
        /* cache parent property to account for missing PHPDoc type specification */
        /** @var backup_activity_task $activitytask */
        $activitytask = $this->task;
        $courseid = $activitytask->get_courseid();
        try {
            // We will check if the record has been restored by a previous activity
            // and if not, we will restore it creating the right grade item and the
            // right entry in the ltiservice_gradebookservices table.
            if ($this->get_mappingid('uncoupled_grade_item_lti2', $data->id, 0) == 0) {
                // Restore the lineitem.
                $oldid = $data->id;
                $params = array();
                $params['itemname'] = $data->itemname;
                $params['gradetype'] = GRADE_TYPE_VALUE;
                $params['grademax']  = $data->grademax;
                $params['grademin']  = $data->grademin;
                $item = (object)new \grade_item(array('id' => 0, 'courseid' => $courseid));
                \grade_item::set_properties($item, $params);
                $item->itemtype = 'mod';
                $item->itemmodule = 'lti';
                $item->itemnumber = get_next_itemnumber();
                $item->idnumber = $data->idnumber;
                $id = $item->insert('mod/ltiservice_gradebookservices');
                $DB->insert_record('ltiservice_gradebookservices', (object) array(
                        'gradeitemid' => $id,
                        'courseid' => $courseid,
                        'toolproxyid' => $newtoolproxyid,
                        'ltilinkid' => null,
                        'typeid' => null,
                        'baseurl' => $data->baseurl,
                        'tag' => $data->tag
                ));
                $this->set_mapping('uncoupled_grade_item_lti2', $oldid, $id);
            }
        } catch (\Exception $e) {
            debugging('Error restoring the lti gradebookservicescreating: ' . $e->getTraceAsString());
        }
    }

    /**
     * Processes one uncoupled lineitem element
     * @param mixed $data
     * @return void
     */
    public function process_ltiservice_gradebookservices_uncoupledgradeitemltiad($data) {
        global $DB;
        $data = (object)$data;
        // We will try to find a valid type in the system. It should have be done before
        // so we just need to find it in the mapping.
        /* cache parent property to account for missing PHPDoc type specification */
        /** @var backup_activity_task $activitytask */
        $activitytask = $this->task;
        $courseid = $activitytask->get_courseid();
        if ($ltitypeid = $this->get_mappingid('ltitype', $data->typeid)) {
            $newtypeid = $ltitypeid;
        } else {
            $courseid = $activitytask->get_courseid();
            $newtypeid = $this->find_typeid($data, $courseid);
        }
        try {
            // We will check if the record has been restored by a previous activity
            // and if not, we will restore it creating the right grade item and the
            // right entry in the ltiservice_gradebookservices table.
            if ($this->get_mappingid('uncoupled_grade_item_ltiad', $data->id, 0) == 0) {
                // Restore the lineitem.
                $oldid = $data->id;
                $params = array();
                $params['itemname'] = $data->itemname;
                $params['gradetype'] = GRADE_TYPE_VALUE;
                $params['grademax']  = $data->grademax;
                $params['grademin']  = $data->grademin;
                $item = (object)new \grade_item(array('id' => 0, 'courseid' => $courseid));
                \grade_item::set_properties($item, $params);
                $item->itemtype = 'mod';
                $item->itemmodule = 'lti';
                $item->itemnumber = get_next_itemnumber();
                $item->idnumber = $data->idnumber;
                $id = $item->insert('mod/ltiservice_gradebookservices');
                $DB->insert_record('ltiservice_gradebookservices', (object) array(
                        'gradeitemid' => $id,
                        'courseid' => $courseid,
                        'toolproxyid' => null,
                        'ltilinkid' => null,
                        'typeid' => $newtypeid,
                        'baseurl' => $data->baseurl,
                        'tag' => $data->tag,
                ));
                $this->set_mapping('uncoupled_grade_item_ltiad', $oldid, $id);
            }
        } catch (\Exception $e) {
            debugging('Error restoring the lti gradebookservicescreating: ' . $e->getTraceAsString());
        }
    }

    /**
     * Processes the grades from the uncoupled lineitem element
     * @param mixed $data
     * @return void
     */
    public function process_ltiservice_gradebookservices_gradegradelti2($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $olduserid = $data->userid;

        $data->itemid = $this->get_mappingid('uncoupled_grade_item_lti2', $data->itemid, null);
        $data->userid = $this->get_mappingid('user', $data->userid, null);

        if (!empty($data->userid)) {
            $data->usermodified = $this->get_mappingid('user', $data->usermodified, null);
            $data->locktime     = $this->apply_date_offset($data->locktime);

            $gradeexists = $DB->record_exists('grade_grades', array('userid' => $data->userid, 'itemid' => $data->itemid));
            if ($gradeexists) {
                $message = "User id '{$data->userid}' already has a grade entry for grade item id '{$data->itemid}'";
                $this->log($message, backup::LOG_DEBUG);
            } else {
                $newitemid = $DB->insert_record('grade_grades', $data);
                $this->set_mapping('grade_grades', $oldid, $newitemid);
            }
        } else {
            $message = "Mapped user id not found for user id '{$olduserid}', grade item id '{$data->itemid}'";
            $this->log($message, backup::LOG_DEBUG);
        }
    }

    /**
     * Processes the grades from the uncoupled lineitem element
     * @param mixed $data
     * @return void
     */
    public function process_ltiservice_gradebookservices_gradegradeltiad($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $olduserid = $data->userid;

        $data->itemid = $this->get_mappingid('uncoupled_grade_item_ltiad', $data->itemid, null);
        $data->userid = $this->get_mappingid('user', $data->userid, null);

        if (!empty($data->userid)) {
            $data->usermodified = $this->get_mappingid('user', $data->usermodified, null);
            $data->locktime     = $this->apply_date_offset($data->locktime);

            $gradeexists = $DB->record_exists('grade_grades', array('userid' => $data->userid, 'itemid' => $data->itemid));
            if ($gradeexists) {
                $message = "User id '{$data->userid}' already has a grade entry for grade item id '{$data->itemid}'";
                $this->log($message, backup::LOG_DEBUG);
            } else {
                $newitemid = $DB->insert_record('grade_grades', $data);
                $this->set_mapping('grade_grades', $oldid, $newitemid);
            }
        } else {
            $message = "Mapped user id not found for user id '{$olduserid}', grade item id '{$data->itemid}'";
            $this->log($message, backup::LOG_DEBUG);
        }
    }

    /**
     * If the toolproxy is not in the mapping (or it is 0)
     * we try to find the toolproxyid.
     * If none is found, then we set it to 0.
     *
     * @param mixed $data
     * @return integer $newtoolproxyid
     */
    private function find_proxy_id($data) {
        global $DB;
        $newtoolproxyid = 0;
        $oldtoolproxyguid = $data->guid;
        $oldtoolproxyvendor = $data->vendorcode;

        $dbtoolproxyjsonparams = array('guid' => $oldtoolproxyguid, 'vendorcode' => $oldtoolproxyvendor);
        $dbtoolproxy = $DB->get_field('lti_tool_proxies', 'id', $dbtoolproxyjsonparams, IGNORE_MISSING);
        if ($dbtoolproxy) {
            $newtoolproxyid = $dbtoolproxy;
        }
        return $newtoolproxyid;
    }

    /**
     * If the typeid is not in the mapping or it is 0, (it should be most of the times)
     * we will try to find the better typeid that matches with the lineitem.
     * If none is found, then we set it to 0.
     *
     * @param mixed $data
     * @param integer @courseid
     * @return integer $newtypeid
     */
    private function find_typeid($data, $courseid) {
        global $DB;
        $newtypeid = 0;
        $oldtypeid = $data->typeid;

        // 1. Find a type with the same id in the same course.
        $dbtypeidparameter = array('id' => $oldtypeid, 'course' => $courseid, 'baseurl' => $data->baseurl);
        $dbtype = $DB->get_field_select('lti_types', 'id', "id=:id
                AND course=:course AND ".$DB->sql_compare_text('baseurl')."=:baseurl",
                $dbtypeidparameter);
        if ($dbtype) {
            $newtypeid = $dbtype;
        } else {
            // 2. Find a site type for all the courses (course == 1), but with the same id.
            $dbtypeidparameter = array('id' => $oldtypeid, 'baseurl' => $data->baseurl);
            $dbtype = $DB->get_field_select('lti_types', 'id', "id=:id
                    AND course=1 AND ".$DB->sql_compare_text('baseurl')."=:baseurl",
                    $dbtypeidparameter);
            if ($dbtype) {
                $newtypeid = $dbtype;
            } else {
                // 3. Find a type with the same baseurl in the actual site.
                $dbtypeidparameter = array('course' => $courseid, 'baseurl' => $data->baseurl);
                $dbtype = $DB->get_field_select('lti_types', 'id', "course=:course
                        AND ".$DB->sql_compare_text('baseurl')."=:baseurl",
                        $dbtypeidparameter);
                if ($dbtype) {
                    $newtypeid = $dbtype;
                } else {
                    // 4. Find a site type for all the courses (course == 1) with the same baseurl.
                    $dbtypeidparameter = array('course' => 1, 'baseurl' => $data->baseurl);
                    $dbtype = $DB->get_field_select('lti_types', 'id', "course=1
                            AND ".$DB->sql_compare_text('baseurl')."=:baseurl",
                            $dbtypeidparameter);
                    if ($dbtype) {
                        $newtypeid = $dbtype;
                    }
                }
            }
        }
        return $newtypeid;
    }

    /**
     * We call the after_restore_lti to update the grade_items id's
     * that we didn't know in the moment of creating the gradebookservices rows.
     */
    protected function after_restore_lti() {
        global $DB;

        $newactivity = $this->task->get_activityid();
        $gbstoupdate = $DB->get_records('ltiservice_gradebookservices', array('ltilinkid' => $newactivity));
        foreach ($gbstoupdate as $gbs) {
            if ($gbs->gradeitemid == 0) {
                $oldgradeitemid = $this->get_mappingid('gbsgradeitemoldid', $gbs->id, 0);
                $newgradeitemid = $this->get_mappingid('grade_item', $oldgradeitemid, 0);
                if ($newgradeitemid > 0) {
                    $gbs->gradeitemid = $newgradeitemid;
                    $DB->update_record('ltiservice_gradebookservices', $gbs);
                }
            }
        }
    }

}
