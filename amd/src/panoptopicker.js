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
 * @copyright  2017 Lancaster University (http://www.lancaster.ac.uk/)
 * @author     Ruslan Kabalin (https://github.com/kabalin)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/notification', 'core/url'], function($, ajax, templates, notification, url) {
    /** @var {Number} contextid Context id. */
    var contextid = 0;

    var onChangeSessionId = function(event) {
        if (event.target.value) {
            $('#panoptopicker-area').empty();
            addSpinner($('#panoptopicker-area'));
            var promises = ajax.call([{
                methodname: 'repository_panopto_get_session_by_id',
                args: {sessionid: event.target.value, contextid: contextid},
            }]);
            promises[0].done(function(response) {
                renderSessionInfo(response);
            }).fail(function(ex) {
                ex.backtrace = null;
                notification.exception(ex);
            });
        }
    };

    var addSpinner = function(element) {
        element.addClass('updating');
        var spinner = element.find('img.spinner');
        if (spinner.length) {
            spinner.show();
        } else {
            spinner = $('<img/>')
                    .attr('src', url.imageUrl('i/loading_small'))
                    .addClass('spinner').addClass('smallicon')
                ;
            element.append(spinner);
        }
    };

    var renderSessionInfo = function(data) {
        // Render the template.
        var context = {};
        if (data.session) {
            $.extend(context, data.session);
        }
        templates.render('repository_panopto/form_panoptopicker', context).done(function(newHTML) {
            // Add it to the page.
            $('#panoptopicker-area').empty().append($(newHTML).html());
        }).fail(notification.exception);
    };

    return /** @alias module:repository_panopto/panoptopicker */ {

        /**
         * Initialise filepicker.
         *
         * @method init
         * @param {array} options
         */
        init: function(options) {
            contextid = options.contextid;
            $('#' + options.elementid).on('change', onChangeSessionId).trigger('change');
        }
    };
});
