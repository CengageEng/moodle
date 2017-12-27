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
 * This file contains a class definition for the Memberships service
 *
 * @package    ltiservice_memberships
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace ltiservice_memberships\local\service;

defined('MOODLE_INTERNAL') || die();

/**
 * A service implementing Memberships.
 *
 * @package    ltiservice_memberships
 * @since      Moodle 3.0
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class memberships extends \mod_lti\local\ltiservice\service_base {

    /** Default prefix for context-level roles */
    const CONTEXT_ROLE_PREFIX = 'http://purl.imsglobal.org/vocab/lis/v2/membership#';
    /** Context-level role for Instructor */
    const CONTEXT_ROLE_INSTRUCTOR = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor';
    /** Context-level role for Learner */
    const CONTEXT_ROLE_LEARNER = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner';
    /** Capability used to identify Instructors */
    const INSTRUCTOR_CAPABILITY = 'moodle/course:manageactivities';

    /**
     * Class constructor.
     */
    public function __construct() {

        parent::__construct();
        $this->id = 'memberships';
        $this->name = get_string('servicename', 'ltiservice_memberships');

    }

    /**
     * Get the resources for this service.
     *
     * @return array
     */
    public function get_resources() {

        if (empty($this->resources)) {
            $this->resources = array();
            $this->resources[] = new \ltiservice_memberships\local\resource\contextmemberships($this);
            $this->resources[] = new \ltiservice_memberships\local\resource\linkmemberships($this);
        }

        return $this->resources;

    }

    /**
     * Get the JSON for members.
     *
     * @param \mod_lti\local\ltiservice\resource_base $resource       Resource handling the request
     * @param \context_course   $context    Course context
     * @param string            $id         Course ID
     * @param object            $tool       Tool instance object
     * @param string            $role       User role requested (empty if none)
     * @param int               $limitfrom  Position of first record to be returned
     * @param int               $limitnum   Maximum number of records to be returned
     * @param object            $lti        LTI instance record
     * @param info_module       $info       Conditional availability information for LTI instance (null if context-level request)
     *
     * @return array
     */
    public static function get_users_json($resource, $context, $id, $tool, $role, $limitfrom, $limitnum, $lti, $info) {

        $withcapability = '';
        $exclude = array();
        if (!empty($role)) {
            if ((strpos($role, 'http://') !== 0) && (strpos($role, 'https://') !== 0)) {
                $role = self::CONTEXT_ROLE_PREFIX . $role;
            }
            if ($role === self::CONTEXT_ROLE_INSTRUCTOR) {
                $withcapability = self::INSTRUCTOR_CAPABILITY;
            } else if ($role === self::CONTEXT_ROLE_LEARNER) {
                $exclude = array_keys(get_enrolled_users($context, self::INSTRUCTOR_CAPABILITY, 0, 'u.id',
                                                         null, null, null, true));
            }
        }
        $users = get_enrolled_users($context, $withcapability, 0, 'u.*', null, $limitfrom, $limitnum, true);
        if (count($users) < $limitnum) {
            $limitfrom = 0;
            $limitnum = 0;
        }
        $json = self::users_to_json($resource, $users, $id, $tool, $exclude, $limitfrom, $limitnum, $lti, $info);

        return $json;

    }

    /**
     * Get the JSON representation of the users.
     *
     * Note that when a limit is set and the exclude array is not empty, then the number of memberships
     * returned may be less than the limit.
     *
     * @param \mod_lti\local\ltiservice\resource_base $resource       Resource handling the request
     * @param array  $users               Array of user records
     * @param string $id                  Course ID
     * @param object $tool                Tool instance object
     * @param array  $exclude             Array of user records to be excluded from the response
     * @param int    $limitfrom           Position of first record to be returned
     * @param int    $limitnum            Maximum number of records to be returned
     * @param object $lti                 LTI instance record
     * @param \core_availability\info_module  $info     Conditional availability information for LTI instance
     *
     * @return string
     */
    private static function users_to_json($resource, $users, $id, $tool, $exclude, $limitfrom, $limitnum,
                                         $lti, $info) {

        $nextpage = 'null';
        if ($limitnum > 0) {
            $limitfrom += $limitnum;
            $nextpage = "\"{$resource->get_endpoint()}?limit={$limitnum}&amp;from={$limitfrom}\"";
        }
        $json = <<< EOD
{
  "@context" : "http://purl.imsglobal.org/ctx/lis/v2/MembershipContainer",
  "@type" : "Page",
  "@id" : "{$resource->get_endpoint()}",
  "nextPage" : {$nextpage},
  "pageOf" : {
    "@type" : "LISMembershipContainer",
    "membershipSubject" : {
      "@type" : "Context",
      "contextId" : "{$id}",
      "membership" : [

EOD;
        $enabledcapabilities = lti_get_enabled_capabilities($tool);
        $sep = '        ';
        foreach ($users as $user) {
            $include = !in_array($user->id, $exclude);
            if ($include && !empty($info)) {
                $include = $info->is_user_visible($info->get_course_module(), $user->id);
            }
            if ($include) {
                $member = new \stdClass();
                $toolconfig = lti_get_type_type_config($tool->id);
                if (in_array('User.id', $enabledcapabilities)
                        || (isset($toolconfig->ltiservice_membershipcapabilityuserid)
                                && $toolconfig->ltiservice_membershipcapabilityuserid == 1)) {
                    $member->userId = $user->id;
                }
                if (in_array('Person.sourcedId', $enabledcapabilities)
                        || (isset($toolconfig->ltiservice_membershipcapabilitypersonsourcedid)
                                && $toolconfig->ltiservice_membershipcapabilitypersonsourcedid == 1)) {
                    $member->sourcedId = format_string($user->idnumber);
                }
                if (in_array('Person.name.full', $enabledcapabilities)
                        || (isset($toolconfig->ltiservice_membershipcapabilitypersonnamefull)
                                && $toolconfig->ltiservice_membershipcapabilitypersonnamefull == 1)) {
                    $member->name = format_string("{$user->firstname} {$user->lastname}");
                }
                if (in_array('Person.name.given', $enabledcapabilities)
                        || (isset($toolconfig->ltiservice_membershipcapabilitypersonnamegiven)
                                && $toolconfig->ltiservice_membershipcapabilitypersonnamegiven == 1)) {
                    $member->givenName = format_string($user->firstname);
                }
                if (in_array('Person.name.family', $enabledcapabilities)
                        || (isset($toolconfig->ltiservice_membershipcapabilitypersonnamefamily)
                                && $toolconfig->ltiservice_membershipcapabilitypersonnamefamily == 1)) {
                    $member->familyName = format_string($user->lastname);
                }
                if (in_array('Person.email.primary', $enabledcapabilities)
                        || (isset($toolconfig->ltiservice_membershipcapabilitypersonemailprimary)
                                && $toolconfig->ltiservice_membershipcapabilitypersonemailprimary == 1)) {
                    $member->email = format_string($user->email);
                }
                if ((in_array('Result.sourcedId', $enabledcapabilities)
                        || (isset($toolconfig->ltiservice_membershipcapabilityresultsourcedid)
                                && $toolconfig->ltiservice_membershipcapabilityresultsourcedid == 1))
                        && !empty($lti) && !empty($lti->servicesalt)) {
                    $member->resultSourcedId = json_encode(lti_build_sourcedid($lti->id, $user->id, $lti->servicesalt,
                                                           $lti->typeid));
                }
                $roles = explode(',', lti_get_ims_role($user->id, null, $id, true));

                $membership = new \stdClass();
                $membership->status = 'Active';
                $membership->member = $member;
                $membership->role = $roles;

                $json .= $sep . json_encode($membership);
                $sep = ",\n        ";
            }

        }

        $json .= <<< EOD

      ]
    }
  }
}
EOD;

        return $json;

    }


    /**
     * Return an array of options to add to the add/edit external tool.
     * The array will have elements with this attributes:
     *
     * - type ( only 'select', 'text', 'checkbox' are
     * allowed by the moment) view lib/pear/HTML/QuickForm for all types.
     * - array of type specific parameters:
     *  - if select it needs:
     *      - name.
     *      - label.
     *      - array of options.
     *  - if text it needs:
     *      - name.
     *      - label.
     *      - parameters (example: array('size' => '64')).
     *  - if checkbox it needs:
     *      - name.
     *      - main label (left side of the form).
     *      - after checkbox lable.
     * - setType value or null, ('int', 'text'...) If null, no default value.
     * - setDefault or null ('2', ...) If null, no default value.
     * - HelpButton $identifier usually the same than the name and it will be
     *  in the texts file with _help at the end, If null, no help button.
     * - HelpButton $component component to find the languages files. If null, no help button.
     *
     * @return an array of options to add to the add/edit external tool or null if no options to add.
     *
     */

    public function get_configuration_options() {

        $configurationoptions = array();

        $optionsmem = array();
        $optionsmem[0] = get_string('notallow', 'ltiservice_memberships');
        $optionsmem[1] = get_string('allow', 'ltiservice_memberships');

        $optionssimple = array();
        $optionssimple[0] = get_string('notallowsimple', 'ltiservice_memberships');
        $optionssimple[1] = get_string('allowsimple', 'ltiservice_memberships');

        $membership = array();
        $membership[0] = 'select';
        $parametersmem = array();
        $parametersmem[0] = 'ltiservice_memberships';
        $parametersmem[1] = get_string('membership_management', 'ltiservice_memberships') . ':';
        $parametersmem[2] = $optionsmem;
        $membership[1] = $parametersmem;
        $membership[2] = 'int';
        $membership[3] = '0';
        $membership[4] = 'membership_management';
        $membership[5] = 'ltiservice_memberships';

        $capabilityuserid = array();
        $capabilityuserid[0] = 'select';
        $parametersuserid = array();
        $parametersuserid[0] = 'ltiservice_membershipcapabilityuserid';
        $parametersuserid[1] = '&nbsp;&nbsp;&nbsp;&nbsp;<i>' .
        get_string('membership_capability_user_id', 'ltiservice_memberships') . '</i>';
        $parametersuserid[2] = $optionssimple;
        $capabilityuserid[1] = $parametersuserid;
        $capabilityuserid[2] = 'int';
        $capabilityuserid[3] = '1';
        $capabilityuserid[4] = 'membership_capability_user_id';
        $capabilityuserid[5] = 'ltiservice_memberships';

        $capabilitypersonsourcedid = array();
        $capabilitypersonsourcedid[0] = 'select';
        $parameterspersonsourcedid = array();
        $parameterspersonsourcedid[0] = 'ltiservice_membershipcapabilitypersonsourcedid';
        $parameterspersonsourcedid[1] = '&nbsp;&nbsp;&nbsp;&nbsp;<i>' .
        get_string('membership_capability_person_sourced_id', 'ltiservice_memberships') . '</i>';
        $parameterspersonsourcedid[2] = $optionssimple;
        $capabilitypersonsourcedid[1] = $parameterspersonsourcedid;
        $capabilitypersonsourcedid[2] = 'int';
        $capabilitypersonsourcedid[3] = '0';
        $capabilitypersonsourcedid[4] = 'membership_capability_person_sourced_id';
        $capabilitypersonsourcedid[5] = 'ltiservice_memberships';

        $capabilitypersonnamefull = array();
        $capabilitypersonnamefull[0] = 'select';
        $parameterspersonnamefull = array();
        $parameterspersonnamefull[0] = 'ltiservice_membershipcapabilitypersonnamefull';
        $parameterspersonnamefull[1] = '&nbsp;&nbsp;&nbsp;&nbsp;<i>' .
        get_string('membership_capability_person_name_full', 'ltiservice_memberships') . '</i>';
        $parameterspersonnamefull[2] = $optionssimple;
        $capabilitypersonnamefull[1] = $parameterspersonnamefull;
        $capabilitypersonnamefull[2] = 'int';
        $capabilitypersonnamefull[3] = '1';
        $capabilitypersonnamefull[4] = 'membership_capability_person_name_full';
        $capabilitypersonnamefull[5] = 'ltiservice_memberships';

        $capabilitypersonnamegiven = array();
        $capabilitypersonnamegiven[0] = 'select';
        $parameterspersonnamegiven = array();
        $parameterspersonnamegiven[0] = 'ltiservice_membershipcapabilitypersonnamegiven';
        $parameterspersonnamegiven[1] = '&nbsp;&nbsp;&nbsp;&nbsp;<i>' .
        get_string('membership_capability_person_name_given', 'ltiservice_memberships') . '</i>';
        $parameterspersonnamegiven[2] = $optionssimple;
        $capabilitypersonnamegiven[1] = $parameterspersonnamegiven;
        $capabilitypersonnamegiven[2] = 'int';
        $capabilitypersonnamegiven[3] = '0';
        $capabilitypersonnamegiven[4] = 'membership_capability_person_name_given';
        $capabilitypersonnamegiven[5] = 'ltiservice_memberships';

        $capabilitypersonnamefamily = array();
        $capabilitypersonnamefamily[0] = 'select';
        $parameterspersonnamefamily = array();
        $parameterspersonnamefamily[0] = 'ltiservice_membershipcapabilitypersonnamefamily';
        $parameterspersonnamefamily[1] = '&nbsp;&nbsp;&nbsp;&nbsp;<i>' .
        get_string('membership_capability_person_name_family', 'ltiservice_memberships') . '</i>';
        $parameterspersonnamefamily[2] = $optionssimple;
        $capabilitypersonnamefamily[1] = $parameterspersonnamefamily;
        $capabilitypersonnamefamily[2] = 'int';
        $capabilitypersonnamefamily[3] = '0';
        $capabilitypersonnamefamily[4] = 'membership_capability_person_name_family';
        $capabilitypersonnamefamily[5] = 'ltiservice_memberships';

        $capabilitypersonemailprimary = array();
        $capabilitypersonemailprimary[0] = 'select';
        $parameterspersonemailprimary = array();
        $parameterspersonemailprimary[0] = 'ltiservice_membershipcapabilitypersonemailprimary';
        $parameterspersonemailprimary[1] = '&nbsp;&nbsp;&nbsp;&nbsp;<i>' .
        get_string('membership_capability_person_email_primary', 'ltiservice_memberships') . '</i>';
        $parameterspersonemailprimary[2] = $optionssimple;
        $capabilitypersonemailprimary[1] = $parameterspersonemailprimary;
        $capabilitypersonemailprimary[2] = 'int';
        $capabilitypersonemailprimary[3] = '0';
        $capabilitypersonemailprimary[4] = 'membership_capability_person_email_primary';
        $capabilitypersonemailprimary[5] = 'ltiservice_memberships';

        $capabilityresultsourcedid = array();
        $capabilityresultsourcedid[0] = 'select';
        $parametersresultsourcedid = array();
        $parametersresultsourcedid[0] = 'ltiservice_membershipcapabilityresultsourcedid';
        $parametersresultsourcedid[1] = '&nbsp;&nbsp;&nbsp;&nbsp;<i>' .
        get_string('membership_capability_result_sourced_id', 'ltiservice_memberships') . '</i>';
        $parametersresultsourcedid[2] = $optionssimple;
        $capabilityresultsourcedid[1] = $parametersresultsourcedid;
        $capabilityresultsourcedid[2] = 'int';
        $capabilityresultsourcedid[3] = '0';
        $capabilityresultsourcedid[4] = 'membership_capability_result_sourced_id';
        $capabilityresultsourcedid[5] = 'ltiservice_memberships';

        $configurationoptions[0] = $membership;
        $configurationoptions[1] = $capabilityuserid;
        $configurationoptions[2] = $capabilitypersonsourcedid;
        $configurationoptions[3] = $capabilitypersonnamefull;
        $configurationoptions[4] = $capabilitypersonnamegiven;
        $configurationoptions[5] = $capabilitypersonnamefamily;
        $configurationoptions[6] = $capabilitypersonemailprimary;
        $configurationoptions[7] = $capabilityresultsourcedid;

        return $configurationoptions;
    }

    /**
     * Return an array with the names of the parameters that the service will be saving in the configuration
     *
     * @return  an array with the names of the parameters that the service will be saving in the configuration
     *
     */
    public function get_configuration_parameter_names() {
        return array('ltiservice_memberships', 'ltiservice_membershipcapabilityuserid',
                'ltiservice_membershipcapabilitypersonsourcedid', 'ltiservice_membershipcapabilitypersonnamefull',
                'ltiservice_membershipcapabilitypersonnamegiven', 'ltiservice_membershipcapabilitypersonnamefamily',
                'ltiservice_membershipcapabilitypersonemailprimary', 'ltiservice_membershipcapabilityresultsourcedid');
    }

    /**
     * Return an array of key/values to add to the launch parameters.
     *
     * @param $messagetype. 'basic-lti-launch-request' or 'ContentItemSelectionRequest'.
     * @param $course. the course id.
     * @param $userid. The user id.
     * @param $typeid. The tool lti type id.
     * @param $modlti. The id of the lti activity.
     *
     * The type is passed to check the configuration
     * and not return parameters for services not used.
     *
     * @return an array of key/value pairs to add as launch parameters.
     */
    public function get_launch_parameters($messagetype, $course, $user, $typeid, $modlti = null) {
        global $DB, $COURSE;

        $launchparameters = array();
        if (is_used_in_context($typeid, $course)) {
            $tool = lti_get_type_type_config($typeid);
            $endpoint = $this->resources[0]->get_endpoint();
            if ($COURSE->id === SITEID) {
                $contexttype = 'Group';
            } else {
                $contexttype = 'CourseSection';
            }
            if ($tool->memberships == '1') {
                $launchparameters['custom_context_memberships_url'] = $endpoint .
                "/{$contexttype}/{$course}/bindings/{$typeid}/memberships";
            }
        }
        return $launchparameters;
    }

}
