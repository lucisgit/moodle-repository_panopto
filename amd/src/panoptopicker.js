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
 * Functionality for the form element panoptopicker
 *
 * @module     repository_panopto/panoptopicker
 * @package    repository_panopto
 * @copyright  2017 Lancaster University (http://www.lancaster.ac.uk/)
 * @author     Ruslan Kabalin (https://github.com/kabalin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax'], function($, ajax) {
    /** @var {Number} contextid Context id. */
    var contextid = 0;

    var onChangeSessionId = function(event) {
        if (event.target.value) {
            var promises = ajax.call([
                { methodname: 'repository_panopto_get_session_by_id', args: { sessionid: event.target.value, contextid: contextid } },
            ]);
            promises[0].done(function(response) {
                console.log(response);
            }).fail(function(ex) {
                console.log(ex);
            });
        }
    };

    return /** @alias module:repository_panopto/panoptopicker */ {
        /**
         * Initialise filepicker.
         *
         * @method init
         * @param {Array} options.
         */
        init: function (options) {
            contextid = options.contextid;
            $('#'+options.elementid).on('change', onChangeSessionId).trigger('change');
        }
    };
});


