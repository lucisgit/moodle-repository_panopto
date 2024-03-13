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
 * Panopto repository library.
 *
 * @package    repository_panopto
 * @copyright  2017 Lancaster University (http://www.lancaster.ac.uk/)
 * @author     Ruslan Kabalin (https://github.com/kabalin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . "/lib/panopto/lib/Client.php");

/**
 * Panopto API interface class.
 *
 * @package    repository_panopto
 * @copyright  2017 Lancaster University (http://www.lancaster.ac.uk/)
 * @author     Ruslan Kabalin (https://github.com/kabalin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_panopto_interface {
    /** @var stdClass Panopto client */
    private $panoptoclient;

    /** @var stdClass Remote Recorder Management client */
    private $authclient;

    /** @var stdClass Session Management client */
    private $smclient;

    /** @var stdClass User Management client */
    private $umclient;

    /** @var stdClass Access Management client */
    private $amclient;

    /** @var stdClass AuthenticationInfo object */
    private $adminauth;

    /**
     * Constructor for the panopto interface.
     */
    public function __construct() {
        $this->panoptoclient = new \Panopto\Client(get_config('panopto', 'serverhostname'));
        $this->authclient = $this->panoptoclient->Auth();
        $this->smclient = $this->panoptoclient->SessionManagement();
        $this->umclient = $this->panoptoclient->UserManagement();
        $this->amclient = $this->panoptoclient->AccessManagement();

        // Set authentication to Panopto admin.
        $this->panoptoclient->setAuthenticationInfo(get_config('panopto', 'userkey'), get_config('panopto', 'password'));
        $this->adminauth = $this->panoptoclient->getAuthenticationInfo();
    }

    /**
     * Sets AuthenticationInfo object for using in requests.
     *
     * This is only required if calls needs to be made by the current user.
     *
     * @param string $userkey User on the server to use for API calls. If used with Application Key from Identity Provider,
     *                        user needs to be prepended with corresponding Instance Name, e.g. 'MyInstanceName\someuser'.
     * @param string $password Password for user authentication (not required if $applicationkey is specified).
     * @param string $applicationkey Application Key from Identity Provider setting, e.g. '00000000-0000-0000-0000-000000000000'
     *
     */
    public function set_authentication_info($userkey = '', $password = '', $applicationkey = '') {
        $this->panoptoclient->setAuthenticationInfo($userkey, $password, $applicationkey);
    }

    /**
     * Get session by id.
     *
     * @param string $sessionid Remote session id.
     * @param bool $useadmin Set true to use admin account to retrieve data, otherwise user set in authinfo object is used.
     * @return mixed Session object on success, false on failure.
     */
    public function get_session_by_id($sessionid, $useadmin = false) {
        $auth = $this->panoptoclient->getAuthenticationInfo();
        if ($useadmin) {
            $auth = $this->adminauth;
        }
        try {
            $param = new \Panopto\SessionManagement\GetSessionsById($auth, [$sessionid]);
            $sessions = $this->smclient->GetSessionsById($param)->getGetSessionsByIdResult()->getSession();
        } catch (Exception $e) {
            return false;
        }
        if (count($sessions)) {
            return $sessions[0];
        }
        return false;
    }

    /**
     * Get authenticated URL.
     *
     * @param string $viewerurl URL that user needs to be redirected bypassing authentication.
     * @return string URL to use for redirect (valid for 10 sec after call).
     */
    public function get_authenticated_url($viewerurl) {
        $param = new \Panopto\Auth\GetAuthenticatedUrl($this->panoptoclient->getAuthenticationInfo(), $viewerurl);
        $authurl = $this->authclient->GetAuthenticatedUrl($param)->getGetAuthenticatedUrlResult();
        return $authurl;
    }

    /**
     * Create external group.
     *
     * @param string $groupname Name of external group to create.
     * @return stdClass Group object.
     */
    public function create_external_group($groupname) {
        $param = new \Panopto\UserManagement\CreateExternalGroup(
            $this->adminauth,
            $groupname,
            get_config('panopto', 'instancename'),
            $groupname,
            []
        );
        return $this->umclient->CreateExternalGroup($param)->getCreateExternalGroupResult();
    }

    /**
     * Delete group.
     *
     * @param string $groupid Remote group id.
     * @return void.
     */
    public function delete_group($groupid) {
        try {
            $param = new \Panopto\UserManagement\DeleteGroup($this->adminauth, $groupid);
            $this->umclient->DeleteGroup($param);
        } catch (SoapFault $exception) {
            debugging("Caught exception deleting external Panopto group {$groupid}: " . $exception->getMessage());
        }
    }

    /**
     * Grant group access to session as viewer.
     *
     * @param string $groupid Remote group id.
     * @param string $sessionid Remote session id.
     * @return void.
     */
    public function grant_group_viewer_access_to_session($groupid, $sessionid) {
        $param = new \Panopto\AccessManagement\GetSessionAccessDetails($this->adminauth, $sessionid);
        $sessionaccessdetails = $this->amclient->GetSessionAccessDetails($param)->getGetSessionAccessDetailsResult();
        $sessionaccessdetails = $sessionaccessdetails->getGroupsWithDirectViewerAccess()->getGuid();

        if ($sessionaccessdetails === null || !in_array($groupid, $sessionaccessdetails)) {
            $param = new \Panopto\AccessManagement\GrantGroupViewerAccessToSession($this->adminauth, $sessionid, $groupid);
            $this->amclient->GrantGroupViewerAccessToSession($param);
        }
    }

    /**
     * Revoke group access from session.
     *
     * @param string $groupid Remote group id.
     * @param string $sessionid Remote session id.
     * @return void.
     */
    public function revoke_group_viewer_access_from_session($groupid, $sessionid) {
        $param = new \Panopto\AccessManagement\RevokeGroupViewerAccessFromSession($this->adminauth, $sessionid, $groupid);
        $this->amclient->RevokeGroupViewerAccessFromSession($param);
    }

    /**
     * Add member to external group.
     *
     * @param string $externalgroupid Remote EXTERNAL group id.
     * @param string $userid Remote user id.
     * @return void.
     */
    public function add_member_to_external_group($externalgroupid, $userid) {
        $param = new \Panopto\UserManagement\AddMembersToExternalGroup(
            $this->adminauth,
            get_config('panopto', 'instancename'),
            $externalgroupid,
            [$userid]
        );
        $this->umclient->AddMembersToExternalGroup($param);
    }

    /**
     * Delete member from external group.
     *
     * @param string $externalgroupid Remote EXTERNAL group id.
     * @param array $userids Remote user ids.
     * @return void.
     */
    public function remove_members_from_external_group($externalgroupid, $userids) {
        $param = new \Panopto\UserManagement\RemoveMembersFromExternalGroup(
            $this->adminauth,
            get_config('panopto', 'instancename'),
            $externalgroupid,
            $userids
        );
        $this->umclient->RemoveMembersFromExternalGroup($param);
    }

    /**
     * Sync $USER data with Panopto.
     *
     * AuthenticationInfo object needs to be set to the current user to make this work.
     *
     * @return stdClass User object.
     */
    public function sync_current_user() {
        global $USER;
        // Check that external user exists, if not, sync user data.
        $getuserbykeyparams = new \Panopto\UserManagement\GetUserByKey(
            $this->panoptoclient->getAuthenticationInfo(),
            get_config('panopto', 'instancename') . '\\' . $USER->username
        );
        $user = $this->umclient->GetUserByKey($getuserbykeyparams)->getGetUserByKeyResult();
        if ($user === null) {
            // User does not exist, sync one.
            $params = new \Panopto\UserManagement\SyncExternalUser(
                $this->panoptoclient->getAuthenticationInfo(),
                $USER->firstname,
                $USER->lastname,
                $USER->email,
                false,
                []
            );
            $this->umclient->SyncExternalUser($params);
            $user = $this->umclient->GetUserByKey($getuserbykeyparams)->getGetUserByKeyResult();
        } else if (!$user->getFirstName() || !$user->getLastName() || !$user->getEmail()) {
            // User exists, but some data is missing, update contact info.
            $params = new \Panopto\UserManagement\UpdateContactInfo(
                $this->panoptoclient->getAuthenticationInfo(),
                $user->getUserId(),
                $USER->firstname,
                $USER->lastname,
                $USER->email,
                false
            );
            $this->umclient->UpdateContactInfo($params);
        }
        return $user;
    }
}
