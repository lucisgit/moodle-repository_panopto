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

require_once(dirname(__FILE__)."/lib/panopto/lib/Client.php");

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
    private $auth;

    /**
     * Constructor for the panopto interface.
     */
    function __construct() {
        $this->panoptoclient = new \Panopto\Client(get_config('panopto', 'serverhostname'), array('keep_alive' => 0));
        $this->authclient = $this->panoptoclient->Auth();
        $this->smclient = $this->panoptoclient->SessionManagement();
        $this->umclient = $this->panoptoclient->UserManagement();
        $this->amclient = $this->panoptoclient->AccessManagement();
    }

    /**
     * Sets AuthenticationInfo object for using in requests.
     *
     * @param string $userkey User on the server to use for API calls. If used with Application Key from Identity Provider, user needs to be preceed with corresponding Instance Name, e.g. 'MyInstanceName\someuser'.
     * @param string $password Password for user authentication (not required if $applicationkey is specified).
     * @param string $applicationkey Application Key value from Identity Provider setting, e.g. '00000000-0000-0000-0000-000000000000'
     *
     */
    public function set_authentication_info($userkey = '', $password = '', $applicationkey = '') {
        $this->panoptoclient->setAuthenticationInfo($userkey, $password, $applicationkey);
        $this->auth = $this->panoptoclient->getAuthenticationInfo();
    }

    /**
     * Get session by id.
     *
     * @param string $sessionid Remote session id.
     * @return mixed Session object on success, false on failure.
     */
    public function get_session_by_id($sessionid) {
        try {
            $param = new \Panopto\SessionManagement\GetSessionsById($this->auth, array($sessionid));
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
        $param = new \Panopto\Auth\GetAuthenticatedUrl($this->auth, $viewerurl);
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
        $param = new \Panopto\UserManagement\CreateExternalGroup($this->auth, $groupname, get_config('panopto', 'instancename'), $groupname, array());
        $group = $this->umclient->CreateExternalGroup($param)->getCreateExternalGroupResult();
        return $group;
    }

    /**
     * Delete group.
     *
     * @param string $groupid group id.
     * @return void.
     */
    public function delete_group($groupid) {
        $param = new \Panopto\UserManagement\DeleteGroup($this->auth, $groupid);
        $this->umclient->DeleteGroup($param);
    }

    /**
     * Grant group access to session as viewer.
     *
     * @param string $groupid Remote group id.
     * @param string $sessionid Remote session id.
     * @return void.
     */
    public function grant_group_viewer_access_to_session($groupid, $sessionid) {
        $param = new \Panopto\AccessManagement\GrantGroupViewerAccessToSession($this->auth, $sessionid, $groupid);
        $this->amclient->GrantGroupViewerAccessToSession($param);
    }

    /**
     * Revoke group access from session.
     *
     * @param string $groupid Remote group id.
     * @param string $sessionid Remote session id.
     * @return void.
     */
    public function revoke_group_viewer_access_from_session($groupid, $sessionid) {
        $param = new \Panopto\AccessManagement\RevokeGroupViewerAccessFromSession($this->auth, $sessionid, $groupid);
        $this->amclient->RevokeGroupViewerAccessFromSession($param);
    }
}