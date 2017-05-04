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
require_once($CFG->dirroot . "/local/panopto/lib/panopto/lib/Client.php");

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
                'sessionid' => new external_value(PARAM_TEXT, 'session id'),
                'contextid' => new external_value(PARAM_INT, 'context id')
            )
        );
    }

    /**
     * Returns Panopto session object for the requested id
     * @param int $sessionid
     * @param int $contextid
     * @return array of warnings and session data.
     */
    public static function get_session_by_id($sessionid, $contextid) {
        global $USER;
        $params = self::validate_parameters(self::get_session_by_id_parameters(),
                array('sessionid' => $sessionid, 'contextid' => $contextid));

        // Security checks.
        $context = self::get_context_from_params($params);
        self::validate_context($context);
        require_capability('repository/panopto:view', $context);

        $result = array();
        $sessiondata = array();

        // Instantiate Panopto client.
        $panoptoclient = new \Panopto\Client(get_config('panopto', 'serverhostname'), array('keep_alive' => 0));
        $panoptoclient->setAuthenticationInfo(
                get_config('panopto', 'instancename') . '\\' . $USER->username, '', get_config('panopto', 'applicationkey'));
        $auth = $panoptoclient->getAuthenticationInfo();
        $smclient = $panoptoclient->SessionManagement();

        // Perform the call to Panopto API.
        try {
            $param = new \Panopto\SessionManagement\GetSessionsById($auth, array($params['sessionid']));
            $sessions = $smclient->GetSessionsById($param)->getGetSessionsByIdResult()->getSession();
        } catch (Exception $e) {
            throw new invalid_parameter_exception('SOAP call error: ' . $e->getMessage());
        }
        if (count($sessions)) {
            $sessiondata['id'] = $sessions[0]->getId();
            $sessiondata['name'] = $sessions[0]->getName();
            $thumburl = new moodle_url('https://' . get_config('panopto', 'serverhostname') . $sessions[0]->getThumbUrl());
            $sessiondata['thumburl'] = $thumburl->out(false);
            $result['session'] = $sessiondata;
        }

        return $result;
    }

    /**
     * Creates Panopto session single structure.
     *
     * @return external_single_structure Panopto session single structure.
     * @since  Moodle 3.1
     */
    public static function get_session_by_id_returns() {
        return new external_single_structure(
            array(
                'session' => new external_single_structure(
                    array(
                        'id'                => new external_value(PARAM_TEXT, 'session id'),
                        'name'              => new external_value(PARAM_TEXT, 'session name'),
                        'thumburl'          => new external_value(PARAM_URL, 'session thumb url'),
                    ), 'session data', VALUE_OPTIONAL),
            )
        );
    }
}
