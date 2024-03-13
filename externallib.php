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

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/repository/panopto/locallib.php");

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
     * @return external_function_parameters
     */
    public static function get_session_by_id_parameters() {
        return new external_function_parameters(
            [
                'sessionid' => new external_value(PARAM_TEXT, 'session id'),
                'contextid' => new external_value(PARAM_INT, 'context id'),
            ]
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
                ['sessionid' => $sessionid, 'contextid' => $contextid]);

        // Security checks.
        $context = self::get_context_from_params($params);
        self::validate_context($context);
        require_capability('repository/panopto:view', $context);

        // Instantiate Panopto client.
        $panoptoclient = new repository_panopto_interface();
        $panoptoclient->set_authentication_info(get_config('panopto', 'instancename') . '\\' . $USER->username, '',
                get_config('panopto', 'applicationkey'));

        // Perform the call to Panopto API.
        $sessions = [];
        $result = [];
        $sessiondata = ['canaccess' => true];
        $session = $panoptoclient->get_session_by_id($params['sessionid']);

        if (!$session) {
            // Try as admin, if session exists display the data, but notify user about access issue.
            // This might happen when a course teacher is editing the Panopto activity,
            // which has been added by the different teacher.
            $session = $panoptoclient->get_session_by_id($params['sessionid'], true);
            $sessiondata['canaccess'] = false;
        }

        if ($session) {
            $sessiondata['id'] = $session->getId();
            $sessiondata['name'] = $session->getName();
            $sessiondata['created'] = userdate($session->getStartTime()->format('U'), get_string('strftimedatetimeshort'));
            $sessiondata['duration'] = format_time($session->getDuration());
            $sessiondata['viewerurl'] = $session->getViewerUrl();
            $sessiondata['thumburl'] = $session->getThumbUrl();
            if (is_string($sessiondata['thumburl']) && strpos($sessiondata['thumburl'], '//') === 0) {
                $thumburl = new moodle_url('https:' . $sessiondata['thumburl']);
                $sessiondata['thumburl'] = $thumburl->out(false);
            }
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
            [
                'session' => new external_single_structure(
                    [
                        'id' => new external_value(PARAM_TEXT, 'session id'),
                        'name' => new external_value(PARAM_TEXT, 'session name'),
                        'created' => new external_value(PARAM_TEXT, 'session created timestamp'),
                        'duration' => new external_value(PARAM_TEXT, 'session duration'),
                        'viewerurl' => new external_value(PARAM_TEXT, 'session viewer url'),
                        'thumburl' => new external_value(PARAM_URL, 'session thumb url'),
                        'canaccess' => new external_value(PARAM_BOOL, 'session access flag'),
                    ], 'session data', VALUE_OPTIONAL
                ),
            ]
        );
    }
}
