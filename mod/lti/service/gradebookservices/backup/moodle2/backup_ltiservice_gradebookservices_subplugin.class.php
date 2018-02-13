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
 * @author     Dirk Singels, Diego del Blanco
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/mod/lti/locallib.php');

/**
 * Provides the information to backup gradebookservices lineitems
 *
 * @package    ltiservice_gradebookservices
 * @copyright  2017 Cengage Learning http://www.cengage.com
 * @author     Dirk Singels, Diego del Blanco, Claude Vervoort
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_ltiservice_gradebookservices_subplugin extends backup_subplugin {

    /** TypeId contained in DB but is invalid */
    const NONVALIDTYPEID = 0;
    /**
     * Returns the subplugin information to attach to submission element
     * @return backup_subplugin_element
     */
    protected function define_lti_subplugin_structure() {
        global $DB;

        $userinfo = $this->get_setting_value('users');
        // Create XML elements.
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        // The lineitem(s) related with this element.
        $thisactivitylineitems = new backup_nested_element('thisactivitylineitems');
        $thisactivitylineitemslti2 = new backup_nested_element('thisactivitylineitemslti2');
        $thisactivitylineitemlti2 = self::get_lti2_elements('coupled_grade_item_lti2');

        $thisactivitylineitemsltiad = new backup_nested_element('thisactivitylineitemsltiad');
        $thisactivitylineitemltiad = self::get_ltiadvangage_elements('coupled_grade_item_ltiad');

        // The lineitem(s) not related with any activity.
        // TODO: This will need to change if this module becomes part of the moodle core.
        $nonactivitylineitems = new backup_nested_element('nonactivitylineitems');
        $nonactivitylineitemslti2 = new backup_nested_element('nonactivitylineitemslti2');
        $nonactivitylineitemlti2 = self::get_lti2_elements('uncoupled_grade_item_lti2');

        $nonactivitylineitemsltiad = new backup_nested_element('nonactivitylineitemsltiad');
        $nonactivitylineitemltiad = self::get_ltiadvangage_elements('uncoupled_grade_item_ltiad');

        // Grades.
        $gradegradeslti2 = new backup_nested_element('grade_grades_lti2');
        $gradegradelti2 = self::get_lti2_grade_elements('grade_grade_lti2');

        $gradegradesltiad = new backup_nested_element('grade_grades_ltiad');
        $gradegradeltiad = self::get_ltiavantage_grade_elements('grade_grade_ltiad');

        // Build the tree.
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($thisactivitylineitems);
        $thisactivitylineitems->add_child($thisactivitylineitemslti2);
        $thisactivitylineitemslti2->add_child($thisactivitylineitemlti2);
        $thisactivitylineitems->add_child($thisactivitylineitemsltiad);
        $thisactivitylineitemsltiad->add_child($thisactivitylineitemltiad);

        $subpluginwrapper->add_child($nonactivitylineitems);
        $nonactivitylineitems->add_child($nonactivitylineitemslti2);
        $nonactivitylineitemslti2->add_child($nonactivitylineitemlti2);
        $nonactivitylineitemlti2->add_child($gradegradeslti2);
        $gradegradeslti2->add_child($gradegradelti2);
        $nonactivitylineitems->add_child($nonactivitylineitemsltiad);
        $nonactivitylineitemsltiad->add_child($nonactivitylineitemltiad);
        $nonactivitylineitemltiad->add_child($gradegradesltiad);
        $gradegradesltiad->add_child($gradegradeltiad);

        // Define sources.
        $thisactivitylineitemslti2sql = "SELECT g.*,l.toolproxyid,l.baseurl,l.tag,t.vendorcode,t.guid
                                           FROM {grade_items} g
                                     INNER JOIN {ltiservice_gradebookservices} l ON (g.id = l.gradeitemid
                                                                                    AND g.courseid = l.courseid)
                                     INNER JOIN {lti_tool_proxies} t ON (t.id = l.toolproxyid)
                                          WHERE g.courseid = ?
                                                AND g.itemtype='mod'
                                                AND g.itemmodule = 'lti'
                                                AND g.iteminstance = ?
                                                AND l.typeid is null";
        $thisactivitylineitemsltiadsql = "SELECT g.*,l.typeid,l.baseurl,l.tag
                                            FROM {grade_items} g
                                      INNER JOIN {ltiservice_gradebookservices} l ON (g.id = l.gradeitemid
                                                                                     AND g.courseid = l.courseid)
                                      INNER JOIN {lti_types} t ON (t.id = l.typeid)
                                           WHERE g.courseid = ?
                                             AND g.itemtype='mod'
                                             AND g.itemmodule = 'lti'
                                             AND g.iteminstance = ?
                                             AND l.toolproxyid is null";

        // If and activity is assigned to a type that doesn't exists we don't want to backup any related lineitems.``
        // Default to invalid condition.
        $typeid = 0;
        $toolproxyid = '0';
        $baseurl = 'NOVALIDTYPE';

        /* cache parent property to account for missing PHPDoc type specification */
        /** @var backup_activity_task $activitytask */
        $activitytask = $this->task;
        $activityid = $activitytask->get_activityid();
        $activitycourseid = $activitytask->get_courseid();
        $lti = $DB->get_record('lti', ['id' => $activityid], 'typeid, toolurl, securetoolurl');
        $ltitype = $DB->get_record('lti_types', ['id' => $lti->typeid], 'toolproxyid, baseurl');

        if ($ltitype) {
            $typeid = $lti->typeid;
            $toolproxyid = $ltitype->toolproxyid;
            $baseurl = $ltitype->baseurl;
        } else if ($lti->typeid == self::NONVALIDTYPEID) { // This activity comes from an old backup.
            // 1. Let's check if the activity is coupled. If so, find the values in the GBS element.
            $gbsrecord = $DB->get_record('ltiservice_gradebookservices',
                    ['ltilinkid' => $activityid], 'typeid,toolproxyid,baseurl');
            if ($gbsrecord) {
                $typeid = $gbsrecord->typeid;
                $toolproxyid = $gbsrecord->toolproxyid;
                $baseurl = $gbsrecord->baseurl;
            } else { // 2. If it is uncoupled... we will need to guess the right activity typeid
                // Guess the typeid for the activity.
                $tool = lti_get_tool_by_url_match($lti->toolurl, $activitycourseid);
                if (!$tool) {
                    $tool = lti_get_tool_by_url_match($lti->securetoolurl, $activitycourseid);
                }
                $alttypeid = $tool->id;
                // If we have a valid typeid then get types again.
                if ($alttypeid != self::NONVALIDTYPEID) {
                    $ltitype = $DB->get_record('lti_types', ['id' => $alttypeid], 'toolproxyid, baseurl');
                    $toolproxyid = $ltitype->toolproxyid;
                    $baseurl = $ltitype->baseurl;
                }
            }
        }
        $nonactivitylineitemslti2sql = "SELECT g.*,l.toolproxyid,l.baseurl,l.tag,t.vendorcode,t.guid
                                          FROM {grade_items} g
                                    INNER JOIN {ltiservice_gradebookservices} l ON (g.id = l.gradeitemid
                                                                                   AND g.courseid = l.courseid)
                                    INNER JOIN {lti_tool_proxies} t ON (t.id = l.toolproxyid)
                                         WHERE g.courseid = ?
                                               AND g.itemtype='mod'
                                               AND g.itemmodule = 'lti'
                                               AND g.iteminstance is null
                                               AND l.typeid is null
                                               AND l.toolproxyid = ?";
        $nonactivitylineitemsltiadsql = "SELECT g.*,l.typeid,l.baseurl,l.tag
                                           FROM {grade_items} g
                                     INNER JOIN {ltiservice_gradebookservices} l ON (g.id = l.gradeitemid
                                                                                    AND g.courseid = l.courseid)
                                     INNER JOIN {lti_types} t ON (t.id = l.typeid)
                                          WHERE g.courseid = ?
                                                AND g.itemtype='mod'
                                                AND g.itemmodule = 'lti'
                                                AND g.iteminstance is null
                                                AND l.typeid = ?
                                                AND l.baseurl = ?
                                                AND l.toolproxyid is null";

        $thisactivitylineitemsparams = ['courseid' => backup::VAR_COURSEID, 'iteminstance' => backup::VAR_ACTIVITYID];
        $thisactivitylineitemlti2->set_source_sql($thisactivitylineitemslti2sql, $thisactivitylineitemsparams);
        $thisactivitylineitemltiad->set_source_sql($thisactivitylineitemsltiadsql, $thisactivitylineitemsparams);
        $nonactivitylineitemslti2params = [backup::VAR_COURSEID, backup_helper::is_sqlparam($toolproxyid)];
        $nonactivitylineitemsltiadparams = [backup::VAR_COURSEID,
                backup_helper::is_sqlparam($typeid), backup_helper::is_sqlparam($baseurl)];
        $nonactivitylineitemlti2->set_source_sql($nonactivitylineitemslti2sql, $nonactivitylineitemslti2params);
        $nonactivitylineitemltiad->set_source_sql($nonactivitylineitemsltiadsql, $nonactivitylineitemsltiadparams);

        if ($userinfo) {
            $gradegradelti2->set_source_table('grade_grades', ['itemid' => backup::VAR_PARENTID]);
            $gradegradeltiad->set_source_table('grade_grades', ['itemid' => backup::VAR_PARENTID]);
        }

        return $subplugin;
    }

    /**
     * Merges and returns a list of common and LTI product specific element names.
     *
     * @param array $typeelements LTI product specific element names, LTI2 or LTI Advantage
     * @return array
     */
    private function get_common_elements($typeelements) {
        return array_merge(['categoryid', 'itemname', 'itemtype', 'itemmodule', 'iteminstance', 'itemnumber', 'iteminfo',
            'idnumber', 'calculation', 'gradetype', 'grademax', 'grademin', 'scaleid', 'outcomeid', 'gradepass', 'multfactor',
            'plusfactor', 'aggregationcoef', 'aggregationcoef2', 'weightoverride', 'sortorder', 'display', 'decimals',
            'hidden', 'locked', 'locktime', 'needsupdate', 'timecreated', 'timemodified', 'baseurl', 'tag'], $typeelements);
    }

    /**
     * Returns backup element containing LTI2 specific elements.
     *
     * @param $elementname
     * @return backup_nested_element
     */
    private function get_lti2_elements($elementname) {
        return new backup_nested_element($elementname, ['id'], self::get_common_elements(['toolproxyid', 'vendorcode', 'guid']));
    }

    /**
     * Returns backup element containing LTI Advantage specific elements.
     *
     * @param $elementname
     * @return backup_nested_element
     */
    private function get_ltiadvangage_elements($elementname) {
        return new backup_nested_element($elementname, ['id'], self::get_common_elements(['typeid']));
    }

    /**
     * Returns backup grade elements.
     *
     * @param string $elementname
     * @return backup_nested_element
     */
    private function get_grade_elements($elementname) {
        return new backup_nested_element($elementname, ['id'], [
            'itemid', 'userid', 'rawgrade', 'rawgrademax', 'rawgrademin', 'rawscaleid', 'usermodified', 'finalgrade', 'hidden',
            'locked', 'locktime', 'exported', 'overridden', 'excluded', 'feedback', 'feedbackformat', 'information',
            'informationformat', 'timecreated', 'timemodified', 'aggregationstatus', 'aggregationweight']);
    }

    /**
     * Returns backup grade element containing LTI2 specific elements.
     *
     * @param string $elementname
     * @return backup_nested_element
     */
    private function get_lti2_grade_elements($elementname) {
        return self::get_grade_elements($elementname);
    }

    /**
     * Returns backup grade element containing LTI Advantage specific elements.
     *
     * @param string $elementname
     * @return backup_nested_element
     */
    private function get_ltiavantage_grade_elements($elementname) {
        return self::get_grade_elements($elementname);
    }
}
