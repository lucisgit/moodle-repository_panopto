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
 * Panopto repository external API.
 *
 * @package    repository_panopto
 * @category   external
 * @copyright  2017 Lancaster University (http://www.lancaster.ac.uk/)
 * @author     Ruslan Kabalin (https://github.com/kabalin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

/**
 * Panopto repository external API methods.
 *
 * @package    repository_panopto
 * @category   external
 * @copyright  2017 Lancaster University (http://www.lancaster.ac.uk/)
 * @author     Ruslan Kabalin (https://github.com/kabalin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class repository_panopto_external extends external_api {
    /**
     * Describes the parameters for get_session_by_id
     * @return external_external_function_parameters
     */
    public static function get_session_by_id_parameters() {
        return new external_function_parameters(
            array(
                'sessionid' => new external_value(PARAM_TEXT, 'session id')
            )
        );
    }

    /**
     * Returns Panopto session object for the requested id
     * @param int $sessionid
     * @return array of warnings and session data.
     */
    public static function get_session_by_id($sessionid) {
        global $USER;
        $params = self::validate_parameters(self::get_session_by_id_parameters(),
                array('sessionid' => $sessionid));
        
        // Security checks.
        $context = get_context_instance(CONTEXT_COURSE);
        self::validate_context($context);
        require_capability('repository/panopto:view', $context);

        $sessiondata = array();
        $warnings = array();

        // Instantiate Panopto client.
        $panoptoclient = new \Panopto\Client(get_config('panopto', 'serverhostname'), array('keep_alive' => 0));
        $panoptoclient->setAuthenticationInfo(
                get_config('panopto', 'instancename') . '\\' . $USER->username, '', get_config('panopto', 'applicationkey'));
        $auth = $panoptoclient->getAuthenticationInfo();
        $smclient = $panoptoclient->SessionManagement();

        // Perform the call to Panopto API.
        try {
            $param = new \Panopto\SessionManagement\GetSessionsById($auth, array($sessionid));
            $sessions = $smclient->GetSessionsById($param)->getGetSessionsByIdResult()->getSession();
        } catch (Exception $e) {
            var_dump($e);
            $warning = array();
            $warning['item'] = 'session';
            $warning['itemid'] = $sessionid;
            $warning['warningcode'] = '3';
            $warning['message'] = 'qqq';
            $warnings[] = $warning;
        }
        if (count($sessions)) {
            $sessiondata['id'] = $sessions[0]->getId();
            $sessiondata['name'] = $sessions[0]->getName();
            $thumburl = new moodle_url('https://' . get_config('panopto', 'serverhostname') . $session->getThumbUrl());
            $sessiondata['thumburl'] = $thumburl->out(false);
        }

        $result = array();
        $result['session'] = $sessiondata;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Creates Panopto session single structure.
     *
     * @return external_single_structure Panopto session single structure.
     * @since  Moodle 3.1
     */
    private static function get_session_by_id_structure($required = VALUE_REQUIRED) {
        return new external_single_structure(
            array(
                'session' => new external_single_structure(
                    array(
                        'id'                => new external_value(PARAM_TEXT, 'session id'),
                        'name'              => new external_value(PARAM_TEXT, 'session name'),
                        'thumburl'          => new external_value(PARAM_URL, 'session thumb url'),
                    ), 'session data', $required),
                'warnings' => new external_warnings(),
            )
        );
    }
}
