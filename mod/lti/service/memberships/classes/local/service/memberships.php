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

use ltiservice_memberships\local\resource\contextmemberships;
use ltiservice_memberships\local\resource\linkmemberships;
use mod_lti\local\ltiservice\service_base;

defined('MOODLE_INTERNAL') || die();

/**
 * A service implementing Memberships.
 *
 * @package    ltiservice_memberships
 * @since      Moodle 3.0
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class memberships extends service_base {

    /** Default prefix for context-level roles */
    const CONTEXT_ROLE_PREFIX = 'http://purl.imsglobal.org/vocab/lis/v2/membership#';
    /** Context-level role for Instructor */
    const CONTEXT_ROLE_INSTRUCTOR = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor';
    /** Context-level role for Learner */
    const CONTEXT_ROLE_LEARNER = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner';
    /** Capability used to identify Instructors */
    const INSTRUCTOR_CAPABILITY = 'moodle/course:manageactivities';
    /** Name of LTI service component */
    const LTI_SERVICE_COMPONENT = 'ltiservice_memberships';
    /** Membership services enabled */
    const MEMBERSHIP_ENABLED = 1;
    /** Always include field */
    const ALWAYS_INCLUDE_FIELD = 1;
    /** Allow the instructor to decide if included */
    const DELEGATE_TO_INSTRUCTOR = 2;
    /** Instructor chose to include field */
    const INSTRUCTOR_INCLUDED = 1;
    /** Instructor delegated and approved for include */
    const INSTRUCTOR_DELEGATE_INCLUDED = array(self::DELEGATE_TO_INSTRUCTOR && self::INSTRUCTOR_INCLUDED);

    /**
     * Class constructor.
     */
    public function __construct() {

        parent::__construct();
        $this->id = 'memberships';
        $this->name = get_string('servicename', self::LTI_SERVICE_COMPONENT);

    }

    /**
     * Get the resources for this service.
     *
     * @return array
     */
    public function get_resources() {

        if (empty($this->resources)) {
            $this->resources = array();
            $this->resources[] = new contextmemberships($this);
            $this->resources[] = new linkmemberships($this);
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
     * @param \core_availability\info_module $info Conditional availability information for LTI instance (null if context-level request)
     *
     * @return string
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
        $isnewtoolproxy = $tool->toolproxyid == 0;
        $sep = '        ';
        foreach ($users as $user) {
            if (in_array($user->id, $exclude)) {
                continue;
            }
            if (empty($info) || !$info->is_user_visible($info->get_course_module(), $user->id)) {
                continue;
            }

            $member = new \stdClass();
            $membership = new \stdClass();
            $membership->status = 'Active';
            $membership->role = explode(',', lti_get_ims_role($user->id, null, $id, true));

            $toolconfig = lti_get_type_type_config($tool->id);

            $instanceconfig = new \stdClass();
            if (!is_null($lti)) {
                $instanceconfig = lti_get_type_config_from_instance($lti->id);
            }

            $includedcapabilities = [
                ['User.id'              => ['type' => 'id',
                                            'member.field' => 'userId',
                                            'source.value' => $user->id]],
                ['Person.sourcedId'     => ['type' => 'id',
                                            'member.field' => 'sourcedId',
                                            'source.value' => format_string($user->idnumber)]],
                ['Person.name.full'     => ['type' => 'name',
                                            'member.field'=> 'name',
                                            'source.value' => format_string("{$user->firstname} {$user->lastname}")]],
                ['Person.name.given'    => ['type' => 'name',
                                            'member.field'=> 'givenName',
                                            'source.value' => format_string($user->firstname)]],
                ['Person.name.family'   => ['type' => 'name',
                                            'member.field'=> 'familyName',
                                            'source.value' => format_string($user->lastname)]],
                ['Person.email.primary' => ['type' => 'email',
                                            'member.field'=> 'email',
                                            'source.value' => format_string($user->email)]],
                ['Result.sourcedId'     => ['type' => 'result',
                                            'member.field' => 'resultSourcedId',
                                            'source.value'=> json_encode(lti_build_sourcedid($lti->id,
                                                                                             $user->id,
                                                                                             $lti->servicesalt,
                                                                                             $lti->typeid))]]];

            $hasmemberships = $toolconfig->ltiservice_memberships == self::MEMBERSHIP_ENABLED;
            $lticonfig = ['tool' => $toolconfig,
                          'instance' => $instanceconfig,
                          'field' => ['name' => 'lti_sendname'],
                                     ['email' => 'lti_sendemailaddr']];
            $isvalidlticonfig = self::is_valid_field_set($lticonfig);

            foreach ($includedcapabilities as $capabilityname => $capability) {
                if (!in_array($capabilityname, $enabledcapabilities)) {
                    continue;
                }

                if ($isnewtoolproxy && $hasmemberships) {
                    if (($capability->type === 'id')
                     || ($capability->type === 'name' && $isvalidlticonfig['name'])
                     || ($capability->type === 'email' && $isvalidlticonfig['email]'])
                     || ($capability->type === 'result' && !empty($lti->servicesalt))) {
                            $member->$capability['member.field'] = $capability['source.value'];
                    }
                }
            }

            $membership->member = $member;
            $json .= $sep . json_encode($membership);
            $sep = ",\n        ";
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
     * Determines whether a user attribute may be used as part of LTI membership
     * @param array $lticonfig Contains configuration for system specific tool,
     *                          instance specific LTI tool and name of the config field to check
     * @return array Verification
     */
    private static function is_valid_field_set($lticonfig) {
        $isvalidstate = [];
        foreach($lticonfig['field'] as $key => $field) {
            $include = self::ALWAYS_INCLUDE_FIELD == $lticonfig['toolconfig']->$lticonfig['field'];
            $delegated = self::DELEGATE_TO_INSTRUCTOR == $lticonfig['toolconfig']->$lticonfig['field'];
            $valid = ($include || ($delegated && ($lticonfig['instanceconfig']->$lticonfig['field'] == self::INSTRUCTOR_INCLUDED)));
            array_push($isvalidstate, [$key, $valid]);
        }
        return $isvalidstate;
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
     * @return array of options to add to the add/edit external tool or null if no options to add.
     *
     */

    public function get_configuration_options() {

        $configurationoptions = array();

        $optionsmem = array();
        $optionsmem[0] = get_string('notallow', 'ltiservice_memberships');
        $optionsmem[1] = get_string('allow', 'ltiservice_memberships');

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

        $configurationoptions[0] = $membership;

        return $configurationoptions;
    }

    /**
     * Return an array with the names of the parameters that the service will be saving in the configuration
     *
     * @return array with the names of the parameters that the service will be saving in the configuration
     *
     */
    public function get_configuration_parameter_names() {
        return array(self::LTI_SERVICE_COMPONENT);
    }

    /**
     * Return an array of key/values to add to the launch parameters.
     *
     * @param $messagetype. 'basic-lti-launch-request' or 'ContentItemSelectionRequest'.
     * @param $courseid. the course id.
     * @param $user. The user id.
     * @param $typeid. The tool lti type id.
     * @param $modlti. The id of the lti activity.
     *
     * The type is passed to check the configuration
     * and not return parameters for services not used.
     *
     * @return array of key/value pairs to add as launch parameters.
     */
    public function get_launch_parameters($messagetype, $courseid, $user, $typeid, $modlti = null) {
        global $COURSE;

        $launchparameters = array();
        $tool = lti_get_type_type_config($typeid);
        if (isset($tool->ltiservice_memberships)){
            if ($tool->ltiservice_memberships == '1' && $this->is_used_in_context($typeid, $courseid)) {
                $endpoint = $this->get_service_path();
                if ($COURSE->id === SITEID) {
                    $contexttype = 'Group';
                } else {
                    $contexttype = 'CourseSection';
                }
                $launchparameters['custom_context_memberships_url'] = $endpoint .
                "/{$contexttype}/{$courseid}/bindings/{$typeid}/memberships";
            }
        }
        return $launchparameters;
    }

}
