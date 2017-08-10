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
 * Panopto repository plugin.
 *
 * @package    repository_panopto
 * @copyright  2017 Lancaster University (http://www.lancaster.ac.uk/)
 * @author     Ruslan Kabalin (https://github.com/kabalin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . "/repository/panopto/lib/panopto/lib/Client.php");
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Repository plugin for accessing Panopto files.
 *
 * @package    repository_panopto
 * @copyright  2017 Lancaster University (http://www.lancaster.ac.uk/)
 * @author     Ruslan Kabalin (https://github.com/kabalin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_panopto extends repository {
    /** Current client version in use. */
    const ROOT_FOLDER_ID = '00000000-0000-0000-0000-000000000000';

    /** @var stdClass Session Management client */
    private $smclient;

    /** @var stdClass User Management client */
    private $umclient;

    /** @var stdClass AuthenticationInfo object */
    private $auth;

    /** @var stdClass AuthenticationInfo object for admin */
    private $adminauth;
    /**
     * Constructor
     *
     * @param int $repositoryid repository instance id.
     * @param int|stdClass $context a context id or context object.
     * @param array $options repository options.
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        global $USER;
        parent::__construct($repositoryid, $context, $options);

        // Disable this repo, we only can use it in Panopto course module at the moment,
        // which will initialise it explicitly and bypass this flag. It is pointless to use it outside
        // Panopto module, as it returns Panopto sessionid string only for the choosen video.
        $this->disabled = true;

        // Instantiate Panopto client.
        $panoptoclient = new \Panopto\Client(get_config('panopto', 'serverhostname'), array('keep_alive' => 0));
        $panoptoclient->setAuthenticationInfo(get_config('panopto', 'userkey'), get_config('panopto', 'password'));
        $this->adminauth = $panoptoclient->getAuthenticationInfo();
        $panoptoclient->setAuthenticationInfo(
                get_config('panopto', 'instancename') . '\\' . $USER->username, '', get_config('panopto', 'applicationkey'));
        $this->auth = $panoptoclient->getAuthenticationInfo();
        try {
            $this->smclient = $panoptoclient->SessionManagement();
            $this->umclient = $panoptoclient->UserManagement();
        } catch (Exception $e) {
            // TODO: Flag this somehow, most likely there is settings issue or
            // server is not available.
        }
    }

    /**
     * Given a path, and perhaps a search, get a list of files.
     *
     * @param string $path identifier for current path.
     * @param string $page the page number of file list.
     * @return array list of files including meta information as specified by parent.
     */
    public function get_listing($path = '', $page = '') {
        // Data preparation.
        if (empty($path)) {
            $path = self::ROOT_FOLDER_ID;
        }
        $navpath = array();

        // Cache setup.
        $cache = cache::make('repository_panopto', 'folderstree');
        if ($cache->get('lastupdated') && (time() - (int) $cache->get('lastupdated') > (int) get_config('panopto', 'folderstreecachettl'))) {
            // Invalidate cache after timeout.
            $cache->purge();
        }

        // Retrieve folders tree from cache, if it does not exist, build one.
        $listfolders = $cache->get('listfolders');
        if ($listfolders === false) {
            // Get the folders and sessions list.
            $listfolders = $this->get_folders_list();
            $listsessions = $this->get_sessions_list();

            // Process folders and replace missing parent folders with root.
            foreach ($listfolders as $folderid => $folder) {
                if ($folder['parentfolderid'] !== self::ROOT_FOLDER_ID && !isset($listfolders[$folder['parentfolderid']])) {
                    // Missing parent folder, set to root.
                    $listfolders[$folderid]['parentfolderid'] = self::ROOT_FOLDER_ID;
                }
            }

            // Process sessions and move those with missing parent folder to root.
            $listsessionsprocessed = array(self::ROOT_FOLDER_ID => array());
            foreach ($listsessions as $parentfolderid => $sessionsarray) {
                if ($parentfolderid !== self::ROOT_FOLDER_ID && !isset($listfolders[$parentfolderid])) {
                    // Missing parent folder.
                    $listsessionsprocessed[self::ROOT_FOLDER_ID] = array_merge($listsessionsprocessed[self::ROOT_FOLDER_ID], $sessionsarray);
                } else {
                    $listsessionsprocessed[$parentfolderid] = $sessionsarray;
                }
            }

            // Build the tree.
            $listfolders = $this->build_folders_tree($listfolders, self::ROOT_FOLDER_ID, $listsessionsprocessed, self::ROOT_FOLDER_ID);
            // Add root level sessions.
            $listfolders = array_merge($listfolders, $listsessionsprocessed[self::ROOT_FOLDER_ID]);

            // Store result in cache.
            if (count($listfolders)) {
                $cache->set_many(array('listfolders' => $listfolders, 'lastupdated' => time()));
            }
        }

        // Split the path requested.
        $patharray = explode('/', $path);
        // Build navigation path.
        $navpathitem = '';
        foreach ($patharray as $pathitem) {
            if ($pathitem === self::ROOT_FOLDER_ID) {
                // Root dir.
                $navpathitem = $pathitem;
                $navpath[] = array('name' => get_string('pluginname', 'repository_panopto'), 'path' => $navpathitem);
            } else {
                // Getting deeper in subdirs...
                // Add navigation path item.
                $navpathitem = $navpathitem . '/' . $pathitem;
                $navpath[] = array('name' => $listfolders[$pathitem]['title'], 'path' => $navpathitem);
                // Reduce the tree to the requested folder.
                $listfolders = $listfolders[$pathitem]['children'];
            }
        }

        // Output result.
        $listing = $this->get_base_listing();
        $listing['list'] = $listfolders;
        $listing['path'] = $navpath;
        return $listing;
    }

    /**
     * Converts flat list of directories with parent data into tree structure
     * suitable for get_listing output.
     *
     * @param array $listfolders list of the folders to use.
     * @param string $parent parent folder to build the list of child directires for.
     * @param array $listsessions list of sessions to embed generated by get_sessions_list().
     * @param string $path the current path
     * @return array $tree directiry tree structure.
     */
    public function build_folders_tree($listfolders = array(), $parent = null, &$listsessions, $path) {
        $tree = array();
        foreach($listfolders as $folderid => $folder) {
            if ($folder['parentfolderid'] === $parent) {
                unset($folder['parentfolderid']);
                unset($listfolders[$folderid]);
                // Recurively get children folders.
                $folder['path'] = $path . '/' . $folderid;
                $folder['children'] = $this->build_folders_tree($listfolders, $folderid, $listsessions, $folder['path']);
                // Check if there are sessions to add to the children list.
                if (isset($listsessions[$folderid])) {
                    $folder['children'] = array_merge($folder['children'], $listsessions[$folderid]);
                    unset($listsessions[$folderid]);
                }
                $tree[$folderid] = $folder;
            }
        }
        return $tree;
    }

    /**
     * Search for results
     * @param   string  $key    The search string
     * @param   int     $page   The search page
     * @return  array   A set of results with the same layout as the 'list' element in 'get_listing'.
     */
    public function search($key, $page = 0) {
        // Data preparation.
        // Get the folders and sessions list for the current path.
        $listfolders = $this->get_folders_list($key);
        $listsessions = $this->get_sessions_list($key);
        $list = array_merge($listfolders, $listsessions[self::ROOT_FOLDER_ID]);

        // Output result.
        $listing = $this->get_base_listing();
        $listing['issearchresult'] = true;
        $listing['list'] = $list;
        return $listing;
    }

    /**
     * Get a list of Panopto directories.
     *
     * @param string $search the search query.
     * @return array list of folders with the same layout as the 'list' element in 'get_listing'.
     */
    private function get_folders_list($search = '') {
        global $OUTPUT;
        $list = array();

        // Build the GetFoldersList request and perform the call.
        $pagination = new \Panopto\RemoteRecorderManagement\Pagination();
        $pagination->setPageNumber(0);
        $pagination->setMaxNumberResults(1000);

        $request = new \Panopto\SessionManagement\ListFoldersRequest();
        $request->setPagination($pagination);
        $request->setSortBy('Name');
        $request->setSortIncreasing(true);

        // If we are searching, there is no need to set parent folder,
        // also a good idea to search by relevance.
        if (!empty($search)) {
            $request->setWildcardSearchNameOnly(true);
        }

        $param = new \Panopto\SessionManagement\GetFoldersList($this->auth, $request, $search);
        $folders = $this->smclient->GetFoldersList($param)->getGetFoldersListResult();
        $totalfolders = $folders->getTotalNumberResults();

        // Processing GetFoldersList result.
        if ($totalfolders) {
            foreach ($folders->getResults() as $folder) {
                // Determine parent folder.
                $parentfolderid = $folder->getParentFolder();
                if (empty($parentfolderid)) {
                    $parentfolderid = self::ROOT_FOLDER_ID;
                }
                $list[$folder->getId()] = array(
                    'title' => $folder->getName(),
                    'shorttitle' => $folder->getName(),
                    'path' => '',
                    'thumbnail' => $OUTPUT->image_url('f/folder-32')->out(false),
                    'children' => array(),
                    // Techical data we need to build directory tree.
                    'parentfolderid' => $parentfolderid,
                );
            }
        }
        return $list;
    }

    /**
     * Get a list of Panopto sessions available for viewing in each directory.
     *
     * List of files with the same layout as the 'list' element in 'get_listing',
     * but with parent directory data. Basically array of arrays with key set to parent directory.
     *
     * @param string $search the search query.
     * @return array $list list of arrays of files.
     */
    private function get_sessions_list($search = '') {
        $list = array();

        // Build the GetFoldersList request and perform the call.
        $pagination = new \Panopto\RemoteRecorderManagement\Pagination();
        $pagination->setPageNumber(0);
        $pagination->setMaxNumberResults(1000);

        $request = new \Panopto\SessionManagement\ListSessionsRequest();
        $request->setPagination($pagination);
        $request->setSortBy('Name');
        $request->setSortIncreasing(true);
        $request->setStates(array('Complete'));

        $param = new \Panopto\SessionManagement\GetSessionsList($this->auth, $request, $search);
        $sessions = $this->smclient->GetSessionsList($param)->getGetSessionsListResult();
        $totalsessions = $sessions->getTotalNumberResults();

        // Processing GetFoldersList result.
        if ($totalsessions) {
            foreach ($sessions->getResults() as $session) {
                // Define parent folder array.
                $parentfolderid = $session->getFolderId();
                if (empty($parentfolderid) || !empty($search)) {
                    $parentfolderid = self::ROOT_FOLDER_ID;
                }
                if (!isset($list[$parentfolderid])) {
                    $list[$parentfolderid] = array();
                }
                // Add session data.
                $title = $session->getName();
                $url = new moodle_url($session->getViewerUrl());
                $thumburl = new moodle_url('https://' . get_config('panopto', 'serverhostname') . $session->getThumbUrl());
                $list[$parentfolderid][] = array(
                    'shorttitle' => $title,
                    'title' => $title,
                    'source' => $session->getId(),
                    'url' => $url->out(false),
                    'thumbnail' => $thumburl->out(false),
                    'thumbnail_title' => $session->getDescription(),
                    'date' => $session->getStartTime()->format('U'),
                );
            }
        }
        return $list;
    }

    /**
     * Return array of default listing properties.
     *
     * @return array of listing properties.
     */
    private function get_base_listing() {
        return array(
            'dynload' => true,
            'nologin' => true,
            'path' => array(array('name' => get_string('pluginname', 'repository_panopto'), 'path' => self::ROOT_FOLDER_ID)),
            'list' => array(),
        );
    }

    /**
     * Return names of the options to display in the repository plugin config form.
     *
     * @return array of option names.
     */
    public static function get_type_option_names() {
        return array('serverhostname', 'userkey', 'password', 'instancename', 'applicationkey', 'pluginname', 'folderstreecachettl');
    }

    /**
     * Setup repistory form.
     *
     * @param moodleform $mform Moodle form (passed by reference).
     * @param string $classname repository class name.
     */
    public static function type_config_form($mform, $classname = 'repository') {
        global $DB;

        // Notice about repo availability.
        $mform->addElement('static', 'pluginnotice', '', html_writer::tag('div', get_string('pluginnotice', 'repository_panopto'), array('class' => 'warning')));
        $strrequired = get_string('required');
        parent::type_config_form($mform);

        // Folder tree cache ttl.
        $mform->addElement('text', 'folderstreecachettl', get_string('folderstreecachettl', 'repository_panopto'));
        $mform->setType('folderstreecachettl', PARAM_INT);
        $mform->setDefault('folderstreecachettl', 300);
        $mform->addElement('static', 'folderstreecachettldesc', '', get_string('folderstreecachettldesc', 'repository_panopto'));

        // Header.
        $mform->addElement('header', 'connectionsettings', get_string('connectionsettings', 'repository_panopto'));

        // Server hostname.
        $mform->addElement('text', 'serverhostname', get_string('serverhostname', 'repository_panopto'));
        $mform->addRule('serverhostname', $strrequired, 'required', null, 'client');
        $mform->setType('serverhostname', PARAM_HOST);
        $mform->addElement('static', 'serverhostnamedesc', '', get_string('serverhostnamedesc', 'repository_panopto'));

        // User key.
        $mform->addElement('text', 'userkey', get_string('userkey', 'repository_panopto'));
        $mform->addRule('userkey', $strrequired, 'required', null, 'client');
        $mform->setType('userkey', PARAM_RAW_TRIMMED);
        $mform->addElement('static', 'userkeydesc', '', get_string('userkeydesc', 'repository_panopto'));

        // Password.
        $mform->addElement('text', 'password', get_string('password', 'repository_panopto'));
        $mform->addRule('password', $strrequired, 'required', null, 'client');
        $mform->setType('password', PARAM_RAW_TRIMMED);
        $mform->addElement('static', 'passworddesc', '', get_string('passworddesc', 'repository_panopto'));

        // Instance name.
        $mform->addElement('text', 'instancename', get_string('instancename', 'repository_panopto'));
        $mform->addRule('instancename', $strrequired, 'required', null, 'client');
        $mform->setType('instancename', PARAM_RAW_TRIMMED);
        $mform->addElement('static', 'instancenamedesc', '', get_string('instancenamedesc', 'repository_panopto'));

        // Application key.
        $mform->addElement('text', 'applicationkey', get_string('applicationkey', 'repository_panopto'));
        $mform->addRule('applicationkey', $strrequired, 'required', null, 'client');
        $mform->setType('applicationkey', PARAM_RAW_TRIMMED);
        $mform->addElement('static', 'applicationkeydesc', '', get_string('applicationkeydesc', 'repository_panopto'));

        // Display Bounce Page URL for Identity Privder setup.
        $type = $DB->get_record('repository', array('type' => 'panopto'));
        if ($type) {
            $url = new \moodle_url('/repository/repository_callback.php', array('repo_id' => $type->id));
            $mform->addElement('static', 'bouncepageurl', get_string('bouncepageurl', 'repository_panopto'), get_string('bouncepageurldesc', 'repository_panopto', $url->out(true)));
        } else {
            $mform->addElement('static', 'bouncepageurl', get_string('bouncepageurl', 'repository_panopto'), get_string('bouncepageurlnotreadydesc', 'repository_panopto'));
        }
    }

    /**
     * This repository doesn't support global search.
     *
     * @return bool if supports global search
     */
    public function global_search() {
        return false;
    }

    /**
     * This repository only supports external files
     *
     * @return int return type bitmask supported
     */
    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }

    /**
     * We do not treat Panopto site data as private.
     *
     * @return bool
     */
    public function contains_private_data() {
        return false;
    }

    /**
     * Use check login for syncling user data with Panopto.
     *
     * @return true.
     */
    public function check_login(){
        $this->sync_user();
        return true;
    }

    /**
     * Sync user data with Panopto.
     *
     * This will create user on Panopto side if needed and populate user data.
     *
     * @return void.
     */
    private function sync_user(){
        global $USER;
        // Check that external user exists, if not, sync user data.
        $params = new \Panopto\UserManagement\GetUserByKey($this->auth, get_config('panopto', 'instancename') . '\\' . $USER->username);
        $user = $this->umclient->GetUserByKey($params)->getGetUserByKeyResult();
        if ($user === null) {
            // User does not exist, sync one.
            $params = new \Panopto\UserManagement\SyncExternalUser($this->auth, $USER->firstname, $USER->lastname, $USER->email, false, array());
            $this->umclient->SyncExternalUser($params);
        } elseif (!$user->getFirstName() || !$user->getLastName() || !$user->getEmail()) {
            // User exists, but some data is missing, update contact info.
            $params = new \Panopto\UserManagement\UpdateContactInfo($this->auth, $user->getUserId(), $USER->firstname, $USER->lastname, $USER->email, false);
            $this->umclient->UpdateContactInfo($params);
        }
    }

    /**
     * Callback for SSO processing.
     *
     * @return true.
     */
    public function callback() {
        global $USER;

        $authcode = required_param("authCode", PARAM_ALPHANUM);
        $servername = required_param("serverName", PARAM_HOST);
        $callbackurl = required_param("callbackURL", PARAM_URL);
        $expiration = required_param("expiration", PARAM_RAW);
        $action = optional_param("action", "", PARAM_ALPHA);

        // Verify provided authcode.
        $authpayload = "serverName=" . $servername . "&expiration=" . $expiration;
        $authpayload = $authpayload . "|" . get_config('panopto', 'applicationkey');
        $encodedpayload = strtoupper(sha1($authpayload));

        if ($encodedpayload !== $authcode) {
            throw new \invalid_parameter_exception('Invalid auth code provided.');
        }

        // Sync user data.
        $this->sync_user();

        // Craft the response to Panopto.
        $userkey = get_config('panopto', 'instancename') . '\\' . $USER->username;
        $responseparams = "serverName=" . $servername . "&externalUserKey=" . $userkey . "&expiration=" . $expiration;
        $responseparams = $responseparams . "|" . get_config('panopto', 'applicationkey');
        $responseauthcode = strtoupper(sha1($responseparams));

        $urlparams = array(
            'serverName' => $servername,
            'externalUserKey' => $userkey,
            'expiration' => $expiration,
            'authCode' => $responseauthcode,
        );
        $redirecturl = new \moodle_url($callbackurl, $urlparams);

        // Redirect to Panopto.
        redirect($redirecturl->out(true));
    }
}
